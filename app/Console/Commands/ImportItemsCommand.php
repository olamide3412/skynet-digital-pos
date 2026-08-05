<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportItemsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:import-items 
                            {file : Path to the CSV file to import} 
                            {--branch= : Optional Branch ID or Slug (defaults to main branch)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import items from an external CSV file (maps old POS schema to new POS schema)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("File not found at: {$filePath}");
            return Command::FAILURE;
        }

        // Determine target branch
        $branchOption = $this->option('branch');
        if ($branchOption) {
            $branch = Branch::where('id', $branchOption)->orWhere('slug', $branchOption)->first();
        } else {
            $branch = Branch::first();
        }

        if (!$branch) {
            $this->error("No active branch found. Please create a branch or pass --branch=ID");
            return Command::FAILURE;
        }

        $this->info("Importing items for branch: {$branch->name} (ID: {$branch->id})");

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->error("Could not open file: {$filePath}");
            return Command::FAILURE;
        }

        // Read header row
        $rawHeader = fgetcsv($handle);
        if (!$rawHeader) {
            $this->error("CSV file appears to be empty.");
            fclose($handle);
            return Command::FAILURE;
        }

        // Normalize header column names (lowercase, trimmed)
        $header = array_map(fn($col) => strtolower(trim($col)), $rawHeader);

        // Helper to find column index case-insensitively
        $getColIndex = function (array $possibleNames) use ($header): ?int {
            foreach ($possibleNames as $name) {
                $idx = array_search(strtolower($name), $header, true);
                if ($idx !== false) return $idx;
            }
            return null;
        };

        $idIdx          = $getColIndex(['id']);
        $barcodeIdx     = $getColIndex(['barcode_number', 'barcode', 'code']);
        $nameIdx        = $getColIndex(['item_name', 'name', 'title']);
        $catIdx         = $getColIndex(['category_name', 'category']);
        $qtyIdx         = $getColIndex(['qty', 'quantity', 'stock']);
        $buyPriceIdx    = $getColIndex(['buy_price', 'buying_price', 'cost']);
        $priceIdx       = $getColIndex(['price', 'selling_price']);
        $wholesaleIdx   = $getColIndex(['wholesale', 'wholesale_price']);
        $expiryIdx      = $getColIndex(['expiry_date', 'expiry']);

        $importedCount = 0;
        $categoryCache = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (empty(array_filter($row))) continue; // Skip blank lines

            $oldId        = $idIdx !== null ? trim($row[$idIdx] ?? '') : '';
            $itemName     = $nameIdx !== null ? trim($row[$nameIdx] ?? '') : '';

            if (!$itemName) continue; // Item name is required

            $barcode      = $barcodeIdx !== null ? trim($row[$barcodeIdx] ?? '') : '';
            $categoryName = $catIdx !== null ? trim($row[$catIdx] ?? '') : '';
            $qty          = $qtyIdx !== null ? (int) ($row[$qtyIdx] ?? 0) : 0;
            $buyPrice     = $buyPriceIdx !== null ? (float) ($row[$buyPriceIdx] ?? 0) : 0.0;
            $price        = $priceIdx !== null ? (float) ($row[$priceIdx] ?? 0) : 0.0;
            $wholesale    = $wholesaleIdx !== null ? (float) ($row[$wholesaleIdx] ?? 0) : $price;
            $expiryDate   = $expiryIdx !== null ? trim($row[$expiryIdx] ?? '') : null;

            // Handle barcode cleaning
            if (strtoupper($barcode) === 'NO_BARCODE' || empty($barcode)) {
                $barcode = 'BAR-' . ($oldId ? str_pad($oldId, 6, '0', STR_PAD_LEFT) : strtoupper(Str::random(6)));
            }

            // Category lookup / creation
            $categoryId = null;
            if ($categoryName) {
                $catKey = strtolower($categoryName);
                if (!isset($categoryCache[$catKey])) {
                    $category = Category::firstOrCreate(
                        ['name' => $categoryName, 'branch_id' => $branch->id],
                        ['slug' => Str::slug($categoryName)]
                    );
                    $categoryCache[$catKey] = $category->id;
                }
                $categoryId = $categoryCache[$catKey];
            }

            // Check if item already exists by barcode or name in branch
            $item = Item::where('branch_id', $branch->id)
                ->where(function($q) use ($barcode, $itemName) {
                    $q->where('barcode_number', $barcode)
                      ->orWhere('item_name', $itemName);
                })
                ->first();

            if (!$item) {
                $item = new Item();
                $item->branch_id      = $branch->id;
                $item->barcode_number = $barcode;
            }

            $item->item_name        = $itemName;
            $item->category_id      = $categoryId;
            $item->buy_price        = $buyPrice;
            $item->price            = $price;
            $item->wholesale_price  = $wholesale > 0 ? $wholesale : $price;
            $item->front_store_qty  = $qty;
            $item->back_store_qty   = 0;
            $item->price_locked     = true;

            if ($expiryDate && strtotime($expiryDate)) {
                $item->expiry_date = date('Y-m-d', strtotime($expiryDate));
            }

            $item->save();
            $importedCount++;
        }

        fclose($handle);

        $this->info("Successfully imported {$importedCount} item(s) to branch '{$branch->name}'!");
        return Command::SUCCESS;
    }
}
