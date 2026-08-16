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
            $table->string('receipt_paper_size', 20)->default('80mm')->after('sell_interface');
            $table->boolean('auto_print_receipt')->default(true)->after('receipt_paper_size');
            $table->boolean('show_receipt_preview')->default(true)->after('auto_print_receipt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->dropColumn(['receipt_paper_size', 'auto_print_receipt', 'show_receipt_preview']);
        });
    }
};
