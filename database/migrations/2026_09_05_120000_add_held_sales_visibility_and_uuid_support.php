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
        // 1. Add held_sales_visibility setting to pos_settings
        if (Schema::hasTable('pos_settings')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('pos_settings', 'held_sales_visibility')) {
                    $table->string('held_sales_visibility', 50)->default('device_only')->after('is_offline_enabled');
                }
            });
        }

        // 2. Add held_sale_uuid to held_sales table
        if (Schema::hasTable('held_sales')) {
            Schema::table('held_sales', function (Blueprint $table) {
                if (!Schema::hasColumn('held_sales', 'held_sale_uuid')) {
                    $table->string('held_sale_uuid', 100)->nullable()->unique()->after('id');
                }
            });
        }

        // 3. Add imei_or_device_id to held_sale_items table
        if (Schema::hasTable('held_sale_items')) {
            Schema::table('held_sale_items', function (Blueprint $table) {
                if (!Schema::hasColumn('held_sale_items', 'imei_or_device_id')) {
                    $table->string('imei_or_device_id', 100)->nullable()->after('item_name');
                }
            });
        }

        // 4. Add held_sale_id to sales table for double-resume conflict tracking
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (!Schema::hasColumn('sales', 'held_sale_id')) {
                    $table->string('held_sale_id', 100)->nullable()->index()->after('offline_sale_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pos_settings') && Schema::hasColumn('pos_settings', 'held_sales_visibility')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->dropColumn('held_sales_visibility');
            });
        }

        if (Schema::hasTable('held_sales') && Schema::hasColumn('held_sales', 'held_sale_uuid')) {
            Schema::table('held_sales', function (Blueprint $table) {
                $table->dropColumn('held_sale_uuid');
            });
        }

        if (Schema::hasTable('held_sale_items') && Schema::hasColumn('held_sale_items', 'imei_or_device_id')) {
            Schema::table('held_sale_items', function (Blueprint $table) {
                $table->dropColumn('imei_or_device_id');
            });
        }

        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'held_sale_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('held_sale_id');
            });
        }
    }
};
