<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('full_name', 100)->nullable()->after('name');
            $table->string('username', 50)->unique()->nullable()->after('full_name');
            $table->boolean('is_active')->default(true)->after('username');
            $table->boolean('is_super_admin')->default(false)->after('is_active');
            $table->foreignId('branch_id')
                ->nullable()
                ->after('is_super_admin')
                ->constrained('branches')
                ->nullOnDelete();
            $table->foreignId('staff_id')
                ->nullable()
                ->unique()
                ->after('branch_id')
                ->constrained('staffs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['staff_id']);
            $table->dropColumn([
                'full_name', 'username', 'is_active', 'is_super_admin',
                'branch_id', 'staff_id',
            ]);
        });
    }
};
