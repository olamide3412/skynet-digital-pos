<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PosLog;
use App\Modules\CommerceIntegration\Models\CommerceIntegration;
use App\Modules\CommerceIntegration\Models\CommerceInventoryMovement;
use App\Modules\CommerceIntegration\Models\CommerceItemMapping;
use App\Modules\CommerceIntegration\Models\CommerceReservation;
use App\Modules\CommerceIntegration\Services\InventoryReservationService;
use App\Modules\CommerceIntegration\Services\MappingRegistryService;
use App\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EcommerceActivityController extends Controller
{
    /**
     * Display branch ecommerce activity dashboard (reservations, movements, mappings).
     */
    public function index(Request $request, $branchParam = null)
    {
        $branch = current_branch() ?? ($branchParam instanceof Branch ? $branchParam : ($branchParam ? Branch::where('slug', $branchParam)->orWhere('id', $branchParam)->first() : null));
        if (!$branch) {
            abort(404, 'Branch not found.');
        }

        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->isBranchAdmin() && !RoleService::canViewEcommerceActivity()) {
            abort(403, 'You do not have permission to view ecommerce activity.');
        }

        $integration = CommerceIntegration::where('branch_id', $branch->id)->first();

        $tab = $request->input('tab', 'reservations');
        if (!in_array($tab, ['reservations', 'movements', 'mappings'], true)) {
            $tab = 'reservations';
        }

        // Stats Summary for top cards
        $now = now();
        $stats = [
            'active_reservations' => CommerceReservation::where('branch_id', $branch->id)
                ->where('status', 'pending')
                ->where('expires_at', '>', $now)
                ->count(),
            'confirmed_reservations' => CommerceReservation::where('branch_id', $branch->id)
                ->where('status', 'confirmed')
                ->count(),
            'active_mappings' => CommerceItemMapping::where('branch_id', $branch->id)
                ->where('is_active', true)
                ->count(),
            'total_movements' => CommerceInventoryMovement::where('branch_id', $branch->id)->count(),
        ];

        $reservations = null;
        $movements = null;
        $mappings = null;

        if ($tab === 'reservations') {
            $reservations = $this->buildReservationsQuery($request, $branch->id, $now)
                ->latest()
                ->paginate(15)
                ->withQueryString();
        } elseif ($tab === 'movements') {
            $movements = $this->buildMovementsQuery($request, $branch->id)
                ->latest()
                ->paginate(15)
                ->withQueryString();
        } elseif ($tab === 'mappings') {
            $mappings = $this->buildMappingsQuery($request, $branch->id)
                ->latest('updated_at')
                ->paginate(15)
                ->withQueryString();
        }

        return Inertia::render('EcommerceActivity/Index', [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'slug' => $branch->slug,
            ],
            'integration' => $integration ? [
                'id' => $integration->id,
                'platform' => $integration->platform,
                'store_name' => $integration->name,
                'store_url' => $integration->store_url ?? null,
                'status' => $integration->status,
                'is_active' => $integration->isActive(),
                'last_synced_at' => $integration->last_synced_at?->format('d M Y, H:i'),
            ] : null,
            'activeTab' => $tab,
            'stats' => $stats,
            'reservations' => $reservations,
            'movements' => $movements,
            'mappings' => $mappings,
            'filters' => $request->only(['search', 'status', 'type', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Manually release a stuck or expired reservation to free inventory back to front store.
     */
    public function releaseReservation(Request $request, $branchParam, CommerceReservation $reservation, InventoryReservationService $reservationService)
    {
        $branch = current_branch();
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->isBranchAdmin() && !RoleService::canViewEcommerceActivity()) {
            abort(403, 'You do not have permission to perform this action.');
        }

        if ($reservation->branch_id !== $branch->id) {
            abort(404, 'Reservation not found.');
        }

        if ($reservation->status === 'confirmed' || $reservation->status === 'released') {
            return back()->with('error', "Reservation #{$reservation->id} is already {$reservation->status} and cannot be released.");
        }

        $integration = $reservation->integration ?? CommerceIntegration::where('branch_id', $branch->id)->first();
        if (!$integration) {
            return back()->with('error', 'No active commerce integration found for this branch.');
        }

        try {
            $reservationService->release(
                $integration,
                $reservation->external_order_reference,
                'Manually released by branch user: ' . ($user->name ?? $user->username)
            );

            PosLog::create([
                'branch_id' => $branch->id,
                'user_id' => $user->id,
                'action_type' => 'ecommerce_reservation_release',
                'log' => "Manually released ecommerce reservation #{$reservation->id} ({$reservation->external_order_reference})",
                'details' => [
                    'reservation_id' => $reservation->id,
                    'external_order_reference' => $reservation->external_order_reference,
                    'released_by' => $user->name,
                    'time' => now()->toIso8601String(),
                ],
                'created_at' => now(),
            ]);

            return back()->with('success', "Reservation #{$reservation->external_order_reference} has been successfully released and reserved stock returned to front store.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to release reservation: ' . $e->getMessage());
        }
    }

    /**
     * Manually mark a stale item mapping as inactive on the POS side.
     */
    public function deactivateMapping(Request $request, $branchParam, CommerceItemMapping $mapping, MappingRegistryService $mappingService)
    {
        $branch = current_branch();
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->isBranchAdmin() && !RoleService::canViewEcommerceActivity()) {
            abort(403, 'You do not have permission to perform this action.');
        }

        if ($mapping->branch_id !== $branch->id) {
            abort(404, 'Item mapping not found.');
        }

        try {
            $mappingService->deactivateMapping($mapping->id, $branch->id);

            PosLog::create([
                'branch_id' => $branch->id,
                'user_id' => $user->id,
                'action_type' => 'ecommerce_mapping_deactivate',
                'log' => "Manually deactivated ecommerce mapping #{$mapping->id} for item: " . ($mapping->item?->item_name ?? $mapping->item_id),
                'details' => [
                    'mapping_id' => $mapping->id,
                    'item_id' => $mapping->item_id,
                    'item_name' => $mapping->item?->item_name,
                    'external_product_id' => $mapping->external_product_id,
                    'deactivated_by' => $user->name,
                    'time' => now()->toIso8601String(),
                ],
                'created_at' => now(),
            ]);

            return back()->with('success', "Item mapping for '" . ($mapping->item?->item_name ?? $mapping->external_product_id) . "' has been deactivated.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to deactivate mapping: ' . $e->getMessage());
        }
    }

    /**
     * Export branch ecommerce activity data to Excel (.xlsx) or CSV (.csv).
     */
    public function export(Request $request, $branchParam = null): StreamedResponse
    {
        $branch = current_branch() ?? ($branchParam instanceof Branch ? $branchParam : ($branchParam ? Branch::where('slug', $branchParam)->orWhere('id', $branchParam)->first() : null));
        if (!$branch) {
            abort(404, 'Branch not found.');
        }

        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->isBranchAdmin() && !RoleService::canViewEcommerceActivity()) {
            abort(403, 'You do not have permission to export ecommerce activity.');
        }

        $tab = $request->input('tab', 'reservations');
        if (!in_array($tab, ['reservations', 'movements', 'mappings'], true)) {
            $tab = 'reservations';
        }

        $format = strtolower($request->query('format', 'xlsx')) === 'csv' ? 'csv' : 'xlsx';
        $now = now();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        if ($tab === 'reservations') {
            $sheet->setTitle('Reservations');
            $headers = [
                'SN',
                'Order Reference',
                'Status',
                'Reserved Items',
                'Total Qty',
                'Created At',
                'Expires At',
                'Resolved At',
            ];

            $records = $this->buildReservationsQuery($request, $branch->id, $now)->latest()->get();

            $rowNum = 2;
            foreach ($records as $idx => $res) {
                $statusLabel = match (true) {
                    $res->status === 'confirmed' => 'Confirmed',
                    $res->status === 'released'  => 'Released',
                    $res->status === 'pending' && $res->expires_at && $res->expires_at <= $now => 'Expired Pending',
                    default                      => 'Pending Hold',
                };

                $itemsSummary = $res->items->map(function ($i) {
                    $name = $i->item?->item_name ?? ('Item #' . $i->item_id);
                    return "{$i->quantity}x {$name}";
                })->implode(', ');

                $totalQty = $res->items->sum('quantity');
                $resolvedAt = $res->confirmed_at ? $res->confirmed_at->format('Y-m-d H:i:s') : ($res->released_at ? $res->released_at->format('Y-m-d H:i:s') : '-');

                $sheet->setCellValue([1, $rowNum], $idx + 1);
                $sheet->setCellValue([2, $rowNum], $res->external_order_reference);
                $sheet->setCellValue([3, $rowNum], $statusLabel);
                $sheet->setCellValue([4, $rowNum], $itemsSummary);
                $sheet->setCellValue([5, $rowNum], (int) $totalQty);
                $sheet->setCellValue([6, $rowNum], $res->created_at ? $res->created_at->format('Y-m-d H:i:s') : '-');
                $sheet->setCellValue([7, $rowNum], $res->expires_at ? $res->expires_at->format('Y-m-d H:i:s') : '-');
                $sheet->setCellValue([8, $rowNum], $resolvedAt);

                $rowNum++;
            }
        } elseif ($tab === 'movements') {
            $sheet->setTitle('Inventory Movements');
            $headers = [
                'SN',
                'Date & Time',
                'Movement Type',
                'Item Name',
                'Barcode / SKU',
                'Qty Change',
                'Previous Stock',
                'Current Stock',
                'Order Reference',
                'Notes',
            ];

            $records = $this->buildMovementsQuery($request, $branch->id)->latest()->get();

            $rowNum = 2;
            foreach ($records as $idx => $m) {
                $typeLabel = match ($m->type) {
                    'reservation_confirm' => 'Stock Deducted (Order Confirmed)',
                    'stock_deduction'     => 'Stock Deducted',
                    'reservation_hold'    => 'Hold Reserved',
                    'reservation_release' => 'Hold Released',
                    default               => $m->type ?: 'Other',
                };

                $sheet->setCellValue([1, $rowNum], $idx + 1);
                $sheet->setCellValue([2, $rowNum], $m->created_at ? $m->created_at->format('Y-m-d H:i:s') : '-');
                $sheet->setCellValue([3, $rowNum], $typeLabel);
                $sheet->setCellValue([4, $rowNum], $m->item?->item_name ?? ('Item #' . $m->item_id));
                $sheet->setCellValue([5, $rowNum], $m->item?->barcode_number ?? '-');
                $sheet->setCellValue([6, $rowNum], (int) $m->quantity);
                $sheet->setCellValue([7, $rowNum], $m->previous_stock !== null ? (int) $m->previous_stock : '-');
                $sheet->setCellValue([8, $rowNum], $m->current_stock !== null ? (int) $m->current_stock : '-');
                $sheet->setCellValue([9, $rowNum], $m->external_reference ?? '-');
                $sheet->setCellValue([10, $rowNum], $m->notes ?? '-');

                $rowNum++;
            }
        } else {
            // Mappings
            $sheet->setTitle('Catalog Mappings');
            $headers = [
                'SN',
                'Item Name',
                'Barcode',
                'POS Price (NGN)',
                'POS Front Store Qty',
                'Online Product ID',
                'Variant ID',
                'Online SKU',
                'Status',
                'Registered At',
                'Last Seen At',
            ];

            $records = $this->buildMappingsQuery($request, $branch->id)->latest('updated_at')->get();

            $rowNum = 2;
            foreach ($records as $idx => $map) {
                $sheet->setCellValue([1, $rowNum], $idx + 1);
                $sheet->setCellValue([2, $rowNum], $map->item?->item_name ?? ('Item #' . $map->item_id));
                $sheet->setCellValue([3, $rowNum], $map->item?->barcode_number ?? '-');
                $sheet->setCellValue([4, $rowNum], $map->item?->price ? (float) $map->item->price : 0);
                $sheet->setCellValue([5, $rowNum], $map->item?->front_store_qty ? (int) $map->item->front_store_qty : 0);
                $sheet->setCellValue([6, $rowNum], $map->external_product_id);
                $sheet->setCellValue([7, $rowNum], $map->external_variant_id ?? 'default');
                $sheet->setCellValue([8, $rowNum], $map->external_sku ?? '-');
                $sheet->setCellValue([9, $rowNum], $map->is_active ? 'Live Online' : 'Deactivated');
                $sheet->setCellValue([10, $rowNum], $map->registered_at ? $map->registered_at->format('Y-m-d H:i:s') : ($map->created_at ? $map->created_at->format('Y-m-d H:i:s') : '-'));
                $sheet->setCellValue([11, $rowNum], $map->last_seen_at ? $map->last_seen_at->format('Y-m-d H:i:s') : ($map->last_synced_at ? $map->last_synced_at->format('Y-m-d H:i:s') : '-'));

                $rowNum++;
            }
        }

        // Write Headers
        foreach ($headers as $colIdx => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
        }

        // Header Styling (Indigo)
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = "A1:{$lastCol}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('4F46E5'); // Indigo 600

        // Auto-fit columns
        for ($col = 1; $col <= count($headers); $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $filename = "branch_ecommerce_{$tab}_" . date('Y-m-d_His') . '.' . $format;

        if ($format === 'csv') {
            $writer = new Csv($spreadsheet);
            $writer->setUseBOM(true);
            $contentType = 'text/csv; charset=UTF-8';
        } else {
            $writer = new Xlsx($spreadsheet);
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

    protected function buildReservationsQuery(Request $request, int $branchId, Carbon $now)
    {
        $q = CommerceReservation::where('branch_id', $branchId)
            ->with(['items.item', 'integration', 'movements.item']);

        if ($search = trim((string) $request->input('search'))) {
            $q->where(function ($sub) use ($search) {
                $sub->where('external_order_reference', 'like', "%{$search}%")
                    ->orWhereHas('items.item', function ($iq) use ($search) {
                        $iq->where('item_name', 'like', "%{$search}%")
                           ->orWhere('barcode_number', 'like', "%{$search}%");
                    });
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'expired') {
                $q->where('status', 'pending')->where('expires_at', '<=', $now);
            } elseif ($status === 'active') {
                $q->where('status', 'pending')->where('expires_at', '>', $now);
            } elseif (in_array($status, ['pending', 'confirmed', 'released'], true)) {
                $q->where('status', $status);
            }
        }

        if ($dateFrom = $request->input('date_from')) {
            try {
                $q->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
            } catch (\Throwable) {}
        }
        if ($dateTo = $request->input('date_to')) {
            try {
                $q->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
            } catch (\Throwable) {}
        }

        return $q;
    }

    protected function buildMovementsQuery(Request $request, int $branchId)
    {
        $q = CommerceInventoryMovement::where('branch_id', $branchId)
            ->with(['item', 'integration']);

        if ($search = trim((string) $request->input('search'))) {
            $q->where(function ($sub) use ($search) {
                $sub->where('external_reference', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('item', function ($iq) use ($search) {
                        $iq->where('item_name', 'like', "%{$search}%")
                           ->orWhere('barcode_number', 'like', "%{$search}%");
                    });
            });
        }

        if ($type = $request->input('type')) {
            $q->where('type', $type);
        }

        if ($dateFrom = $request->input('date_from')) {
            try {
                $q->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
            } catch (\Throwable) {}
        }
        if ($dateTo = $request->input('date_to')) {
            try {
                $q->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
            } catch (\Throwable) {}
        }

        return $q;
    }

    protected function buildMappingsQuery(Request $request, int $branchId)
    {
        $q = CommerceItemMapping::where('branch_id', $branchId)
            ->with(['item', 'integration']);

        if ($search = trim((string) $request->input('search'))) {
            $q->where(function ($sub) use ($search) {
                $sub->where('external_product_id', 'like', "%{$search}%")
                    ->orWhere('external_variant_id', 'like', "%{$search}%")
                    ->orWhere('external_sku', 'like', "%{$search}%")
                    ->orWhereHas('item', function ($iq) use ($search) {
                        $iq->where('item_name', 'like', "%{$search}%")
                           ->orWhere('barcode_number', 'like', "%{$search}%");
                    });
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $q->where('is_active', true);
            } elseif ($status === 'inactive') {
                $q->where('is_active', false);
            }
        }

        return $q;
    }
}
