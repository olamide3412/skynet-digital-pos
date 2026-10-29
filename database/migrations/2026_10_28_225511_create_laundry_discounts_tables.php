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
        // 1. Create laundry_discounts table
        if (!Schema::hasTable('laundry_discounts')) {
            Schema::create('laundry_discounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->index();
                $table->string('scope', 20)->default('general')->index(); // 'general' (order-level) | 'service' (service-level)
                $table->unsignedBigInteger('service_id')->nullable()->index();
                $table->string('name', 150);
                $table->string('discount_type', 20)->default('percentage'); // 'percentage' | 'fixed'
                $table->decimal('discount_value', 12, 2)->default(0);
                $table->dateTime('starts_at')->index();
                $table->dateTime('ends_at')->index();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->string('notes', 500)->nullable();
                $table->timestamps();

                $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
                $table->foreign('service_id')->references('id')->on('laundry_services')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

                $table->index(['branch_id', 'scope', 'is_active']);
                $table->index(['service_id', 'is_active']);
            });
        }

        // 2. Add discount metadata to laundry_orders if not present
        if (Schema::hasTable('laundry_orders')) {
            Schema::table('laundry_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('laundry_orders', 'laundry_discount_id')) {
                    $table->unsignedBigInteger('laundry_discount_id')->nullable()->after('discount_type')->index();
                    $table->foreign('laundry_discount_id')->references('id')->on('laundry_discounts')->onDelete('set null');
                }
            });
        }

        // 3. Add original_price, discount_amount, laundry_discount_id to laundry_order_items if not present
        if (Schema::hasTable('laundry_order_items')) {
            Schema::table('laundry_order_items', function (Blueprint $table) {
                if (!Schema::hasColumn('laundry_order_items', 'original_price')) {
                    $table->decimal('original_price', 12, 2)->nullable()->after('pricing_type');
                }
                if (!Schema::hasColumn('laundry_order_items', 'discount_amount')) {
                    $table->decimal('discount_amount', 12, 2)->default(0)->after('quantity');
                }
                if (!Schema::hasColumn('laundry_order_items', 'laundry_discount_id')) {
                    $table->unsignedBigInteger('laundry_discount_id')->nullable()->after('discount_amount')->index();
                    $table->foreign('laundry_discount_id')->references('id')->on('laundry_discounts')->onDelete('set null');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('laundry_order_items')) {
            Schema::table('laundry_order_items', function (Blueprint $table) {
                if (Schema::hasColumn('laundry_order_items', 'laundry_discount_id')) {
                    $table->dropForeign(['laundry_discount_id']);
                    $table->dropColumn('laundry_discount_id');
                }
                if (Schema::hasColumn('laundry_order_items', 'discount_amount')) {
                    $table->dropColumn('discount_amount');
                }
                if (Schema::hasColumn('laundry_order_items', 'original_price')) {
                    $table->dropColumn('original_price');
                }
            });
        }

        if (Schema::hasTable('laundry_orders')) {
            Schema::table('laundry_orders', function (Blueprint $table) {
                if (Schema::hasColumn('laundry_orders', 'laundry_discount_id')) {
                    $table->dropForeign(['laundry_discount_id']);
                    $table->dropColumn('laundry_discount_id');
                }
            });
        }

        Schema::dropIfExists('laundry_discounts');
    }
};
