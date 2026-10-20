<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\OrganizationActivityLog;
use App\Models\PosLog;
use App\Modules\CommerceIntegration\Models\CommerceIntegration;
use App\Modules\CommerceIntegration\Models\CommerceInventoryMovement;
use App\Modules\CommerceIntegration\Models\CommerceItemMapping;
use App\Modules\CommerceIntegration\Models\CommerceReservation;
use App\Modules\CommerceIntegration\Services\InventoryReservationService;
use App\Modules\CommerceIntegration\Services\MappingRegistryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrgEcommerceActivityController extends Controller
{
    /**
     * Resolve the active organization for the current user session.
     */
    protected function resolveOrganization(Request $request): ?Organization
    {
        $user = Auth::guard('web')->user();

        if (!$user) {
            return null;
        }

        if ($user->isSuperAdmin()) {
            if ($request->has('org_id')) {
                return Organization::find($request->get('org_id')) ?: Organization::first();
            }
            return $user->organization_id ? Organization::find($user->organization_id) : Organization::first();
        }

        return $user->organization_id ? Organization::find($user->organization_id) : null;
    }

    /**
     * Display the Organization Ecommerce Activity Dashboard.
     */
    public function index(Request $request): Response
    {
        $org = $this->resolveOrganization($request);

        if (!$org) {
            abort(404, 'No organization found.');
        }

        $branches = Branch::where('organization_id', $org->id)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $allBranchIds = $branches->pluck('id')->toArray();

        $selectedBranchId = $request->input('branch_id');
        if ($selectedBranchId && in_array((int) $selectedBranchId, $allBranchIds, true)) {
            $targetBranchIds = [(int) $selectedBranchId];
        } else {
            $targetBranchIds = $allBranchIds;
            $selectedBranchId = 'all';
        }

        $tab = $request->input('tab', 'reservations');
        $now = now();

        $stats = [
            'connected_branches'     => CommerceIntegration::whereIn('branch_id', $allBranchIds)->count(),
            'active_reservations'    => CommerceReservation::whereIn('branch_id', $targetBranchIds)->where('status', 'pending')->where('expires_at', '>', $now)->count(),
            'confirmed_reservations' => CommerceReservation::whereIn('branch_id', $targetBranchIds)->where('status', 'confirmed')->count(),
            'active_mappings'        => CommerceItemMapping::whereIn('branch_id', $targetBranchIds)->where('is_active', true)->count(),
            'total_movements'        => CommerceInventoryMovement::whereIn('branch_id', $targetBranchIds)->count(),
        ];

        $reservations = null;
        $movements = null;
        $mappings = null;

        if ($tab === 'reservations') {
            $reservations = $this->buildReservationsQuery($request, $targetBranchIds, $now)
                ->latest()
                ->paginate(15)
                ->withQueryString();
        } elseif ($tab === 'movements') {
            $movements = $this->buildMovementsQuery($request, $targetBranchIds)
                ->latest()
                ->paginate(15)
                ->withQueryString();
        } elseif ($tab === 'mappings') {
            $mappings = $this->buildMappingsQuery($request, $targetBranchIds)
                ->latest('updated_at')
                ->paginate(15)
                ->withQueryString();
        }

        return Inertia::render('Org/EcommerceActivity/Index', [
            'organization'     => [
                'id'   => $org->id,
                'name' => $org->name,
            ],
            'branches'         => $branches,
            'selectedBranchId' => $selectedBranchId,
            'activeTab'        => $tab,
            'stats'            => $stats,
            'reservations'     => $reservations,
            'movements'        => $movements,
            'mappings'         => $mappings,
            'filters'          => $request->only(['search', 'status', 'type', 'date_from', 'date_to', 'branch_id']),
        ]);
    }

    /**
     * Manually release an expired or stuck reservation within the organization.
     */
    public function releaseReservation(Request $request, CommerceReservation $reservation, InventoryReservationService $reservationService)
    {
        $org = $this->resolveOrganization($request);
        $user = $request->user();

        if (!$org) {
            abort(404, 'Organization not found.');
        }

        $allBranchIds = Branch::where('organization_id', $org->id)->pluck('id')->toArray();
        if (!in_array($reservation->branch_id, $allBranchIds, true)) {
            abort(404, 'Reservation not found.');
        }

        if ($reservation->status === 'confirmed' || $reservation->status === 'released') {
            return back()->with('error', "Reservation #{$reservation->id} is already {$reservation->status} and cannot be released.");
        }

        $integration = $reservation->integration ?? CommerceIntegration::where('branch_id', $reservation->branch_id)->first();
        if (!$integration) {
            return back()->with('error', 'No active commerce store linked to this branch.');
        }

        try {
            $reservationService->release(
                $integration,
                $reservation->external_order_reference,
                'Manually released by organization admin: ' . ($user->name ?? $user->username)
            );

            // Audit in Org Activity Log
            OrganizationActivityLog::create([
                'organization_id' => $org->id,
                'actor_user_id'   => $user->id,
                'action'          => 'ecommerce_reservation_released',
                'details'         => [
                    'reservation_id'           => $reservation->id,
                    'external_order_reference' => $reservation->external_order_reference,
                    'branch_id'                => $reservation->branch_id,
                    'released_by'              => $user->name,
                ],
                'created_at'      => now(),
            ]);

            // Audit in Branch PosLog
            PosLog::create([
                'branch_id'   => $reservation->branch_id,
                'user_id'     => $user->id,
                'action_type' => 'ecommerce_reservation_release',
                'log'         => "Manually released ecommerce reservation #{$reservation->id} ({$reservation->external_order_reference}) from Organization Portal",
                'details'     => [
                    'reservation_id'           => $reservation->id,
                    'external_order_reference' => $reservation->external_order_reference,
                    'actor_portal'             => 'organization_portal',
                    'released_by'              => $user->name,
                ],
                'created_at'  => now(),
            ]);

            return back()->with('success', "Reservation #{$reservation->external_order_reference} successfully released and reserved stock returned to branch front store.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to release reservation: ' . $e->getMessage());
        }
    }

    /**
     * Manually mark a stale item mapping as inactive on the POS side.
     */
    public function deactivateMapping(Request $request, CommerceItemMapping $mapping, MappingRegistryService $mappingService)
    {
        $org = $this->resolveOrganization($request);
        $user = $request->user();

        if (!$org) {
            abort(404, 'Organization not found.');
        }

        $allBranchIds = Branch::where('organization_id', $org->id)->pluck('id')->toArray();
        if (!in_array($mapping->branch_id, $allBranchIds, true)) {
            abort(404, 'Item mapping not found.');
        }

        try {
            $mappingService->deactivateMapping($mapping->id, $mapping->branch_id);

            // Audit in Org Activity Log
            OrganizationActivityLog::create([
                'organization_id' => $org->id,
                'actor_user_id'   => $user->id,
                'action'          => 'ecommerce_mapping_deactivated',
                'details'         => [
                    'mapping_id'          => $mapping->id,
                    'branch_id'           => $mapping->branch_id,
                    'item_id'             => $mapping->item_id,
                    'item_name'           => $mapping->item?->item_name,
                    'external_product_id' => $mapping->external_product_id,
                    'deactivated_by'      => $user->name,
                ],
                'created_at'      => now(),
            ]);

            // Audit in Branch PosLog
            PosLog::create([
                'branch_id'   => $mapping->branch_id,
                'user_id'     => $user->id,
                'action_type' => 'ecommerce_mapping_deactivate',
                'log'         => "Manually deactivated ecommerce mapping #{$mapping->id} for item: " . ($mapping->item?->item_name ?? $mapping->item_id) . " from Organization Portal",
                'details'     => [
                    'mapping_id'          => $mapping->id,
                    'branch_id'           => $mapping->branch_id,
                    'item_id'             => $mapping->item_id,
                    'item_name'           => $mapping->item?->item_name,
                    'external_product_id' => $mapping->external_product_id,
                    'actor_portal'        => 'organization_portal',
                    'deactivated_by'      => $user->name,
                ],
                'created_at'  => now(),
            ]);

            return back()->with('success', "Item mapping for '" . ($mapping->item?->item_name ?? $mapping->external_product_id) . "' has been deactivated.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to deactivate mapping: ' . $e->getMessage());
        }
    }

    /**
     * Export aggregated organization ecommerce activity data to Excel (.xlsx) or CSV (.csv).
     */
    public function export(Request $request): StreamedResponse
    {
        $org = $this->resolveOrganization($request);
        if (!$org) {
            abort(404, 'No organization found.');
        }

        $allBranchIds = Branch::where('organization_id', $org->id)->pluck('id')->toArray();
        $selectedBranchId = $request->input('branch_id');

        if ($selectedBranchId && in_array((int) $selectedBranchId, $allBranchIds, true)) {
            $targetBranchIds = [(int) $selectedBranchId];
        } else {
            $targetBranchIds = $allBranchIds;
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
                'Branch',
                'Order Reference',
                'Status',
                'Reserved Items',
                'Total Qty',
                'Created At',
                'Expires At',
                'Resolved At',
            ];

            $records = $this->buildReservationsQuery($request, $targetBranchIds, $now)->latest()->get();

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
                $sheet->setCellValue([2, $rowNum], $res->branch?->name ?? 'Unknown Branch');
                $sheet->setCellValue([3, $rowNum], $res->external_order_reference);
                $sheet->setCellValue([4, $rowNum], $statusLabel);
                $sheet->setCellValue([5, $rowNum], $itemsSummary);
                $sheet->setCellValue([6, $rowNum], (int) $totalQty);
                $sheet->setCellValue([7, $rowNum], $res->created_at ? $res->created_at->format('Y-m-d H:i:s') : '-');
                $sheet->setCellValue([8, $rowNum], $res->expires_at ? $res->expires_at->format('Y-m-d H:i:s') : '-');
                $sheet->setCellValue([9, $rowNum], $resolvedAt);

                $rowNum++;
            }
        } elseif ($tab === 'movements') {
            $sheet->setTitle('Inventory Movements');
            $headers = [
                'SN',
                'Branch',
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

            $records = $this->buildMovementsQuery($request, $targetBranchIds)->latest()->get();

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
                $sheet->setCellValue([2, $rowNum], $m->branch?->name ?? 'Unknown Branch');
                $sheet->setCellValue([3, $rowNum], $m->created_at ? $m->created_at->format('Y-m-d H:i:s') : '-');
                $sheet->setCellValue([4, $rowNum], $typeLabel);
                $sheet->setCellValue([5, $rowNum], $m->item?->item_name ?? ('Item #' . $m->item_id));
                $sheet->setCellValue([6, $rowNum], $m->item?->barcode_number ?? '-');
                $sheet->setCellValue([7, $rowNum], (int) $m->quantity);
                $sheet->setCellValue([8, $rowNum], $m->previous_stock !== null ? (int) $m->previous_stock : '-');
                $sheet->setCellValue([9, $rowNum], $m->current_stock !== null ? (int) $m->current_stock : '-');
                $sheet->setCellValue([10, $rowNum], $m->external_reference ?? '-');
                $sheet->setCellValue([11, $rowNum], $m->notes ?? '-');

                $rowNum++;
            }
        } else {
            // Mappings
            $sheet->setTitle('Catalog Mappings');
            $headers = [
                'SN',
                'Branch',
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

            $records = $this->buildMappingsQuery($request, $targetBranchIds)->latest('updated_at')->get();

            $rowNum = 2;
            foreach ($records as $idx => $map) {
                $sheet->setCellValue([1, $rowNum], $idx + 1);
                $sheet->setCellValue([2, $rowNum], $map->branch?->name ?? 'Unknown Branch');
                $sheet->setCellValue([3, $rowNum], $map->item?->item_name ?? ('Item #' . $map->item_id));
                $sheet->setCellValue([4, $rowNum], $map->item?->barcode_number ?? '-');
                $sheet->setCellValue([5, $rowNum], $map->item?->price ? (float) $map->item->price : 0);
                $sheet->setCellValue([6, $rowNum], $map->item?->front_store_qty ? (int) $map->item->front_store_qty : 0);
                $sheet->setCellValue([7, $rowNum], $map->external_product_id);
                $sheet->setCellValue([8, $rowNum], $map->external_variant_id ?? 'default');
                $sheet->setCellValue([9, $rowNum], $map->external_sku ?? '-');
                $sheet->setCellValue([10, $rowNum], $map->is_active ? 'Live Online' : 'Deactivated');
                $sheet->setCellValue([11, $rowNum], $map->registered_at ? $map->registered_at->format('Y-m-d H:i:s') : ($map->created_at ? $map->created_at->format('Y-m-d H:i:s') : '-'));
                $sheet->setCellValue([12, $rowNum], $map->last_seen_at ? $map->last_seen_at->format('Y-m-d H:i:s') : ($map->last_synced_at ? $map->last_synced_at->format('Y-m-d H:i:s') : '-'));

                $rowNum++;
            }
        }

        // Write Headers
        foreach ($headers as $colIdx => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
        }

        // Header Styling (Purple matching Org Portal)
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = "A1:{$lastCol}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('7C3AED'); // Purple 600

        // Auto-fit columns
        for ($col = 1; $col <= count($headers); $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $filename = "org_ecommerce_{$tab}_" . date('Y-m-d_His') . '.' . $format;

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

    protected function buildReservationsQuery(Request $request, array $targetBranchIds, Carbon $now)
    {
        $q = CommerceReservation::whereIn('branch_id', $targetBranchIds)
            ->with([
                'items.item:id,item_name,barcode_number',
                'branch:id,name,slug',
                'integration:id,platform,name',
            ]);

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
            if ($status === 'active') {
                $q->where('status', 'pending')->where('expires_at', '>', $now);
            } elseif ($status === 'expired') {
                $q->where('status', 'pending')->where('expires_at', '<=', $now);
            } elseif (in_array($status, ['confirmed', 'released'])) {
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

    protected function buildMovementsQuery(Request $request, array $targetBranchIds)
    {
        $q = CommerceInventoryMovement::whereIn('branch_id', $targetBranchIds)
            ->with([
                'item:id,item_name,barcode_number',
                'branch:id,name,slug',
                'integration:id,platform,name',
            ]);

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

    protected function buildMappingsQuery(Request $request, array $targetBranchIds)
    {
        $q = CommerceItemMapping::whereIn('branch_id', $targetBranchIds)
            ->with([
                'item:id,item_name,barcode_number,price,front_store_qty',
                'branch:id,name,slug',
                'integration:id,platform,name',
            ]);

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
