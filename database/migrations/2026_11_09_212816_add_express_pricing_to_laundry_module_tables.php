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
        // 1. Service Catalogue: custom express price
        if (Schema::hasTable('laundry_services')) {
            Schema::table('laundry_services', function (Blueprint $table) {
                if (!Schema::hasColumn('laundry_services', 'express_price')) {
                    $table->decimal('express_price', 12, 2)->nullable()->after('price');
                }
            });
        }

        // 2. Laundry Settings: branch default turnaround hours and fallback markup multiplier
        if (Schema::hasTable('laundry_settings')) {
            Schema::table('laundry_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('laundry_settings', 'enable_express_service')) {
                    $table->boolean('enable_express_service')->default(true)->after('unclaimed_after_days');
                }
                if (!Schema::hasColumn('laundry_settings', 'express_turnaround_hours')) {
                    $table->integer('express_turnaround_hours')->default(24)->after('enable_express_service');
                }
                if (!Schema::hasColumn('laundry_settings', 'default_express_multiplier')) {
                    $table->decimal('default_express_multiplier', 4, 2)->default(1.50)->after('express_turnaround_hours');
                }
            });
        }

        // 3. Laundry Orders: speed tier and express flag
        if (Schema::hasTable('laundry_orders')) {
            Schema::table('laundry_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('laundry_orders', 'is_express')) {
                    $table->boolean('is_express')->default(false)->index()->after('status');
                }
                if (!Schema::hasColumn('laundry_orders', 'speed_tier')) {
                    $table->string('speed_tier', 20)->default('standard')->after('is_express');
                }
            });
        }

        // 4. Laundry Order Items: line-level express flag
        if (Schema::hasTable('laundry_order_items')) {
            Schema::table('laundry_order_items', function (Blueprint $table) {
                if (!Schema::hasColumn('laundry_order_items', 'is_express')) {
                    $table->boolean('is_express')->default(false)->after('pricing_type');
                }
                if (!Schema::hasColumn('laundry_order_items', 'speed_tier')) {
                    $table->string('speed_tier', 20)->default('standard')->after('is_express');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('laundry_order_items')) {
            Schema::table('laundry_order_items', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('laundry_order_items', 'speed_tier')) $columns[] = 'speed_tier';
                if (Schema::hasColumn('laundry_order_items', 'is_express')) $columns[] = 'is_express';
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('laundry_orders')) {
            Schema::table('laundry_orders', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('laundry_orders', 'speed_tier')) $columns[] = 'speed_tier';
                if (Schema::hasColumn('laundry_orders', 'is_express')) $columns[] = 'is_express';
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('laundry_settings')) {
            Schema::table('laundry_settings', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('laundry_settings', 'default_express_multiplier')) $columns[] = 'default_express_multiplier';
                if (Schema::hasColumn('laundry_settings', 'express_turnaround_hours')) $columns[] = 'express_turnaround_hours';
                if (Schema::hasColumn('laundry_settings', 'enable_express_service')) $columns[] = 'enable_express_service';
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('laundry_services')) {
            Schema::table('laundry_services', function (Blueprint $table) {
                if (Schema::hasColumn('laundry_services', 'express_price')) {
                    $table->dropColumn('express_price');
                }
            });
        }
    }
};
