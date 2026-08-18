<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockConflict extends Model
{
    protected $fillable = [
        'branch_id',
        'sale_id',
        'offline_sale_id',
        'item_id',
        'item_name',
        'conflict_type',
        'requested_qty',
        'available_qty_at_sync',
        'imei_or_device_id',
        'status',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_qty'         => 'integer',
            'available_qty_at_sync' => 'integer',
            'resolved_at'           => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending_review');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }
}
