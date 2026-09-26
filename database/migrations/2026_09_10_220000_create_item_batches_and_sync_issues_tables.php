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
        // 1. item_batches table
        if (!Schema::hasTable('item_batches')) {
            Schema::create('item_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->string('batch_label')->nullable();
                $table->integer('quantity')->default(0);
                $table->date('expiry_date');
                $table->date('received_date')->nullable();
                $table->decimal('cost_price', 12, 2)->nullable();
                $table->enum('status', ['active', 'expired', 'cleared'])->default('active');
                $table->timestamps();

                $table->index(['branch_id', 'item_id', 'status']);
                $table->index(['branch_id', 'expiry_date']);
            });
        }

        // 2. item_batch_sync_issues table
        if (!Schema::hasTable('item_batch_sync_issues')) {
            Schema::create('item_batch_sync_issues', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
                $table->string('action_type'); // 'sale', 'adjustment', etc.
                $table->integer('requested_qty');
                $table->integer('available_batch_qty');
                $table->text('reason');
                $table->string('reference_id')->nullable();
                $table->enum('status', ['logged', 'resolved'])->default('logged');
                $table->timestamps();

                $table->index(['branch_id', 'status']);
                $table->index(['branch_id', 'item_id']);
            });
        }

        // 3. Add batch_tracking_enabled to items table
        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'batch_tracking_enabled')) {
                $table->boolean('batch_tracking_enabled')->default(false)->after('is_imei_tracked');
            }
        });

        // 4. Add batch_tracking_enabled & expiry_alert_days to pos_settings table
        Schema::table('pos_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('pos_settings', 'batch_tracking_enabled')) {
                $table->boolean('batch_tracking_enabled')->default(false)->after('is_check_expiration');
            }
            if (!Schema::hasColumn('pos_settings', 'expiry_alert_days')) {
                $table->integer('expiry_alert_days')->default(30)->after('batch_tracking_enabled');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_batch_sync_issues');
        Schema::dropIfExists('item_batches');

        Schema::table('items', function (Blueprint $table) {
            if (Schema::hasColumn('items', 'batch_tracking_enabled')) {
                $table->dropColumn('batch_tracking_enabled');
            }
        });

        Schema::table('pos_settings', function (Blueprint $table) {
            if (Schema::hasColumn('pos_settings', 'expiry_alert_days')) {
                $table->dropColumn('expiry_alert_days');
            }
            if (Schema::hasColumn('pos_settings', 'batch_tracking_enabled')) {
                $table->dropColumn('batch_tracking_enabled');
            }
        });
    }
};
