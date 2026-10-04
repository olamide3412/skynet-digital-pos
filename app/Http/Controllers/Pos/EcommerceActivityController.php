<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
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

class EcommerceActivityController extends Controller
{
    /**
     * Display branch ecommerce activity dashboard (reservations, movements, mappings).
     */
    public function index(Request $request)
    {
        $branch = current_branch();
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
            $q = CommerceReservation::where('branch_id', $branch->id)
                ->with(['items.item', 'integration']);

            if ($search = trim((string) $request->input('search'))) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('external_order_reference', 'like', "%{$search}%")
                        ->orWhereHas('items.item', function ($iq) use ($search) {
                            $iq->where('name', 'like', "%{$search}%")
                               ->orWhere('barcode', 'like', "%{$search}%");
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

            $reservations = $q->latest()->paginate(15)->withQueryString();
        } elseif ($tab === 'movements') {
            $q = CommerceInventoryMovement::where('branch_id', $branch->id)
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

            $movements = $q->latest()->paginate(15)->withQueryString();
        } elseif ($tab === 'mappings') {
            $q = CommerceItemMapping::where('branch_id', $branch->id)
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

            $mappings = $q->latest('updated_at')->paginate(15)->withQueryString();
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
}
