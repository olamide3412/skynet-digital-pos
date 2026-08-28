<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Category;
use App\Models\GroupAddress;
use App\Models\ImportLibraryFile;
use App\Models\Item;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ItemImportService
{
    /**
     * Standard column key definitions and possible header aliases.
     */
    protected static array $headerAliases = [
        'item_name' => ['item_name', 'item name', 'name', 'product_name', 'product name', 'item', 'title', 'product'],
        'category'  => ['category', 'category_name', 'category name', 'category_hint', 'dept', 'department'],
        'group_address' => ['group_address_name', 'group_address', 'group address', 'group', 'storage_location', 'storage location', 'shelf', 'shelf_location', 'shelf location', 'rack'],
        'barcode'   => ['barcode', 'barcode_number', 'barcode number', 'sku', 'upc', 'ean', 'code'],
        'description' => ['description', 'item_description', 'details', 'item description', 'notes'],
        'cost_price'  => ['cost_price', 'cost price', 'cost', 'buy_price', 'buy price', 'purchase_price', 'buying_price'],
        'base_unit_name' => ['base_unit_name', 'base unit name', 'unit_name', 'unit name', 'unit_label', 'unit label', 'unit', 'base_unit'],
        'base_unit_price' => ['base_unit_price', 'base unit price', 'price', 'selling_price', 'selling price', 'unit_price', 'retail_price', 'retail price'],
        'wholesale_price' => ['wholesale_price', 'wholesale price', 'ws_price', 'wholesale', 'base_wholesale_price'],
        'pack_unit_name' => ['pack_unit_name', 'pack unit name', 'pack_name', 'pack name', 'pack_label', 'pack label', 'pack_unit'],
        'pack_quantity'  => ['pack_quantity', 'pack quantity', 'units_per_pack', 'units per pack', 'pack_qty', 'pack size', 'pack_size'],
        'pack_price'     => ['pack_price', 'pack price', 'pack_selling_price', 'pack selling price'],
        'pack_wholesale_price' => ['pack_wholesale_price', 'pack wholesale price', 'pack_ws_price'],
        'carton_unit_name' => ['carton_unit_name', 'carton unit name', 'carton_name', 'carton name', 'carton_label', 'carton label', 'carton_unit'],
        'carton_quantity'  => ['carton_quantity', 'carton quantity', 'packs_per_carton', 'packs per carton', 'carton_qty', 'carton size', 'carton_size'],
        'carton_price'     => ['carton_price', 'carton price', 'carton_selling_price', 'carton selling price'],
        'carton_wholesale_price' => ['carton_wholesale_price', 'carton wholesale price', 'carton_ws_price'],
        'track_by_imei' => ['track_by_imei', 'track by imei', 'is_imei_tracked', 'imei_tracked', 'imei tracking', 'imei', 'track_imei'],
        'initial_stock_quantity' => ['initial_stock_quantity', 'initial stock quantity', 'initial_stock', 'initial stock', 'stock', 'stock_qty', 'qty', 'quantity', 'front_store_qty'],
        'expiry_date' => ['expiry_date', 'expiry date', 'expiration_date', 'expiration date', 'expiry', 'exp_date'],
    ];

    /**
     * Parse an uploaded file or stored path into structured row items.
     */
    public function parseFile(string|UploadedFile $file, ?string $extension = null): array
    {
        $realPath = $file instanceof UploadedFile ? $file->getRealPath() : (file_exists($file) ? $file : Storage::path($file));
        $ext = strtolower($extension ?: ($file instanceof UploadedFile ? $file->getClientOriginalExtension() : pathinfo($realPath, PATHINFO_EXTENSION)));

        if (!file_exists($realPath)) {
            throw new \InvalidArgumentException("Import file does not exist at: {$realPath}");
        }

        try {
            $spreadsheet = IOFactory::load($realPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rawRows = $sheet->toArray(null, true, true, true);
        } catch (Throwable $e) {
            throw new \RuntimeException("Unable to read file. Please ensure it is a valid CSV or Excel file. Error: " . $e->getMessage());
        }

        if (empty($rawRows)) {
            return [
                'total_rows'   => 0,
                'valid_rows'   => 0,
                'invalid_rows' => 0,
                'rows'         => [],
            ];
        }

        // Identify header row
        $headerRow = null;
        $headerIndex = 0;
        foreach ($rawRows as $idx => $row) {
            $nonEmpty = array_filter(array_map('trim', array_values($row)));
            if (!empty($nonEmpty)) {
                $headerRow = $row;
                $headerIndex = $idx;
                break;
            }
        }

        if (!$headerRow) {
            return [
                'total_rows'   => 0,
                'valid_rows'   => 0,
                'invalid_rows' => 0,
                'rows'         => [],
            ];
        }

        // Map column letter -> normalized key
        $columnMap = [];
        foreach ($headerRow as $colLetter => $headerText) {
            $normalizedHeader = strtolower(trim(preg_replace('/[\s\-_]+/', ' ', (string) $headerText)));
            foreach (self::$headerAliases as $standardKey => $aliases) {
                if (in_array($normalizedHeader, $aliases, true)) {
                    $columnMap[$colLetter] = $standardKey;
                    break;
                }
            }
        }

        $parsedRows = [];
        $validCount = 0;
        $invalidCount = 0;

        // Process data rows
        foreach ($rawRows as $idx => $row) {
            if ($idx <= $headerIndex) {
                continue;
            }

            // Skip entirely blank rows
            $hasContent = false;
            foreach ($row as $val) {
                if (trim((string) $val) !== '') {
                    $hasContent = true;
                    break;
                }
            }
            if (!$hasContent) {
                continue;
            }

            $mappedData = [
                'item_name'              => '',
                'category'               => null,
                'barcode'                => null,
                'description'            => null,
                'cost_price'             => 0.0,
                'base_unit_name'         => 'Unit',
                'base_unit_price'        => 0.0,
                'wholesale_price'        => null,
                'pack_unit_name'         => 'Pack',
                'pack_quantity'          => 1,
                'pack_price'             => null,
                'pack_wholesale_price'   => null,
                'carton_unit_name'       => 'Carton',
                'carton_quantity'        => 1,
                'carton_price'           => null,
                'carton_wholesale_price' => null,
                'track_by_imei'          => false,
                'initial_stock_quantity' => 0,
                'expiry_date'            => null,
            ];

            foreach ($row as $colLetter => $cellValue) {
                if (!isset($columnMap[$colLetter])) {
                    continue;
                }
                $key = $columnMap[$colLetter];
                $val = trim((string) $cellValue);

                switch ($key) {
                    case 'item_name':
                    case 'category':
                    case 'group_address':
                    case 'description':
                    case 'base_unit_name':
                    case 'pack_unit_name':
                    case 'carton_unit_name':
                        $mappedData[$key] = $val !== '' ? $val : null;
                        break;

                    case 'barcode':
                        $valStr = trim((string) $val);
                        // Strip formula quotes like ="123456" or '123456
                        if (str_starts_with($valStr, '="') && str_ends_with($valStr, '"')) {
                            $valStr = substr($valStr, 2, -1);
                        }
                        $valStr = trim($valStr, " '\"\t\n\r\0\x0B");

                        // Fix scientific notation e.g. 6.154E+12, 7.14085E+11, 6.154e+12
                        if (preg_match('/^[+\-]?[0-9]+(\.[0-9]+)?[eE][+\-]?[0-9]+$/', $valStr)) {
                            $valStr = number_format((float) $valStr, 0, '', '');
                        } elseif (is_numeric($valStr) && str_contains($valStr, '.')) {
                            // If it's a numeric float with zero decimal part e.g. 6154000123456.0
                            $parts = explode('.', $valStr);
                            if (isset($parts[1]) && ltrim($parts[1], '0') === '') {
                                $valStr = $parts[0];
                            }
                        }

                        $valUpper = strtoupper($valStr);
                        if ($valStr === '' || $valUpper === 'NULL' || $valUpper === 'N/A' || $valUpper === 'NONE' || $valUpper === 'NO_BARCODE' || $valUpper === 'NO-BARCODE') {
                            $mappedData[$key] = null;
                        } else {
                            $mappedData[$key] = $valStr;
                        }
                        break;

                    case 'cost_price':
                    case 'base_unit_price':
                    case 'wholesale_price':
                    case 'pack_price':
                    case 'pack_wholesale_price':
                    case 'carton_price':
                    case 'carton_wholesale_price':
                        $cleanedNum = preg_replace('/[^\d.]/', '', $val);
                        $mappedData[$key] = is_numeric($cleanedNum) ? (float) $cleanedNum : null;
                        break;

                    case 'pack_quantity':
                    case 'carton_quantity':
                    case 'initial_stock_quantity':
                        $cleanedInt = preg_replace('/[^\d]/', '', $val);
                        $mappedData[$key] = is_numeric($cleanedInt) ? max(0, (int) $cleanedInt) : 0;
                        break;

                    case 'track_by_imei':
                        $lower = strtolower($val);
                        $mappedData[$key] = in_array($lower, ['yes', 'y', 'true', '1', 'track'], true);
                        break;

                    case 'expiry_date':
                        $mappedData[$key] = self::parseDate($val);
                        break;
                }
            }

            // Defaults
            if (empty($mappedData['base_unit_name'])) {
                $mappedData['base_unit_name'] = 'Unit';
            }
            if (empty($mappedData['pack_unit_name'])) {
                $mappedData['pack_unit_name'] = 'Pack';
            }
            if (empty($mappedData['carton_unit_name'])) {
                $mappedData['carton_unit_name'] = 'Carton';
            }
            if (($mappedData['pack_quantity'] ?? 0) <= 0) {
                $mappedData['pack_quantity'] = 1;
            }
            if (($mappedData['carton_quantity'] ?? 0) <= 0) {
                $mappedData['carton_quantity'] = 1;
            }

            // Validation
            $errors = [];
            $warnings = [];

            if (empty($mappedData['item_name'])) {
                $errors[] = 'Item Name is required.';
            }

            if ($mappedData['base_unit_price'] === null || $mappedData['base_unit_price'] < 0) {
                $errors[] = 'Base Unit Price must be a valid positive number.';
            }

            $isValid = empty($errors);
            if ($isValid) {
                $validCount++;
            } else {
                $invalidCount++;
            }

            $parsedRows[] = [
                'row_number' => $idx,
                'is_valid'   => $isValid,
                'errors'     => $errors,
                'warnings'   => $warnings,
                'data'       => $mappedData,
            ];
        }

        return [
            'total_rows'   => count($parsedRows),
            'valid_rows'   => $validCount,
            'invalid_rows' => $invalidCount,
            'rows'         => $parsedRows,
        ];
    }

    /**
     * Preview import results against a specific branch before committing.
     */
    public function previewImport(Branch $branch, array $parsedRows): array
    {
        $barcodesInFile = [];
        $namesInFile    = [];

        foreach ($parsedRows as $row) {
            if (!$row['is_valid']) {
                continue;
            }
            if (!empty($row['data']['barcode'])) {
                $barcodesInFile[] = $row['data']['barcode'];
            }
            if (!empty($row['data']['item_name'])) {
                $namesInFile[] = $row['data']['item_name'];
            }
        }

        // Find existing barcodes in branch
        $existingBarcodes = [];
        if (!empty($barcodesInFile)) {
            $existingBarcodes = Item::where('branch_id', $branch->id)
                ->whereIn('barcode_number', array_unique($barcodesInFile))
                ->pluck('barcode_number')
                ->all();
        }

        // Find existing item names in branch
        $existingNames = [];
        if (!empty($namesInFile)) {
            $existingNames = Item::where('branch_id', $branch->id)
                ->whereIn('item_name', array_unique($namesInFile))
                ->pluck('item_name')
                ->all();
        }

        $annotatedRows = [];
        $barcodeConflictCount = 0;
        $nameConflictCount = 0;

        foreach ($parsedRows as $row) {
            $item = $row;
            if ($row['is_valid']) {
                $barcode = $row['data']['barcode'];
                $name    = $row['data']['item_name'];

                if ($barcode && in_array($barcode, $existingBarcodes, true)) {
                    $item['warnings'][] = "Barcode '{$barcode}' already in use in this branch.";
                    $barcodeConflictCount++;
                }
                if ($name && in_array($name, $existingNames, true)) {
                    $item['warnings'][] = "An item named '{$name}' already exists in this branch.";
                    $nameConflictCount++;
                }
            }
            $annotatedRows[] = $item;
        }

        return [
            'total_rows'               => count($annotatedRows),
            'valid_rows'               => count(array_filter($annotatedRows, fn($r) => $r['is_valid'])),
            'invalid_rows'             => count(array_filter($annotatedRows, fn($r) => !$r['is_valid'])),
            'barcode_conflicts_count'  => $barcodeConflictCount,
            'existing_names_count'     => $nameConflictCount,
            'sample_rows'              => array_slice($annotatedRows, 0, 30),
            'rows'                     => $annotatedRows,
        ];
    }

    /**
     * Commit and execute item import into a branch.
     */
    public function executeImport(
        Branch $branch,
        array $rows,
        array $options = [],
        ?ImportLibraryFile $sourceFile = null,
        ?int $userId = null
    ): array {
        $onDuplicateBarcode = $options['on_duplicate_barcode'] ?? 'skip'; // 'skip', 'generate_new', or 'overwrite'
        $defaultStockLocation = $options['default_stock_location'] ?? 'front_store'; // 'front_store' or 'back_store'

        $importedCount = 0;
        $skippedCount  = 0;
        $categoriesCreated = 0;
        $groupsCreated = 0;
        $errors = [];

        // Category cache for fast lookup (Scoped strictly to this target branch)
        $categoryCache = [];
        $existingCategories = Category::where('branch_id', $branch->id)->get();
        foreach ($existingCategories as $cat) {
            $categoryCache[strtolower(trim($cat->name))] = $cat->id;
        }

        // Group Address / Storage Location cache for fast lookup (Scoped strictly to this target branch)
        $groupCache = [];
        $existingGroups = GroupAddress::where('branch_id', $branch->id)->get();
        foreach ($existingGroups as $grp) {
            $groupCache[strtolower(trim($grp->name))] = $grp->id;
        }

        DB::beginTransaction();
        try {
            foreach ($rows as $rowWrapper) {
                $row = isset($rowWrapper['data']) ? $rowWrapper['data'] : $rowWrapper;

                if (empty($row['item_name']) || !isset($row['base_unit_price']) || $row['base_unit_price'] < 0) {
                    $skippedCount++;
                    $errors[] = "Row with empty item name or invalid price skipped.";
                    continue;
                }

                // Category Resolution (Auto-create exclusively for this branch if missing)
                $categoryId = null;
                if (!empty($row['category'])) {
                    $catName = trim($row['category']);
                    $catKey = strtolower($catName);

                    if (isset($categoryCache[$catKey])) {
                        $categoryId = $categoryCache[$catKey];
                    } else {
                        $newCat = Category::create([
                            'branch_id' => $branch->id,
                            'name'      => $catName,
                            'slug'      => Str::slug($catName) . '-' . $branch->id . '-' . rand(100, 999),
                        ]);
                        $categoryCache[$catKey] = $newCat->id;
                        $categoryId = $newCat->id;
                        $categoriesCreated++;
                    }
                }

                // Group Address / Storage Location Resolution (Auto-create exclusively for this branch if missing)
                $groupId = null;
                if (!empty($row['group_address'])) {
                    $grpName = trim($row['group_address']);
                    $grpKey = strtolower($grpName);

                    if (isset($groupCache[$grpKey])) {
                        $groupId = $groupCache[$grpKey];
                    } else {
                        $newGrp = GroupAddress::create([
                            'branch_id' => $branch->id,
                            'name'      => $grpName,
                        ]);
                        $groupCache[$grpKey] = $newGrp->id;
                        $groupId = $newGrp->id;
                        $groupsCreated++;
                    }
                }

                // Barcode handling: strictly leave as null if empty or null, never auto-generate
                $barcode = !empty($row['barcode']) ? trim((string) $row['barcode']) : null;
                if ($barcode !== null) {
                    $valUpper = strtoupper($barcode);
                    if ($valUpper === '' || $valUpper === 'NULL' || $valUpper === 'N/A' || $valUpper === 'NONE' || $valUpper === 'NO_BARCODE' || $valUpper === 'NO-BARCODE') {
                        $barcode = null;
                    }
                }

                if ($barcode !== null) {
                    // Strict branch isolation check: only check duplicate barcodes within this branch
                    $barcodeExists = Item::where('branch_id', $branch->id)
                        ->where('barcode_number', $barcode)
                        ->exists();

                    if ($barcodeExists) {
                        if ($onDuplicateBarcode === 'skip') {
                            $skippedCount++;
                            $errors[] = "Skipped '{$row['item_name']}': Barcode '{$barcode}' already exists in this branch.";
                            continue;
                        } elseif ($onDuplicateBarcode === 'leave_empty' || $onDuplicateBarcode === 'generate_new') {
                            // Keep the item, but leave barcode empty / null
                            $barcode = null;
                        }
                    }
                }

                $frontStoreQty = $defaultStockLocation === 'front_store' ? (int) ($row['initial_stock_quantity'] ?? 0) : 0;
                $backStoreQty  = $defaultStockLocation === 'back_store'  ? (int) ($row['initial_stock_quantity'] ?? 0) : 0;

                // Create the Item (Explicitly assigned to target branch)
                $newItem = Item::create([
                    'branch_id'              => $branch->id,
                    'category_id'            => $categoryId,
                    'group_address_id'       => $groupId,
                    'item_name'              => $row['item_name'],
                    'barcode_number'         => $barcode,
                    'buy_price'              => (float) ($row['cost_price'] ?? 0),
                    'price'                  => (float) $row['base_unit_price'],
                    'wholesale_price'        => !empty($row['wholesale_price']) ? (float) $row['wholesale_price'] : (float) $row['base_unit_price'],
                    'pack_price'             => !empty($row['pack_price']) ? (float) $row['pack_price'] : null,
                    'carton_price'           => !empty($row['carton_price']) ? (float) $row['carton_price'] : null,
                    'pack_wholesale_price'   => !empty($row['pack_wholesale_price']) ? (float) $row['pack_wholesale_price'] : null,
                    'carton_wholesale_price' => !empty($row['carton_wholesale_price']) ? (float) $row['carton_wholesale_price'] : null,
                    'unit_label'             => !empty($row['base_unit_name']) ? $row['base_unit_name'] : 'Unit',
                    'pack_label'             => !empty($row['pack_unit_name']) ? $row['pack_unit_name'] : 'Pack',
                    'carton_label'           => !empty($row['carton_unit_name']) ? $row['carton_unit_name'] : 'Carton',
                    'units_per_pack'         => max(1, (int) ($row['pack_quantity'] ?? 1)),
                    'packs_per_carton'       => max(1, (int) ($row['carton_quantity'] ?? 1)),
                    'is_imei_tracked'        => !empty($row['track_by_imei']),
                    'front_store_qty'        => $frontStoreQty,
                    'back_store_qty'         => $backStoreQty,
                    'expiry_date'            => $row['expiry_date'] ?? null,
                    'item_description'       => $row['description'] ?? null,
                ]);

                // Also populate ItemUnit records for flexible variant system
                $packQty = max(1, (int) ($row['pack_quantity'] ?? 1));
                if ((float) ($row['pack_price'] ?? 0) > 0 || $packQty > 1) {
                    \App\Models\ItemUnit::create([
                        'item_id'                => $newItem->id,
                        'branch_id'              => $branch->id,
                        'unit_name'              => !empty($row['pack_unit_name']) ? $row['pack_unit_name'] : 'Pack',
                        'quantity_in_base_units' => $packQty,
                        'selling_price'          => (float) ($row['pack_price'] ?? 0),
                        'wholesale_price'        => (float) ($row['pack_wholesale_price'] ?? 0),
                        'sort_order'             => 1,
                        'status'                 => 'active',
                    ]);
                }

                $cartonQty = max(1, (int) ($row['carton_quantity'] ?? 1));
                if ((float) ($row['carton_price'] ?? 0) > 0 || $cartonQty > 1) {
                    $cartonBaseQty = $packQty * $cartonQty;
                    \App\Models\ItemUnit::create([
                        'item_id'                => $newItem->id,
                        'branch_id'              => $branch->id,
                        'unit_name'              => !empty($row['carton_unit_name']) ? $row['carton_unit_name'] : 'Carton',
                        'quantity_in_base_units' => $cartonBaseQty,
                        'selling_price'          => (float) ($row['carton_price'] ?? 0),
                        'wholesale_price'        => (float) ($row['carton_wholesale_price'] ?? 0),
                        'sort_order'             => 2,
                        'status'                 => 'active',
                    ]);
                }

                $importedCount++;
            }

            // Update source file statistics if this was from the library
            if ($sourceFile) {
                $sourceFile->increment('import_count');
                $sourceFile->update(['last_imported_at' => now()]);
            }

            // Audit log
            $sourceName = $sourceFile ? "Library File '{$sourceFile->name}'" : "Uploaded Spreadsheet";
            ActivityLogger::log(
                actionType: 'import',
                message: "Super Admin imported {$importedCount} items into branch '{$branch->name}' via {$sourceName} ({$skippedCount} skipped).",
                branchId: $branch->id,
                details: [
                    'source'             => $sourceName,
                    'imported_count'     => $importedCount,
                    'skipped_count'      => $skippedCount,
                    'categories_created' => $categoriesCreated,
                    'groups_created'     => $groupsCreated,
                ],
                userId: $userId ?? auth()->id()
            );

            DB::commit();

            return [
                'success'            => true,
                'imported_count'     => $importedCount,
                'skipped_count'      => $skippedCount,
                'categories_created' => $categoriesCreated,
                'groups_created'     => $groupsCreated,
                'errors'             => $errors,
            ];
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate and stream a blank or sample template file (XLSX or CSV).
     */
    public function generateTemplate(string $format = 'xlsx', bool $withSampleData = false): StreamedResponse
    {
        $format = strtolower($format) === 'csv' ? 'csv' : 'xlsx';
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Items Import Template');

        $headers = [
            'item_name',
            'category',
            'barcode',
            'description',
            'cost_price',
            'base_unit_name',
            'base_unit_price',
            'wholesale_price',
            'pack_unit_name',
            'pack_quantity',
            'pack_price',
            'pack_wholesale_price',
            'carton_unit_name',
            'carton_quantity',
            'carton_price',
            'carton_wholesale_price',
            'track_by_imei',
            'initial_stock_quantity',
            'expiry_date',
        ];

        // Write headers
        foreach ($headers as $colIdx => $header) {
            $cellCoord = Coordinate::stringFromColumnIndex($colIdx + 1) . '1';
            $sheet->setCellValue($cellCoord, $header);
        }

        // Header Styling
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = "A1:{$lastCol}1";

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 11,
                'name'  => 'Segoe UI',
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'], // Indigo-600
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '3730A3'],
                ],
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Format barcode column (C) as text so spreadsheets never show scientific notation (e.g. 6.154E+12)
        $sheet->getStyle('C')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        // Add sample rows if requested
        if ($withSampleData) {
            $sampleRows = [
                [
                    'Paracetamol 500mg (10x10)',
                    'Analgesics & Pain Relief',
                    '8901234567890',
                    'Fast acting pain reliever and antipyretic',
                    150.00,
                    'Sachet',
                    200.00,
                    180.00,
                    'Pack',
                    10,
                    1800.00,
                    1650.00,
                    'Carton',
                    20,
                    34000.00,
                    31000.00,
                    'No',
                    100,
                    date('Y-12-31', strtotime('+2 years')),
                ],
                [
                    'Coca Cola PET 350ml',
                    'Soft Drinks & Beverages',
                    '5449000000996',
                    'Refreshing carbonated soft drink',
                    220.00,
                    'Bottle',
                    300.00,
                    280.00,
                    'Pack',
                    12,
                    3300.00,
                    3100.00,
                    'Carton',
                    24,
                    6400.00,
                    6000.00,
                    'No',
                    50,
                    date('Y-06-30', strtotime('+1 year')),
                ],
                [
                    'iPhone 15 Pro 128GB Blue Titanium',
                    'Smartphones & Gadgets',
                    '1959490001234',
                    'A17 Pro chip, Super Retina XDR display',
                    1100000.00,
                    'Piece',
                    1250000.00,
                    1200000.00,
                    'Pack',
                    1,
                    null,
                    null,
                    'Carton',
                    1,
                    null,
                    null,
                    'Yes',
                    5,
                    null,
                ],
                [
                    'Basmati Royal Rice 5kg',
                    'Grains & Cereals',
                    '6001234987654',
                    'Long grain aromatic white rice',
                    6500.00,
                    'Bag',
                    8200.00,
                    7800.00,
                    'Bundle',
                    4,
                    31500.00,
                    30000.00,
                    'Carton',
                    1,
                    null,
                    null,
                    'No',
                    25,
                    date('Y-12-31', strtotime('+3 years')),
                ],
            ];

            foreach ($sampleRows as $rIdx => $rowValues) {
                $rowNum = $rIdx + 2;
                foreach ($rowValues as $cIdx => $val) {
                    $colNumber = $cIdx + 1;
                    $cellCoord = Coordinate::stringFromColumnIndex($colNumber) . $rowNum;
                    if ($colNumber === 3) {
                        if ($val !== null && $val !== '') {
                            $sheet->setCellValueExplicit($cellCoord, (string) $val, DataType::TYPE_STRING);
                            $sheet->getStyle($cellCoord)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                        } else {
                            $sheet->setCellValue($cellCoord, '');
                        }
                    } else {
                        $sheet->setCellValue($cellCoord, $val);
                    }
                }
                $sheet->getRowDimension($rowNum)->setRowHeight(22);
            }
        }

        // Auto-fit column widths
        foreach (range(1, count($headers)) as $colIndex) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $filename = ($withSampleData ? 'pos_items_sample_template' : 'pos_items_blank_template') . '.' . $format;

        return new StreamedResponse(function () use ($spreadsheet, $format) {
            if ($format === 'csv') {
                $writer = new CsvWriter($spreadsheet);
                $writer->setUseBOM(true);
            } else {
                $writer = new XlsxWriter($spreadsheet);
            }
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => $format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    /**
     * Export all items of a branch in CSV or Excel (XLSX) format.
     *
     * @param Branch $branch Target branch
     * @param string $format 'xlsx' or 'csv'
     * @param string $quantityOption 'total', 'front_store', 'back_store', 'none'
     */
    public function exportBranchItems(
        Branch $branch,
        string $format = 'xlsx',
        string $quantityOption = 'total'
    ): StreamedResponse {
        $format = in_array(strtolower($format), ['csv', 'xlsx']) ? strtolower($format) : 'xlsx';
        $quantityOption = in_array($quantityOption, ['total', 'front_store', 'back_store', 'none']) ? $quantityOption : 'total';

        $items = Item::where('branch_id', $branch->id)
            ->with('category:id,name')
            ->orderBy('item_name')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr(preg_replace('/[^A-Za-z0-9_ ]/', '', $branch->name), 0, 30) ?: 'Branch Items');

        $headers = [
            'item_name',
            'category',
            'barcode',
            'description',
            'cost_price',
            'base_unit_name',
            'base_unit_price',
            'wholesale_price',
            'pack_unit_name',
            'pack_quantity',
            'pack_price',
            'pack_wholesale_price',
            'carton_unit_name',
            'carton_quantity',
            'carton_price',
            'carton_wholesale_price',
            'track_by_imei',
            'initial_stock_quantity',
            'expiry_date',
        ];

        // Format header row
        foreach ($headers as $colIdx => $headerName) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue($colLetter . '1', $headerName);
        }

        if ($format === 'xlsx') {
            $headerRange = 'A1:' . Coordinate::stringFromColumnIndex(count($headers)) . '1';
            $sheet->getStyle($headerRange)->applyFromArray([
                'font' => [
                    'bold'  => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size'  => 11,
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5'], // Indigo-600
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
            ]);
            $sheet->getRowDimension(1)->setRowHeight(28);
        }

        // Format barcode column (C) as text so spreadsheets never show scientific notation (e.g. 6.154E+12)
        $sheet->getStyle('C')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        $rowNumber = 2;
        foreach ($items as $item) {
            // Determine stock quantity based on user selection
            $qty = match ($quantityOption) {
                'front_store' => (int) ($item->front_store_qty ?? 0),
                'back_store'  => (int) ($item->back_store_qty ?? 0),
                'none'        => 0,
                default       => (int) (($item->front_store_qty ?? 0) + ($item->back_store_qty ?? 0)),
            };

            $rowData = [
                $item->item_name,
                $item->category?->name ?? '',
                $item->barcode_number ?? '',
                $item->item_description ?? '',
                (float) ($item->buy_price ?? 0),
                $item->unit_label ?: 'Unit',
                (float) ($item->price ?? 0),
                (float) ($item->wholesale_price ?? $item->price ?? 0),
                $item->pack_label ?: 'Pack',
                (int) ($item->units_per_pack ?? 1),
                $item->pack_price !== null ? (float) $item->pack_price : null,
                $item->pack_wholesale_price !== null ? (float) $item->pack_wholesale_price : null,
                $item->carton_label ?: 'Carton',
                (int) ($item->packs_per_carton ?? 1),
                $item->carton_price !== null ? (float) $item->carton_price : null,
                $item->carton_wholesale_price !== null ? (float) $item->carton_wholesale_price : null,
                $item->is_imei_tracked ? 'Yes' : 'No',
                $qty,
                $item->expiry_date ? $item->expiry_date->format('Y-m-d') : '',
            ];

            foreach ($rowData as $cIdx => $val) {
                $colNumber = $cIdx + 1;
                $cellCoord = Coordinate::stringFromColumnIndex($colNumber) . $rowNumber;
                if ($colNumber === 3) {
                    if ($val !== null && $val !== '') {
                        $sheet->setCellValueExplicit($cellCoord, (string) $val, DataType::TYPE_STRING);
                        $sheet->getStyle($cellCoord)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                    } else {
                        $sheet->setCellValue($cellCoord, '');
                    }
                } else {
                    $sheet->setCellValue($cellCoord, $val);
                }
            }

            if ($format === 'xlsx') {
                $sheet->getRowDimension($rowNumber)->setRowHeight(20);
            }
            $rowNumber++;
        }

        // Auto-fit column widths
        foreach (range(1, count($headers)) as $colIndex) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $branchSlug = Str::slug($branch->slug ?: $branch->name);
        $qtySuffix = match ($quantityOption) {
            'front_store' => 'front-stock',
            'back_store'  => 'back-stock',
            'none'        => 'catalog-only',
            default       => 'total-stock',
        };
        $filename = "{$branchSlug}-items-{$qtySuffix}-" . date('Y-m-d') . ".{$format}";

        return new StreamedResponse(function () use ($spreadsheet, $format) {
            if ($format === 'csv') {
                $writer = new CsvWriter($spreadsheet);
                $writer->setUseBOM(true);
            } else {
                $writer = new XlsxWriter($spreadsheet);
            }
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => $format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    /**
     * Parse date string into Y-m-d format.
     */
    protected static function parseDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            $time = strtotime($value);
            return $time ? date('Y-m-d', $time) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
