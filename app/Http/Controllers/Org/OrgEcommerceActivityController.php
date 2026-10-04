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

            $reservations = $q->latest()->paginate(15)->withQueryString();
        } elseif ($tab === 'movements') {
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

            $movements = $q->latest()->paginate(15)->withQueryString();
        } elseif ($tab === 'mappings') {
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

            $mappings = $q->latest('updated_at')->paginate(15)->withQueryString();
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
}
