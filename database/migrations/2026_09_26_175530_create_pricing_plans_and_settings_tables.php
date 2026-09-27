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
        Schema::create('pricing_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('show_pricing_section')->default(true);
            $table->string('pricing_intro_heading')->nullable();
            $table->text('pricing_intro_text')->nullable();
            $table->string('whats_included_heading')->nullable();
            $table->string('addons_heading')->nullable();
            $table->text('addons_content')->nullable();
            $table->string('commercial_notes_heading')->nullable();
            $table->text('commercial_notes_content')->nullable();
            $table->string('currency_symbol', 10)->default('₦');
            $table->timestamps();
        });

        Schema::create('pricing_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->string('first_year_price');
            $table->string('renewal_price');
            $table->string('branches_limit');
            $table->string('users_limit');
            $table->string('ai_credits')->nullable();
            $table->string('support_level')->nullable();
            $table->boolean('is_highlighted')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pricing_plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_plan_id')->constrained('pricing_plans')->onDelete('cascade');
            $table->string('feature_text');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('pricing_included_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_text');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('pricing_value_props', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtext');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_plan_features');
        Schema::dropIfExists('pricing_plans');
        Schema::dropIfExists('pricing_included_items');
        Schema::dropIfExists('pricing_value_props');
        Schema::dropIfExists('pricing_settings');
    }
};
