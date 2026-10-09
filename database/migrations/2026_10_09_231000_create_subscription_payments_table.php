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
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->onDelete('cascade');
            $table->foreignId('pricing_plan_id')
                ->constrained('pricing_plans')
                ->onDelete('restrict');
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->enum('type', ['renewal', 'upgrade']);
            $table->decimal('amount_paid', 12, 2);
            $table->string('currency', 10)->default('NGN');
            $table->string('gateway', 50); // 'paystack' or 'flutterwave'
            $table->string('gateway_reference', 191)->unique();
            $table->string('gateway_transaction_id', 191)->nullable();
            $table->enum('status', ['pending', 'successful', 'failed'])->default('pending');
            $table->unsignedInteger('period_months')->default(12);
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'idx_sub_payments_org_status');
            $table->index('gateway_reference', 'idx_sub_payments_ref');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
