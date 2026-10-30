<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSettings extends Model
{
    protected $table = 'pos_settings';

    public $timestamps = false;

    const UPDATED_AT = 'updated_at';

    protected $attributes = [
        'is_camera_scanner_enabled' => true,
    ];

    protected $fillable = [
        'branch_id',
        'is_price_editable', 'is_qty_deduction', 'is_offline_qty_deduction', 'out_of_stock',
        'is_check_expiration', 'batch_tracking_enabled', 'expiry_alert_days', 'is_imei_enabled', 'is_camera_scanner_enabled', 'is_offline_enabled', 'require_till_opening', 'print_till_slip', 'held_sales_visibility', 'is_turnstile_enabled', 'offline_receipt_prefix',
        'is_show_buy_price', 'is_use_profit_percentage',
        'is_tax_enabled', 'tax_percentage',
        'is_accounting_vat_enabled', 'accounting_started_at',
        'item_icon_preview', 'is_grid_separate_rows', 'is_cart_auto_scroll_highlight', 'is_auto_fullscreen_enabled', 'wholesale_profit_percent', 'consumer_profit_percent',
        'sell_interface', 'quick_access_mode', 'pinned_quick_access_items', 'quick_access_category_ids', 'business_sector',
        'theme_color', 'custom_primary_hex', 'custom_secondary_hex', 'dark_mode_accent_mode', 'custom_dark_primary_hex',
        'laundry_enabled', 'pos_enabled', 'default_interface',
        'receipt_paper_size', 'receipt_printer_name', 'printer_type', 'printer_connection', 'printer_ip_address',
        'receipt_copies',
        'auto_print_receipt', 'show_receipt_preview',
    ];

    protected function casts(): array
    {
        return [
            'is_price_editable'             => 'boolean',
            'is_qty_deduction'              => 'boolean',
            'is_offline_qty_deduction'      => 'boolean',
            'is_check_expiration'           => 'boolean',
            'batch_tracking_enabled'        => 'boolean',
            'expiry_alert_days'             => 'integer',
            'is_imei_enabled'               => 'boolean',
            'is_camera_scanner_enabled'     => 'boolean',
            'is_offline_enabled'            => 'boolean',
            'laundry_enabled'               => 'boolean',
            'pos_enabled'                   => 'boolean',
            'require_till_opening'          => 'boolean',
            'print_till_slip'               => 'boolean',
            'is_turnstile_enabled'          => 'boolean',
            'is_auto_fullscreen_enabled'    => 'boolean',
            'is_show_buy_price'             => 'boolean',
            'is_use_profit_percentage'      => 'boolean',
            'is_tax_enabled'                => 'boolean',
            'tax_percentage'                => 'decimal:2',
            'is_accounting_vat_enabled'     => 'boolean',
            'accounting_started_at'         => 'datetime',
            'item_icon_preview'             => 'boolean',
            'is_grid_separate_rows'         => 'boolean',
            'is_cart_auto_scroll_highlight' => 'boolean',
            'wholesale_profit_percent'      => 'decimal:2',
            'consumer_profit_percent'       => 'decimal:2',
            'out_of_stock'                  => 'integer',
            'receipt_copies'                => 'integer',
            'auto_print_receipt'            => 'boolean',
            'show_receipt_preview'          => 'boolean',
            'pinned_quick_access_items'     => 'array',
            'quick_access_category_ids'     => 'array',
        ];
    }

    public function getPinnedQuickAccessItemsAttribute($value): array
    {
        if (is_array($value)) return $value;
        if (is_string($value)) return json_decode($value, true) ?: [];
        return [];
    }

    public function getQuickAccessCategoryIdsAttribute($value): array
    {
        if (is_array($value)) return $value;
        if (is_string($value)) return json_decode($value, true) ?: [];
        return [];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get settings for the currently-resolved branch.
     */
    public static function forBranch(int $branchId): static
    {
        return static::firstOrCreate(
            ['branch_id' => $branchId],
            [
                'sell_interface'             => 'classic',
                'quick_access_mode'          => 'top_sellers',
                'pinned_quick_access_items'   => [],
                'quick_access_category_ids'   => [],
                'business_sector'            => 'commerce',
                'laundry_enabled'            => false,
                'pos_enabled'                => true,
                'default_interface'          => 'pos',
                'out_of_stock'               => 25,
                'is_camera_scanner_enabled'  => true,
                'batch_tracking_enabled'     => false,
                'expiry_alert_days'          => 30,
                'is_offline_qty_deduction'   => false,
                'held_sales_visibility'      => 'device_only',
                'receipt_paper_size'         => '80mm',
                'receipt_printer_name'       => 'Default POS Printer',
                'printer_type'               => 'thermal_80mm',
                'printer_connection'         => 'kiosk_direct',
                'printer_ip_address'         => null,
                'receipt_copies'             => 1,
                'auto_print_receipt'         => false,
                'show_receipt_preview'       => true,
                'is_accounting_vat_enabled'  => false,
                'accounting_started_at'      => null,
            ]
        );
    }

    /**
     * Shorthand — reads branch from the request context.
     */
    public static function current(): static
    {
        $branch = current_branch();
        if (!$branch) {
            // Super Admin context or no branch — return a dummy in-memory settings object
            return new static([
                'is_price_editable'          => false,
                'is_qty_deduction'           => true,
                'is_offline_qty_deduction'   => false,
                'is_check_expiration'        => true,
                'is_show_buy_price'          => false,
                'is_camera_scanner_enabled'  => true,
                'out_of_stock'               => 25,
                'batch_tracking_enabled'     => false,
                'expiry_alert_days'          => 30,
                'held_sales_visibility'      => 'device_only',
                'sell_interface'             => 'classic',
                'quick_access_mode'          => 'top_sellers',
                'pinned_quick_access_items'   => [],
                'quick_access_category_ids'   => [],
                'business_sector'            => 'commerce',
                'laundry_enabled'            => false,
                'pos_enabled'                => true,
                'default_interface'          => 'pos',
                'receipt_paper_size'         => '80mm',
                'receipt_printer_name'       => 'Default POS Printer',
                'printer_type'               => 'thermal_80mm',
                'printer_connection'         => 'kiosk_direct',
                'printer_ip_address'         => null,
                'auto_print_receipt'         => false,
                'show_receipt_preview'       => true,
                'is_accounting_vat_enabled'  => false,
                'accounting_started_at'      => null,
            ]);
        }
        return static::forBranch($branch->id);
    }
}
