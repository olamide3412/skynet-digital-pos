<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('item_name');
            $table->integer('qty')->default(1);
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->enum('purchase_type', ['Wholesale', 'Consumer'])->default('Consumer');
            $table->string('return_reason', 255)->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('held_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('hold_name', 255)->nullable();
            $table->enum('status', ['In-Progress', 'Held', 'Cancelled', 'Completed'])->default('Held');
            $table->foreignId('customer_id')->nullable()->constrained('pos_customers')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('held_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('held_sale_id')->constrained('held_sales')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('qty')->default(1);
            $table->decimal('price', 10, 2)->default(0);
            $table->string('unit_used', 50)->default('unit');
            $table->string('item_name', 255)->nullable();
            $table->enum('purchase_type', ['Wholesale', 'Consumer'])->default('Consumer');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('held_sale_items');
        Schema::dropIfExists('held_sales');
        Schema::dropIfExists('sale_return_items');
    }
};
