<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('stock_worth', 15, 2)->default(0)->after('buy_price')
                ->comment('Stored buy_price × (front_store_qty + back_store_qty), updated on save');
        });

        // Backfill existing items
        DB::statement('UPDATE items SET stock_worth = ROUND(buy_price * (front_store_qty + back_store_qty), 2)');
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('stock_worth');
        });
    }
};
