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
        Schema::create('commerce_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('branch_id')->unique()->constrained('branches')->cascadeOnDelete();
            $table->string('name');
            $table->string('platform')->default('custom'); // shopify, woocommerce, custom, etc.
            $table->enum('status', ['active', 'inactive', 'revoked'])->default('active');
            $table->string('api_key_hash')->nullable();
            $table->json('settings')->nullable();
            $table->string('sync_cursor')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('webhook_url')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commerce_integrations');
    }
};
