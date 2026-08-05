<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'pack_price')) {
                $table->decimal('pack_price', 10, 2)->nullable()->after('wholesale_price');
            }
            if (!Schema::hasColumn('items', 'carton_price')) {
                $table->decimal('carton_price', 10, 2)->nullable()->after('pack_price');
            }
            if (!Schema::hasColumn('items', 'pack_wholesale_price')) {
                $table->decimal('pack_wholesale_price', 10, 2)->nullable()->after('carton_price');
            }
            if (!Schema::hasColumn('items', 'carton_wholesale_price')) {
                $table->decimal('carton_wholesale_price', 10, 2)->nullable()->after('pack_wholesale_price');
            }
        });

        Schema::table('global_items', function (Blueprint $table) {
            if (!Schema::hasColumn('global_items', 'pack_price')) {
                $table->decimal('pack_price', 10, 2)->nullable()->after('wholesale_price');
            }
            if (!Schema::hasColumn('global_items', 'carton_price')) {
                $table->decimal('carton_price', 10, 2)->nullable()->after('pack_price');
            }
            if (!Schema::hasColumn('global_items', 'pack_wholesale_price')) {
                $table->decimal('pack_wholesale_price', 10, 2)->nullable()->after('carton_price');
            }
            if (!Schema::hasColumn('global_items', 'carton_wholesale_price')) {
                $table->decimal('carton_wholesale_price', 10, 2)->nullable()->after('pack_wholesale_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['pack_price', 'carton_price', 'pack_wholesale_price', 'carton_wholesale_price']);
        });

        Schema::table('global_items', function (Blueprint $table) {
            $table->dropColumn(['pack_price', 'carton_price', 'pack_wholesale_price', 'carton_wholesale_price']);
        });
    }
};
