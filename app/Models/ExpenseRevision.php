<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseRevision extends Model
{
    use HasFactory;

    protected $table = 'expense_revisions';

    protected $fillable = [
        'expense_id',
        'user_id',
        'action', // 'created', 'updated', 'deleted'
        'changes',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
