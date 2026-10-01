<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\PosSettings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemExpiryReportController extends Controller
{
    /**
     * Display the Item-Level Expiry Report.
     * Focuses on products with direct expiry dates on items.
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

        $search     = trim($request->input('search', ''));
        $categoryId = $request->input('category_id');
        $status     = $request->input('status', 'all');
        $preset     = $request->input('preset', 'all');
        $expiryFrom = $request->input('expiry_from');
        $expiryTo   = $request->input('expiry_to');

        // ── Branch-wide KPI Stats (unfiltered baseline) ──────────────────────
        $baseQuery = Item::where('branch_id', $branch->id)->whereNotNull('expiry_date');

        $totalTracked = (clone $baseQuery)->count();

        $expiredCount = (clone $baseQuery)
            ->where('expiry_date', '<', $today)
            ->count();

        $expiringSoonCount = (clone $baseQuery)
            ->whereBetween('expiry_date', [$today, $targetAlertDate])
            ->whereRaw('(COALESCE(front_store_qty, 0) + COALESCE(back_store_qty, 0)) > 0')
            ->count();

        // Stock value at risk (expired + expiring soon items that have quantity in stock)
        $atRiskItems = (clone $baseQuery)
            ->where(function ($q) use ($today, $targetAlertDate) {
                $q->where('expiry_date', '<', $today)
                  ->orWhereBetween('expiry_date', [$today, $targetAlertDate]);
            })
            ->whereRaw('(COALESCE(front_store_qty, 0) + COALESCE(back_store_qty, 0)) > 0')
            ->get(['buy_price', 'front_store_qty', 'back_store_qty']);

        $valueAtRisk = $atRiskItems->sum(function ($item) {
            $qty = ((int) $item->front_store_qty) + ((int) $item->back_store_qty);
            return $qty * (float) ($item->buy_price ?? 0);
        });

        // ── Filtered Items Query ─────────────────────────────────────────────
        $itemsQuery = Item::where('branch_id', $branch->id)
            ->whereNotNull('expiry_date')
            ->with(['category:id,name'])
            ->orderBy('expiry_date', 'asc')
            ->orderBy('id', 'asc');

        if ($search) {
            $itemsQuery->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                  ->orWhere('barcode_number', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $itemsQuery->where('category_id', $categoryId);
        }

        // Custom Date Range
        if (!empty($expiryFrom) && !empty($expiryTo)) {
            $itemsQuery->whereBetween('expiry_date', [
                Carbon::parse($expiryFrom)->toDateString(),
                Carbon::parse($expiryTo)->toDateString()
            ]);
        } elseif ($preset === 'next_7') {
            $itemsQuery->whereBetween('expiry_date', [$today, Carbon::today()->addDays(7)->toDateString()]);
        } elseif ($preset === 'next_30') {
            $itemsQuery->whereBetween('expiry_date', [$today, Carbon::today()->addDays(30)->toDateString()]);
        } elseif ($preset === 'next_60') {
            $itemsQuery->whereBetween('expiry_date', [$today, Carbon::today()->addDays(60)->toDateString()]);
        } elseif ($preset === 'next_90') {
            $itemsQuery->whereBetween('expiry_date', [$today, Carbon::today()->addDays(90)->toDateString()]);
        } elseif ($preset === 'expired') {
            $itemsQuery->where('expiry_date', '<', $today);
        } else {
            // Tab / Status filter
            if ($status === 'expiring_soon') {
                $itemsQuery->whereBetween('expiry_date', [$today, $targetAlertDate]);
            } elseif ($status === 'expired') {
                $itemsQuery->where('expiry_date', '<', $today);
            } elseif ($status === 'good') {
                $itemsQuery->where('expiry_date', '>', $targetAlertDate);
            }
        }

        $items = $itemsQuery->paginate(25)->withQueryString()->through(function ($item) use ($today, $targetAlertDate) {
            $expiry = Carbon::parse($item->expiry_date)->startOf('day');
            $current = Carbon::parse($today)->startOf('day');
            $daysRemaining = (int) $current->diffInDays($expiry, false);

            $statusKey = 'good';
            if ($daysRemaining < 0) {
                $statusKey = 'expired';
            } elseif ($daysRemaining <= Carbon::parse($targetAlertDate)->diffInDays(Carbon::parse($today))) {
                $statusKey = 'expiring_soon';
            }

            $totalQty = (int) (($item->front_store_qty ?? 0) + ($item->back_store_qty ?? 0));
            $buyPrice = (float) ($item->buy_price ?? 0);
            $stockWorth = round($totalQty * $buyPrice, 2);

            return [
                'id'                 => $item->id,
                'item_name'          => $item->item_name,
                'barcode_number'     => $item->barcode_number,
                'category_name'      => $item->category?->name ?? 'Uncategorized',
                'front_store_qty'    => (int) ($item->front_store_qty ?? 0),
                'back_store_qty'     => (int) ($item->back_store_qty ?? 0),
                'total_qty'          => $totalQty,
                'unit_label'         => $item->unit_label ?: 'Unit',
                'buy_price'          => $buyPrice,
                'price'              => (float) ($item->price ?? 0),
                'stock_worth'        => $stockWorth,
                'expiry_date'        => $item->expiry_date?->format('Y-m-d'),
                'days_remaining'     => $daysRemaining,
                'status_key'         => $statusKey,
                'is_in_stock'        => $totalQty > 0,
            ];
        });

        $categories = Category::where(function ($q) use ($branch) {
            $q->where('branch_id', $branch->id)->orWhereNull('branch_id');
        })->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Reports/ItemExpiry', [
            'items'      => $items,
            'categories' => $categories,
            'stats'      => [
                'total_tracked'       => $totalTracked,
                'expiring_soon_count' => $expiringSoonCount,
                'expired_count'       => $expiredCount,
                'value_at_risk'       => $valueAtRisk,
                'alert_days'          => $alertDays,
            ],
            'filters'    => [
                'search'      => $search,
                'category_id' => $categoryId,
                'status'      => $status,
                'preset'      => $preset,
                'expiry_from' => $expiryFrom,
                'expiry_to'   => $expiryTo,
            ],
            'settings'   => [
                'batch_tracking_enabled' => (bool) ($settings->batch_tracking_enabled ?? false),
                'expiry_alert_days'      => $alertDays,
            ],
        ]);
    }

    /**
     * Export Item Expiry Report to Excel.
     */
    public function export(Request $request, $branchParam = null)
    {
        $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->firstOrFail() : null);
        if (!$branch) {
            abort(404, 'Branch not found.');
        }

        $settings = PosSettings::forBranch($branch->id);
        $alertDays = max(1, (int) ($settings->expiry_alert_days ?? 30));
        $today = Carbon::today()->toDateString();
        $targetAlertDate = Carbon::today()->addDays($alertDays)->toDateString();

        $search     = trim($request->input('search', ''));
        $categoryId = $request->input('category_id');
        $status     = $request->input('status', 'all');
        $preset     = $request->input('preset', 'all');
        $expiryFrom = $request->input('expiry_from');
        $expiryTo   = $request->input('expiry_to');

        $itemsQuery = Item::where('branch_id', $branch->id)
            ->whereNotNull('expiry_date')
            ->with(['category:id,name'])
            ->orderBy('expiry_date', 'asc');

        if ($search) {
            $itemsQuery->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                  ->orWhere('barcode_number', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $itemsQuery->where('category_id', $categoryId);
        }

        if (!empty($expiryFrom) && !empty($expiryTo)) {
            $itemsQuery->whereBetween('expiry_date', [
                Carbon::parse($expiryFrom)->toDateString(),
                Carbon::parse($expiryTo)->toDateString()
            ]);
        } elseif ($preset === 'next_7') {
            $itemsQuery->whereBetween('expiry_date', [$today, Carbon::today()->addDays(7)->toDateString()]);
        } elseif ($preset === 'next_30') {
            $itemsQuery->whereBetween('expiry_date', [$today, Carbon::today()->addDays(30)->toDateString()]);
        } elseif ($preset === 'next_60') {
            $itemsQuery->whereBetween('expiry_date', [$today, Carbon::today()->addDays(60)->toDateString()]);
        } elseif ($preset === 'next_90') {
            $itemsQuery->whereBetween('expiry_date', [$today, Carbon::today()->addDays(90)->toDateString()]);
        } elseif ($preset === 'expired') {
            $itemsQuery->where('expiry_date', '<', $today);
        } else {
            if ($status === 'expiring_soon') {
                $itemsQuery->whereBetween('expiry_date', [$today, $targetAlertDate]);
            } elseif ($status === 'expired') {
                $itemsQuery->where('expiry_date', '<', $today);
            } elseif ($status === 'good') {
                $itemsQuery->where('expiry_date', '>', $targetAlertDate);
            }
        }

        $items = $itemsQuery->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Item Expiry Report');

        $headers = [
            'Item Name', 'Barcode / SKU', 'Category', 'Expiry Date',
            'Days Remaining', 'Status', 'Front Store Qty', 'Back Store Qty',
            'Total Stock', 'Cost Price (₦)', 'Selling Price (₦)', 'Stock Worth (₦)'
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        $current = Carbon::parse($today)->startOf('day');

        foreach ($items as $item) {
            $expiry = Carbon::parse($item->expiry_date)->startOf('day');
            $days = (int) $current->diffInDays($expiry, false);

            $statusLabel = 'Valid / Good';
            if ($days < 0) {
                $statusLabel = 'Expired (' . abs($days) . 'd ago)';
            } elseif ($days <= $alertDays) {
                $statusLabel = 'Expiring Soon (' . $days . 'd left)';
            }

            $totalQty = (int) (($item->front_store_qty ?? 0) + ($item->back_store_qty ?? 0));
            $buyPrice = (float) ($item->buy_price ?? 0);
            $stockWorth = round($totalQty * $buyPrice, 2);

            $sheet->fromArray([
                $item->item_name,
                $item->barcode_number ?: 'N/A',
                $item->category?->name ?? 'Uncategorized',
                $expiry->format('Y-m-d'),
                $days < 0 ? (-$days . ' days overdue') : ($days . ' days remaining'),
                $statusLabel,
                (int) ($item->front_store_qty ?? 0),
                (int) ($item->back_store_qty ?? 0),
                $totalQty,
                number_format($buyPrice, 2),
                number_format((float) ($item->price ?? 0), 2),
                number_format($stockWorth, 2),
            ], null, 'A' . $row);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'item_expiry_report_' . date('Y-m-d_His') . '.xlsx';

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }
}
