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
        Schema::table('ecommerce_product_links', function (Blueprint $table) {
            $table->string('ecommerce_product_id', 191)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ecommerce_product_links', function (Blueprint $table) {
            $table->unsignedBigInteger('ecommerce_product_id')->change();
        });
    }
};
