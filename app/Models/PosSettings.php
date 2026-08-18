<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSettings extends Model
{
    protected $table = 'pos_settings';

    public $timestamps = false;

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'branch_id',
        'is_price_editable', 'is_qty_deduction', 'out_of_stock',
        'is_check_expiration', 'is_imei_enabled', 'is_offline_enabled', 'offline_receipt_prefix',
        'is_show_buy_price', 'is_use_profit_percentage',
        'is_tax_enabled', 'tax_percentage',
        'item_icon_preview', 'wholesale_profit_percent', 'consumer_profit_percent',
        'sell_interface', 'business_sector',
        'receipt_paper_size', 'receipt_printer_name', 'printer_type', 'printer_connection', 'printer_ip_address',
        'receipt_copies',
        'auto_print_receipt', 'show_receipt_preview',
    ];

    protected function casts(): array
    {
        return [
            'is_price_editable'        => 'boolean',
            'is_qty_deduction'         => 'boolean',
            'is_check_expiration'      => 'boolean',
            'is_imei_enabled'          => 'boolean',
            'is_offline_enabled'       => 'boolean',
            'is_show_buy_price'        => 'boolean',
            'is_use_profit_percentage' => 'boolean',
            'is_tax_enabled'           => 'boolean',
            'tax_percentage'           => 'decimal:2',
            'item_icon_preview'        => 'boolean',
            'wholesale_profit_percent' => 'decimal:2',
            'consumer_profit_percent'  => 'decimal:2',
            'out_of_stock'             => 'integer',
            'receipt_copies'           => 'integer',
            'auto_print_receipt'       => 'boolean',
            'show_receipt_preview'     => 'boolean',
        ];
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
                'sell_interface'       => 'classic',
                'business_sector'      => 'commerce',
                'out_of_stock'         => 25,
                'receipt_paper_size'   => '80mm',
                'receipt_printer_name' => 'Default POS Printer',
                'printer_type'         => 'thermal_80mm',
                'printer_connection'   => 'kiosk_direct',
                'printer_ip_address'   => null,
                'receipt_copies'       => 1,
                'auto_print_receipt'   => false,
                'show_receipt_preview' => true,
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
                'is_price_editable'    => false,
                'is_qty_deduction'     => true,
                'is_check_expiration'  => true,
                'is_show_buy_price'    => false,
                'out_of_stock'         => 25,
                'sell_interface'       => 'classic',
                'business_sector'      => 'commerce',
                'receipt_paper_size'   => '80mm',
                'receipt_printer_name' => 'Default POS Printer',
                'printer_type'         => 'thermal_80mm',
                'printer_connection'   => 'kiosk_direct',
                'printer_ip_address'   => null,
                'auto_print_receipt'   => false,
                'show_receipt_preview' => true,
            ]);
        }
        return static::forBranch($branch->id);
    }
}
