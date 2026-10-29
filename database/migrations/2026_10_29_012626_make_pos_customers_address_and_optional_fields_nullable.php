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
        Schema::table('pos_customers', function (Blueprint $table) {
            $table->string('address', 255)->nullable()->default('NA')->change();
            $table->mediumText('note')->nullable()->default('No Note')->change();
            $table->string('contact_name', 255)->nullable()->default('NA')->change();
            $table->string('contact_phone', 255)->nullable()->default('NA')->change();
            $table->string('contact_address', 255)->nullable()->default('NA')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_customers', function (Blueprint $table) {
            $table->string('address', 255)->nullable(false)->default('NA')->change();
            $table->mediumText('note')->nullable(false)->default('No Note')->change();
            $table->string('contact_name', 255)->nullable(false)->default('NA')->change();
            $table->string('contact_phone', 255)->nullable(false)->default('NA')->change();
            $table->string('contact_address', 255)->nullable(false)->default('NA')->change();
        });
    }
};
