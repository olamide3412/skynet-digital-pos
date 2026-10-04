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
        if (Schema::hasTable('commerce_item_mappings')) {
            Schema::table('commerce_item_mappings', function (Blueprint $table) {
                if (Schema::hasColumn('commerce_item_mappings', 'external_product_id')) {
                    $table->string('external_product_id', 100)->change();
                }
                if (Schema::hasColumn('commerce_item_mappings', 'external_variant_id')) {
                    $table->string('external_variant_id', 100)->nullable()->change();
                }
                if (Schema::hasColumn('commerce_item_mappings', 'external_sku')) {
                    $table->string('external_sku', 100)->nullable()->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed as 100 characters is the intended safe size
    }
};
