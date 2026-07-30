<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (!Schema::hasColumn('vendors', 'name')) {
                $table->string('name')->after('id')->nullable();
            }
            if (!Schema::hasColumn('vendors', 'company_name')) {
                $table->string('company_name')->after('name')->nullable();
            }
            if (!Schema::hasColumn('vendors', 'phone')) {
                $table->string('phone')->after('company_name')->nullable();
            }
            if (!Schema::hasColumn('vendors', 'status')) {
                $table->string('status')->default('Active')->after('address');
            }
            if (Schema::hasColumn('vendors', 'vendor_name')) {
                $table->string('vendor_name')->nullable()->change();
            }
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'po_number')) {
                $table->string('po_number')->after('id')->nullable()->unique();
            }
            if (!Schema::hasColumn('purchase_orders', 'expected_date')) {
                $table->date('expected_date')->after('order_date')->nullable();
            }
            if (!Schema::hasColumn('purchase_orders', 'subtotal')) {
                $table->decimal('subtotal', 10, 2)->default(0)->after('expected_date');
            }
            if (!Schema::hasColumn('purchase_orders', 'shipping_cost')) {
                $table->decimal('shipping_cost', 10, 2)->default(0)->after('subtotal');
            }
            if (!Schema::hasColumn('purchase_orders', 'discount')) {
                $table->decimal('discount', 10, 2)->default(0)->after('shipping_cost');
            }
            if (!Schema::hasColumn('purchase_orders', 'notes')) {
                $table->text('notes')->after('total_amount')->nullable();
            }
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('status', 50)->default('Pending')->change();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_order_items', 'item_name')) {
                $table->string('item_name')->after('item_id')->nullable();
            }
            if (!Schema::hasColumn('purchase_order_items', 'qty')) {
                $table->integer('qty')->default(1)->after('item_name');
            }
            if (!Schema::hasColumn('purchase_order_items', 'cost')) {
                $table->decimal('cost', 10, 2)->default(0)->after('qty');
            }
            if (!Schema::hasColumn('purchase_order_items', 'total')) {
                $table->decimal('total', 10, 2)->default(0)->after('cost');
            }
        });

        Schema::table('received_items', function (Blueprint $table) {
            if (!Schema::hasColumn('received_items', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('item_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('received_items', 'qty')) {
                $table->integer('qty')->default(0)->after('user_id');
            }
            if (!Schema::hasColumn('received_items', 'cost')) {
                $table->decimal('cost', 10, 2)->default(0)->after('qty');
            }
        });
    }

    public function down(): void
    {
    }
};
