<?php

namespace App\Console\Commands;

use App\Models\Branch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrganizationsBackfillCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'organizations:backfill
                            {--map= : Path to CSV file mapping branch_slug to organization_name (columns: branch_slug,organization_name[,contact_name,contact_email,contact_phone,address])}
                            {--auto : Automatically create/assign each unassigned branch to an organization with the same name}
                            {--dry-run : Simulate execution without persisting any database changes}
                            {--rollback : Roll back organization assignments recorded in organization_activity_log}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Idempotently backfill existing branches into organizations using a mapping file or auto-matching, with dry-run and rollback support.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $isRollback = (bool) $this->option('rollback');
        $mapPath = $this->option('map');

        $this->info('====================================================');
        $this->info(' Organization Layer — Branches Backfill Utility');
        $this->info('====================================================');

        if ($isRollback) {
            return $this->handleRollback($isDryRun);
        }

        if ($this->option('auto')) {
            return $this->handleAutoBackfill($isDryRun);
        }

        if (empty($mapPath)) {
            $this->error('Error: Either the --map option or --auto flag is required.');
            $this->line('Usage:');
            $this->line('  php artisan organizations:backfill --map=path/to/mapping.csv [--dry-run]');
            $this->line('  php artisan organizations:backfill --auto [--dry-run]');
            return Command::FAILURE;
        }

        return $this->handleBackfill($mapPath, $isDryRun);
    }

    /**
     * Handle auto-backfilling branches into organizations using branch details.
     */
    protected function handleAutoBackfill(bool $isDryRun): int
    {
        $unassignedBranches = Branch::whereNull('organization_id')->get();

        if ($unassignedBranches->isEmpty()) {
            $this->info('All branches already belong to an organization. Nothing to backfill.');
            return Command::SUCCESS;
        }

        $this->info("Found " . $unassignedBranches->count() . " unassigned branch(es). Auto-generating organization mappings from branch profiles...");

        $mappings = [];
        foreach ($unassignedBranches as $branch) {
            $mappings[$branch->slug] = [
                'branch_slug'       => $branch->slug,
                'organization_name' => $branch->name,
                'contact_name'      => $branch->name,
                'contact_email'     => $branch->email,
                'contact_phone'     => $branch->phone,
                'address'           => $branch->address,
            ];
        }

        return $this->executeBackfill($mappings, $unassignedBranches, $isDryRun);
    }

    /**
     * Handle backfilling branches into organizations using a mapping file.
     */
    protected function handleBackfill(string $mapPath, bool $isDryRun): int
    {
        if (!file_exists($mapPath)) {
            $this->error("Mapping file not found at: {$mapPath}");
            return Command::FAILURE;
        }

        // Parse CSV mapping file
        $mappings = $this->parseMapFile($mapPath);
        if ($mappings === null) {
            return Command::FAILURE;
        }

        $this->info("Loaded " . count($mappings) . " branch mappings from CSV.");

        // Fetch all branches with null organization_id
        $unassignedBranches = Branch::whereNull('organization_id')->get();

        if ($unassignedBranches->isEmpty()) {
            $this->info('All branches already belong to an organization. Nothing to backfill.');
            return Command::SUCCESS;
        }

        // Verify that EVERY branch with null organization_id is in the map
        $unmappedSlugs = [];
        foreach ($unassignedBranches as $branch) {
            if (!isset($mappings[$branch->slug])) {
                $unmappedSlugs[] = "ID {$branch->id}: {$branch->name} (slug: '{$branch->slug}')";
            }
        }

        if (!empty($unmappedSlugs)) {
            $this->error('ABORTING: The following unassigned branches are missing from the mapping file:');
            foreach ($unmappedSlugs as $unmapped) {
                $this->line("  - {$unmapped}");
            }
            $this->warn('Per specification, the command will NOT guess or assign a default organization.');
            $this->line('Please add these branches to the mapping file, or run with --auto.');
            return Command::FAILURE;
        }

        return $this->executeBackfill($mappings, $unassignedBranches, $isDryRun);
    }

    /**
     * Execute the backfill transaction with mapped assignments.
     */
    protected function executeBackfill(array $mappings, $unassignedBranches, bool $isDryRun): int
    {
        if ($isDryRun) {
            $this->warn('[DRY-RUN MODE] No database changes will be written.');
        }

        $summary = [];

        DB::beginTransaction();

        try {
            foreach ($unassignedBranches as $branch) {
                $mapping = $mappings[$branch->slug];
                $orgName = trim($mapping['organization_name']);

                // Find or create organization (idempotent)
                $organization = DB::table('organizations')->where('name', $orgName)->first();

                if (!$organization) {
                    $insertData = [
                        'name'          => $orgName,
                        'contact_name'  => $mapping['contact_name'] ?? null,
                        'contact_email' => $mapping['contact_email'] ?? null,
                        'contact_phone' => $mapping['contact_phone'] ?? null,
                        'address'       => $mapping['address'] ?? null,
                        'status'        => 'active',
                        'notes'         => 'Created via organizations:backfill migration',
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];

                    if (!$isDryRun) {
                        $orgId = DB::table('organizations')->insertGetId($insertData);
                    } else {
                        $orgId = 9999; // Placeholder for dry run
                    }
                    $orgAction = 'CREATE';
                } else {
                    $orgId = $organization->id;
                    $orgAction = 'EXISTING';
                }

                // Assign branch to organization
                if (!$isDryRun) {
                    DB::table('branches')
                        ->where('id', $branch->id)
                        ->update(['organization_id' => $orgId]);

                    // Audit log entry
                    DB::table('organization_activity_log')->insert([
                        'organization_id' => $orgId,
                        'actor_user_id'   => null, // System console execution
                        'action'          => 'branch_backfilled',
                        'details'         => json_encode([
                            'branch_id'   => $branch->id,
                            'branch_name' => $branch->name,
                            'branch_slug' => $branch->slug,
                            'command'     => 'organizations:backfill',
                        ]),
                        'created_at'      => now(),
                    ]);
                }

                $summary[] = [
                    'Branch ID'    => $branch->id,
                    'Branch Slug'  => $branch->slug,
                    'Branch Name'  => $branch->name,
                    'Org Action'   => $orgAction,
                    'Org Name'     => $orgName,
                    'Org ID'       => $orgId,
                ];
            }

            if ($isDryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }

            $this->table(['Branch ID', 'Branch Slug', 'Branch Name', 'Org Action', 'Org Name', 'Org ID'], $summary);

            if ($isDryRun) {
                $this->info('Dry-run completed successfully. Run without --dry-run to commit changes.');
            } else {
                $this->info('Backfill completed successfully. All assignments logged to organization_activity_log.');
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Failed to backfill branches: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Handle rollback of backfilled assignments.
     */
    protected function handleRollback(bool $isDryRun): int
    {
        $this->warn('Initiating rollback of backfilled branch assignments...');

        // Find activity log records created by organizations:backfill
        $logs = DB::table('organization_activity_log')
            ->where('action', 'branch_backfilled')
            ->orderBy('id', 'desc')
            ->get();

        if ($logs->isEmpty()) {
            $this->info('No backfilled branch assignments found in organization_activity_log. Nothing to roll back.');
            return Command::SUCCESS;
        }

        $this->info("Found {$logs->count()} backfill log entries to revert.");

        if ($isDryRun) {
            $this->warn('[DRY-RUN MODE] No changes will be persisted.');
        }

        DB::beginTransaction();

        try {
            $reverted = [];

            foreach ($logs as $log) {
                $details = json_decode($log->details, true) ?? [];
                $branchId = $details['branch_id'] ?? null;

                if ($branchId) {
                    $branch = Branch::find($branchId);
                    if ($branch && $branch->organization_id) {
                        if (!$isDryRun) {
                            $branch->organization_id = null;
                            $branch->save();

                            // Record rollback in activity log
                            DB::table('organization_activity_log')->insert([
                                'organization_id' => $log->organization_id,
                                'actor_user_id'   => null,
                                'action'          => 'branch_backfill_rolled_back',
                                'details'         => json_encode([
                                    'branch_id'   => $branch->id,
                                    'branch_slug' => $branch->slug,
                                    'reverted_log_id' => $log->id,
                                ]),
                                'created_at'      => now(),
                            ]);
                        }

                        $reverted[] = [
                            'Branch ID'   => $branch->id,
                            'Branch Slug' => $branch->slug,
                            'Name'        => $branch->name,
                            'Previous Org ID' => $log->organization_id,
                        ];
                    }
                }
            }

            if ($isDryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }

            if (!empty($reverted)) {
                $this->table(['Branch ID', 'Branch Slug', 'Name', 'Previous Org ID'], $reverted);
            }

            $this->info('Rollback completed successfully.');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Failed to rollback backfill: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Parse CSV mapping file.
     *
     * @return array<string, array>|null Keyed by branch_slug
     */
    protected function parseMapFile(string $filePath): ?array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->error("Unable to open file: {$filePath}");
            return null;
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            $this->error("Mapping file is empty.");
            return null;
        }

        // Clean UTF-8 BOM if present
        $header[0] = preg_replace('/[\xEF\xBB\xBF]/', '', $header[0]);
        $header = array_map('trim', array_map('strtolower', $header));

        $slugIndex = array_search('branch_slug', $header);
        $orgNameIndex = array_search('organization_name', $header);

        if ($slugIndex === false || $orgNameIndex === false) {
            fclose($handle);
            $this->error("Mapping file must have 'branch_slug' and 'organization_name' columns in the header.");
            return null;
        }

        $contactNameIndex  = array_search('contact_name', $header);
        $contactEmailIndex = array_search('contact_email', $header);
        $contactPhoneIndex = array_search('contact_phone', $header);
        $addressIndex      = array_search('address', $header);

        $mappings = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (empty(array_filter($row))) {
                continue;
            }

            $slug = trim($row[$slugIndex] ?? '');
            $orgName = trim($row[$orgNameIndex] ?? '');

            if ($slug === '' || $orgName === '') {
                continue;
            }

            $mappings[$slug] = [
                'branch_slug'       => $slug,
                'organization_name' => $orgName,
                'contact_name'      => $contactNameIndex !== false ? trim($row[$contactNameIndex] ?? '') : null,
                'contact_email'     => $contactEmailIndex !== false ? trim($row[$contactEmailIndex] ?? '') : null,
                'contact_phone'     => $contactPhoneIndex !== false ? trim($row[$contactPhoneIndex] ?? '') : null,
                'address'           => $addressIndex !== false ? trim($row[$addressIndex] ?? '') : null,
            ];
        }

        fclose($handle);

        if (empty($mappings)) {
            $this->error("Mapping file contains no valid rows.");
            return null;
        }

        return $mappings;
    }
}
