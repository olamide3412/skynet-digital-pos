<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $bronzePlan = \App\Models\PricingPlan::where('name', 'Bronze')->first();
        if ($bronzePlan) {
            \Illuminate\Support\Facades\DB::table('plan_capabilities')
                ->where('pricing_plan_id', $bronzePlan->id)
                ->update([
                    'feature_ai_chat_assistant'     => true,
                    'feature_ai_reorder_prediction' => true,
                    'updated_at'                    => now(),
                ]);
        }
    }

    public function down(): void
    {
        $bronzePlan = \App\Models\PricingPlan::where('name', 'Bronze')->first();
        if ($bronzePlan) {
            \Illuminate\Support\Facades\DB::table('plan_capabilities')
                ->where('pricing_plan_id', $bronzePlan->id)
                ->update([
                    'feature_ai_chat_assistant'     => false,
                    'feature_ai_reorder_prediction' => false,
                    'updated_at'                    => now(),
                ]);
        }
    }
};
