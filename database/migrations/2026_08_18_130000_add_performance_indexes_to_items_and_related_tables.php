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
        // 1. Indexes on `items` table
        Schema::table('items', function (Blueprint $table) {
            $table->index(['branch_id', 'item_name'], 'items_branch_id_item_name_index');
            $table->index(['branch_id', 'category_id'], 'items_branch_id_category_id_index');
            $table->index(['branch_id', 'is_imei_tracked'], 'items_branch_id_is_imei_tracked_index');
            $table->index(['branch_id', 'front_store_qty'], 'items_branch_id_front_store_qty_index');
            $table->index(['branch_id', 'expiry_date'], 'items_branch_id_expiry_date_index');
            $table->fullText('item_name', 'items_item_name_fulltext');
        });

        // 2. Indexes on `item_device_units` table
        if (Schema::hasTable('item_device_units')) {
            Schema::table('item_device_units', function (Blueprint $table) {
                $table->unique(['branch_id', 'imei_or_device_id'], 'item_device_units_branch_id_imei_unique');
                $table->index(['item_id', 'status'], 'item_device_units_item_id_status_index');
            });
        }

        // 3. Indexes on `barcode_print_logs` table
        if (Schema::hasTable('barcode_print_logs')) {
            Schema::table('barcode_print_logs', function (Blueprint $table) {
                $table->index(['branch_id', 'item_id'], 'barcode_print_logs_branch_id_item_id_index');
                $table->index(['branch_id', 'created_at'], 'barcode_print_logs_branch_id_created_at_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex('items_branch_id_item_name_index');
            $table->dropIndex('items_branch_id_category_id_index');
            $table->dropIndex('items_branch_id_is_imei_tracked_index');
            $table->dropIndex('items_branch_id_front_store_qty_index');
            $table->dropIndex('items_branch_id_expiry_date_index');
            $table->dropFullText('items_item_name_fulltext');
        });

        if (Schema::hasTable('item_device_units')) {
            Schema::table('item_device_units', function (Blueprint $table) {
                $table->dropUnique('item_device_units_branch_id_imei_unique');
                $table->dropIndex('item_device_units_item_id_status_index');
            });
        }

        if (Schema::hasTable('barcode_print_logs')) {
            Schema::table('barcode_print_logs', function (Blueprint $table) {
                $table->dropIndex('barcode_print_logs_branch_id_item_id_index');
                $table->dropIndex('barcode_print_logs_branch_id_created_at_index');
            });
        }
    }
};
