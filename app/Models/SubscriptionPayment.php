<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    protected $table = 'subscription_payments';

    protected $fillable = [
        'organization_id',
        'pricing_plan_id',
        'user_id',
        'type',
        'amount_paid',
        'currency',
        'gateway',
        'gateway_reference',
        'gateway_transaction_id',
        'status',
        'period_months',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'amount_paid'   => 'float',
        'period_months' => 'integer',
        'metadata'      => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function pricingPlan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', 'successful');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
