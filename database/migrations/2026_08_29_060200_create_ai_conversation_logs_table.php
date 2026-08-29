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
        Schema::create('ai_conversation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_id')->index();
            $table->string('role')->default('user'); // user, assistant, system
            $table->text('message');
            $table->string('detected_intent')->nullable(); // sales_summary, top_sellers, stock_status, tax_summary, imei_lookup, conflict_summary, general
            $table->json('structured_payload')->nullable(); // Raw query results passed to AI
            $table->string('provider_used')->nullable();
            $table->string('model_used')->nullable();
            $table->integer('tokens_used')->nullable();
            $table->integer('execution_time_ms')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_conversation_logs');
    }
};
