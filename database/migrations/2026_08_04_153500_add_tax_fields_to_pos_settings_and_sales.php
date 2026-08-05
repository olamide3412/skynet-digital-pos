<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('pos_settings', 'is_tax_enabled')) {
                $table->boolean('is_tax_enabled')->default(false)->after('is_show_buy_price');
            }
            if (!Schema::hasColumn('pos_settings', 'tax_percentage')) {
                $table->decimal('tax_percentage', 5, 2)->default(0.00)->after('is_tax_enabled');
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'tax_amount')) {
                $table->decimal('tax_amount', 10, 2)->default(0.00)->after('discount_amount');
            }
            if (!Schema::hasColumn('sales', 'tax_percentage')) {
                $table->decimal('tax_percentage', 5, 2)->default(0.00)->after('tax_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->dropColumn(['is_tax_enabled', 'tax_percentage']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['tax_amount', 'tax_percentage']);
        });
    }
};
