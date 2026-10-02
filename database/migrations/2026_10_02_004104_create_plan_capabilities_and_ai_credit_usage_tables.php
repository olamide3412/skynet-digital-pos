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
        // 1. Plan Capabilities (structured machine-readable feature and limit definitions)
        Schema::create('plan_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_plan_id')
                ->unique()
                ->constrained('pricing_plans')
                ->cascadeOnDelete();
            $table->integer('max_branches')->nullable();
            $table->integer('max_users_per_branch')->nullable();
            $table->boolean('feature_multi_branch')->default(true);
            $table->boolean('feature_debt_customer_accounts')->default(true);
            $table->boolean('feature_discounts')->default(true);
            $table->boolean('feature_batch_expiry_tracking')->default(true);
            $table->boolean('feature_imei_tracking')->default(true);
            $table->boolean('feature_barcode_label_printing')->default(true);
            $table->boolean('feature_inventory_import')->default(true);
            $table->boolean('feature_advanced_staff_permissions')->default(true);
            $table->boolean('feature_ai_chat_assistant')->default(true);
            $table->boolean('feature_ai_reorder_prediction')->default(true);
            $table->boolean('feature_broadcast_notifications')->default(true);

            // Part 3 AI Credit Pool Fields
            $table->enum('ai_credit_pool_type', ['account_wide', 'per_branch'])->default('per_branch');
            $table->integer('ai_credits_monthly')->nullable(); // null = unmetered (Enterprise)

            $table->timestamps();
        });

        // 2. AI Credit Usage Ledger
        Schema::create('ai_credit_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('used_at');
            $table->string('feature'); // e.g. chat_query, reorder_prediction
            $table->integer('credits_used')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'used_at']);
            $table->index(['branch_id', 'used_at']);
        });

        // 3. Seed capabilities for existing pricing plans
        $plans = DB::table('pricing_plans')->get();
        foreach ($plans as $plan) {
            $name = strtolower($plan->name);
            $poolType = ($name === 'starter' || $name === 'enterprise') ? 'account_wide' : 'per_branch';

            $monthlyCredits = match ($name) {
                'starter'    => 25,
                'bronze'     => 60,
                'silver'     => 150,
                'gold'       => 400,
                'enterprise' => null,
                default      => 50,
            };

            $maxBranches = match ($name) {
                'starter'    => 1,
                'bronze'     => 3,
                'silver'     => 7,
                'gold'       => 10,
                'enterprise' => null,
                default      => null,
            };

            $maxUsers = match ($name) {
                'starter'    => 5,
                'bronze'     => 10,
                'silver'     => 20,
                'gold'       => 50,
                'enterprise' => null,
                default      => null,
            };

            DB::table('plan_capabilities')->insert([
                'pricing_plan_id'                   => $plan->id,
                'max_branches'                      => $maxBranches,
                'max_users_per_branch'              => $maxUsers,
                'feature_multi_branch'              => $maxBranches !== 1,
                'feature_debt_customer_accounts'    => $name !== 'starter',
                'feature_discounts'                 => $name !== 'starter',
                'feature_batch_expiry_tracking'     => in_array($name, ['silver', 'gold', 'enterprise']),
                'feature_imei_tracking'             => in_array($name, ['silver', 'gold', 'enterprise']),
                'feature_barcode_label_printing'    => in_array($name, ['silver', 'gold', 'enterprise']),
                'feature_inventory_import'          => in_array($name, ['silver', 'gold', 'enterprise']),
                'feature_advanced_staff_permissions'=> in_array($name, ['silver', 'gold', 'enterprise']),
                'feature_ai_chat_assistant'         => in_array($name, ['silver', 'gold', 'enterprise']),
                'feature_ai_reorder_prediction'     => in_array($name, ['silver', 'gold', 'enterprise']),
                'feature_broadcast_notifications'   => in_array($name, ['silver', 'gold', 'enterprise']),
                'ai_credit_pool_type'               => $poolType,
                'ai_credits_monthly'                => $monthlyCredits,
                'created_at'                        => now(),
                'updated_at'                        => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_credit_usage');
        Schema::dropIfExists('plan_capabilities');
    }
};
