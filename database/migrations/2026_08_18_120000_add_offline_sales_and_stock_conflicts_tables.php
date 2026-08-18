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
        // 1. Add offline toggles to pos_settings
        Schema::table('pos_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('pos_settings', 'is_offline_enabled')) {
                $table->boolean('is_offline_enabled')->default(false)->after('is_imei_enabled');
            }
            if (!Schema::hasColumn('pos_settings', 'offline_receipt_prefix')) {
                $table->string('offline_receipt_prefix', 20)->default('OFF')->after('is_offline_enabled');
            }
        });

        // 2. Add offline sync metadata to sales
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'offline_sale_id')) {
                $table->string('offline_sale_id', 100)->nullable()->unique()->after('receipt_id');
            }
            if (!Schema::hasColumn('sales', 'is_offline_sale')) {
                $table->boolean('is_offline_sale')->default(false)->after('offline_sale_id');
            }
            if (!Schema::hasColumn('sales', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->after('is_offline_sale');
            }
            if (!Schema::hasColumn('sales', 'has_conflict')) {
                $table->boolean('has_conflict')->default(false)->after('synced_at');
            }
        });

        // 3. Create stock_conflicts table for offline sales reconciliation
        if (!Schema::hasTable('stock_conflicts')) {
            Schema::create('stock_conflicts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->foreignId('sale_id')->nullable()->constrained('sales')->onDelete('cascade');
                $table->string('offline_sale_id', 100)->nullable()->index();
                $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
                $table->string('item_name');
                $table->string('conflict_type', 50)->default('stock_shortfall'); // 'stock_shortfall', 'imei_already_sold', 'price_mismatch'
                $table->integer('requested_qty')->default(1);
                $table->integer('available_qty_at_sync')->default(0);
                $table->string('imei_or_device_id', 100)->nullable()->index();
                $table->string('status', 30)->default('pending_review'); // 'pending_review', 'resolved', 'dismissed'
                $table->text('resolution_notes')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['branch_id', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_conflicts');

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['offline_sale_id', 'is_offline_sale', 'synced_at', 'has_conflict']);
        });

        Schema::table('pos_settings', function (Blueprint $table) {
            $table->dropColumn(['is_offline_enabled', 'offline_receipt_prefix']);
        });
    }
};
