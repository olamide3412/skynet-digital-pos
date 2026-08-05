<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'branch_id', 'customer_id', 'receipt_id', 'items_order_count', 'consultation_fee',
        'payment_method', 'bank_transfer', 'cash', 'amount_cost', 'amount_paid',
        'change_bal', 'purchase_type', 'profit_made', 'sale_discount_id',
        'discount_amount', 'tax_amount', 'tax_percentage', 'final_total', 'is_debt', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_debt'           => 'boolean',
            'amount_cost'       => 'decimal:2',
            'amount_paid'       => 'decimal:2',
            'change_bal'        => 'decimal:2',
            'profit_made'       => 'decimal:2',
            'discount_amount'   => 'decimal:2',
            'tax_amount'        => 'decimal:2',
            'tax_percentage'    => 'decimal:2',
            'final_total'       => 'decimal:2',
            'consultation_fee'  => 'decimal:2',
            'bank_transfer'     => 'decimal:2',
            'cash'              => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PosCustomer::class, 'customer_id');
    }

    public function saleDiscount(): BelongsTo
    {
        return $this->belongsTo(SaleDiscount::class);
    }

    public function saleOrders(): HasMany
    {
        return $this->hasMany(SaleOrder::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
