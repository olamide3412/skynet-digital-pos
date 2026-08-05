<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('pos_customers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2)->default(0);
            $table->enum('type', ['debit', 'credit']);
            $table->string('narration', 255)->default('NO_NARRATION');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->enum('transaction_type', ['sale', 'purchase', 'return', 'adjustment', 'transfer']);
            $table->integer('qty')->default(0);       // in base units
            $table->integer('previous_qty')->default(0);
            $table->integer('new_qty')->default(0);
            $table->string('location', 20)->default('front_store'); // which stock location
            $table->string('reference_id', 50)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('adjustment_type', 50);
            $table->integer('quantity')->default(0);
            $table->string('reason', 255)->nullable();
            $table->string('adjusted_by', 100)->nullable();
            $table->timestamp('adjustment_date')->useCurrent();
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('qty_base_units');   // always stored in base units
            $table->string('unit_used', 20)->default('unit'); // what the user typed it in as
            $table->enum('from_location', ['back_store', 'front_store']);
            $table->enum('to_location', ['back_store', 'front_store']);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('pos_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->text('log');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('most_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('qty')->default(0);
            $table->date('date_created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('most_sale_items');
        Schema::dropIfExists('pos_logs');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('debt_payments');
    }
};
