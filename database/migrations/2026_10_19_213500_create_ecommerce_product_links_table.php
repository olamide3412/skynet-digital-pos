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
        Schema::create('ecommerce_product_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('pos_item_id')->index();
            $table->unsignedBigInteger('ecommerce_product_id')->index();
            $table->string('type')->default('simple'); // 'simple' or 'variant'
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // Unique constraint: prevent duplicate link per branch & item
            $table->unique(['pos_item_id', 'ecommerce_product_id'], 'unique_pos_ecom_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ecommerce_product_links');
    }
};
