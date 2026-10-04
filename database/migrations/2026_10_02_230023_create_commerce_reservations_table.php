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
        Schema::create('commerce_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commerce_integration_id')->constrained('commerce_integrations')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('external_order_reference');
            $table->enum('status', ['pending', 'confirmed', 'released', 'expired'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['commerce_integration_id', 'external_order_reference'], 'comm_res_integration_ext_ref_unique');
            $table->index(['status', 'expires_at'], 'comm_res_status_expires_at_idx');
            $table->index(['branch_id', 'status'], 'comm_res_branch_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commerce_reservations');
    }
};
