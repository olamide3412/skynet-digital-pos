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
        if (Schema::hasTable('branches') && !Schema::hasColumn('branches', 'public_catalog_enabled')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->boolean('public_catalog_enabled')->default(false)->after('is_ai_enabled');
            });
        }

        if (Schema::hasTable('items') && !Schema::hasColumn('items', 'hide_from_public_catalog')) {
            Schema::table('items', function (Blueprint $table) {
                $table->boolean('hide_from_public_catalog')->default(false)->after('image_path');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('branches') && Schema::hasColumn('branches', 'public_catalog_enabled')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropColumn('public_catalog_enabled');
            });
        }

        if (Schema::hasTable('items') && Schema::hasColumn('items', 'hide_from_public_catalog')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('hide_from_public_catalog');
            });
        }
    }
};
