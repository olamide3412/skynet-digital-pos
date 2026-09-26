<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\InventoryTransaction;

use App\Models\Item;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $branch = current_branch();

        $transactions = $this->buildFilteredInventoryQuery($request, $branch)
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Inventory/Index', [
            'transactions' => $transactions,
            'filters'      => $request->only('item_id', 'type', 'search', 'from', 'to'),
        ]);
    }

    /**
     * Shared filter query builder for index and export.
     */
    protected function buildFilteredInventoryQuery(Request $request, $branch)
    {
        return InventoryTransaction::with(['item', 'user'])
            ->where('branch_id', $branch->id)
            ->when($request->item_id, fn ($q) => $q->where('item_id', $request->item_id))
            ->when($request->from, fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->when($request->type, function ($q, $type) {
                $q->where(function ($sub) use ($type) {
                    $sub->where('transaction_type', $type)
                        ->orWhere('transaction_type', 'like', "%{$type}%")
                        ->orWhere('notes', 'like', "%{$type}%");

                    $lower = strtolower($type);
                    if (str_contains($lower, 'sale')) {
                        $sub->orWhere('transaction_type', 'sale');
                    } elseif (str_contains($lower, 'purchase') || str_contains($lower, 'receive')) {
                        $sub->orWhere('transaction_type', 'purchase');
                    } elseif (str_contains($lower, 'adjust') || str_contains($lower, 'manual')) {
                        $sub->orWhere('transaction_type', 'adjustment');
                    } elseif (str_contains($lower, 'transfer')) {
                        $sub->orWhere('transaction_type', 'transfer');
                    } elseif (str_contains($lower, 'return')) {
                        $sub->orWhere('transaction_type', 'return');
                    } elseif (str_contains($lower, 'initial')) {
                        $sub->orWhere('transaction_type', 'like', '%initial%')
                            ->orWhere('notes', 'like', '%initial%');
                    }
                });
            })
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('reference_id', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('item', fn ($iq) => $iq->where('item_name', 'like', "%{$search}%")->orWhere('barcode_number', 'like', "%{$search}%"));
                });
            })
            ->latest();
    }

    /**
     * Export inventory logs to Excel (.xlsx) or CSV (.csv).
     */
    public function export(Request $request)
    {
        $branch = current_branch();
        $format = strtolower($request->get('format', 'xlsx')) === 'csv' ? 'csv' : 'xlsx';
        $logs   = $this->buildFilteredInventoryQuery($request, $branch)->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Inventory Logs');

        $headers = [
            'Log ID',
            'Date & Time',
            'Item Name',
            'Barcode / SKU',
            'Location',
            'Transaction Type',
            'Qty Change',
            'Previous Qty',
            'New Qty',
            'Reason / Notes',
            'Reference ID',
            'Logged By',
        ];

        // Write Headers
        foreach ($headers as $colIdx => $header) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
        }

        // Header Styling
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = "A1:{$lastCol}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('059669'); // Emerald

        // Data Rows
        $rowNum = 2;
        foreach ($logs as $log) {
            $staffName = $log->user?->full_name ?: ($log->user?->name ?: ($log->user?->username ?: 'System'));
            $locName   = $log->location === 'back_store' ? 'Back Store' : 'Front Store';
            $typeLabel = match (strtolower($log->transaction_type ?? '')) {
                'sale'       => 'Sale Deduction',
                'purchase'   => 'Purchase In / Restock',
                'transfer'   => 'Stock Transfer',
                'adjustment' => 'Stock Adjustment',
                'return'     => 'Return Restock',
                default      => $log->transaction_type ?: 'Other',
            };

            $sheet->setCellValue("A{$rowNum}", $log->id);
            $sheet->setCellValue("B{$rowNum}", $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : '-');
            $sheet->setCellValue("C{$rowNum}", $log->item?->item_name ?? '#'.$log->item_id);
            $sheet->setCellValue("D{$rowNum}", $log->item?->barcode_number ?? '-');
            $sheet->setCellValue("E{$rowNum}", $locName);
            $sheet->setCellValue("F{$rowNum}", $typeLabel);
            $sheet->setCellValue("G{$rowNum}", (int) $log->qty);
            $sheet->setCellValue("H{$rowNum}", $log->previous_qty !== null ? (int) $log->previous_qty : '-');
            $sheet->setCellValue("I{$rowNum}", $log->new_qty !== null ? (int) $log->new_qty : '-');
            $sheet->setCellValue("J{$rowNum}", $log->notes ?? '-');
            $sheet->setCellValue("K{$rowNum}", $log->reference_id ?? '-');
            $sheet->setCellValue("L{$rowNum}", $staffName);

            $rowNum++;
        }

        // Auto-fit columns
        for ($col = 1; $col <= count($headers); $col++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $filename = 'inventory_logs_' . date('Y-m-d_His') . '.' . $format;

        if ($format === 'csv') {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
            $writer->setUseBOM(true);
            $contentType = 'text/csv; charset=UTF-8';
        } else {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
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

    public function adjustForm()
    {
        $branch = current_branch();
        return Inertia::render('Inventory/Adjust', [
            'items' => Item::where('branch_id', $branch->id)
                ->select('id', 'item_name', 'barcode_number', 'front_store_qty', 'back_store_qty', 'batch_tracking_enabled', 'buy_price')
                ->orderBy('item_name')
                ->limit(50)
                ->get()
        ]);
    }

    public function processAdjustment(Request $request)
    {
        $branch = current_branch();
        $data = $request->validate([
            'item_id'           => 'required|exists:items,id',
            'location'          => 'required|in:front_store,back_store',
            'type'              => 'required|in:Addition,Subtraction',
            'qty'               => 'required|integer|min:1',
            'reason'            => 'required|string|max:255',
            'deduct_from_batch' => 'nullable|boolean',
        ]);

        $item = Item::where('branch_id', $branch->id)->findOrFail($data['item_id']);
        $col  = $data['location'] === 'back_store' ? 'back_store_qty' : 'front_store_qty';
        
        if ($data['type'] === 'Subtraction' && $item->{$col} < $data['qty']) {
            $locName = $data['location'] === 'back_store' ? 'Back Store' : 'Front Store';
            throw \Illuminate\Validation\ValidationException::withMessages([
                'qty' => "Cannot subtract {$data['qty']} from {$item->{$col}} in {$locName}. Insufficient stock.",
            ]);
        }

        $adjType = $data['type'] === 'Addition' ? 'add' : 'subtract';
        
        // 1. Stock update happens first and independently (§3, §4.2, §5.1)
        InventoryService::adjust(
            $data['item_id'],
            $adjType,
            $data['qty'],
            $data['reason'],
            Auth::user(),
            $data['location']
        );

        // 2. Batch handling layered on
        if ($data['type'] === 'Subtraction' && !empty($data['deduct_from_batch'])) {
            \App\Services\BatchTrackingService::deductFefo(
                $item,
                (int) $data['qty'],
                'adjustment',
                "ADJ-" . time(),
                Auth::user(),
                $data['reason']
            );
        }

        // For additions on batch-tracked items, provide prompt payload for the Create Batch modal (§5.2)
        if ($data['type'] === 'Addition' && \App\Services\BatchTrackingService::isBatchTrackingActive($branch->id, $item)) {
            return redirect()->route('pos.inventory.index', ['branch' => $branch->slug])->with([
                'success' => 'Inventory successfully adjusted.',
                'batch_prompt' => [
                    'item_id'     => $item->id,
                    'item_name'   => $item->item_name,
                    'added_qty'   => (int) $data['qty'],
                    'cost_price'  => (float) $item->buy_price,
                ]
            ]);
        }

        return redirect()->route('pos.inventory.index', ['branch' => $branch->slug])->with('success', 'Inventory successfully adjusted.');
    }
}
