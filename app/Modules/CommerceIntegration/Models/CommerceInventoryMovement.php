<?php

namespace App\Modules\CommerceIntegration\Models;

use App\Models\Branch;
use App\Models\Item;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceInventoryMovement extends Model
{
    use HasFactory;

    protected $table = 'commerce_inventory_movements';

    // Movement types
    public const TYPE_RESERVATION_HOLD = 'reservation_hold';
    public const TYPE_RESERVATION_RELEASE = 'reservation_release';
    public const TYPE_RESERVATION_CONFIRM = 'reservation_confirm';
    public const TYPE_STOCK_DEDUCTION = 'stock_deduction';
    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'commerce_integration_id',
        'organization_id',
        'branch_id',
        'item_id',
        'commerce_reservation_id',
        'type',
        'quantity',
        'previous_stock',
        'current_stock',
        'external_reference',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'previous_stock' => 'integer',
        'current_stock' => 'integer',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(CommerceIntegration::class, 'commerce_integration_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(CommerceReservation::class, 'commerce_reservation_id');
    }
}
