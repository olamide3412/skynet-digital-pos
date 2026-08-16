<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarcodePrintLog extends Model
{
    protected $fillable = [
        'branch_id',
        'item_id',
        'item_name',
        'barcode_value',
        'label_size',
        'quantity_printed',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity_printed' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
