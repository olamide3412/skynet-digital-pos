<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('group_address_id')->nullable()->constrained('group_addresses')->nullOnDelete();
            $table->string('item_name');
            $table->string('barcode_number')->unique();
            $table->integer('qty')->default(0);
            $table->decimal('buy_price', 10, 2)->default(0);
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('wholesale_price', 10, 2)->default(0);
            $table->date('expiry_date')->nullable();
            $table->string('item_description', 255)->nullable();
            $table->boolean('price_locked')->default(false);
            $table->timestamps();
        });

        Schema::create('item_menu_grids', function (Blueprint $table) {
            $table->id();
            $table->string('menu_name')->unique();
            $table->string('menu_text');
            $table->string('fore_color')->nullable();
            $table->string('back_color')->nullable();
            $table->string('font', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('item_grids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('menu_name')->nullable();
            $table->integer('menu_index')->default(0);
            $table->string('fore_color')->nullable();
            $table->string('back_color')->nullable();
            $table->string('font')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_grids');
        Schema::dropIfExists('item_menu_grids');
        Schema::dropIfExists('items');
    }
};
