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
        // 1. Laundry Categories (Price List Groups per Branch)
        Schema::create('laundry_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->unique(['branch_id', 'name']);
            $table->index(['branch_id', 'sort_order']);
        });

        // 2. Laundry Services (Price List Catalogue per Branch)
        Schema::create('laundry_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('name');
            $table->string('pricing_type', 20)->default('per_piece'); // per_piece, per_kg
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('laundry_categories')->onDelete('set null');
            $table->index(['branch_id', 'is_active']);
            $table->index(['branch_id', 'sort_order']);
        });

        // 3. Laundry Orders
        Schema::create('laundry_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->string('order_number', 50)->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('status', 30)->default('received'); // received, in_progress, ready, collected, cancelled
            $table->dateTime('received_at');
            $table->dateTime('due_at')->index();
            $table->dateTime('collected_at')->nullable();
            $table->unsignedBigInteger('collected_by_user_id')->nullable();
            $table->string('collected_by_name')->nullable();
            $table->unsignedBigInteger('created_by')->index();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->string('discount_type', 20)->nullable();
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('pos_customers')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('collected_by_user_id')->references('id')->on('users')->onDelete('set null');

            $table->unique(['branch_id', 'order_number']);
            $table->index(['branch_id', 'status', 'due_at']);
            $table->index(['branch_id', 'customer_id']);
        });

        // 3. Laundry Order Items
        Schema::create('laundry_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('service_id')->nullable()->index();
            $table->string('service_name');
            $table->string('pricing_type', 20); // per_piece, per_kg
            $table->decimal('unit_price', 12, 2);
            $table->decimal('quantity', 10, 2); // supports decimal weight in kg or integer units
            $table->decimal('line_total', 12, 2);
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('laundry_orders')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('laundry_services')->onDelete('set null');
        });

        // 4. Laundry Order Status History
        Schema::create('laundry_order_status_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);
            $table->unsignedBigInteger('changed_by')->index();
            $table->dateTime('changed_at');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('laundry_orders')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('restrict');
        });

        // 5. Laundry Payments & Refunds
        Schema::create('laundry_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('branch_id')->index();
            $table->string('type', 20)->default('payment'); // payment, refund
            $table->decimal('amount', 12, 2);
            $table->string('method', 50); // Cash, Bank Transfer, POS/Card
            $table->unsignedBigInteger('till_session_id')->nullable()->index();
            $table->unsignedBigInteger('recorded_by')->index();
            $table->dateTime('paid_at')->index();
            $table->string('reference', 100)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('laundry_orders')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('till_session_id')->references('id')->on('till_sessions')->onDelete('set null');
            $table->foreign('recorded_by')->references('id')->on('users')->onDelete('restrict');

            $table->index(['branch_id', 'paid_at']);
        });

        // 6. Branch Laundry Settings
        Schema::create('laundry_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->unique();
            $table->string('order_prefix', 20)->default('LDY-');
            $table->integer('default_turnaround_days')->default(2);
            $table->integer('unclaimed_after_days')->default(14);
            $table->text('whatsapp_template')->nullable();
            $table->string('default_country_code', 10)->default('+234');
            $table->text('receipt_footer')->nullable();
            $table->boolean('allow_price_override')->default(false);
            $table->boolean('allow_unpaid_pickup')->default(false);
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laundry_settings');
        Schema::dropIfExists('laundry_payments');
        Schema::dropIfExists('laundry_order_status_history');
        Schema::dropIfExists('laundry_order_items');
        Schema::dropIfExists('laundry_orders');
        Schema::dropIfExists('laundry_services');
        Schema::dropIfExists('laundry_categories');
    }
};
