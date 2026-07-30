<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'name', 'slug', 'url_key', 'product_number',
        'sku', 'description',
        'price', 'cost_price', 'discount_price',
        'special_price', 'special_price_from', 'special_price_to',
        'stock', 'image', 'images', 'variants', 'attributes', 'related_products', 'status',
        'is_new', 'is_featured', 'visible_individually', 'show_stock_level',
        'short_description',
    ];

    protected $casts = [
        'images'             => 'array',
        'variants'           => 'array',
        'attributes'         => 'array',
        'related_products'   => 'array',
        'status'             => 'boolean',
        'is_new'             => 'boolean',
        'is_featured'        => 'boolean',
        'visible_individually' => 'boolean',
        'show_stock_level'   => 'boolean',
        'special_price_from' => 'datetime',
        'special_price_to'   => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the effective current price (respects special price window).
     */
    public function getEffectivePriceAttribute(): float
    {
        if ($this->special_price &&
            (!$this->special_price_from || now()->gte($this->special_price_from)) &&
            (!$this->special_price_to   || now()->lte($this->special_price_to))) {
            return (float) $this->special_price;
        }
        return $this->discount_price ? (float) $this->discount_price : (float) $this->price;
    }
}
