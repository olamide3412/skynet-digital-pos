<?php

namespace App\Services;

use App\Mail\BroadcastNotificationMail;
use App\Models\AdminBroadcast;
use App\Models\AppNotification;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BroadcastService
{
    /**
     * Resolve the target recipient users based on audience criteria.
     *
     * @param string $targetType  'all' | 'branch' | 'branches' | 'role' | 'branch_role'
     * @param array|null $targetData
     * @return Collection<int, User>
     */
    public static function resolveRecipients(string $targetType, ?array $targetData = []): Collection
    {
        $query = User::with('branch');

        switch ($targetType) {
            case 'branch':
                $branchId = $targetData['branch_id'] ?? null;
                if ($branchId) {
                    $query->where('branch_id', $branchId);
                }
                break;

            case 'branches':
                $branchIds = $targetData['branch_ids'] ?? [];
                if (!empty($branchIds)) {
                    $query->whereIn('branch_id', (array) $branchIds);
                }
                break;

            case 'role':
                $roles = (array) ($targetData['roles'] ?? $targetData['role'] ?? []);
                if (!empty($roles)) {
                    $query->whereHas('roles', function ($q) use ($roles) {
                        $q->whereIn('name', $roles);
                    });
                }
                break;

            case 'branch_role':
                $branchIds = (array) ($targetData['branch_ids'] ?? $targetData['branch_id'] ?? []);
                $roles = (array) ($targetData['roles'] ?? $targetData['role'] ?? []);

                if (!empty($branchIds)) {
                    $query->whereIn('branch_id', $branchIds);
                }
                if (!empty($roles)) {
                    $query->whereHas('roles', function ($q) use ($roles) {
                        $q->whereIn('name', $roles);
                    });
                }
                break;

            case 'all':
            default:
                // System-wide: all users
                break;
        }

        return $query->get();
    }

    /**
     * Process and dispatch a broadcast notification to targeted recipients.
     */
    public static function sendBroadcast(array $payload, ?User $sender = null): AdminBroadcast
    {
        $targetType = $payload['target_type'] ?? 'all';
        $targetData = $payload['target_data'] ?? [];
        $channels   = $payload['channels'] ?? ['in_app'];
        $title      = trim($payload['title']);
        $message    = trim($payload['message']);

        // Create the master broadcast record
        $broadcast = AdminBroadcast::create([
            'sender_id'     => $sender?->id ?? auth()->id(),
            'title'         => $title,
            'message'       => $message,
            'target_type'   => $targetType,
            'target_data'   => $targetData,
            'channels'      => $channels,
            'status'        => 'processing',
        ]);

        $recipients = static::resolveRecipients($targetType, $targetData);

        $inAppCount        = 0;
        $emailSentCount    = 0;
        $emailFailedCount  = 0;
        $noEmailCount      = 0;
        $deliveryLogs      = [];

        $sendInApp = in_array('in_app', $channels);
        $sendEmail = in_array('email', $channels);

        $canSendEmail = false;
        if ($sendEmail) {
            $canSendEmail = MailConfigService::isConfiguredAndEnabled();
            if ($canSendEmail) {
                MailConfigService::applyDynamicConfig();
            } else {
                $deliveryLogs[] = [
                    'type'    => 'warning',
                    'message' => 'Email delivery was requested, but SMTP outgoing mail is disabled or not configured in Mail Settings.',
                    'time'    => now()->toIso8601String(),
                ];
            }
        }

        $notificationsToInsert = [];
        $now = now();

        foreach ($recipients as $recipient) {
            // ── 1. In-App Notification ─────────────────────────────────────────
            if ($sendInApp) {
                $branchSlug = $recipient->branch?->slug;
                $actionUrl = $branchSlug ? "/{$branchSlug}/notifications" : null;

                $notificationsToInsert[] = [
                    'branch_id'      => $recipient->branch_id,
                    'user_id'        => $recipient->id,
                    'type'           => 'admin_broadcast',
                    'title'          => $title,
                    'message'        => $message,
                    'action_url'     => $actionUrl,
                    'reference_type' => AdminBroadcast::class,
                    'reference_id'   => $broadcast->id,
                    'data'           => json_encode([
                        'broadcast_id' => $broadcast->id,
                        'sender_name'  => $sender?->name ?? 'Super Admin',
                        'target_type'  => $targetType,
                    ]),
                    'is_read'        => false,
                    'is_resolved'    => true, // Broadcasts are informational (pre-resolved)
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
                $inAppCount++;
            }

            // ── 2. Email Delivery ──────────────────────────────────────────────
            if ($sendEmail && $canSendEmail) {
                $email = trim($recipient->email ?? '');

                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $noEmailCount++;
                    $deliveryLogs[] = [
                        'user_id'   => $recipient->id,
                        'user_name' => $recipient->name,
                        'status'    => 'skipped_no_email',
                        'message'   => "User '{$recipient->name}' has no valid email address on file.",
                    ];
                    continue;
                }

                try {
                    $loginUrl = $recipient->branch?->slug
                        ? url("/{$recipient->branch->slug}/login")
                        : url('/');

                    Mail::to($email)->send(new BroadcastNotificationMail($broadcast, $recipient, $loginUrl));
                    $emailSentCount++;
                } catch (\Throwable $e) {
                    $emailFailedCount++;
                    Log::error("Failed to send broadcast email to {$email}: " . $e->getMessage());
                    $deliveryLogs[] = [
                        'user_id'   => $recipient->id,
                        'user_name' => $recipient->name,
                        'email'     => $email,
                        'status'    => 'failed',
                        'error'     => $e->getMessage(),
                    ];
                }
            }
        }

        // Bulk insert in-app notifications
        if (!empty($notificationsToInsert)) {
            AppNotification::insert($notificationsToInsert);
        }

        // Update master record
        $broadcast->update([
            'in_app_count'       => $inAppCount,
            'email_sent_count'   => $emailSentCount,
            'email_failed_count' => $emailFailedCount,
            'no_email_count'     => $noEmailCount,
            'delivery_logs'      => $deliveryLogs,
            'status'             => 'completed',
        ]);

        \App\Services\ActivityLogger::setting(
            "Dispatched Super Admin Broadcast '{$title}' to {$recipients->count()} user(s) [In-App: {$inAppCount}, Emails Sent: {$emailSentCount}]"
        );

        return $broadcast;
    }
}
