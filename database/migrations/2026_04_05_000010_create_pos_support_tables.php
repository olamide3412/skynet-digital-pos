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
            $table->foreignId('customer_id')->constrained('pos_customers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2)->default(0);
            $table->enum('type', ['debit', 'credit']);
            $table->string('narration', 255)->default('NO_NARRATION');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->enum('transaction_type', ['sale', 'purchase', 'return', 'adjustment']);
            $table->integer('qty')->default(0);
            $table->integer('previous_qty')->default(0);
            $table->integer('new_qty')->default(0);
            $table->string('reference_id', 50)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('adjustment_type', 50);
            $table->integer('quantity')->default(0);
            $table->string('reason', 255)->nullable();
            $table->string('adjusted_by', 100)->nullable();
            $table->timestamp('adjustment_date')->useCurrent();
        });

        Schema::create('users_roles', function (Blueprint $table) {
            $table->id();
            $table->string('role_name', 100);
            $table->string('role', 100);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('pos_logs', function (Blueprint $table) {
            $table->id();
            $table->text('log');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('most_sale_items', function (Blueprint $table) {
            $table->id();
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
        Schema::dropIfExists('users_roles');
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('debt_payments');
    }
};
