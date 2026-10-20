<?php

namespace App\Actions\User;

use App\Models\AppNotification;
use App\Models\Branch;
use App\Models\HeldSale;
use App\Models\OrganizationActivityLog;
use App\Models\User;
use App\Models\UserBranchHistory;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class MoveUserToBranchAction
{
    /**
     * Run pre-flight checks and return blocks and warnings before moving a user.
     */
    public function precheck(User $actor, User $user, Branch $destinationBranch): array
    {
        $blocks = [];
        $warnings = [];

        // 1. Cross-organization isolation
        $userOrgId = $user->organization_id ?: $user->branch?->organization_id;
        if ((int)$destinationBranch->organization_id !== (int)$userOrgId) {
            $blocks[] = 'Cross-organization transfers are strictly prohibited. The destination branch belongs to a different organization.';
        }

        if (!$destinationBranch->is_active) {
            $blocks[] = "Destination branch '{$destinationBranch->name}' is currently disabled.";
        }

        // 2. Privilege & Actor protection
        if ($user->id === $actor->id) {
            $blocks[] = 'You cannot transfer your own account.';
        }

        if ($user->isSuperAdmin() || $user->isOrganizationAdmin()) {
            $blocks[] = 'Super Admin and Organization Admin accounts cannot be transferred across branches.';
        }

        if ((int)$user->branch_id === (int)$destinationBranch->id) {
            $blocks[] = 'The user is already assigned to this branch.';
        }

        // 3. Open Till Session Block (§6.2)
        $activeTill = $user->activeTillSession();
        if ($activeTill) {
            $blocks[] = "User has an open till session (#{$activeTill->id}) in branch '{$user->branch?->name}'. The till session must be closed before the user can be transferred.";
        }

        // 4. Plan Limit Block (§6.2)
        if (!$destinationBranch->canCreateUser()) {
            $userLimit = $destinationBranch->getUserLimit();
            $blocks[] = "Destination branch '{$destinationBranch->name}' has reached its maximum staff limit ({$userLimit} staff) permitted by its organization plan.";
        }

        // 5. Warnings (§6.2)
        // Check for held sales
        $heldSalesCount = HeldSale::where('user_id', $user->id)
            ->where('branch_id', $user->branch_id)
            ->where('status', 'held')
            ->count();
        if ($heldSalesCount > 0) {
            $warnings[] = "User has {$heldSalesCount} held sale(s) at '{$user->branch?->name}'. These held sales will remain with the old branch and cannot be resumed from the new branch.";
        }

        // Check for last active branch admin
        $sourceBranch = $user->branch;
        if ($sourceBranch) {
            setPermissionsTeamId($sourceBranch->id);
            if ($user->hasRole('branch-admin')) {
                $otherAdminsCount = User::where('branch_id', $sourceBranch->id)
                    ->where('id', '!=', $user->id)
                    ->where('is_active', true)
                    ->get()
                    ->filter(function ($u) use ($sourceBranch) {
                        setPermissionsTeamId($sourceBranch->id);
                        return $u->hasRole('branch-admin');
                    })
                    ->count();

                if ($otherAdminsCount === 0) {
                    $warnings[] = "This user is the last active Branch Admin for '{$sourceBranch->name}'. Moving them will leave the source branch without an active Branch Admin.";
                }
            }
        }

        $warnings[] = "Please confirm that any offline sales made on this user's terminal have been synchronized before completing the move.";

        return [
            'can_move' => empty($blocks),
            'blocks'   => $blocks,
            'warnings' => $warnings,
        ];
    }

    /**
     * Execute atomic branch transfer.
     */
    public function execute(
        User $actor,
        User $user,
        Branch $destinationBranch,
        string $destinationRole,
        ?string $reason = null
    ): array {
        $precheck = $this->precheck($actor, $user, $destinationBranch);
        if (!$precheck['can_move']) {
            throw ValidationException::withMessages([
                'destination_branch_id' => $precheck['blocks'],
            ]);
        }

        return DB::transaction(function () use ($actor, $user, $destinationBranch, $destinationRole, $reason) {
            $sourceBranch = $user->branch;
            $oldBranchId = $user->branch_id;
            $oldBranchName = $sourceBranch?->name ?? 'Previous Branch';

            // 1. Prepare Destination Roles
            setPermissionsTeamId($destinationBranch->id);
            $destinationBranch->ensureDefaultRoles();

            $validRole = Role::where('name', $destinationRole)
                ->where('team_id', $destinationBranch->id)
                ->first();

            if (!$validRole) {
                throw ValidationException::withMessages([
                    'destination_role' => "The selected role '{$destinationRole}' does not exist in branch '{$destinationBranch->name}'.",
                ]);
            }

            // 2. Update user's branch & organization
            $user->branch_id = $destinationBranch->id;
            $user->organization_id = $destinationBranch->organization_id;
            $user->save();

            // 3. Assign destination branch role
            setPermissionsTeamId($destinationBranch->id);
            $user->syncRoles([$destinationRole]);

            // 4. Update branch history lazy-initialization
            $hasPreviousHistory = UserBranchHistory::where('user_id', $user->id)->exists();
            if (!$hasPreviousHistory && $oldBranchId) {
                // Lazily seed initial row from user creation until now
                UserBranchHistory::create([
                    'user_id'    => $user->id,
                    'branch_id'  => $oldBranchId,
                    'started_at' => $user->created_at ?: now()->subMinute(),
                    'ended_at'   => now(),
                    'moved_by'   => $actor->id,
                    'reason'     => $reason,
                ]);
            } else {
                // Close any currently open history rows
                UserBranchHistory::where('user_id', $user->id)
                    ->whereNull('ended_at')
                    ->update(['ended_at' => now()]);
            }

            // Open new branch history row
            UserBranchHistory::create([
                'user_id'    => $user->id,
                'branch_id'  => $destinationBranch->id,
                'started_at' => now(),
                'ended_at'   => null,
                'moved_by'   => $actor->id,
                'reason'     => $reason,
            ]);

            // 5. Invalidate active database sessions immediately
            try {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            } catch (\Throwable $e) {
                // Ignore if sessions table is not using database driver in testing
            }

            // 6. Audit Logging
            if ($user->organization_id) {
                OrganizationActivityLog::create([
                    'organization_id' => $user->organization_id,
                    'actor_user_id'   => $actor->id,
                    'action'          => 'user_transferred_branch',
                    'details'         => [
                        'user_id'                => $user->id,
                        'username'               => $user->username,
                        'source_branch_id'       => $oldBranchId,
                        'source_branch_name'     => $oldBranchName,
                        'destination_branch_id'  => $destinationBranch->id,
                        'destination_branch_name'=> $destinationBranch->name,
                        'new_role'               => $destinationRole,
                        'reason'                 => $reason,
                    ],
                    'created_at'      => now(),
                ]);
            }

            ActivityLogger::userAction(
                "Transferred staff user '{$user->name}' (@{$user->username}) from '{$oldBranchName}' to '{$destinationBranch->name}' as '{$destinationRole}'",
                $destinationBranch->id
            );

            // 7. In-App Notifications
            if ($oldBranchId) {
                AppNotification::create([
                    'branch_id'      => $oldBranchId,
                    'user_id'        => null,
                    'type'           => 'staff_transferred_out',
                    'title'          => 'Staff Transferred Out',
                    'message'        => "Staff member {$user->name} (@{$user->username}) was transferred to {$destinationBranch->name}.",
                    'reference_type' => User::class,
                    'reference_id'   => $user->id,
                    'data'           => [
                        'user_id'               => $user->id,
                        'user_name'             => $user->name,
                        'destination_branch_id' => $destinationBranch->id,
                        'moved_by_id'           => $actor->id,
                        'moved_by_name'         => $actor->name,
                    ],
                ]);
            }

            AppNotification::create([
                'branch_id'      => $destinationBranch->id,
                'user_id'        => null,
                'type'           => 'staff_transferred_in',
                'title'          => 'Staff Transferred In',
                'message'        => "Staff member {$user->name} (@{$user->username}) has joined your branch.",
                'reference_type' => User::class,
                'reference_id'   => $user->id,
                'data'           => [
                    'user_id'          => $user->id,
                    'user_name'        => $user->name,
                    'source_branch_id' => $oldBranchId,
                    'role'             => $destinationRole,
                    'moved_by_id'      => $actor->id,
                    'moved_by_name'    => $actor->name,
                ],
            ]);

            return [
                'user'            => $user,
                'source_branch'   => $sourceBranch,
                'destination'     => $destinationBranch,
                'new_login_url'   => url("/{$destinationBranch->slug}"),
                'message'         => "Successfully transferred {$user->name} to {$destinationBranch->name}.",
            ];
        });
    }
}
