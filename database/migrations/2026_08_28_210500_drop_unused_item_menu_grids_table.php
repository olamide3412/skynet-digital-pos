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
        Schema::dropIfExists('item_menu_grids');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
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
    }
};
