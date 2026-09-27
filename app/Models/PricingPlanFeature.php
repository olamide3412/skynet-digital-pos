<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingPlanFeature extends Model
{
    protected $table = 'pricing_plan_features';

    protected $fillable = [
        'pricing_plan_id',
        'feature_text',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class, 'pricing_plan_id');
    }
}
