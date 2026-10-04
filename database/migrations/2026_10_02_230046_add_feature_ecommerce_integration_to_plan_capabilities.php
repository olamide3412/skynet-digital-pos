<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plan_capabilities', function (Blueprint $table) {
            $table->boolean('feature_ecommerce_integration')->default(false)->after('feature_broadcast_notifications');
        });

        // Enable feature for Enterprise plan
        $enterprisePlanIds = DB::table('pricing_plans')
            ->whereRaw('LOWER(name) = ?', ['enterprise'])
            ->pluck('id');

        if ($enterprisePlanIds->isNotEmpty()) {
            DB::table('plan_capabilities')
                ->whereIn('pricing_plan_id', $enterprisePlanIds)
                ->update(['feature_ecommerce_integration' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_capabilities', function (Blueprint $table) {
            $table->dropColumn('feature_ecommerce_integration');
        });
    }
};
