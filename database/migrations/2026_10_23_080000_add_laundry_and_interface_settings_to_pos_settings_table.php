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
            $table->boolean('laundry_enabled')->default(false)->after('business_sector');
            $table->boolean('pos_enabled')->default(true)->after('laundry_enabled');
            $table->string('default_interface', 20)->default('pos')->after('pos_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->dropColumn(['laundry_enabled', 'pos_enabled', 'default_interface']);
        });
    }
};
