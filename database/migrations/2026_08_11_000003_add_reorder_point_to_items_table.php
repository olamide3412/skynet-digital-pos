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
        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'reorder_point')) {
                $table->integer('reorder_point')->default(10)->after('front_store_qty');
            }
            if (!Schema::hasColumn('items', 'reorder_unit')) {
                $table->string('reorder_unit', 20)->default('unit')->after('reorder_point');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('items', 'reorder_unit'))  $columns[] = 'reorder_unit';
            if (Schema::hasColumn('items', 'reorder_point')) $columns[] = 'reorder_point';

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
