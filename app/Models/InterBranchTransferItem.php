<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterBranchTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'inter_branch_transfer_id',
        'product_group_id',
        'source_item_id',
        'destination_item_id',
        'quantity_sent',
        'quantity_received',
        'shortfall_quantity',
        'sender_cost_price',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_sent'      => 'integer',
            'quantity_received'  => 'integer',
            'shortfall_quantity' => 'integer',
            'sender_cost_price'  => 'decimal:2',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(InterBranchTransfer::class, 'inter_branch_transfer_id');
    }

    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class);
    }

    public function sourceItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'source_item_id');
    }

    public function destinationItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'destination_item_id');
    }
}
