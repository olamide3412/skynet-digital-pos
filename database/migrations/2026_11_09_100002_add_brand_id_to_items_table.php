<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('brand_id')
                ->nullable()
                ->after('category_id')
                ->constrained('brands')
                ->nullOnDelete();
        });

        if (Schema::hasTable('global_items') && !Schema::hasColumn('global_items', 'brand_hint')) {
            Schema::table('global_items', function (Blueprint $table) {
                $table->string('brand_hint')->nullable()->after('category_hint');
            });
        }
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });

        if (Schema::hasTable('global_items') && Schema::hasColumn('global_items', 'brand_hint')) {
            Schema::table('global_items', function (Blueprint $table) {
                $table->dropColumn('brand_hint');
            });
        }
    }
};

