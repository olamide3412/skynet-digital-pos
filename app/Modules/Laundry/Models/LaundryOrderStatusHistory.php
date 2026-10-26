<?php

namespace App\Modules\Laundry\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaundryOrderStatusHistory extends Model
{
    protected $table = 'laundry_order_status_history';

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'changed_by',
        'changed_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(LaundryOrder::class, 'order_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
