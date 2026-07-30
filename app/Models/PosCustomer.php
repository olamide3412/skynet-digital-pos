<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosCustomer extends Model
{
    protected $table = 'pos_customers';

    protected $fillable = [
        'name', 'phone', 'address', 'gender', 'dob', 'note',
        'debt_bal', 'contact_name', 'contact_phone', 'contact_address', 'watch_list',
    ];

    protected function casts(): array
    {
        return [
            'watch_list' => 'boolean',
            'debt_bal'   => 'decimal:2',
            'dob'        => 'date',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'customer_id');
    }

    public function debtPayments(): HasMany
    {
        return $this->hasMany(DebtPayment::class, 'customer_id');
    }

    public function heldSales(): HasMany
    {
        return $this->hasMany(HeldSale::class, 'customer_id');
    }
}
