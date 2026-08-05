<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_settings') && !Schema::hasColumn('pos_settings', 'is_use_profit_percentage')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->boolean('is_use_profit_percentage')->default(false)->after('is_show_buy_price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_settings') && Schema::hasColumn('pos_settings', 'is_use_profit_percentage')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->dropColumn('is_use_profit_percentage');
            });
        }
    }
};
