<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\ItemBatchSyncIssue;
use App\Models\PosSettings;
use App\Services\BatchTrackingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpiryReportController extends Controller
{
    /**
     * Branch-wide Expiry Overview report and untracked stock variance dashboard.
     */
    public function index(Request $request, $branchParam = null): Response
    {
        $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->firstOrFail() : null);
        if (!$branch) {
            abort(404, 'Branch not found.');
        }

        $settings = PosSettings::forBranch($branch->id);
        $alertDays = max(1, (int) ($settings->expiry_alert_days ?? 30));
        $today = Carbon::today()->toDateString();
        $targetAlertDate = Carbon::today()->addDays($alertDays)->toDateString();

        // Trigger notifications and status sync for approaching expiries
        BatchTrackingService::checkAndGenerateExpiryNotifications($branch->id);

        $tab = $request->input('tab', 'all');
        $search = trim($request->input('search', ''));
        $preset = $request->input('preset', 'all');
        $expiryFrom = $request->input('expiry_from');
        $expiryTo = $request->input('expiry_to');

        // KPI Stats
        $totalBatches = ItemBatch::where('branch_id', $branch->id)->where('status', 'active')->count();
        $expiringSoonCount = ItemBatch::where('branch_id', $branch->id)
            ->where('status', 'active')
            ->where('quantity', '>', 0)
            ->whereBetween('expiry_date', [$today, $targetAlertDate])
            ->count();
        $expiredCount = ItemBatch::where('branch_id', $branch->id)
            ->where('status', '!=', 'cleared')
            ->where('expiry_date', '<', $today)
            ->count();
        $syncIssuesCount = ItemBatchSyncIssue::where('branch_id', $branch->id)
            ->where('status', 'logged')
            ->count();

        // Query Batches
        $batchesQuery = ItemBatch::where('branch_id', $branch->id)
            ->with(['item:id,item_name,barcode_number,front_store_qty,back_store_qty,buy_price,unit_label'])
            ->orderBy('expiry_date', 'asc')
            ->orderBy('id', 'asc');

        if ($search) {
            $batchesQuery->where(function ($q) use ($search) {
                $q->where('batch_label', 'like', "%{$search}%")
                  ->orWhereHas('item', function ($iq) use ($search) {
                      $iq->where('item_name', 'like', "%{$search}%")
                         ->orWhere('barcode_number', 'like', "%{$search}%");
                  });
            });
        }

        // Apply Date Range Filter if provided
        if (!empty($expiryFrom) && !empty($expiryTo)) {
            $batchesQuery->whereBetween('expiry_date', [
                Carbon::parse($expiryFrom)->toDateString(),
                Carbon::parse($expiryTo)->toDateString()
            ]);
        } elseif ($preset === 'next_7') {
            $batchesQuery->where('status', 'active')
                ->where('quantity', '>', 0)
                ->whereBetween('expiry_date', [$today, Carbon::today()->addDays(7)->toDateString()]);
        } elseif ($preset === 'next_30') {
            $batchesQuery->where('status', 'active')
                ->where('quantity', '>', 0)
                ->whereBetween('expiry_date', [$today, Carbon::today()->addDays(30)->toDateString()]);
        } elseif ($preset === 'next_60') {
            $batchesQuery->where('status', 'active')
                ->where('quantity', '>', 0)
                ->whereBetween('expiry_date', [$today, Carbon::today()->addDays(60)->toDateString()]);
        } elseif ($preset === 'expired') {
            $batchesQuery->where('status', '!=', 'cleared')
                ->where('expiry_date', '<', $today);
        } else {
            // Tab based filtering
            if ($tab === 'expiring_soon') {
                $batchesQuery->where('status', 'active')
                    ->where('quantity', '>', 0)
                    ->whereBetween('expiry_date', [$today, $targetAlertDate]);
            } elseif ($tab === 'expired') {
                $batchesQuery->where('status', '!=', 'cleared')
                    ->where('expiry_date', '<', $today);
            } elseif ($tab === 'cleared') {
                $batchesQuery->where('status', 'cleared');
            }
        }

        $batches = $batchesQuery->paginate(25)->withQueryString();

        // Tracked Items Variance List
        $trackedItems = Item::where('branch_id', $branch->id)
            ->where('batch_tracking_enabled', true)
            ->with(['batches' => fn($q) => $q->where('status', 'active')->where('quantity', '>', 0)])
            ->get()
            ->map(function (Item $item) {
                $actualStock = ((int) ($item->front_store_qty ?? 0)) + ((int) ($item->back_store_qty ?? 0));
                $batchTotal = (int) $item->batches->sum('quantity');
                $variance = $actualStock - $batchTotal;

                return [
                    'id'              => $item->id,
                    'item_name'       => $item->item_name,
                    'barcode_number'  => $item->barcode_number,
                    'actual_stock'    => $actualStock,
                    'batch_total'     => $batchTotal,
                    'variance'        => $variance,
                    'untracked_units' => max(0, $variance),
                    'excess_units'    => max(0, -$variance),
                    'is_synced'       => $variance === 0,
                    'batches_count'   => $item->batches->count(),
                ];
            });

        $varianceMismatches = $trackedItems->filter(fn($i) => !$i['is_synced'])->values();
        $varianceItemsCount = $varianceMismatches->count();

        // Sync Issues Log
        $syncIssuesQuery = ItemBatchSyncIssue::where('branch_id', $branch->id)
            ->with('item:id,item_name,barcode_number')
            ->latest();

        if ($search) {
            $syncIssuesQuery->where(function ($q) use ($search) {
                $q->where('reference_id', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhereHas('item', fn($iq) => $iq->where('item_name', 'like', "%{$search}%"));
            });
        }

        $syncIssues = $syncIssuesQuery->paginate(25)->withQueryString();

        return Inertia::render('Reports/ExpiryOverview', [
            'batches'             => $batches,
            'variance_items'      => $varianceMismatches,
            'all_tracked_items'   => $trackedItems,
            'sync_issues'         => $syncIssues,
            'stats'               => [
                'total_batches'        => $totalBatches,
                'expiring_soon_count'  => $expiringSoonCount,
                'expired_count'        => $expiredCount,
                'variance_items_count' => $varianceItemsCount,
                'sync_issues_count'    => $syncIssuesCount,
                'alert_days'           => $alertDays,
            ],
            'filters'             => [
                'tab'         => $tab,
                'search'      => $search,
                'preset'      => $preset,
                'expiry_from' => $expiryFrom,
                'expiry_to'   => $expiryTo,
            ],
            'settings'            => $settings,
        ]);
    }

    /**
     * Export batch expiry report to Excel.
     */
    public function export(Request $request, $branchParam = null)
    {
        $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->firstOrFail() : null);
        if (!$branch) {
            abort(404, 'Branch not found.');
        }

        $search = trim($request->input('search', ''));
        $preset = $request->input('preset', 'all');
        $expiryFrom = $request->input('expiry_from');
        $expiryTo = $request->input('expiry_to');
        $today = Carbon::today()->toDateString();

        $batchesQuery = ItemBatch::where('branch_id', $branch->id)
            ->with(['item:id,item_name,barcode_number,front_store_qty,back_store_qty'])
            ->orderBy('expiry_date', 'asc');

        if ($search) {
            $batchesQuery->where(function ($q) use ($search) {
                $q->where('batch_label', 'like', "%{$search}%")
                  ->orWhereHas('item', function ($iq) use ($search) {
                      $iq->where('item_name', 'like', "%{$search}%")
                         ->orWhere('barcode_number', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($expiryFrom) && !empty($expiryTo)) {
            $batchesQuery->whereBetween('expiry_date', [
                Carbon::parse($expiryFrom)->toDateString(),
                Carbon::parse($expiryTo)->toDateString()
            ]);
        } elseif ($preset === 'next_7') {
            $batchesQuery->where('status', 'active')
                ->where('quantity', '>', 0)
                ->whereBetween('expiry_date', [$today, Carbon::today()->addDays(7)->toDateString()]);
        } elseif ($preset === 'next_30') {
            $batchesQuery->where('status', 'active')
                ->where('quantity', '>', 0)
                ->whereBetween('expiry_date', [$today, Carbon::today()->addDays(30)->toDateString()]);
        } elseif ($preset === 'next_60') {
            $batchesQuery->where('status', 'active')
                ->where('quantity', '>', 0)
                ->whereBetween('expiry_date', [$today, Carbon::today()->addDays(60)->toDateString()]);
        } elseif ($preset === 'expired') {
            $batchesQuery->where('status', '!=', 'cleared')
                ->where('expiry_date', '<', $today);
        }

        $batches = $batchesQuery->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Expiry Batches');

        $headers = [
            'Batch/Lot #', 'Item Name', 'Barcode', 'Batch Qty',
            'Expiry Date', 'Days Until Expiry', 'Status', 'Cost Price', 'Received Date'
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        $today = Carbon::today();

        foreach ($batches as $batch) {
            $expiry = Carbon::parse($batch->expiry_date);
            $days = (int) $today->diffInDays($expiry, false);

            $sheet->fromArray([
                $batch->batch_label ?: 'N/A',
                $batch->item?->item_name ?? 'N/A',
                $batch->item?->barcode_number ?? '',
                $batch->quantity,
                $expiry->format('Y-m-d'),
                $days < 0 ? "Expired (" . abs($days) . "d ago)" : "{$days} days",
                ucfirst($batch->status),
                $batch->cost_price ? number_format((float) $batch->cost_price, 2) : '',
                $batch->received_date ? Carbon::parse($batch->received_date)->format('Y-m-d') : '',
            ], null, 'A' . $row);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'expiry_batches_' . date('Y-m-d_His') . '.xlsx';

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }
}
