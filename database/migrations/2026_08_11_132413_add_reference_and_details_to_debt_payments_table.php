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
            if (!Schema::hasColumn('debt_payments', 'reference')) {
                $table->string('reference', 50)->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('debt_payments', 'sale_id')) {
                $table->foreignId('sale_id')->nullable()->after('reference')->constrained('sales')->nullOnDelete();
            }
            if (!Schema::hasColumn('debt_payments', 'balance_before')) {
                $table->decimal('balance_before', 10, 2)->default(0)->after('amount');
            }
            if (!Schema::hasColumn('debt_payments', 'balance_after')) {
                $table->decimal('balance_after', 10, 2)->default(0)->after('balance_before');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('debt_payments', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->dropColumn(['reference', 'sale_id', 'balance_before', 'balance_after']);
        });
    }
};
