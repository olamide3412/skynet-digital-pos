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
        if (!Schema::hasTable('admin_broadcasts')) {
            Schema::create('admin_broadcasts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title');
                $table->text('message');
                $table->string('target_type', 50)->default('all'); // all, branch, branches, role, branch_role
                $table->json('target_data')->nullable(); // { branch_ids: [...], roles: [...] }
                $table->json('channels')->nullable(); // ['in_app', 'email']
                $table->unsignedInteger('in_app_count')->default(0);
                $table->unsignedInteger('email_sent_count')->default(0);
                $table->unsignedInteger('email_failed_count')->default(0);
                $table->unsignedInteger('no_email_count')->default(0);
                $table->json('delivery_logs')->nullable();
                $table->string('status', 30)->default('completed'); // pending, processing, completed, failed
                $table->timestamps();

                $table->index(['target_type', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_broadcasts');
    }
};
