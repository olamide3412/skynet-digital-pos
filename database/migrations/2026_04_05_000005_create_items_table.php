<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── POS Items ────────────────────────────────────────────────────────
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('group_address_id')->nullable()->constrained('group_addresses')->nullOnDelete();
            $table->string('item_name');
            $table->string('barcode_number')->nullable();
            $table->unique(['branch_id', 'barcode_number']); // unique per branch

            // ── Pricing ──────────────────────────────────────────────────────
            $table->decimal('buy_price', 10, 2)->default(0);
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('wholesale_price', 10, 2)->default(0);
            $table->decimal('pack_price', 10, 2)->nullable();
            $table->decimal('carton_price', 10, 2)->nullable();
            $table->decimal('pack_wholesale_price', 10, 2)->nullable();
            $table->decimal('carton_wholesale_price', 10, 2)->nullable();
            $table->boolean('price_locked')->default(false);

            // ── Stock (back-store = warehouse; front-store = available for POS) ─
            $table->integer('back_store_qty')->default(0);
            $table->integer('front_store_qty')->default(0);

            // ── Unit conversion ───────────────────────────────────────────────
            // e.g. 1 carton = packs_per_carton packs, 1 pack = units_per_pack units
            $table->string('unit_label', 50)->default('Unit');
            $table->string('pack_label', 50)->default('Pack');
            $table->string('carton_label', 50)->default('Carton');
            $table->unsignedInteger('units_per_pack')->default(1);
            $table->unsignedInteger('packs_per_carton')->default(1);

            // ── Other ─────────────────────────────────────────────────────────
            $table->date('expiry_date')->nullable();
            $table->string('item_description', 500)->nullable();
            $table->string('image_path', 500)->nullable();
            $table->timestamps();
        });

        // ── Item Menu Grids ───────────────────────────────────────────────────
        Schema::create('item_menu_grids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('menu_name');
            $table->unique(['branch_id', 'menu_name']);
            $table->string('menu_text');
            $table->string('fore_color')->nullable();
            $table->string('back_color')->nullable();
            $table->string('font', 255)->nullable();
            $table->timestamps();
        });

        // ── Item Grids (POS button/gallery layout) ────────────────────────────
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
