<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\StockConflict;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

class StockConflictController extends Controller
{
    /**
     * Shared filter query builder for conflicts index and export.
     */
    protected function buildFilteredQuery(Request $request, $branchId)
    {
        $query = StockConflict::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with([
                'sale:id,receipt_id,offline_sale_id,final_total,payment_method,created_at,user_id',
                'sale.user:id,name',
                'item:id,item_name,barcode_number,front_store_qty,back_store_qty',
                'resolver:id,name',
            ]);

        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                  ->orWhere('imei_or_device_id', 'like', "%{$search}%")
                  ->orWhere('offline_sale_id', 'like', "%{$search}%")
                  ->orWhereHas('sale', function ($sq) use ($search) {
                      $sq->where('receipt_id', 'like', "%{$search}%");
                  });
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->input('conflict_type')) {
            $query->where('conflict_type', $type);
        }

        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query->latest();
    }

    public function index(Request $request, $branchParam = null)
    {
        $branch = current_branch() ?? ($branchParam ? \App\Models\Branch::where('slug', $branchParam)->first() : null);
        $branchId = $branch?->id;

        $conflicts = $this->buildFilteredQuery($request, $branchId)
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'pending'   => StockConflict::when($branchId, fn ($q) => $q->where('branch_id', $branchId))->where('status', 'pending_review')->count(),
            'resolved'  => StockConflict::when($branchId, fn ($q) => $q->where('branch_id', $branchId))->where('status', 'resolved')->count(),
            'dismissed' => StockConflict::when($branchId, fn ($q) => $q->where('branch_id', $branchId))->where('status', 'dismissed')->count(),
            'total'     => StockConflict::when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count(),
        ];

        return Inertia::render('Inventory/Conflicts', [
            'conflicts' => $conflicts,
            'filters'   => $request->only(['search', 'status', 'conflict_type', 'from', 'to']),
            'stats'     => $stats,
        ]);
    }

    /**
     * Export stock conflicts spreadsheet (Excel .xlsx or CSV .csv) based on active filters.
     */
    public function export(Request $request, $branchParam = null)
    {
        $branch   = current_branch() ?? ($branchParam ? \App\Models\Branch::where('slug', $branchParam)->first() : null);
        $branchId = $branch?->id;
        $format   = strtolower($request->get('format', 'xlsx')) === 'csv' ? 'csv' : 'xlsx';

        $conflicts = $this->buildFilteredQuery($request, $branchId)->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Stock Conflicts');

        $headers = [
            'Conflict ID',
            'Date & Time',
            'Item Name',
            'Barcode / SKU',
            'Conflict Type',
            'Requested Qty',
            'Available at Sync',
            'Shortfall Qty',
            'Conflict IMEI / Device ID',
            'Receipt #',
            'Offline Sale ID',
            'Cashier / Staff',
            'Status',
            'Resolution Notes',
            'Resolved By',
            'Resolved At',
        ];

        // Write Headers
        foreach ($headers as $colIdx => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
        }

        // Header Styling
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = "A1:{$lastCol}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('059669'); // Emerald

        // Data Rows
        $rowNum = 2;
        foreach ($conflicts as $c) {
            $staffName    = $c->sale?->user?->full_name ?: ($c->sale?->user?->name ?: ($c->sale?->user?->username ?: '-'));
            $resolverName = $c->resolver?->full_name ?: ($c->resolver?->name ?: ($c->resolver?->username ?: '-'));

            $typeLabel = match ($c->conflict_type) {
                'stock_shortfall' => 'Insufficient Stock',
                'imei_already_sold', 'duplicate_imei' => 'Duplicate IMEI',
                default => ucwords(str_replace('_', ' ', $c->conflict_type ?: 'Unknown')),
            };

            $statusLabel = match ($c->status) {
                'pending_review' => 'Pending Review',
                'resolved'       => 'Resolved',
                'dismissed'      => 'Dismissed',
                default          => ucwords(str_replace('_', ' ', $c->status ?: '-')),
            };

            $shortfall = max(0, (int) $c->requested_qty - (int) $c->available_qty_at_sync);

            $sheet->setCellValue("A{$rowNum}", $c->id);
            $sheet->setCellValue("B{$rowNum}", $c->created_at ? $c->created_at->format('Y-m-d H:i:s') : '-');
            $sheet->setCellValue("C{$rowNum}", $c->item_name ?: ($c->item?->item_name ?? '-'));
            $sheet->setCellValue("D{$rowNum}", $c->item?->barcode_number ?? '-');
            $sheet->setCellValue("E{$rowNum}", $typeLabel);
            $sheet->setCellValue("F{$rowNum}", (int) $c->requested_qty);
            $sheet->setCellValue("G{$rowNum}", (int) $c->available_qty_at_sync);
            $sheet->setCellValue("H{$rowNum}", $shortfall);
            $sheet->setCellValue("I{$rowNum}", $c->imei_or_device_id ?: '-');
            $sheet->setCellValue("J{$rowNum}", $c->sale?->receipt_id ?? '-');
            $sheet->setCellValue("K{$rowNum}", $c->offline_sale_id ?: '-');
            $sheet->setCellValue("L{$rowNum}", $staffName);
            $sheet->setCellValue("M{$rowNum}", $statusLabel);
            $sheet->setCellValue("N{$rowNum}", $c->resolution_notes ?: '-');
            $sheet->setCellValue("O{$rowNum}", $resolverName);
            $sheet->setCellValue("P{$rowNum}", $c->resolved_at ? $c->resolved_at->format('Y-m-d H:i:s') : '-');

            $rowNum++;
        }

        // Auto-fit columns
        for ($col = 1; $col <= count($headers); $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $filename = 'stock_conflicts_' . date('Y-m-d_His') . '.' . $format;

        if ($format === 'csv') {
            $writer = new CsvWriter($spreadsheet);
            $writer->setUseBOM(true);
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

    public function resolve(Request $request, $branchParam = null, StockConflict $conflict = null)
    {
        $branch = current_branch() ?? ($branchParam ? \App\Models\Branch::where('slug', $branchParam)->first() : null);
        if ($branch && $conflict && $conflict->branch_id !== $branch->id) {
            abort(403, 'Unauthorized access to this conflict record.');
        }

        $data = $request->validate([
            'status'           => 'required|in:resolved,dismissed',
            'resolution_notes' => 'nullable|string|max:1000',
        ]);

        $conflict->update([
            'status'           => $data['status'],
            'resolution_notes' => $data['resolution_notes'],
            'resolved_by'      => Auth::id(),
            'resolved_at'      => now(),
        ]);

        // Mark associated notifications as resolved
        \App\Services\NotificationService::resolveForReference(
            StockConflict::class,
            $conflict->id,
            Auth::id()
        );

        \App\Services\ActivityLogger::stock(
            "Marked stock conflict #{$conflict->id} ({$conflict->item_name}) as {$data['status']}",
            $branch?->id
        );

        return back()->with('success', 'Conflict updated successfully.');
    }
}
