<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debt_payments', function (Blueprint $table) {
            // Reference number for receipt/audit
            $table->string('reference', 30)->nullable()->after('id');
            // Link to original sale that created the debt (nullable for manual entries)
            $table->foreignId('sale_id')->nullable()->after('reference')
                  ->constrained('sales')->nullOnDelete();
            // Running balance snapshot for audit trail
            $table->decimal('balance_before', 12, 2)->default(0)->after('amount');
            $table->decimal('balance_after',  12, 2)->default(0)->after('balance_before');
        });
    }

    public function down(): void
    {
        Schema::table('debt_payments', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->dropColumn(['reference', 'sale_id', 'balance_before', 'balance_after']);
        });
    }
};
