<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change transaction_type from strict ENUM to VARCHAR(50) so ecommerce_sale is supported
        DB::statement("ALTER TABLE inventory_transactions MODIFY transaction_type VARCHAR(50) NOT NULL DEFAULT 'sale'");

        // Make user_id nullable for automated API/system transactions
        DB::statement("ALTER TABLE inventory_transactions MODIFY user_id BIGINT UNSIGNED NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE inventory_transactions MODIFY transaction_type ENUM('sale','purchase','return','adjustment','transfer') NOT NULL DEFAULT 'sale'");
        DB::statement("ALTER TABLE inventory_transactions MODIFY user_id BIGINT UNSIGNED NOT NULL");
    }
};

