<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiCreditUsage extends Model
{
    protected $table = 'ai_credit_usage';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'user_id',
        'used_at',
        'feature',
        'credits_used',
        'metadata',
    ];

    protected $casts = [
        'used_at'      => 'datetime',
        'credits_used' => 'integer',
        'metadata'     => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
