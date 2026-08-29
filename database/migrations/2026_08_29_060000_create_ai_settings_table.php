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
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('active_provider')->default('gemini'); // gemini, openai, deepseek, kimi, claude
            $table->text('encrypted_api_keys')->nullable(); // JSON object storing encrypted keys per provider
            $table->json('provider_models')->nullable(); // Custom model preferences per provider
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->integer('max_tokens')->default(2048);
            $table->boolean('is_system_enabled')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable(); // success, failed
            $table->text('last_test_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
