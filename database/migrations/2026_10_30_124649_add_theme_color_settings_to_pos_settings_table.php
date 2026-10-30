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
            $table->string('theme_color', 50)->nullable()->after('business_sector');
            $table->string('custom_primary_hex', 10)->nullable()->after('theme_color');
            $table->string('custom_secondary_hex', 10)->nullable()->after('custom_primary_hex');
            $table->string('dark_mode_accent_mode', 20)->nullable()->after('custom_secondary_hex');
            $table->string('custom_dark_primary_hex', 10)->nullable()->after('dark_mode_accent_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->dropColumn([
                'theme_color',
                'custom_primary_hex',
                'custom_secondary_hex',
                'dark_mode_accent_mode',
                'custom_dark_primary_hex',
            ]);
        });
    }
};
