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
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->string('receipt_printer_name', 100)->nullable()->after('receipt_paper_size');
            $table->string('printer_type', 50)->default('thermal_80mm')->after('receipt_printer_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->dropColumn(['receipt_printer_name', 'printer_type']);
        });
    }
};
