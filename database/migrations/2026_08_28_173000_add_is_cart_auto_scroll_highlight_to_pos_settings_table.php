<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_settings') && !Schema::hasColumn('pos_settings', 'is_cart_auto_scroll_highlight')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->boolean('is_cart_auto_scroll_highlight')->default(true)->after('is_grid_separate_rows');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_settings') && Schema::hasColumn('pos_settings', 'is_cart_auto_scroll_highlight')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->dropColumn('is_cart_auto_scroll_highlight');
            });
        }
    }
};
