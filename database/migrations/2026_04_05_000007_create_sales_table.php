<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_discounts', function (Blueprint $table) {
            $table->id();
            $table->enum('discount_type', ['percentage', 'fixed']);
            $table->decimal('discount_value', 10, 2);
            $table->dateTime('start_date_time');
            $table->dateTime('end_date_time');
            $table->string('applies_to', 50)->nullable();
            $table->string('description', 255)->nullable();
            $table->boolean('is_apply')->default(false);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('pos_customers')->nullOnDelete();
            $table->string('receipt_id', 50)->unique();
            $table->integer('items_order_count')->default(0);
            $table->decimal('consultation_fee', 10, 2)->default(0);
            $table->string('payment_method', 100)->default('Cash');
            $table->decimal('bank_transfer', 10, 2)->default(0);
            $table->decimal('cash', 10, 2)->default(0);
            $table->decimal('amount_cost', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('change_bal', 10, 2)->default(0);
            $table->enum('purchase_type', ['Wholesale', 'Consumer'])->default('Consumer');
            $table->decimal('profit_made', 10, 2)->default(0);
            $table->foreignId('sale_discount_id')->nullable()->constrained('sale_discounts')->nullOnDelete();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('final_total', 10, 2)->default(0);
            $table->boolean('is_debt')->default(false);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('sale_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('item_name', 255);
            $table->decimal('selling_price', 10, 2);
            $table->decimal('total_selling_price', 10, 2);
            $table->integer('qty')->default(1);
            $table->enum('purchase_type', ['Wholesale', 'Consumer'])->default('Consumer');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('sort_date')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_orders');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('sale_discounts');
    }
};
