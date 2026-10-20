<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Populate organization_id on users where user belongs to a branch that has an organization_id
        DB::statement("
            UPDATE users 
            INNER JOIN branches ON users.branch_id = branches.id 
            SET users.organization_id = branches.organization_id 
            WHERE users.organization_id IS NULL 
              AND users.branch_id IS NOT NULL 
              AND branches.organization_id IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe to keep
    }
};
