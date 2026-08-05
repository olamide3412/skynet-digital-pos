<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spatie/laravel-permission manages its own tables.
     * We create the global_items table here (Super Admin's master catalog),
     * and let Spatie handle roles/permissions/pivots via its published migration.
     */
    public function up(): void
    {
        // Global Item Pool — owned by Super Admin, not branch-scoped
        Schema::create('global_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_name');
            $table->string('barcode_number')->nullable()->unique();
            $table->decimal('buy_price', 10, 2)->default(0);
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('wholesale_price', 10, 2)->default(0);
            $table->decimal('pack_price', 10, 2)->nullable();
            $table->decimal('carton_price', 10, 2)->nullable();
            $table->decimal('pack_wholesale_price', 10, 2)->nullable();
            $table->decimal('carton_wholesale_price', 10, 2)->nullable();
            $table->string('unit_label', 50)->default('Unit');
            $table->string('pack_label', 50)->default('Pack');
            $table->string('carton_label', 50)->default('Carton');
            $table->unsignedInteger('units_per_pack')->default(1);
            $table->unsignedInteger('packs_per_carton')->default(1);
            $table->string('item_description', 500)->nullable();
            $table->string('image_path', 500)->nullable();
            $table->string('category_hint', 100)->nullable(); // suggested category name
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_items');
    }
};
