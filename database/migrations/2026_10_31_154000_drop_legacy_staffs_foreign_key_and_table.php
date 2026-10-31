<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'staff_id')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropForeign(['staff_id']);
                });
            } catch (\Throwable $e) {
                // Foreign key may already be dropped or not exist on SQLite/existing DBs
            }
        }

        Schema::dropIfExists('staffs');
    }

    public function down(): void
    {
        // Legacy table removed intentionally
    }
};

