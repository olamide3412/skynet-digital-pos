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
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->string('quick_access_mode')->default('top_sellers')->after('sell_interface');
            $table->json('pinned_quick_access_items')->nullable()->after('quick_access_mode');
            $table->json('quick_access_category_ids')->nullable()->after('pinned_quick_access_items');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->dropColumn([
                'quick_access_mode',
                'pinned_quick_access_items',
                'quick_access_category_ids',
            ]);
        });
    }
};
