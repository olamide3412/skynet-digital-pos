<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ReorderPointController extends Controller
{
    public function index(Request $request)
    {
        $branch = current_branch();

        $query = Item::where('branch_id', $branch->id)
            ->with(['category:id,name'])
            ->orderBy('item_name');

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Search by keyword
        if ($request->filled('search')) {
            $search = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', $search)
                  ->orWhere('barcode_number', 'like', $search);
            });
        }

        $allItems = $query->get();

        // Calculate summary counts
        $totalCount    = $allItems->count();
        $reorderItems  = $allItems->filter(fn($item) => $item->needs_reorder);
        $reorderCount  = $reorderItems->count();
        $criticalCount = $allItems->filter(fn($item) => ((int) $item->front_store_qty + (int) $item->back_store_qty) <= 0)->count();
        $adequateCount = $totalCount - $reorderCount;

        // Apply Tab Filter
        $tab = $request->get('tab', 'reorder'); // Default to showing reorder needed items
        $filteredCollection = match($tab) {
            'reorder'  => $allItems->filter(fn($item) => $item->needs_reorder),
            'critical' => $allItems->filter(fn($item) => ((int) $item->front_store_qty + (int) $item->back_store_qty) <= 0),
            'adequate' => $allItems->filter(fn($item) => !$item->needs_reorder),
            default    => $allItems,
        };

        // Paginate manually / slice collection
        $page     = (int) $request->get('page', 1);
        $perPage  = 25;
        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $filteredCollection->forPage($page, $perPage)->values(),
            $filteredCollection->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $categories = Category::where(fn($q) => $q->where('branch_id', $branch->id)->orWhereNull('branch_id'))
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventory/ReorderPoints', [
            'items'         => $paginated,
            'summary'       => [
                'total_count'    => $totalCount,
                'reorder_count'  => $reorderCount,
                'critical_count' => $criticalCount,
                'adequate_count' => $adequateCount,
            ],
            'categories'    => $categories,
            'filters'       => [
                'tab'         => $tab,
                'category_id' => $request->get('category_id', ''),
                'search'      => $request->get('search', ''),
            ],
        ]);
    }

    public function update(Request $request, $branchParam, Item $item)
    {
        $branch = current_branch();
        if ($item->branch_id !== $branch->id) {
            abort(403);
        }

        $data = $request->validate([
            'reorder_point' => 'required|integer|min:0',
            'reorder_unit'  => 'required|in:unit,pack,carton',
        ]);

        $item->update($data);

        ActivityLogger::stock(
            "Updated reorder point for '{$item->item_name}' to {$data['reorder_point']} {$data['reorder_unit']}(s)",
            $branch->id,
            [
                'item_id'       => $item->id,
                'reorder_point' => $data['reorder_point'],
                'reorder_unit'  => $data['reorder_unit'],
            ]
        );

        return back()->with('success', "Reorder point for '{$item->item_name}' updated successfully.");
    }

    public function export(Request $request)
    {
        $branch = current_branch();

        $query = Item::where('branch_id', $branch->id)
            ->with(['category:id,name'])
            ->orderBy('item_name');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $search = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', $search)
                  ->orWhere('barcode_number', 'like', $search);
            });
        }

        $allItems = $query->get();

        $tab = $request->get('tab', 'reorder');
        $filtered = match($tab) {
            'reorder'  => $allItems->filter(fn($item) => $item->needs_reorder),
            'critical' => $allItems->filter(fn($item) => ((int) $item->front_store_qty + (int) $item->back_store_qty) <= 0),
            'adequate' => $allItems->filter(fn($item) => !$item->needs_reorder),
            default    => $allItems,
        };

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reorder & Replenishment');

        $headers = [
            '#',
            'Item Name',
            'Barcode / SKU',
            'Category',
            'Front Store Qty',
            'Back Store Qty',
            'Total Available Stock',
            'Reorder Point (Threshold)',
            'Reorder Unit',
            'Stock Status',
            'Suggested Order Qty',
            'Cost Price (₦)',
            'Selling Price (₦)',
        ];

        foreach ($headers as $colIdx => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
        }

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = "A1:{$lastCol}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('059669'); // Emerald

        $format = strtolower($request->get('format', 'xlsx')) === 'csv' ? 'csv' : 'xlsx';
        $rowNum = 2;
        $idx = 1;
        foreach ($filtered as $item) {
            $totalStock   = (int) $item->front_store_qty + (int) $item->back_store_qty;
            $reorderPoint = (int) ($item->reorder_point ?? 10);
            $reorderUnit  = $item->reorder_unit ?: 'unit';

            $status = 'ADEQUATE';
            if ($totalStock <= 0) {
                $status = 'CRITICAL / OUT OF STOCK';
            } elseif ($item->needs_reorder) {
                $status = 'REORDER NOW';
            }

            $suggestedQty = max(0, ($reorderPoint * 2) - $totalStock);

            $col = 1;
            $sheet->setCellValue([$col++, $rowNum], $idx++);
            $sheet->setCellValue([$col++, $rowNum], $item->item_name);
            if (!empty($item->barcode_number)) {
                $barcodeVal = (string) $item->barcode_number;
                if ($format === 'csv') {
                    $sheet->setCellValueExplicit([$col++, $rowNum], '="' . $barcodeVal . '"', DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValueExplicit([$col++, $rowNum], $barcodeVal, DataType::TYPE_STRING);
                    $sheet->getStyle([$col - 1, $rowNum])->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                }
            } else {
                $sheet->setCellValue([$col++, $rowNum], '-');
            }
            $sheet->setCellValue([$col++, $rowNum], $item->category?->name ?: 'Uncategorized');
            $sheet->setCellValue([$col++, $rowNum], (int) $item->front_store_qty);
            $sheet->setCellValue([$col++, $rowNum], (int) $item->back_store_qty);
            $sheet->setCellValue([$col++, $rowNum], $totalStock);
            $sheet->setCellValue([$col++, $rowNum], $reorderPoint);
            $sheet->setCellValue([$col++, $rowNum], $reorderUnit);
            $sheet->setCellValue([$col++, $rowNum], $status);
            $sheet->setCellValue([$col++, $rowNum], (int) $suggestedQty);
            $sheet->setCellValue([$col++, $rowNum], (float) $item->buy_price);
            $sheet->setCellValue([$col++, $rowNum], (float) $item->unit_price);

            $rowNum++;
        }

        if ($format !== 'csv' && $rowNum > 2) {
            $sheet->getStyle('C2:C' . ($rowNum - 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        for ($c = 1; $c <= count($headers); $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        $filename = 'reorder_points_' . $branch->slug . '_' . date('Y-m-d_His') . '.' . $format;

        if ($format === 'csv') {
            $writer = new CsvWriter($spreadsheet);
            $writer->setUseBOM(true);
            $writer->setPreCalculateFormulas(false);
            $contentType = 'text/csv; charset=UTF-8';
        } else {
            $writer = new XlsxWriter($spreadsheet);
            $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }
}
