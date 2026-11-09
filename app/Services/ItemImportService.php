<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Category;
use App\Models\GroupAddress;
use App\Models\ImportLibraryFile;
use App\Models\Item;
use App\Models\ItemUnit;
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
        'item_ref'               => ['item_ref', 'item ref', 'ref', 'item_code', 'group_ref', 'reference', 'item_id'],
        'item_name'              => ['item_name', 'item name', 'name', 'product_name', 'product name', 'item', 'title', 'product'],
        'category'               => ['category', 'category_name', 'category name', 'category_hint', 'dept', 'department'],
        'brand'                  => ['brand', 'brand_name', 'brand name', 'brand_hint', 'manufacturer', 'make'],
        'group_address'          => ['group_address_name', 'group_address', 'group address', 'group address shelf', 'group address / shelf', 'shelf group address', 'group shelf', 'shelf location', 'shelf_location', 'group', 'storage_location', 'storage location', 'shelf', 'rack', 'location'],
        'barcode'                => ['barcode', 'barcode_number', 'barcode number', 'barcode sku', 'barcode / sku', 'sku barcode', 'sku / barcode', 'barcode or sku', 'sku', 'upc', 'ean', 'code'],
        'description'            => ['description', 'item_description', 'details', 'item description', 'notes'],
        'cost_price'             => ['cost_price', 'cost price', 'cost', 'buy_price', 'buy price', 'purchase_price', 'buying_price', 'cost price ₦', 'cost price n', 'cost price ngn', 'cost price $', 'cost price usd'],
        'unit_name'              => ['unit_name', 'unit name', 'unit_label', 'unit label', 'unit', 'base_unit_name', 'base_unit', 'variant_name', 'variant name', 'packaging_name', 'pack_unit_name', 'carton_unit_name'],
        'quantity_in_base_units' => ['quantity_in_base_units', 'quantity in base units', 'base_quantity', 'qty_in_base', 'multiplier', 'units_per_pack', 'pack_quantity', 'carton_quantity', 'conversion_qty', 'unit_quantity'],
        'selling_price'          => ['selling_price', 'selling price', 'price', 'retail_price', 'retail price', 'base_unit_price', 'unit_price', 'pack_price', 'carton_price', 'selling price ₦', 'selling price n', 'selling price ngn', 'selling price $', 'selling price usd', 'price ₦', 'unit price ₦'],
        'wholesale_price'        => ['wholesale_price', 'wholesale price', 'ws_price', 'wholesale', 'base_wholesale_price', 'unit_wholesale_price', 'pack_wholesale_price', 'carton_wholesale_price', 'wholesale price ₦', 'wholesale price n', 'wholesale price ngn', 'wholesale price $', 'wholesale price usd'],
        'is_base_unit'           => ['is_base_unit', 'is base unit', 'base_unit', 'is_base', 'base', 'is base', 'is_based_unit'],
        'track_by_imei'          => ['track_by_imei', 'track by imei', 'is_imei_tracked', 'imei_tracked', 'imei tracked', 'imei tracking', 'imei', 'track_imei'],
        'initial_stock_quantity' => ['initial_stock_quantity', 'initial stock quantity', 'initial_stock', 'initial stock', 'stock', 'stock_qty', 'qty', 'quantity', 'front_store_qty', 'front_qty', 'front store qty', 'front store quantity', 'front store stock', 'front store', 'total_stock_qty', 'total stock qty', 'total_stock', 'total stock', 'total_stock_quantity', 'total stock quantity'],
        'back_store_quantity'    => ['back_store_qty', 'back_store_quantity', 'back store quantity', 'back store qty', 'back store stock', 'back store', 'back_qty', 'warehouse_qty', 'warehouse quantity'],
        'expiry_date'            => ['expiry_date', 'expiry date', 'expiration_date', 'expiration date', 'expiry', 'exp_date'],
    ];

    /**
     * Parse an uploaded file or stored path into structured row items with multi-row item_ref grouping.
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
                'groups'       => [],
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
                'groups'       => [],
            ];
        }

        // Map column letter -> normalized key
        $columnMap = [];
        $hasItemRefCol = false;
        $hasIsBaseUnitCol = false;

        foreach ($headerRow as $colLetter => $headerText) {
            $rawText = (string) $headerText;
            if (trim($rawText) === '') {
                continue;
            }

            // Strip parenthetical content such as currency notes: (₦), (NGN), ($), (USD), (units), etc.
            $clean = preg_replace('/\([^)]*\)/u', '', $rawText);
            // Replace slashes, backslashes, and pipes with spaces: 'Barcode / SKU' -> 'Barcode   SKU'
            $clean = str_replace(['/', '\\', '|'], ' ', $clean);
            // Strip standalone currency symbols
            $clean = preg_replace('/[₦$€£]/u', '', $clean);
            // Normalize spaces/hyphens/underscores to single space
            $normalizedHeader = strtolower(trim(preg_replace('/[\s\-_]+/u', ' ', $clean)));
            $rawNormalized = strtolower(trim(preg_replace('/[\s\-_]+/u', ' ', $rawText)));

            foreach (self::$headerAliases as $standardKey => $aliases) {
                if (in_array($normalizedHeader, $aliases, true) || in_array($rawNormalized, $aliases, true)) {
                    // Check if standardKey was already mapped to a column
                    if (in_array($standardKey, $columnMap, true)) {
                        // Special handling for initial_stock_quantity:
                        // 'front store qty' is more specific than 'total stock qty'.
                        // If previously mapped column wasn't front store, but current one is, reassign to current.
                        if ($standardKey === 'initial_stock_quantity' && str_contains($normalizedHeader, 'front')) {
                            $prevCol = array_search('initial_stock_quantity', $columnMap, true);
                            if ($prevCol !== false) {
                                unset($columnMap[$prevCol]);
                            }
                            $columnMap[$colLetter] = $standardKey;
                        }
                        continue;
                    }

                    $columnMap[$colLetter] = $standardKey;
                    if ($standardKey === 'item_ref') {
                        $hasItemRefCol = true;
                    }
                    if ($standardKey === 'is_base_unit') {
                        $hasIsBaseUnitCol = true;
                    }
                    break;
                }
            }
        }

        $parsedRows = [];
        $seenRefs = [];
        $itemGroups = [];

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
                'item_ref'               => null,
                'item_name'              => '',
                'category'               => null,
                'brand'                  => null,
                'group_address'          => null,
                'barcode'                => null,
                'description'            => null,
                'cost_price'             => null,
                'unit_name'              => 'Unit',
                'quantity_in_base_units' => 1,
                'selling_price'          => null,
                'wholesale_price'        => null,
                'is_base_unit'           => null,
                'track_by_imei'          => false,
                'initial_stock_quantity' => 0,
                'back_store_quantity'    => 0,
                'expiry_date'            => null,
            ];

            foreach ($row as $colLetter => $cellValue) {
                if (!isset($columnMap[$colLetter])) {
                    continue;
                }
                $key = $columnMap[$colLetter];
                $val = trim((string) $cellValue);

                switch ($key) {
                    case 'item_ref':
                    case 'item_name':
                    case 'description':
                    case 'unit_name':
                        $mappedData[$key] = $val !== '' ? $val : null;
                        break;

                    case 'category':
                    case 'brand':
                    case 'group_address':
                        $valStr = trim((string) $val);
                        if ($valStr === '' || in_array(strtoupper($valStr), ['-', '--', '---', 'N/A', 'NONE', 'NULL'], true)) {
                            $mappedData[$key] = null;
                        } else {
                            $mappedData[$key] = $valStr;
                        }
                        break;

                    case 'barcode':
                        $valStr = trim((string) $val);
                        // Strip formula quotes like ="123456" or '123456
                        if (str_starts_with($valStr, '="') && str_ends_with($valStr, '"')) {
                            $valStr = substr($valStr, 2, -1);
                        }
                        $valStr = trim($valStr, " '\"\t\n\r\0\x0B");

                        // Fix scientific notation e.g. 6.154E+12
                        if (preg_match('/^[+\-]?[0-9]+(\.[0-9]+)?[eE][+\-]?[0-9]+$/', $valStr)) {
                            $valStr = number_format((float) $valStr, 0, '', '');
                        } elseif (is_numeric($valStr) && str_contains($valStr, '.')) {
                            $parts = explode('.', $valStr);
                            if (isset($parts[1]) && ltrim($parts[1], '0') === '') {
                                $valStr = $parts[0];
                            }
                        }

                        $valUpper = strtoupper($valStr);
                        if ($valStr === '' || in_array($valUpper, ['NULL', 'N/A', 'NONE', 'NO_BARCODE', 'NO-BARCODE', '-', '--', '---'], true)) {
                            $mappedData[$key] = null;
                        } else {
                            $mappedData[$key] = $valStr;
                        }
                        break;

                    case 'cost_price':
                    case 'selling_price':
                    case 'wholesale_price':
                        $cleanedNum = preg_replace('/[^\d.]/', '', $val);
                        $mappedData[$key] = is_numeric($cleanedNum) ? (float) $cleanedNum : null;
                        break;

                    case 'quantity_in_base_units':
                        $cleanedInt = preg_replace('/[^\d]/', '', $val);
                        $mappedData[$key] = is_numeric($cleanedInt) ? max(1, (int) $cleanedInt) : 1;
                        break;

                    case 'initial_stock_quantity':
                    case 'back_store_quantity':
                        $cleanedInt = preg_replace('/[^\d]/', '', $val);
                        $mappedData[$key] = is_numeric($cleanedInt) ? max(0, (int) $cleanedInt) : 0;
                        break;

                    case 'is_base_unit':
                        $lower = strtolower($val);
                        if (in_array($lower, ['yes', 'y', 'true', '1', 'base'], true)) {
                            $mappedData[$key] = true;
                        } elseif (in_array($lower, ['no', 'n', 'false', '0'], true)) {
                            $mappedData[$key] = false;
                        } else {
                            $mappedData[$key] = null;
                        }
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

            // Establish item_ref
            $rawRef = $mappedData['item_ref'];
            if (!empty($rawRef)) {
                $itemRefKey = trim((string) $rawRef);
            } else {
                // If no item_ref column or empty, fallback to item name or unique row ref
                $itemRefKey = !empty($mappedData['item_name']) ? 'AUTO_' . Str::slug($mappedData['item_name']) : 'ROW_' . $idx;
                $mappedData['item_ref'] = $itemRefKey;
            }

            // Infer is_base_unit if not specified
            if ($mappedData['is_base_unit'] === null) {
                if (!isset($seenRefs[$itemRefKey])) {
                    $mappedData['is_base_unit'] = true;
                } else {
                    $mappedData['is_base_unit'] = false;
                }
            }
            $seenRefs[$itemRefKey] = true;

            // Defaults
            if (empty($mappedData['unit_name'])) {
                $mappedData['unit_name'] = $mappedData['is_base_unit'] ? 'Unit' : 'Variant';
            }
            if ($mappedData['is_base_unit']) {
                $mappedData['quantity_in_base_units'] = 1;
            }

            $parsedRows[] = [
                'row_number' => $idx,
                'is_valid'   => true, // will be evaluated in group validation
                'errors'     => [],
                'warnings'   => [],
                'data'       => $mappedData,
            ];

            if (!isset($itemGroups[$itemRefKey])) {
                $itemGroups[$itemRefKey] = [
                    'item_ref'     => $itemRefKey,
                    'rows'         => [],
                    'base_row'     => null,
                    'variant_rows' => [],
                    'errors'       => [],
                    'warnings'     => [],
                    'is_valid'     => true,
                ];
            }
            $itemGroups[$itemRefKey]['rows'][] = count($parsedRows) - 1; // store index in $parsedRows
        }

        // Validate Groups
        $validCount = 0;
        $invalidCount = 0;

        foreach ($itemGroups as $refKey => &$group) {
            $baseRowIndices = [];
            $variantRowIndices = [];
            $inheritedName = null;
            $inheritedCategory = null;
            $inheritedBrand = null;
            $inheritedBarcode = null;

            foreach ($group['rows'] as $rIdx) {
                $rData = $parsedRows[$rIdx]['data'];
                if ($rData['is_base_unit']) {
                    $baseRowIndices[] = $rIdx;
                } else {
                    $variantRowIndices[] = $rIdx;
                }
                if (!empty($rData['item_name']) && !$inheritedName) {
                    $inheritedName = $rData['item_name'];
                }
                if (!empty($rData['category']) && !$inheritedCategory) {
                    $inheritedCategory = $rData['category'];
                }
                if (!empty($rData['brand']) && !$inheritedBrand) {
                    $inheritedBrand = $rData['brand'];
                }
                if (!empty($rData['barcode']) && !$inheritedBarcode) {
                    $inheritedBarcode = $rData['barcode'];
                }
            }

            $groupErrors = [];

            if (count($baseRowIndices) === 0) {
                $groupErrors[] = "Item group '{$refKey}' is missing a Base Unit row. Exactly one row must have is_base_unit = Yes.";
            } elseif (count($baseRowIndices) > 1) {
                $groupErrors[] = "Item group '{$refKey}' has multiple Base Unit rows. Exactly one row must have is_base_unit = Yes.";
            } else {
                $baseIdx = $baseRowIndices[0];
                $baseRow = &$parsedRows[$baseIdx];

                // Propagate inherited name/category/brand/barcode to base row if missing
                if (empty($baseRow['data']['item_name']) && $inheritedName) {
                    $baseRow['data']['item_name'] = $inheritedName;
                }
                if (empty($baseRow['data']['category']) && $inheritedCategory) {
                    $baseRow['data']['category'] = $inheritedCategory;
                }
                if (empty($baseRow['data']['brand']) && $inheritedBrand) {
                    $baseRow['data']['brand'] = $inheritedBrand;
                }
                if (empty($baseRow['data']['barcode']) && $inheritedBarcode) {
                    $baseRow['data']['barcode'] = $inheritedBarcode;
                }

                if (empty($baseRow['data']['item_name'])) {
                    $baseRow['errors'][] = "Item Name is required on base unit row.";
                    $groupErrors[] = "Item Name is required.";
                }

                // If selling price is empty or 0, fall back to wholesale price or cost price if available
                if ($baseRow['data']['selling_price'] === null || (float) $baseRow['data']['selling_price'] <= 0) {
                    if (!empty($baseRow['data']['wholesale_price']) && (float) $baseRow['data']['wholesale_price'] > 0) {
                        $baseRow['data']['selling_price'] = (float) $baseRow['data']['wholesale_price'];
                    } elseif (!empty($baseRow['data']['cost_price']) && (float) $baseRow['data']['cost_price'] > 0) {
                        $baseRow['data']['selling_price'] = (float) $baseRow['data']['cost_price'];
                    }
                }

                if ($baseRow['data']['selling_price'] === null || (float) $baseRow['data']['selling_price'] < 0) {
                    $baseRow['errors'][] = "Base Unit Selling Price must be a valid positive number.";
                    $groupErrors[] = "Base Unit Selling Price must be a valid positive number.";
                }

                $group['base_row'] = $baseRow['data'];

                // Validate variant rows
                foreach ($variantRowIndices as $vIdx) {
                    $vRow = &$parsedRows[$vIdx];
                    if (empty($vRow['data']['item_name']) && $inheritedName) {
                        $vRow['data']['item_name'] = $inheritedName;
                    }
                    if (empty($vRow['data']['unit_name'])) {
                        $vRow['errors'][] = "Variant Unit Name is required.";
                        $groupErrors[] = "Variant Unit Name is required.";
                    }
                    if (($vRow['data']['quantity_in_base_units'] ?? 0) <= 0) {
                        $vRow['errors'][] = "Variant quantity_in_base_units must be a whole number greater than 0.";
                        $groupErrors[] = "Variant conversion quantity must be > 0.";
                    }
                    if ($vRow['data']['selling_price'] === null || (float) $vRow['data']['selling_price'] <= 0) {
                        if (!empty($vRow['data']['wholesale_price']) && (float) $vRow['data']['wholesale_price'] > 0) {
                            $vRow['data']['selling_price'] = (float) $vRow['data']['wholesale_price'];
                        } elseif (!empty($baseRow['data']['selling_price']) && (float) $baseRow['data']['selling_price'] > 0) {
                            $vRow['data']['selling_price'] = (float) $baseRow['data']['selling_price'];
                        }
                    }
                    if ($vRow['data']['selling_price'] === null || (float) $vRow['data']['selling_price'] < 0) {
                        $vRow['errors'][] = "Variant Selling Price must be a valid positive number.";
                        $groupErrors[] = "Variant Selling Price must be a valid positive number.";
                    }
                    $group['variant_rows'][] = $vRow['data'];
                }
            }

            $isGroupValid = empty($groupErrors);
            $group['is_valid'] = $isGroupValid;
            $group['errors'] = $groupErrors;

            foreach ($group['rows'] as $rIdx) {
                if (!$isGroupValid) {
                    $parsedRows[$rIdx]['is_valid'] = false;
                    $parsedRows[$rIdx]['errors'] = array_unique(array_merge($parsedRows[$rIdx]['errors'], $groupErrors));
                    $invalidCount++;
                } else {
                    $parsedRows[$rIdx]['is_valid'] = empty($parsedRows[$rIdx]['errors']);
                    if ($parsedRows[$rIdx]['is_valid']) {
                        $validCount++;
                    } else {
                        $invalidCount++;
                    }
                }
            }
        }
        unset($group);

        return [
            'total_rows'   => count($parsedRows),
            'valid_rows'   => $validCount,
            'invalid_rows' => $invalidCount,
            'rows'         => $parsedRows,
            'groups'       => array_values($itemGroups),
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
        $onDuplicateBarcode = $options['on_duplicate_barcode'] ?? 'skip'; // 'skip', 'leave_empty', or 'generate_new'
        $defaultStockLocation = $options['default_stock_location'] ?? 'front_store'; // 'front_store' or 'back_store'

        $importedCount = 0;
        $skippedCount  = 0;
        $categoriesCreated = 0;
        $brandsCreated = 0;
        $groupsCreated = 0;
        $errors = [];

        // Category cache for fast lookup (Scoped strictly to this target branch)
        $categoryCache = [];
        $existingCategories = Category::where('branch_id', $branch->id)->get();
        foreach ($existingCategories as $cat) {
            $categoryCache[strtolower(trim($cat->name))] = $cat->id;
        }

        // Brand cache for fast lookup (Scoped to this target branch and global brands)
        $brandCache = [];
        $existingBrands = \App\Models\Brand::where(fn($q) => $q->where('branch_id', $branch->id)->orWhereNull('branch_id'))->get();
        foreach ($existingBrands as $b) {
            $brandCache[strtolower(trim($b->name))] = $b->id;
        }

        // Group Address / Storage Location cache for fast lookup (Scoped strictly to this target branch)
        $groupCache = [];
        $existingGroups = GroupAddress::where('branch_id', $branch->id)->get();
        foreach ($existingGroups as $grp) {
            $groupCache[strtolower(trim($grp->name))] = $grp->id;
        }

        // Group rows in-memory by item_ref
        $itemGroups = [];
        foreach ($rows as $rowWrapper) {
            $rowData = isset($rowWrapper['data']) ? $rowWrapper['data'] : $rowWrapper;
            $ref = !empty($rowData['item_ref']) ? trim((string)$rowData['item_ref']) : (!empty($rowData['item_name']) ? 'AUTO_' . Str::slug($rowData['item_name']) : 'ROW_' . rand(1000, 9999));
            if (!isset($itemGroups[$ref])) {
                $itemGroups[$ref] = [
                    'base_row'     => null,
                    'variant_rows' => [],
                ];
            }
            if (!empty($rowData['is_base_unit'])) {
                $itemGroups[$ref]['base_row'] = $rowData;
            } else {
                $itemGroups[$ref]['variant_rows'][] = $rowData;
            }
        }

        DB::beginTransaction();
        try {
            foreach ($itemGroups as $refKey => $group) {
                $baseRow = $group['base_row'];
                if (!$baseRow && !empty($group['variant_rows'])) {
                    // Fallback to first row as base
                    $baseRow = array_shift($group['variant_rows']);
                }

                if (!$baseRow || empty($baseRow['item_name']) || !isset($baseRow['selling_price']) || $baseRow['selling_price'] < 0) {
                    $skippedCount++;
                    $errors[] = "Item ref '{$refKey}' skipped: Missing base item name or invalid price.";
                    continue;
                }

                // Category Resolution (Auto-create exclusively for this branch if missing)
                $categoryId = null;
                if (!empty($baseRow['category'])) {
                    $catName = trim($baseRow['category']);
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

                // Brand Resolution (Auto-create exclusively for this branch if missing)
                $brandId = null;
                if (!empty($baseRow['brand'])) {
                    $brandName = trim($baseRow['brand']);
                    $brandKey = strtolower($brandName);

                    if (isset($brandCache[$brandKey])) {
                        $brandId = $brandCache[$brandKey];
                    } else {
                        $newBrand = \App\Models\Brand::create([
                            'branch_id' => $branch->id,
                            'name'      => $brandName,
                            'slug'      => Str::slug($brandName) . '-' . $branch->id . '-' . rand(100, 999),
                            'is_active' => true,
                        ]);
                        $brandCache[$brandKey] = $newBrand->id;
                        $brandId = $newBrand->id;
                        $brandsCreated++;
                    }
                }

                // Group Address / Storage Location Resolution
                $groupId = null;
                if (!empty($baseRow['group_address'])) {
                    $grpName = trim($baseRow['group_address']);
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
                $barcode = !empty($baseRow['barcode']) ? trim((string) $baseRow['barcode']) : null;
                if ($barcode !== null) {
                    $valUpper = strtoupper($barcode);
                    if ($valUpper === '' || in_array($valUpper, ['NULL', 'N/A', 'NONE', 'NO_BARCODE', 'NO-BARCODE', '-', '--', '---'], true)) {
                        $barcode = null;
                    }
                }

                if ($barcode !== null) {
                    $barcodeExists = Item::where('branch_id', $branch->id)
                        ->where('barcode_number', $barcode)
                        ->exists();

                    if ($barcodeExists) {
                        if ($onDuplicateBarcode === 'skip') {
                            $skippedCount++;
                            $errors[] = "Skipped '{$baseRow['item_name']}': Barcode '{$barcode}' already exists in this branch.";
                            continue;
                        } elseif ($onDuplicateBarcode === 'leave_empty' || $onDuplicateBarcode === 'generate_new') {
                            $barcode = null;
                        }
                    }
                }

                // Exact Item Name duplication check in branch
                $nameExists = Item::where('branch_id', $branch->id)
                    ->where('item_name', $baseRow['item_name'])
                    ->exists();
                if ($nameExists) {
                    $skippedCount++;
                    $errors[] = "Skipped '{$baseRow['item_name']}': Item name already exists in this branch.";
                    continue;
                }

                $frontStoreQty = $defaultStockLocation === 'front_store' ? (int) ($baseRow['initial_stock_quantity'] ?? 0) : 0;
                $backStoreQty  = $defaultStockLocation === 'back_store'  ? (int) ($baseRow['initial_stock_quantity'] ?? 0) : (int) ($baseRow['back_store_quantity'] ?? 0);

                // Create the Item in database (No item_ref or is_base_unit columns in DB!)
                $newItem = Item::create([
                    'branch_id'              => $branch->id,
                    'category_id'            => $categoryId,
                    'brand_id'               => $brandId,
                    'group_address_id'       => $groupId,
                    'item_name'              => $baseRow['item_name'],
                    'barcode_number'         => $barcode,
                    'buy_price'              => (float) ($baseRow['cost_price'] ?? 0),
                    'price'                  => (float) $baseRow['selling_price'],
                    'wholesale_price'        => !empty($baseRow['wholesale_price']) ? (float) $baseRow['wholesale_price'] : (float) $baseRow['selling_price'],
                    'unit_label'             => !empty($baseRow['unit_name']) ? $baseRow['unit_name'] : 'Unit',
                    'is_imei_tracked'        => !empty($baseRow['track_by_imei']),
                    'front_store_qty'        => $frontStoreQty,
                    'back_store_qty'         => $backStoreQty,
                    'expiry_date'            => $baseRow['expiry_date'] ?? null,
                    'item_description'       => $baseRow['description'] ?? null,
                    'price_locked'           => true,
                ]);

                // Create ItemUnit records for each variant row in this item group
                $variantIndex = 1;
                foreach ($group['variant_rows'] as $vRow) {
                    $unitName = !empty($vRow['unit_name']) ? trim($vRow['unit_name']) : 'Variant ' . $variantIndex;
                    $qtyInBase = max(1, (int) ($vRow['quantity_in_base_units'] ?? 1));
                    $vPrice = isset($vRow['selling_price']) ? (float) $vRow['selling_price'] : ($newItem->price * $qtyInBase);
                    $vWholesale = isset($vRow['wholesale_price']) && (float) $vRow['wholesale_price'] > 0
                        ? (float) $vRow['wholesale_price']
                        : ($newItem->wholesale_price * $qtyInBase);

                    ItemUnit::create([
                        'item_id'                => $newItem->id,
                        'branch_id'              => $branch->id,
                        'unit_name'              => $unitName,
                        'quantity_in_base_units' => $qtyInBase,
                        'selling_price'          => $vPrice,
                        'wholesale_price'        => $vWholesale,
                        'sort_order'             => $variantIndex,
                        'status'                 => 'active',
                    ]);
                    $variantIndex++;
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
            'item_ref',
            'item_name',
            'category',
            'brand',
            'group_address',
            'barcode',
            'description',
            'cost_price',
            'unit_name',
            'quantity_in_base_units',
            'selling_price',
            'wholesale_price',
            'is_base_unit',
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

        // Format barcode column (F) as text so spreadsheets never show scientific notation (e.g. 6.154E+12)
        $sheet->getStyle('F')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        // Add sample rows if requested
        if ($withSampleData) {
            $sampleRows = [
                // Group 1: Peak Milk Sachet with Roll & Carton variants
                [
                    'P001',
                    'Peak Milk Sachet',
                    'Dairy & Provisions',
                    'Peak',
                    'Shelf A1',
                    '8901234567890',
                    'Instant full cream milk powder sachet',
                    200.00,
                    'Unit',
                    1,
                    250.00,
                    220.00,
                    'Yes',
                    'No',
                    100,
                    date('Y-12-31', strtotime('+2 years')),
                ],
                [
                    'P001',
                    'Peak Milk Sachet',
                    'Dairy & Provisions',
                    'Peak',
                    'Shelf A1',
                    '8901234567890',
                    'Instant full cream milk powder sachet',
                    null,
                    'Roll (10-in-1)',
                    10,
                    2350.00,
                    2100.00,
                    'No',
                    'No',
                    null,
                    null,
                ],
                [
                    'P001',
                    'Peak Milk Sachet',
                    'Dairy & Provisions',
                    'Peak',
                    'Shelf A1',
                    '8901234567890',
                    'Instant full cream milk powder sachet',
                    null,
                    'Carton (100-in-1)',
                    100,
                    22500.00,
                    20000.00,
                    'No',
                    'No',
                    null,
                    null,
                ],
                // Group 2: Coca Cola PET with Pack variant
                [
                    'P002',
                    'Coca Cola PET 350ml',
                    'Soft Drinks & Beverages',
                    'Coca-Cola',
                    'Cooler 1',
                    '5449000000996',
                    'Refreshing carbonated soft drink',
                    220.00,
                    'Bottle',
                    1,
                    300.00,
                    280.00,
                    'Yes',
                    'No',
                    50,
                    date('Y-06-30', strtotime('+1 year')),
                ],
                [
                    'P002',
                    'Coca Cola PET 350ml',
                    'Soft Drinks & Beverages',
                    'Coca-Cola',
                    'Cooler 1',
                    '5449000000996',
                    'Refreshing carbonated soft drink',
                    null,
                    'Pack (12-in-1)',
                    12,
                    3300.00,
                    3100.00,
                    'No',
                    'No',
                    null,
                    null,
                ],
                // Group 3: Paracetamol 500mg (Base unit only, no variants)
                [
                    'P003',
                    'Paracetamol 500mg',
                    'Analgesics & Pain Relief',
                    'Emzor',
                    'Pharmacy Shelf',
                    '6001234987654',
                    'Fast acting pain reliever and antipyretic',
                    150.00,
                    'Tablet',
                    1,
                    200.00,
                    180.00,
                    'Yes',
                    'No',
                    200,
                    date('Y-12-31', strtotime('+3 years')),
                ],
                // Group 4: Bottled Water (Base unit only, blank barcode)
                [
                    'P004',
                    'Bottled Water 75cl',
                    'Beverages',
                    'Eva',
                    'Aisle 2',
                    null,
                    'Pure natural spring water',
                    80.00,
                    'Bottle',
                    1,
                    120.00,
                    100.00,
                    'Yes',
                    'No',
                    80,
                    date('Y-12-31', strtotime('+1 year')),
                ],
            ];

            foreach ($sampleRows as $rIdx => $rowValues) {
                $rowNum = $rIdx + 2;
                foreach ($rowValues as $cIdx => $val) {
                    $colNumber = $cIdx + 1;
                    $cellCoord = Coordinate::stringFromColumnIndex($colNumber) . $rowNum;
                    if ($colNumber === 6) { // Barcode column
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

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new CsvWriter($spreadsheet) : new XlsxWriter($spreadsheet);
            if ($format === 'csv') {
                $writer->setUseBOM(true);
            }
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => $format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    /**
     * Export existing branch items into an Excel / CSV spreadsheet with multi-row item_ref & variant structure.
     */
    public function exportBranchItems(
        Branch $branch,
        string $format = 'xlsx',
        string $quantityOption = 'combined', // 'combined' | 'front_store' | 'back_store' | 'none'
        ?array $categoryIds = null,
        ?string $search = null
    ): StreamedResponse {
        $format = strtolower($format) === 'csv' ? 'csv' : 'xlsx';

        $query = Item::where('branch_id', $branch->id)->with(['category', 'brand', 'groupAddress', 'units']);

        if (!empty($categoryIds)) {
            $query->whereIn('category_id', $categoryIds);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                  ->orWhere('barcode_number', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('item_name')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Branch Items');

        $headers = [
            'item_ref',
            'item_name',
            'category',
            'brand',
            'group_address',
            'barcode',
            'description',
            'cost_price',
            'unit_name',
            'quantity_in_base_units',
            'selling_price',
            'wholesale_price',
            'is_base_unit',
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

        // Format barcode column (F) as text so spreadsheets never show scientific notation (e.g. 6.154E+12)
        $sheet->getStyle('F')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        $rowNumber = 2;
        foreach ($items as $item) {
            // Determine stock quantity based on user selection
            $qty = match ($quantityOption) {
                'front_store' => (int) ($item->front_store_qty ?? 0),
                'back_store'  => (int) ($item->back_store_qty ?? 0),
                'none'        => 0,
                default       => (int) (($item->front_store_qty ?? 0) + ($item->back_store_qty ?? 0)),
            };

            $itemRef = 'REF-' . str_pad($item->id, 5, '0', STR_PAD_LEFT);

            // Base Unit Row
            $baseRow = [
                $itemRef,
                $item->item_name,
                $item->category?->name ?? '',
                $item->brand?->name ?? '',
                $item->groupAddress?->name ?? '',
                $item->barcode_number ?? '',
                $item->item_description ?? '',
                (float) ($item->buy_price ?? 0),
                $item->unit_label ?: 'Unit',
                1,
                (float) ($item->price ?? 0),
                (float) ($item->wholesale_price ?? $item->price ?? 0),
                'Yes',
                $item->is_imei_tracked ? 'Yes' : 'No',
                $qty,
                $item->expiry_date ? $item->expiry_date->format('Y-m-d') : '',
            ];

            foreach ($baseRow as $cIdx => $val) {
                $colNumber = $cIdx + 1;
                $cellCoord = Coordinate::stringFromColumnIndex($colNumber) . $rowNumber;
                if ($colNumber === 6) { // Barcode column
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

            // Variant Rows (ItemUnit)
            if ($item->units && $item->units->count() > 0) {
                foreach ($item->units as $unit) {
                    $variantRow = [
                        $itemRef,
                        $item->item_name,
                        $item->category?->name ?? '',
                        $item->brand?->name ?? '',
                        '',
                        $item->barcode_number ?? '',
                        '',
                        null,
                        $unit->unit_name,
                        (int) $unit->quantity_in_base_units,
                        (float) $unit->selling_price,
                        (float) ($unit->wholesale_price ?? $unit->selling_price),
                        'No',
                        '',
                        null,
                        '',
                    ];

                    foreach ($variantRow as $cIdx => $val) {
                        $colNumber = $cIdx + 1;
                        $cellCoord = Coordinate::stringFromColumnIndex($colNumber) . $rowNumber;
                        if ($colNumber === 6) {
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
            }
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

