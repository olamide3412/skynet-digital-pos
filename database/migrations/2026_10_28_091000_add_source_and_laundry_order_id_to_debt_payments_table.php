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
        Schema::table('debt_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('debt_payments', 'source')) {
                $table->string('source', 50)->default('pos')->after('sale_id')->index();
            }
            if (!Schema::hasColumn('debt_payments', 'laundry_order_id')) {
                $table->foreignId('laundry_order_id')->nullable()->after('source')->constrained('laundry_orders')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('debt_payments', function (Blueprint $table) {
            if (Schema::hasColumn('debt_payments', 'laundry_order_id')) {
                $table->dropForeign(['laundry_order_id']);
                $table->dropColumn('laundry_order_id');
            }
            if (Schema::hasColumn('debt_payments', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
