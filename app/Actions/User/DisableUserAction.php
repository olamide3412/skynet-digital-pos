<?php

namespace App\Actions\User;

use App\Models\AppNotification;
use App\Models\OrganizationActivityLog;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisableUserAction
{
    /**
     * Pre-check disabling a user.
     */
    public function precheck(User $actor, User $user): array
    {
        $blocks = [];
        $warnings = [];

        if ($user->id === $actor->id) {
            $blocks[] = 'You cannot disable your own account.';
        }

        if ($user->isSuperAdmin() || $user->isOrganizationAdmin()) {
            $blocks[] = 'Super Admin and Organization Admin accounts cannot be disabled here.';
        }

        if (!$user->is_active) {
            $blocks[] = 'This account is already disabled.';
        }

        // Warning if last active Branch Admin
        $branch = $user->branch;
        if ($branch) {
            setPermissionsTeamId($branch->id);
            if ($user->hasRole('branch-admin')) {
                $otherAdminsCount = User::where('branch_id', $branch->id)
                    ->where('id', '!=', $user->id)
                    ->where('is_active', true)
                    ->get()
                    ->filter(function ($u) use ($branch) {
                        setPermissionsTeamId($branch->id);
                        return $u->hasRole('branch-admin');
                    })
                    ->count();

                if ($otherAdminsCount === 0) {
                    $warnings[] = "Warning: This user is the last active Branch Admin for '{$branch->name}'. Disabling them will leave the branch without an active Branch Admin.";
                }
            }
        }

        return [
            'can_disable' => empty($blocks),
            'blocks'      => $blocks,
            'warnings'    => $warnings,
        ];
    }

    /**
     * Disable user account, invalidate sessions, audit log, and notify branch admin.
     */
    public function execute(User $actor, User $user, ?string $reason = null): void
    {
        $precheck = $this->precheck($actor, $user);
        if (!$precheck['can_disable']) {
            throw ValidationException::withMessages([
                'user' => $precheck['blocks'],
            ]);
        }

        DB::transaction(function () use ($actor, $user, $reason) {
            $user->update([
                'is_active'       => false,
                'disabled_at'     => now(),
                'disabled_by'     => $actor->id,
                'disabled_reason' => $reason,
            ]);

            // Immediately flush active database sessions
            try {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            } catch (\Throwable $e) {
                // Ignore if not database driver in test
            }

            // Audit Log
            if ($user->organization_id) {
                OrganizationActivityLog::create([
                    'organization_id' => $user->organization_id,
                    'actor_user_id'   => $actor->id,
                    'action'          => 'user_disabled',
                    'details'         => [
                        'user_id'   => $user->id,
                        'username'  => $user->username,
                        'branch_id' => $user->branch_id,
                        'reason'    => $reason,
                    ],
                    'created_at'      => now(),
                ]);
            }

            ActivityLogger::userAction(
                "Disabled staff user account '{$user->name}' (@{$user->username})" . ($reason ? " Reason: {$reason}" : ''),
                $user->branch_id
            );

            // In-app Notification to Branch Admin
            if ($user->branch_id) {
                AppNotification::create([
                    'branch_id'      => $user->branch_id,
                    'user_id'        => null,
                    'type'           => 'staff_disabled',
                    'title'          => 'Staff Account Disabled',
                    'message'        => "Account for {$user->name} (@{$user->username}) was disabled by {$actor->name}." . ($reason ? " Reason: {$reason}" : ''),
                    'reference_type' => User::class,
                    'reference_id'   => $user->id,
                    'data'           => [
                        'user_id'        => $user->id,
                        'user_name'      => $user->name,
                        'disabled_by_id' => $actor->id,
                        'reason'         => $reason,
                    ],
                ]);
            }
        });
    }
}
