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
        Schema::table('commerce_item_mappings', function (Blueprint $table) {
            if (!Schema::hasColumn('commerce_item_mappings', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('commerce_integration_id')->constrained('branches')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('commerce_item_mappings', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('external_sku')->index();
            }
            if (!Schema::hasColumn('commerce_item_mappings', 'registered_at')) {
                $table->timestamp('registered_at')->nullable()->after('sync_status');
            }
            if (!Schema::hasColumn('commerce_item_mappings', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('registered_at');
            }
            if (!Schema::hasColumn('commerce_item_mappings', 'unregistered_at')) {
                $table->timestamp('unregistered_at')->nullable()->after('last_seen_at');
            }
        });

        // Backfill branch_id, registered_at, last_seen_at for any existing records
        try {
            DB::statement("
                UPDATE commerce_item_mappings cim
                JOIN commerce_integrations ci ON cim.commerce_integration_id = ci.id
                SET cim.branch_id = ci.branch_id,
                    cim.registered_at = COALESCE(cim.registered_at, cim.created_at, NOW()),
                    cim.last_seen_at = COALESCE(cim.last_seen_at, cim.last_synced_at, cim.updated_at, NOW()),
                    cim.is_active = 1
                WHERE cim.branch_id IS NULL
            ");
        } catch (\Throwable $e) {
            // Ignore if tables are empty or dialect differs
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commerce_item_mappings', function (Blueprint $table) {
            if (Schema::hasColumn('commerce_item_mappings', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            }
            $columnsToDrop = array_filter(['is_active', 'registered_at', 'last_seen_at', 'unregistered_at'], fn($col) => Schema::hasColumn('commerce_item_mappings', $col));
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
