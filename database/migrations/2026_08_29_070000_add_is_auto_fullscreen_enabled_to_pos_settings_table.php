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
            if (!Schema::hasColumn('pos_settings', 'is_auto_fullscreen_enabled')) {
                $table->boolean('is_auto_fullscreen_enabled')->default(false)->after('is_cart_auto_scroll_highlight');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            if (Schema::hasColumn('pos_settings', 'is_auto_fullscreen_enabled')) {
                $table->dropColumn('is_auto_fullscreen_enabled');
            }
        });
    }
};
