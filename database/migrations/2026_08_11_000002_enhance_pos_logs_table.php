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
        Schema::table('pos_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('pos_logs', 'action_type')) {
                $table->string('action_type', 50)->default('info')->after('log');
            }
            if (!Schema::hasColumn('pos_logs', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('action_type');
            }
            if (!Schema::hasColumn('pos_logs', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
            if (!Schema::hasColumn('pos_logs', 'details')) {
                $table->json('details')->nullable()->after('user_agent');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_logs', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('pos_logs', 'details'))     $columns[] = 'details';
            if (Schema::hasColumn('pos_logs', 'user_agent'))  $columns[] = 'user_agent';
            if (Schema::hasColumn('pos_logs', 'ip_address'))  $columns[] = 'ip_address';
            if (Schema::hasColumn('pos_logs', 'action_type')) $columns[] = 'action_type';

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
