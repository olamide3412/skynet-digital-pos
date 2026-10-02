<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Safety verification: abort if any branch is unassigned
        $unassignedCount = DB::table('branches')->whereNull('organization_id')->count();
        if ($unassignedCount > 0) {
            throw new \RuntimeException(
                "Cannot alter branches.organization_id to NOT NULL: {$unassignedCount} branch(es) have no organization assigned. " .
                "Run 'php artisan organizations:backfill' first before migrating."
            );
        }

        // 2. Alter column to NOT NULL
        Schema::table('branches', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->change();
        });
    }
};
