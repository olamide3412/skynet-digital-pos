<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\PosSettings;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\InventoryPostingService;
use App\Modules\Accounting\Services\SalePostingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AccountingRepostCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounting:repost
                            {--branch= : Branch ID or slug}
                            {--from= : Start date (YYYY-MM-DD)}
                            {--to= : End date (YYYY-MM-DD)}
                            {--dry-run : Only inspect and list unposted transactions without creating journal entries}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry failed or missing accounting postings on or after accounting_started_at (idempotent)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $branchOption = $this->option('branch');
        $fromOption   = $this->option('from');
        $toOption     = $this->option('to');
        $isDryRun     = $this->option('dry-run');

        $query = Branch::with('organization')->where('is_active', true);

        if ($branchOption) {
            $query->where(function ($q) use ($branchOption) {
                if (is_numeric($branchOption)) {
                    $q->where('id', (int) $branchOption);
                } else {
                    $q->where('slug', $branchOption);
                }
            });
        }

        $branches = $query->get()->filter(function ($branch) {
            $hasFeature = !$branch->organization || $branch->organization->hasFeature('accounting');
            $settings = PosSettings::forBranch($branch->id);
            return $hasFeature && !empty($settings->accounting_started_at);
        });

        if ($branches->isEmpty()) {
            $this->warn('No eligible active accounting branches found matching criteria.');
            return 0;
        }

        $this->info("Scanning " . $branches->count() . " active accounting branch(es)..." . ($isDryRun ? ' [DRY RUN MODE]' : ''));

        $totalSalesReposted = 0;
        $totalExpensesReposted = 0;
        $totalPoReposted = 0;

        foreach ($branches as $branch) {
            $settings = PosSettings::forBranch($branch->id);
            $startDate = $settings->accounting_started_at->startOfDay();

            if ($fromOption) {
                $fromDate = Carbon::parse($fromOption)->startOfDay();
                if ($fromDate->gt($startDate)) {
                    $startDate = $fromDate;
                }
            }

            $endDate = $toOption ? Carbon::parse($toOption)->endOfDay() : now()->endOfDay();

            $this->line("--> Branch: [{$branch->id}] {$branch->name} (Books active since: {$settings->accounting_started_at->toDateString()})");

            // 1. Unposted Sales
            Sale::where('branch_id', $branch->id)
                ->where('created_at', '>=', $startDate)
                ->where('created_at', '<=', $endDate)
                ->whereNotExists(function ($q) {
                    $q->select(\Illuminate\Support\Facades\DB::raw(1))
                      ->from('journal_entries')
                      ->whereColumn('journal_entries.source_id', 'sales.id')
                      ->where('journal_entries.source_type', Sale::class)
                      ->where('journal_entries.event', 'sale_completed')
                      ->whereNull('journal_entries.reversal_of_id');
                })
                ->chunkById(50, function ($sales) use ($isDryRun, &$totalSalesReposted) {
                    foreach ($sales as $sale) {
                        if ($isDryRun) {
                            $this->line("    [Sale] #{$sale->receipt_id} (Amount: {$sale->final_total}) - unposted");
                            $totalSalesReposted++;
                        } else {
                            $entry = SalePostingService::postSale($sale);
                            if ($entry) {
                                $totalSalesReposted++;
                            }
                        }
                    }
                });

            // 2. Unposted Expenses
            Expense::where('branch_id', $branch->id)
                ->where('expense_date', '>=', $startDate->toDateString())
                ->where('expense_date', '<=', $endDate->toDateString())
                ->whereNotExists(function ($q) {
                    $q->select(\Illuminate\Support\Facades\DB::raw(1))
                      ->from('journal_entries')
                      ->whereColumn('journal_entries.source_id', 'expenses.id')
                      ->where('journal_entries.source_type', Expense::class)
                      ->where('journal_entries.event', 'expense_recorded')
                      ->whereNull('journal_entries.reversal_of_id');
                })
                ->chunkById(50, function ($expenses) use ($isDryRun, &$totalExpensesReposted) {
                    foreach ($expenses as $expense) {
                        if ($isDryRun) {
                            $this->line("    [Expense] #{$expense->id} (Amount: {$expense->amount}) - unposted");
                            $totalExpensesReposted++;
                        } else {
                            $entry = InventoryPostingService::postExpense($expense);
                            if ($entry) {
                                $totalExpensesReposted++;
                            }
                        }
                    }
                });

            // 3. Unposted Received POs
            PurchaseOrder::where('branch_id', $branch->id)
                ->whereIn('status', ['Received', 'Partial'])
                ->where('created_at', '>=', $startDate)
                ->where('created_at', '<=', $endDate)
                ->whereNotExists(function ($q) {
                    $q->select(\Illuminate\Support\Facades\DB::raw(1))
                      ->from('journal_entries')
                      ->whereColumn('journal_entries.source_id', 'purchase_orders.id')
                      ->where('journal_entries.source_type', PurchaseOrder::class)
                      ->where('journal_entries.event', 'like', 'purchase_receipt%')
                      ->whereNull('journal_entries.reversal_of_id');
                })
                ->chunkById(50, function ($pos) use ($isDryRun, &$totalPoReposted) {
                    foreach ($pos as $po) {
                        $receivedTotal = (float) $po->receivedItems()->sum(\Illuminate\Support\Facades\DB::raw('qty * cost'));
                        if ($receivedTotal <= 0) {
                            $receivedTotal = (float) $po->total_amount;
                        }

                        if ($isDryRun) {
                            $this->line("    [PO] #{$po->po_number} (Received Cost: {$receivedTotal}) - unposted");
                            $totalPoReposted++;
                        } else {
                            $entry = InventoryPostingService::postPurchaseOrderReceipt($po, $receivedTotal, $po->user_id);
                            if ($entry) {
                                $totalPoReposted++;
                            }
                        }
                    }
                });
        }

        if ($isDryRun) {
            $this->info("Dry run complete: Found {$totalSalesReposted} sales, {$totalExpensesReposted} expenses, {$totalPoReposted} PO receipts pending posting.");
        } else {
            $this->info("Re-post complete: Successfully processed {$totalSalesReposted} sales, {$totalExpensesReposted} expenses, {$totalPoReposted} PO receipts.");
        }

        return 0;
    }
}
