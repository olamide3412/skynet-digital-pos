<?php

namespace App\Modules\CommerceIntegration\Services;

use App\Models\Branch;
use App\Models\Item;
use App\Modules\CommerceIntegration\Models\CommerceIntegration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CommerceWebhookDispatcher
{
    /**
     * Whether outbound webhooks are suppressed for the current execution context.
     */
    protected static bool $suppressed = false;

    /**
     * In-memory registry of items deferred for non-blocking dispatch after HTTP response.
     * Structured as: [branchId => [itemId => action]]
     */
    protected static array $deferredItems = [];

    /**
     * Whether the application terminating listener has been registered.
     */
    protected static bool $terminatingRegistered = false;

    /**
     * Suppress all outbound webhook dispatches.
     */
    public static function suppress(): void
    {
        static::$suppressed = true;
    }

    /**
     * Resume outbound webhook dispatches.
     */
    public static function resume(): void
    {
        static::$suppressed = false;
    }

    /**
     * Check if webhook dispatches are currently suppressed.
     */
    public static function isSuppressed(): bool
    {
        return static::$suppressed;
    }

    /**
     * Execute a callable while strictly suppressing outbound ecommerce webhooks.
     * Prevents circular HTTP deadlocks during API calls originating from Ecommerce.
     */
    public static function withoutWebhooks(callable $callback): mixed
    {
        $wasSuppressed = static::$suppressed;
        static::$suppressed = true;
        try {
            return $callback();
        } finally {
            static::$suppressed = $wasSuppressed;
        }
    }

    /**
     * Helper to schedule an inventory or price update for a POS item.
     * Non-blocking: defers actual HTTP delivery until after the HTTP response has been sent to client.
     */
    public static function dispatchItemUpdated(Item $item, string $action = 'inventory.updated'): void
    {
        if (static::$suppressed || empty($item->branch_id)) {
            return;
        }

        $branchId = (int) $item->branch_id;
        $itemId = (int) $item->id;

        if (!isset(static::$deferredItems[$branchId])) {
            static::$deferredItems[$branchId] = [];
        }

        // Deduplicate updates for the same item within the same request lifecycle
        static::$deferredItems[$branchId][$itemId] = $action;

        // Register post-response terminating callback once
        static::registerTerminatingCallback();
    }

    /**
     * Register a callback with the application to flush deferred webhooks after HTTP response.
     */
    protected static function registerTerminatingCallback(): void
    {
        if (static::$terminatingRegistered) {
            return;
        }

        static::$terminatingRegistered = true;

        try {
            if (function_exists('app') && app()->bound('events')) {
                app()->terminating(function () {
                    static::flushDeferred();
                });
                return;
            }
        } catch (\Throwable) {
            // Fall through to shutdown function fallback
        }

        register_shutdown_function(function () {
            static::flushDeferred();
        });
    }

    /**
     * Flush and dispatch all deferred item inventory webhooks.
     * Executes after the HTTP response has been sent to the client.
     */
    public static function flushDeferred(): void
    {
        if (empty(static::$deferredItems) || static::$suppressed) {
            static::$deferredItems = [];
            return;
        }

        $pending = static::$deferredItems;
        static::$deferredItems = [];

        foreach ($pending as $branchId => $items) {
            foreach ($items as $itemId => $action) {
                try {
                    $item = Item::find($itemId);
                    if (!$item) {
                        continue;
                    }

                    // Calculate current available stock for ecommerce (front store qty minus active holds)
                    $holds = (int) DB::table('commerce_reservation_items')
                        ->join('commerce_reservations', 'commerce_reservation_items.commerce_reservation_id', '=', 'commerce_reservations.id')
                        ->where('commerce_reservation_items.item_id', $item->id)
                        ->where('commerce_reservations.status', 'pending')
                        ->where('commerce_reservations.expires_at', '>', now())
                        ->sum('commerce_reservation_items.quantity');

                    $availableStock = max(0, ((int) ($item->front_store_qty ?? 0)) - $holds);

                    static::dispatch($branchId, $action, [
                        'pos_item_id' => $item->id,
                        'item_name' => $item->item_name,
                        'barcode_number' => $item->barcode_number,
                        'pos_category_id' => $item->category_id,
                        'pos_category_name' => $item->category?->name,
                        'price' => (float) $item->price,
                        'buy_price' => (float) ($item->buy_price ?? 0),
                        'available_qty' => $availableStock,
                        'front_store_qty' => (int) ($item->front_store_qty ?? 0),
                        'active_holds' => $holds,
                        'is_active' => true,
                        'updated_at' => now()->toIso8601String(),
                    ]);
                } catch (\Throwable $e) {
                    Log::warning("Deferred webhook dispatch failed for item {$itemId} in branch {$branchId}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Dispatch an event to the configured webhook URL for a branch integration.
     */
    public static function dispatch(int|Branch $branch, string $event, array $data = []): bool
    {
        if (static::$suppressed) {
            return false;
        }

        $branchId = $branch instanceof Branch ? $branch->id : $branch;

        $integration = CommerceIntegration::where('branch_id', $branchId)
            ->where('status', 'active')
            ->whereNotNull('webhook_url')
            ->first();

        if (!$integration || empty($integration->webhook_url)) {
            return false;
        }

        $payload = [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'branch_id' => $branchId,
            'data' => $data,
        ];

        try {
            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'SkynetPOS-Webhook/1.0',
                'X-Skynet-Event' => $event,
                'X-Branch-Id' => (string) $branchId,
            ];

            if (!empty($integration->webhook_secret)) {
                $headers['Authorization']      = 'Bearer ' . $integration->webhook_secret;
                $headers['X-Commerce-Key']     = $integration->webhook_secret;
                $signature = hash_hmac('sha256', json_encode($payload), $integration->webhook_secret);
                $headers['X-Skynet-Signature'] = 'sha256=' . $signature;
            }

            // Timeout (5s) for outbound webhook deliveries
            $timeout = (int) config('commerce.webhooks.timeout_seconds', 5);

            $response = Http::timeout($timeout)
                ->withHeaders($headers)
                ->post($integration->webhook_url, $payload);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning("Commerce webhook dispatch failed for branch {$branchId}: " . $e->getMessage(), [
                'event' => $event,
                'webhook_url' => $integration->webhook_url,
            ]);
            return false;
        }
    }
}
