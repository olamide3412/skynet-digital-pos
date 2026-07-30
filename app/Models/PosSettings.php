<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosSettings extends Model
{
    protected $table = 'pos_settings';

    public $timestamps = false;

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'is_price_editable', 'is_qty_deduction', 'out_of_stock',
        'is_check_expiration', 'is_show_buy_price', 'business_name',
        'business_address', 'business_contact_number', 'business_email',
        'item_icon_preview', 'wholesale_profit_percent', 'consumer_profit_percent',
        'sell_interface', 'business_sector',
    ];

    protected function casts(): array
    {
        return [
            'is_price_editable'        => 'boolean',
            'is_qty_deduction'         => 'boolean',
            'is_check_expiration'      => 'boolean',
            'is_show_buy_price'        => 'boolean',
            'item_icon_preview'        => 'boolean',
            'wholesale_profit_percent' => 'decimal:2',
            'consumer_profit_percent'  => 'decimal:2',
            'out_of_stock'             => 'integer',
        ];
    }

    /**
     * Get the single settings row (creates default if missing).
     */
    public static function current(): static
    {
        return static::firstOrCreate([], [
            'business_name'     => 'SkyNet POS',
            'sell_interface'    => 'classic',
            'business_sector'   => 'commerce',
            'out_of_stock'      => 25,
        ]);
    }
}
