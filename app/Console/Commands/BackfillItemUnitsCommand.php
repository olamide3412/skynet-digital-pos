<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillItemUnitsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'items:backfill-units 
                            {--branch= : Specific branch ID to backfill} 
                            {--dry-run : Simulate backfill without making database changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely backfills legacy pack and carton item pricing/quantities into the flexible item_units table.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun  = $this->option('dry-run');
        $branchId  = $this->option('branch');

        $this->info('=====================================================');
        $this->info('  Item Units / Variants Safe Backfill (Step 2)       ');
        $this->info('=====================================================');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE ENABLED: No database records will be created.');
        }

        $query = Item::query();
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $items = $query->orderBy('id')->get();
        $totalItems = $items->count();
        $this->info("Found {$totalItems} item(s) to inspect.");

        $createdPacks   = 0;
        $createdCartons = 0;
        $skippedCount   = 0;
        $itemsWithUnits = 0;

        $rows = [];

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                $hasUnitCreatedForItem = false;

                // 1. Pack Variant
                $hasPackData = ((int) $item->units_per_pack > 1)
                    || ((float) $item->pack_price > 0)
                    || ((float) $item->pack_wholesale_price > 0);

                if ($hasPackData) {
                    $packName = trim($item->pack_label ?: 'Pack');
                    $packQty  = max(1, (int) ($item->units_per_pack ?: 1));
                    $packSell = (float) $item->pack_price > 0
                        ? (float) $item->pack_price
                        : round((float) $item->price * $packQty, 2);
                    $packWs   = (float) $item->pack_wholesale_price > 0
                        ? (float) $item->pack_wholesale_price
                        : 0;

                    // Idempotency check: don't create if matching unit name or qty already exists
                    $existingPack = ItemUnit::where('item_id', $item->id)
                        ->where(function ($q) use ($packName, $packQty) {
                            $q->whereRaw('LOWER(unit_name) = ?', [strtolower($packName)])
                              ->orWhere('quantity_in_base_units', $packQty);
                        })
                        ->first();

                    if (!$existingPack) {
                        if (!$isDryRun) {
                            ItemUnit::create([
                                'item_id'                => $item->id,
                                'branch_id'              => $item->branch_id,
                                'unit_name'              => $packName,
                                'quantity_in_base_units' => $packQty,
                                'selling_price'          => $packSell,
                                'wholesale_price'        => $packWs,
                                'sort_order'             => 1,
                                'status'                 => 'active',
                            ]);
                        }
                        $createdPacks++;
                        $hasUnitCreatedForItem = true;
                        $rows[] = [
                            'item_id'   => $item->id,
                            'item_name' => mb_strimwidth($item->item_name, 0, 25, '…'),
                            'variant'   => $packName,
                            'base_qty'  => $packQty,
                            'price'     => '₦' . number_format($packSell, 2),
                            'action'    => $isDryRun ? '[DRY RUN] Would Create' : 'Created',
                        ];
                    }
                }

                // 2. Carton Variant
                $unitsPerPack   = max(1, (int) ($item->units_per_pack ?: 1));
                $packsPerCarton = max(1, (int) ($item->packs_per_carton ?: 1));
                $totalCartonQty = $unitsPerPack * $packsPerCarton;

                $hasCartonData = ((int) $item->packs_per_carton > 1)
                    || ((float) $item->carton_price > 0)
                    || ((float) $item->carton_wholesale_price > 0);

                if ($hasCartonData && $totalCartonQty > 1) {
                    $cartonName = trim($item->carton_label ?: 'Carton');
                    $cartonSell = (float) $item->carton_price > 0
                        ? (float) $item->carton_price
                        : round((float) $item->price * $totalCartonQty, 2);
                    $cartonWs   = (float) $item->carton_wholesale_price > 0
                        ? (float) $item->carton_wholesale_price
                        : 0;

                    // Idempotency check
                    $existingCarton = ItemUnit::where('item_id', $item->id)
                        ->where(function ($q) use ($cartonName, $totalCartonQty) {
                            $q->whereRaw('LOWER(unit_name) = ?', [strtolower($cartonName)])
                              ->orWhere('quantity_in_base_units', $totalCartonQty);
                        })
                        ->first();

                    if (!$existingCarton) {
                        if (!$isDryRun) {
                            ItemUnit::create([
                                'item_id'                => $item->id,
                                'branch_id'              => $item->branch_id,
                                'unit_name'              => $cartonName,
                                'quantity_in_base_units' => $totalCartonQty,
                                'selling_price'          => $cartonSell,
                                'wholesale_price'        => $cartonWs,
                                'sort_order'             => 2,
                                'status'                 => 'active',
                            ]);
                        }
                        $createdCartons++;
                        $hasUnitCreatedForItem = true;
                        $rows[] = [
                            'item_id'   => $item->id,
                            'item_name' => mb_strimwidth($item->item_name, 0, 25, '…'),
                            'variant'   => $cartonName,
                            'base_qty'  => $totalCartonQty,
                            'price'     => '₦' . number_format($cartonSell, 2),
                            'action'    => $isDryRun ? '[DRY RUN] Would Create' : 'Created',
                        ];
                    }
                }

                if ($hasUnitCreatedForItem) {
                    $itemsWithUnits++;
                } else {
                    $skippedCount++;
                }
            }

            if ($isDryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Error during backfill: ' . $e->getMessage());
            return Command::FAILURE;
        }

        if (!empty($rows)) {
            $this->table(['Item ID', 'Item Name', 'Variant Unit', 'Base Units Multiplier', 'Selling Price', 'Status'], array_slice($rows, 0, 40));
            if (count($rows) > 40) {
                $this->line("... and " . (count($rows) - 40) . " more variants.");
            }
        }

        $this->newLine();
        $this->info('--- Summary ---');
        $this->line("Total items inspected:        {$totalItems}");
        $this->line("Items with variants created:  {$itemsWithUnits}");
        $this->line("Pack variants generated:      {$createdPacks}");
        $this->line("Carton variants generated:    {$createdCartons}");
        $this->line("Items base-unit only/skipped: {$skippedCount}");
        $this->line("Total item_units in DB now:   " . ItemUnit::count());
        $this->newLine();

        $this->info('Backfill completed successfully!');
        return Command::SUCCESS;
    }
}
