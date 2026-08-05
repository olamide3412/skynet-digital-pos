<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->unique()->constrained('branches')->cascadeOnDelete();
            $table->boolean('is_price_editable')->default(false);
            $table->boolean('is_qty_deduction')->default(true);
            $table->integer('out_of_stock')->default(25);
            $table->boolean('is_check_expiration')->default(true);
            $table->boolean('is_show_buy_price')->default(false);
            $table->boolean('item_icon_preview')->default(false);
            $table->decimal('wholesale_profit_percent', 10, 2)->default(10.00);
            $table->decimal('consumer_profit_percent', 10, 2)->default(15.00);
            $table->enum('sell_interface', ['classic', 'gallery'])->default('classic');
            $table->enum('business_sector', ['health', 'commerce'])->default('commerce');
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_settings');
    }
};
