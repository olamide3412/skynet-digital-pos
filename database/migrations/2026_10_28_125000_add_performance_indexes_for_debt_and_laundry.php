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
        // 1. Composite indexes on `debt_payments` table for End of Day reports & customer ledger
        if (Schema::hasTable('debt_payments')) {
            Schema::table('debt_payments', function (Blueprint $table) {
                $table->index(['branch_id', 'created_at', 'type', 'source'], 'debt_pmts_branch_date_type_src_idx');
                $table->index(['customer_id', 'type', 'created_at'], 'debt_pmts_cust_type_date_idx');
            });
        }

        // 2. Composite indexes on `laundry_orders` for instant active balance lookups
        if (Schema::hasTable('laundry_orders')) {
            Schema::table('laundry_orders', function (Blueprint $table) {
                $table->index(['customer_id', 'status', 'balance'], 'laundry_orders_cust_status_bal_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('debt_payments')) {
            Schema::table('debt_payments', function (Blueprint $table) {
                $table->dropIndex('debt_pmts_branch_date_type_src_idx');
                $table->dropIndex('debt_pmts_cust_type_date_idx');
            });
        }

        if (Schema::hasTable('laundry_orders')) {
            Schema::table('laundry_orders', function (Blueprint $table) {
                $table->dropIndex('laundry_orders_cust_status_bal_idx');
            });
        }
    }
};
