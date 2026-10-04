<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TillCashMovement extends Model
{
    use HasFactory;

    protected $table = 'till_cash_movements';

    protected $fillable = [
        'till_session_id',
        'type', // 'add' | 'remove'
        'amount',
        'reason',
        'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function tillSession(): BelongsTo
    {
        return $this->belongsTo(TillSession::class, 'till_session_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
