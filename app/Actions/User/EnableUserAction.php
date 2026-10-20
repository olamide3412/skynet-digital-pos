<?php

namespace App\Actions\User;

use App\Models\AppNotification;
use App\Models\OrganizationActivityLog;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnableUserAction
{
    /**
     * Pre-check enabling a user.
     */
    public function precheck(User $actor, User $user): array
    {
        $blocks = [];

        if ($user->is_active) {
            $blocks[] = 'This account is already enabled.';
        }

        if ($user->isSuperAdmin() || $user->isOrganizationAdmin()) {
            $blocks[] = 'Super Admin and Organization Admin accounts cannot be managed here.';
        }

        return [
            'can_enable' => empty($blocks),
            'blocks'     => $blocks,
            'warnings'   => [],
        ];
    }

    /**
     * Re-enable user account, audit log, and notify branch admin.
     */
    public function execute(User $actor, User $user, ?string $reason = null): void
    {
        $precheck = $this->precheck($actor, $user);
        if (!$precheck['can_enable']) {
            throw ValidationException::withMessages([
                'user' => $precheck['blocks'],
            ]);
        }

        DB::transaction(function () use ($actor, $user, $reason) {
            $user->update([
                'is_active'       => true,
                'disabled_at'     => null,
                'disabled_by'     => null,
                'disabled_reason' => null,
            ]);

            // Audit Log
            if ($user->organization_id) {
                OrganizationActivityLog::create([
                    'organization_id' => $user->organization_id,
                    'actor_user_id'   => $actor->id,
                    'action'          => 'user_enabled',
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
                "Restored staff user account '{$user->name}' (@{$user->username})" . ($reason ? " Note: {$reason}" : ''),
                $user->branch_id
            );

            // In-app Notification to Branch Admin
            if ($user->branch_id) {
                AppNotification::create([
                    'branch_id'      => $user->branch_id,
                    'user_id'        => null,
                    'type'           => 'staff_enabled',
                    'title'          => 'Staff Account Restored',
                    'message'        => "Account for {$user->name} (@{$user->username}) has been re-enabled by {$actor->name}.",
                    'reference_type' => User::class,
                    'reference_id'   => $user->id,
                    'data'           => [
                        'user_id'       => $user->id,
                        'user_name'     => $user->name,
                        'enabled_by_id' => $actor->id,
                        'reason'        => $reason,
                    ],
                ]);
            }
        });
    }
}
