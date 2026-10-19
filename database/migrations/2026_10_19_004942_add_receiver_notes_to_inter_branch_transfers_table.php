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
        Schema::table('inter_branch_transfers', function (Blueprint $table) {
            if (!Schema::hasColumn('inter_branch_transfers', 'receiver_notes')) {
                $table->text('receiver_notes')->nullable()->after('notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inter_branch_transfers', function (Blueprint $table) {
            if (Schema::hasColumn('inter_branch_transfers', 'receiver_notes')) {
                $table->dropColumn('receiver_notes');
            }
        });
    }
};
