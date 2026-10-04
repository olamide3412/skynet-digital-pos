<?php

namespace App\Modules\CommerceIntegration\Models;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceItemMapping extends Model
{
    use HasFactory;

    protected $table = 'commerce_item_mappings';

    public const STATUS_SYNCED = 'synced';
    public const STATUS_PENDING = 'pending';
    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'commerce_integration_id',
        'branch_id',
        'item_id',
        'external_product_id',
        'external_variant_id',
        'external_sku',
        'is_active',
        'sync_status',
        'registered_at',
        'last_seen_at',
        'unregistered_at',
        'last_synced_at',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'registered_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'unregistered_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(CommerceIntegration::class, 'commerce_integration_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Branch::class, 'branch_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
