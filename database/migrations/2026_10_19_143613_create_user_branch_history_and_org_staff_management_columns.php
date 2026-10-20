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
        Schema::create('user_branch_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('moved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'ended_at']);
            $table->index('branch_id');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('can_manage_staff')->default(true)->after('can_create_branches');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('disabled_at')->nullable()->after('is_active');
            $table->foreignId('disabled_by')->nullable()->after('disabled_at')->constrained('users')->nullOnDelete();
            $table->text('disabled_reason')->nullable()->after('disabled_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['disabled_by']);
            $table->dropColumn(['disabled_at', 'disabled_by', 'disabled_reason']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('can_manage_staff');
        });

        Schema::dropIfExists('user_branch_history');
    }
};
