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
        Schema::create('commerce_reservation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commerce_reservation_id')->constrained('commerce_reservations')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2)->nullable();
            $table->timestamps();

            $table->index(['commerce_reservation_id', 'item_id'], 'comm_res_items_res_item_idx');
            $table->index('item_id', 'comm_res_items_item_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commerce_reservation_items');
    }
};
