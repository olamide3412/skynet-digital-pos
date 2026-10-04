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
        Schema::dropIfExists('commerce_item_mappings');

        Schema::create('commerce_item_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commerce_integration_id')->constrained('commerce_integrations')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('external_product_id', 100);
            $table->string('external_variant_id', 100)->nullable();
            $table->string('external_sku', 100)->nullable();
            $table->enum('sync_status', ['synced', 'pending', 'error'])->default('synced');
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['commerce_integration_id', 'item_id'], 'comm_map_integration_item_unique');
            $table->index(['commerce_integration_id', 'external_product_id', 'external_variant_id'], 'comm_map_ext_prod_var_idx');
            $table->index(['commerce_integration_id', 'external_sku'], 'comm_map_ext_sku_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commerce_item_mappings');
    }
};
