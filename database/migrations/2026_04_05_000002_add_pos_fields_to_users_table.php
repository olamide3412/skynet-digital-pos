<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('full_name', 50)->nullable()->after('name');
            $table->string('username', 30)->unique()->nullable()->after('full_name');
            $table->boolean('is_active')->default(true)->after('username');
            $table->boolean('is_admin')->default(false)->after('is_active');
            $table->integer('acct_tier')->default(0)->after('is_admin');
            $table->foreignId('staff_id')->nullable()->unique()->constrained('staffs')->nullOnDelete()->after('acct_tier');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
            $table->dropColumn(['full_name', 'username', 'is_active', 'is_admin', 'acct_tier', 'staff_id']);
        });
    }
};
