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
        // 1. Current Active Subscriptions (one row per organization)
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->unique()
                ->constrained('organizations')
                ->onDelete('restrict');
            $table->foreignId('pricing_plan_id')
                ->constrained('pricing_plans')
                ->onDelete('restrict');
            $table->date('started_at');
            $table->date('renewal_date');
            $table->enum('status', ['active', 'grace_period', 'expired'])->default('active');
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 2. Subscription History (audit ledger of plan assignments)
        Schema::create('subscription_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->onDelete('cascade');
            $table->foreignId('pricing_plan_id')
                ->constrained('pricing_plans')
                ->onDelete('restrict');
            $table->date('started_at');
            $table->date('renewal_date');
            $table->enum('status', ['active', 'grace_period', 'expired']);
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_histories');
        Schema::dropIfExists('subscriptions');
    }
};
