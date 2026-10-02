<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PricingPlan extends Model
{
    protected $table = 'pricing_plans';

    protected $fillable = [
        'name',
        'tagline',
        'first_year_price',
        'renewal_price',
        'branches_limit',
        'users_limit',
        'ai_credits',
        'support_level',
        'is_highlighted',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_highlighted' => 'boolean',
        'is_active'      => 'boolean',
        'sort_order'     => 'integer',
    ];

    public function features(): HasMany
    {
        return $this->hasMany(PricingPlanFeature::class, 'pricing_plan_id')->orderBy('sort_order');
    }

    public function capabilities(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PlanCapability::class, 'pricing_plan_id');
    }
}
