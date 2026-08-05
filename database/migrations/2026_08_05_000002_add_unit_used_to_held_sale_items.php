<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('held_sale_items') && !Schema::hasColumn('held_sale_items', 'unit_used')) {
            Schema::table('held_sale_items', function (Blueprint $table) {
                $table->string('unit_used', 50)->default('unit')->after('price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('held_sale_items') && Schema::hasColumn('held_sale_items', 'unit_used')) {
            Schema::table('held_sale_items', function (Blueprint $table) {
                $table->dropColumn('unit_used');
            });
        }
    }
};
