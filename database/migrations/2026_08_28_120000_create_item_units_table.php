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
        if (!Schema::hasTable('item_units')) {
            Schema::create('item_units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->string('unit_name');
                $table->unsignedInteger('quantity_in_base_units')->default(1);
                $table->decimal('selling_price', 12, 2)->default(0);
                $table->decimal('wholesale_price', 12, 2)->default(0);
                $table->integer('sort_order')->default(0);
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->index(['branch_id', 'item_id']);
                $table->index(['item_id', 'status']);
                $table->index(['item_id', 'sort_order']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_units');
    }
};
