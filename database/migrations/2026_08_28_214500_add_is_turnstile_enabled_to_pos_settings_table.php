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
            if (!Schema::hasColumn('pos_settings', 'is_turnstile_enabled')) {
                $table->boolean('is_turnstile_enabled')->default(true)->after('is_offline_enabled');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            if (Schema::hasColumn('pos_settings', 'is_turnstile_enabled')) {
                $table->dropColumn('is_turnstile_enabled');
            }
        });
    }
};
