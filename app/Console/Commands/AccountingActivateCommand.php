<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Modules\Accounting\Services\AccountSetupService;
use Illuminate\Console\Command;

class AccountingActivateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounting:activate {--branch= : Specific branch ID to activate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed Chart of Accounts and activate accounting for Silver+ branches (§5, §10)';

    /**
     * Execute the console command.
     */
    public function handle(AccountSetupService $setupService): int
    {
        $branchId = $this->option('branch');

        if ($branchId) {
            $branch = is_numeric($branchId)
                ? Branch::with('organization')->find($branchId)
                : Branch::with('organization')->where('slug', $branchId)->first();

            if (!$branch) {
                $this->error("Branch with identifier {$branchId} not found.");
                return self::FAILURE;
            }

            $branches = collect([$branch]);
        } else {
            // Find all active branches whose organization has feature_accounting
            $branches = Branch::with('organization')
                ->where('is_active', true)
                ->get()
                ->filter(function (Branch $b) {
                    return $b->organization && $b->organization->hasFeature('accounting');
                });
        }

        if ($branches->isEmpty()) {
            $this->info('No qualifying branches found to activate.');
            return self::SUCCESS;
        }

        $this->info("Activating accounting for {$branches->count()} branch(es)...");

        $bar = $this->output->createProgressBar($branches->count());
        $bar->start();

        $results = [];
        foreach ($branches as $branch) {
            $res = $setupService->setupBranch($branch);
            $results[] = [
                'ID'         => $branch->id,
                'Branch'     => $branch->name,
                'Org'        => $branch->organization?->name,
                'Accounts'   => $res['accounts_created'],
                'Started At' => $res['accounting_started_at'],
            ];
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['ID', 'Branch', 'Organization', 'New Accounts', 'Started At'], $results);
        $this->info('Accounting activation completed successfully.');

        return self::SUCCESS;
    }
}
