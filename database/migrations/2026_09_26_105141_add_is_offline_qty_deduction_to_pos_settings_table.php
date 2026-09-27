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
            if (!Schema::hasColumn('pos_settings', 'is_offline_qty_deduction')) {
                $table->boolean('is_offline_qty_deduction')->default(false)->after('is_qty_deduction');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            if (Schema::hasColumn('pos_settings', 'is_offline_qty_deduction')) {
                $table->dropColumn('is_offline_qty_deduction');
            }
        });
    }
};
