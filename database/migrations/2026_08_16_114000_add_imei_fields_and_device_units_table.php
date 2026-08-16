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
        // 1. Add feature toggle to pos_settings
        Schema::table('pos_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('pos_settings', 'is_imei_enabled')) {
                $table->boolean('is_imei_enabled')->default(false)->after('is_check_expiration');
            }
        });

        // 2. Add is_imei_tracked toggle to items
        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'is_imei_tracked')) {
                $table->boolean('is_imei_tracked')->default(false)->after('price_locked');
            }
        });

        // 3. Add imei_or_device_id to sale_orders and sale_return_items
        Schema::table('sale_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_orders', 'imei_or_device_id')) {
                $table->string('imei_or_device_id', 100)->nullable()->after('item_name');
            }
        });

        Schema::table('sale_return_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_return_items', 'imei_or_device_id')) {
                $table->string('imei_or_device_id', 100)->nullable()->after('item_name');
            }
        });

        // 4. Create item_device_units table
        if (!Schema::hasTable('item_device_units')) {
            Schema::create('item_device_units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
                $table->string('imei_or_device_id', 100)->index();
                $table->string('status', 30)->default('in_stock')->index(); // in_stock, sold, returned, damaged, transferred
                $table->string('location', 30)->default('front_store')->index(); // front_store, back_store
                $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
                $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
                $table->foreignId('sale_order_id')->nullable()->constrained('sale_orders')->nullOnDelete();
                $table->timestamp('sold_at')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['branch_id', 'item_id', 'status', 'location']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_device_units');

        Schema::table('sale_return_items', function (Blueprint $table) {
            if (Schema::hasColumn('sale_return_items', 'imei_or_device_id')) {
                $table->dropColumn('imei_or_device_id');
            }
        });

        Schema::table('sale_orders', function (Blueprint $table) {
            if (Schema::hasColumn('sale_orders', 'imei_or_device_id')) {
                $table->dropColumn('imei_or_device_id');
            }
        });

        Schema::table('items', function (Blueprint $table) {
            if (Schema::hasColumn('items', 'is_imei_tracked')) {
                $table->dropColumn('is_imei_tracked');
            }
        });

        Schema::table('pos_settings', function (Blueprint $table) {
            if (Schema::hasColumn('pos_settings', 'is_imei_enabled')) {
                $table->dropColumn('is_imei_enabled');
            }
        });
    }
};
