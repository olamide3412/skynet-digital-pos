<?php

namespace App\Modules\CommerceIntegration\Models;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceReservationItem extends Model
{
    use HasFactory;

    protected $table = 'commerce_reservation_items';

    protected $fillable = [
        'commerce_reservation_id',
        'item_id',
        'quantity',
        'unit_price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(CommerceReservation::class, 'commerce_reservation_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
