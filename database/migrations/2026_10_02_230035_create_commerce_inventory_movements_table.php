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
        Schema::create('commerce_inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commerce_integration_id')->constrained('commerce_integrations')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('commerce_reservation_id')->nullable()->constrained('commerce_reservations')->nullOnDelete();
            $table->string('type'); // reservation_hold, reservation_release, reservation_confirm, stock_deduction, adjustment
            $table->integer('quantity');
            $table->integer('previous_stock');
            $table->integer('current_stock');
            $table->string('external_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'item_id'], 'comm_movements_branch_item_idx');
            $table->index(['commerce_integration_id', 'type'], 'comm_movements_integration_type_idx');
            $table->index('created_at', 'comm_movements_created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commerce_inventory_movements');
    }
};
