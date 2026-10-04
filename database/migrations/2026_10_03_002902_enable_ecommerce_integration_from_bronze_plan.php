<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $eligiblePlanIds = DB::table('pricing_plans')
            ->whereIn(DB::raw('LOWER(name)'), ['bronze', 'silver', 'gold', 'enterprise'])
            ->pluck('id');

        if ($eligiblePlanIds->isNotEmpty()) {
            DB::table('plan_capabilities')
                ->whereIn('pricing_plan_id', $eligiblePlanIds)
                ->update([
                    'feature_ecommerce_integration' => true,
                    'updated_at'                    => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $nonEnterprisePlanIds = DB::table('pricing_plans')
            ->whereIn(DB::raw('LOWER(name)'), ['bronze', 'silver', 'gold'])
            ->pluck('id');

        if ($nonEnterprisePlanIds->isNotEmpty()) {
            DB::table('plan_capabilities')
                ->whereIn('pricing_plan_id', $nonEnterprisePlanIds)
                ->update([
                    'feature_ecommerce_integration' => false,
                    'updated_at'                    => now(),
                ]);
        }
    }
};
