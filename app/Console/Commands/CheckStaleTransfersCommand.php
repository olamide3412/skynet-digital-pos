<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\InterBranchTransfer;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckStaleTransfersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transfers:check-stale {--max-days=5}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for stale pending inter-branch transfers and dispatch reminder or escalation notifications (max 5 days)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $maxDays = min(5, max(1, (int) $this->option('max-days')));
        $this->info("Checking for pending inter-branch transfers (Max allowed: {$maxDays} days)...");

        $pendingTransfers = InterBranchTransfer::with(['sourceBranch', 'destinationBranch', 'organization'])
            ->where('status', 'pending')
            ->get();

        $remindersCount = 0;
        $escalationsCount = 0;

        foreach ($pendingTransfers as $transfer) {
            $sentAt = $transfer->sent_at ?? $transfer->created_at;
            $ageInDays = Carbon::now()->diffInDays($sentAt);

            if ($ageInDays >= $maxDays) {
                // Day 5 Escalation: Notify destination, sender, and admin
                AppNotification::create([
                    'branch_id'      => $transfer->destination_branch_id,
                    'user_id'        => null,
                    'type'           => 'stock_conflict',
                    'title'          => "URGENT: Stale Transfer Escalation ({$ageInDays} Days) — {$transfer->reference}",
                    'message'        => "Stock transfer {$transfer->reference} from '{$transfer->sourceBranch?->name}' has been pending for {$ageInDays} days without action. Please inspect and accept or request sender cancellation.",
                    'action_url'     => "/{$transfer->destinationBranch?->slug}/inventory/transfers/inter-branch",
                    'reference_type' => InterBranchTransfer::class,
                    'reference_id'   => $transfer->id,
                ]);

                AppNotification::create([
                    'branch_id'      => $transfer->source_branch_id,
                    'user_id'        => null,
                    'type'           => 'stock_conflict',
                    'title'          => "URGENT: Stale Transfer Dispatched ({$ageInDays} Days) — {$transfer->reference}",
                    'message'        => "Transfer {$transfer->reference} sent to '{$transfer->destinationBranch?->name}' is unaccepted after {$ageInDays} days. You may cancel it to restore stock if the goods did not arrive.",
                    'action_url'     => "/{$transfer->sourceBranch?->slug}/inventory/transfers/inter-branch",
                    'reference_type' => InterBranchTransfer::class,
                    'reference_id'   => $transfer->id,
                ]);

                $escalationsCount++;
                $this->warn("Escalated transfer {$transfer->reference} (Age: {$ageInDays} days).");
            } elseif ($ageInDays >= 2) {
                // Day 2-4 Reminder
                AppNotification::create([
                    'branch_id'      => $transfer->destination_branch_id,
                    'user_id'        => null,
                    'type'           => 'inter_branch_transfer',
                    'title'          => "Reminder: Pending Stock Transfer — {$transfer->reference}",
                    'message'        => "Transfer {$transfer->reference} from '{$transfer->sourceBranch?->name}' has been in transit for {$ageInDays} days awaiting receipt.",
                    'action_url'     => "/{$transfer->destinationBranch?->slug}/inventory/transfers/inter-branch",
                    'reference_type' => InterBranchTransfer::class,
                    'reference_id'   => $transfer->id,
                ]);

                $remindersCount++;
                $this->line("Sent reminder for transfer {$transfer->reference} (Age: {$ageInDays} days).");
            }
        }

        $this->info("Completed check: {$remindersCount} reminders sent, {$escalationsCount} escalations dispatched.");
        return Command::SUCCESS;
    }
}
