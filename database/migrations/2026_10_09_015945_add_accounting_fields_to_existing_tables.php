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
        // 1. sale_orders: unit_cost_snapshot
        if (Schema::hasTable('sale_orders') && !Schema::hasColumn('sale_orders', 'unit_cost_snapshot')) {
            Schema::table('sale_orders', function (Blueprint $table) {
                $table->decimal('unit_cost_snapshot', 15, 2)->nullable()->after('selling_price');
            });
        }

        // 2. expense_categories: account_id
        if (Schema::hasTable('expense_categories') && !Schema::hasColumn('expense_categories', 'account_id')) {
            Schema::table('expense_categories', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->nullable()->after('branch_id');
            });
        }

        // 3. plan_capabilities: feature_accounting
        if (Schema::hasTable('plan_capabilities') && !Schema::hasColumn('plan_capabilities', 'feature_accounting')) {
            Schema::table('plan_capabilities', function (Blueprint $table) {
                $table->boolean('feature_accounting')->default(false)->after('feature_ecommerce_integration');
            });

            // Set feature_accounting = true for Silver, Gold, and Enterprise (Decision 4)
            DB::table('plan_capabilities')
                ->whereIn('pricing_plan_id', function ($query) {
                    $query->select('id')
                        ->from('pricing_plans')
                        ->whereIn('name', ['Silver', 'Gold', 'Enterprise']);
                })
                ->update(['feature_accounting' => true]);
        }

        // 4. pos_settings: is_accounting_vat_enabled & accounting_started_at
        if (Schema::hasTable('pos_settings')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('pos_settings', 'is_accounting_vat_enabled')) {
                    $table->boolean('is_accounting_vat_enabled')->default(false)->after('is_tax_enabled');
                }
                if (!Schema::hasColumn('pos_settings', 'accounting_started_at')) {
                    $table->timestamp('accounting_started_at')->nullable()->after('is_accounting_vat_enabled');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sale_orders') && Schema::hasColumn('sale_orders', 'unit_cost_snapshot')) {
            Schema::table('sale_orders', function (Blueprint $table) {
                $table->dropColumn('unit_cost_snapshot');
            });
        }

        if (Schema::hasTable('expense_categories') && Schema::hasColumn('expense_categories', 'account_id')) {
            Schema::table('expense_categories', function (Blueprint $table) {
                $table->dropColumn('account_id');
            });
        }

        if (Schema::hasTable('plan_capabilities') && Schema::hasColumn('plan_capabilities', 'feature_accounting')) {
            Schema::table('plan_capabilities', function (Blueprint $table) {
                $table->dropColumn('feature_accounting');
            });
        }

        if (Schema::hasTable('pos_settings')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                if (Schema::hasColumn('pos_settings', 'accounting_started_at')) {
                    $table->dropColumn('accounting_started_at');
                }
                if (Schema::hasColumn('pos_settings', 'is_accounting_vat_enabled')) {
                    $table->dropColumn('is_accounting_vat_enabled');
                }
            });
        }
    }
};
