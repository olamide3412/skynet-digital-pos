<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanCapability extends Model
{
    protected $table = 'plan_capabilities';

    protected $fillable = [
        'pricing_plan_id',
        'max_branches',
        'max_users_per_branch',
        'feature_multi_branch',
        'feature_debt_customer_accounts',
        'feature_discounts',
        'feature_batch_expiry_tracking',
        'feature_imei_tracking',
        'feature_barcode_label_printing',
        'feature_inventory_import',
        'feature_advanced_staff_permissions',
        'feature_ai_chat_assistant',
        'feature_ai_reorder_prediction',
        'feature_broadcast_notifications',
        'feature_ecommerce_integration',
        'feature_accounting',
        'ai_credit_pool_type',
        'ai_credits_monthly',
    ];

    protected $casts = [
        'max_branches'                       => 'integer',
        'max_users_per_branch'               => 'integer',
        'feature_multi_branch'               => 'boolean',
        'feature_debt_customer_accounts'     => 'boolean',
        'feature_discounts'                  => 'boolean',
        'feature_batch_expiry_tracking'      => 'boolean',
        'feature_imei_tracking'              => 'boolean',
        'feature_barcode_label_printing'     => 'boolean',
        'feature_inventory_import'           => 'boolean',
        'feature_advanced_staff_permissions' => 'boolean',
        'feature_ai_chat_assistant'          => 'boolean',
        'feature_ai_reorder_prediction'      => 'boolean',
        'feature_broadcast_notifications'    => 'boolean',
        'feature_ecommerce_integration'      => 'boolean',
        'feature_accounting'                 => 'boolean',
        'ai_credits_monthly'                 => 'integer',
    ];

    public function pricingPlan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class);
    }
}
