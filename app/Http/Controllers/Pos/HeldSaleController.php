<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\HeldSale;
use App\Models\HeldSaleItem;
use App\Models\PosSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HeldSaleController extends Controller
{
    /** API: list held carts respecting branch visibility setting */
    public function apiIndex(Request $request)
    {
        $branch = current_branch();
        $settings = $branch ? PosSettings::forBranch($branch->id) : null;
        $isShared = ($settings?->held_sales_visibility === 'shared_across_branch');

        $query = HeldSale::with(['items.item', 'customer', 'user'])
            ->where('status', 'Held');

        if ($branch) {
            $query->where('branch_id', $branch->id);
            if (!$isShared) {
                $query->where('user_id', Auth::id());
            }
        } else {
            $query->where('user_id', Auth::id());
        }

        $heldSales = $query->latest()->get()->map(function ($held) {
            return [
                'id'             => $held->id,
                'held_sale_id'   => $held->held_sale_uuid ?: (string) $held->id,
                'held_sale_uuid' => $held->held_sale_uuid,
                'hold_name'      => $held->hold_name,
                'user_id'        => $held->user_id,
                'cashier_name'   => $held->cashier_display_name,
                'user'           => $held->user ? [
                    'id'        => $held->user->id,
                    'name'      => $held->user->name,
                    'full_name' => $held->user->full_name ?? $held->user->name,
                ] : null,
                'customer_id'    => $held->customer_id,
                'customer'       => $held->customer ? [
                    'id'    => $held->customer->id,
                    'name'  => $held->customer->name,
                    'phone' => $held->customer->phone,
                ] : null,
                'created_at'     => $held->created_at?->toIso8601String(),
                'status'         => $held->status,
                'items'          => $held->items->map(function ($item) {
                    return [
                        'id'                => $item->id,
                        'item_id'           => $item->item_id,
                        'item_name'         => $item->item_name ?: $item->item?->item_name,
                        'qty'               => (int) $item->qty,
                        'price'             => (float) $item->price,
                        'unit_price'        => (float) $item->price,
                        'unit_used'         => $item->unit_used ?: 'unit',
                        'purchase_type'     => $item->purchase_type ?: 'Consumer',
                        'imei_or_device_id' => $item->imei_or_device_id,
                        'item'              => $item->item,
                    ];
                })->values()->all(),
            ];
        });

        return response()->json($heldSales);
    }

    /** API: save (hold) cart */
    public function apiStore(Request $request, $branchParam = null)
    {
        $branch = current_branch();
        if (!$branch && $branchParam) {
            $branch = $branchParam instanceof \App\Models\Branch ? $branchParam : \App\Models\Branch::where('slug', (string) $branchParam)->first();
        }
        if (!$branch) {
            $branch = Auth::user()?->branch;
        }
        $branchId = $branch?->id;

        $data = $request->validate([
            'held_sale_id'              => 'nullable|string|max:100',
            'held_sale_uuid'            => 'nullable|string|max:100',
            'hold_name'                 => 'nullable|string|max:255',
            'user_id'                   => 'nullable|integer',
            'cashier_name'              => 'nullable|string|max:150',
            'customer_id'               => [
                'nullable',
                'integer',
                $branchId
                    ? Rule::exists('pos_customers', 'id')->where('branch_id', $branchId)
                    : 'exists:pos_customers,id',
            ],
            'items'                     => 'required|array|min:1',
            'items.*.item_id'           => 'required|integer|exists:items,id',
            'items.*.qty'               => 'required|integer|min:1',
            'items.*.price'             => 'required|numeric|min:0',
            'items.*.unit_used'         => 'nullable|string|max:100',
            'items.*.item_name'         => 'nullable|string',
            'items.*.purchase_type'     => 'nullable|in:Wholesale,Consumer',
            'items.*.imei_or_device_id' => 'nullable|string|max:100',
        ]);

        $heldSaleUuid = $data['held_sale_uuid'] ?? $data['held_sale_id'] ?? (string) Str::uuid();

        $held = DB::transaction(function () use ($data, $branch, $heldSaleUuid) {
            $authUser = Auth::user();
            $cashierName = $data['cashier_name'] ?? ($authUser?->full_name ?: ($authUser?->name ?: 'Staff'));
            $userId = $data['user_id'] ?? Auth::id() ?: 1;

            $heldSale = HeldSale::where('held_sale_uuid', $heldSaleUuid)->first();

            if ($heldSale) {
                $heldSale->update([
                    'hold_name'    => $data['hold_name'] ?? $heldSale->hold_name,
                    'status'       => 'Held',
                    'customer_id'  => $data['customer_id'] ?? null,
                    'cashier_name' => $cashierName,
                ]);
                $heldSale->items()->delete();
            } else {
                $heldSale = HeldSale::create([
                    'branch_id'      => $branch?->id,
                    'held_sale_uuid' => $heldSaleUuid,
                    'user_id'        => $userId,
                    'cashier_name'   => $cashierName,
                    'hold_name'      => $data['hold_name'] ?? null,
                    'status'         => 'Held',
                    'customer_id'    => $data['customer_id'] ?? null,
                ]);
            }

            foreach ($data['items'] as $item) {
                HeldSaleItem::create([
                    'held_sale_id'      => $heldSale->id,
                    'item_id'           => $item['item_id'],
                    'qty'               => $item['qty'],
                    'price'             => $item['price'],
                    'unit_used'         => $item['unit_used'] ?? 'unit',
                    'item_name'         => $item['item_name'] ?? null,
                    'purchase_type'     => $item['purchase_type'] ?? 'Consumer',
                    'imei_or_device_id' => $item['imei_or_device_id'] ?? null,
                ]);
            }

            return $heldSale->load(['items.item', 'customer', 'user']);
        });

        return response()->json([
            'id'             => $held->id,
            'held_sale_id'   => $held->held_sale_uuid ?: (string) $held->id,
            'held_sale_uuid' => $held->held_sale_uuid,
            'hold_name'      => $held->hold_name,
            'user_id'        => $held->user_id,
            'cashier_name'   => $held->cashier_display_name,
            'user'           => $held->user ? [
                'id'        => $held->user->id,
                'name'      => $held->user->name,
                'full_name' => $held->user->full_name ?? $held->user->name,
            ] : null,
            'customer_id'    => $held->customer_id,
            'customer'       => $held->customer,
            'items'          => $held->items,
            'status'         => $held->status,
        ], 201);
    }

    /** API: delete (discard) a held cart */
    public function apiDestroy($branchParam, $id = null)
    {
        $targetId = $id ?? $branchParam;
        $branch = current_branch();

        $query = HeldSale::query();
        if (is_numeric($targetId)) {
            $query->where(function ($q) use ($targetId) {
                $q->where('id', $targetId)->orWhere('held_sale_uuid', (string) $targetId);
            });
        } else {
            $query->where('held_sale_uuid', (string) $targetId);
        }

        if ($branch) {
            $query->where('branch_id', $branch->id);
        }

        $held = $query->first();
        if ($held) {
            $held->delete();
        }

        return response()->json(['message' => 'Held sale discarded.']);
    }

    /** API: batch sync held sales pushed from client */
    public function apiSyncBatch(Request $request, $branchParam = null)
    {
        $branch = current_branch();
        $data = $request->validate([
            'held_sales' => 'nullable|array',
            'sales'      => 'nullable|array',
        ]);

        $holds = $data['held_sales'] ?? $data['sales'] ?? [];
        $results = [];

        foreach ($holds as $holdData) {
            $uuid = $holdData['held_sale_id'] ?? $holdData['held_sale_uuid'] ?? null;
            if (!$uuid) continue;

            try {
                $status = $holdData['status'] ?? 'Held';
                if ($status === 'discarded' || $status === 'resumed') {
                    $existing = HeldSale::where('held_sale_uuid', $uuid)->first();
                    if ($existing) {
                        $existing->delete();
                    }
                    $results[] = ['held_sale_id' => $uuid, 'status' => 'synced'];
                    continue;
                }

                $targetUserId = $holdData['user_id'] ?? $holdData['cashier_id'] ?? null;
                $resolvedUserId = ($targetUserId && \App\Models\User::where('id', $targetUserId)->exists())
                    ? (int) $targetUserId
                    : (Auth::id() ?: 1);

                $cashierName = $holdData['cashier_name']
                    ?? (Auth::user()?->full_name ?: (Auth::user()?->name ?: null));

                if (!$cashierName && $resolvedUserId) {
                    $userRecord = \App\Models\User::find($resolvedUserId);
                    $cashierName = $userRecord?->full_name ?: ($userRecord?->name ?: 'Staff');
                }

                $targetCustomerId = $holdData['customer_id'] ?? null;
                if ($targetCustomerId && $branch && !\App\Models\PosCustomer::where('id', $targetCustomerId)->where('branch_id', $branch->id)->exists()) {
                    $targetCustomerId = null;
                }

                $heldSale = HeldSale::where('held_sale_uuid', $uuid)->first();
                if ($heldSale) {
                    $heldSale->update([
                        'hold_name'    => $holdData['hold_name'] ?? $heldSale->hold_name,
                        'status'       => 'Held',
                        'customer_id'  => $targetCustomerId ?? $heldSale->customer_id,
                        'cashier_name' => $cashierName ?: $heldSale->cashier_name,
                    ]);
                    $heldSale->items()->delete();
                } else {
                    $heldSale = HeldSale::create([
                        'branch_id'      => $branch?->id,
                        'held_sale_uuid' => $uuid,
                        'user_id'        => $resolvedUserId,
                        'cashier_name'   => $cashierName ?: 'Staff',
                        'hold_name'      => $holdData['hold_name'] ?? null,
                        'status'         => 'Held',
                        'customer_id'    => $targetCustomerId,
                        'created_at'     => !empty($holdData['created_at']) ? date('Y-m-d H:i:s', strtotime($holdData['created_at'])) : now(),
                    ]);
                }

                if (!empty($holdData['items']) && is_array($holdData['items'])) {
                    foreach ($holdData['items'] as $item) {
                        HeldSaleItem::create([
                            'held_sale_id'      => $heldSale->id,
                            'item_id'           => $item['item_id'],
                            'qty'               => (int) ($item['qty'] ?? 1),
                            'price'             => (float) ($item['price'] ?? ($item['unit_price'] ?? 0)),
                            'unit_used'         => $item['unit_used'] ?? 'unit',
                            'item_name'         => $item['item_name'] ?? null,
                            'purchase_type'     => $item['purchase_type'] ?? 'Consumer',
                            'imei_or_device_id' => $item['imei_or_device_id'] ?? null,
                        ]);
                    }
                }

                $results[] = ['held_sale_id' => $uuid, 'status' => 'synced'];
            } catch (\Throwable $e) {
                $results[] = ['held_sale_id' => $uuid, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'message' => 'Held sales batch synced.',
            'results' => $results,
        ]);
    }
}

