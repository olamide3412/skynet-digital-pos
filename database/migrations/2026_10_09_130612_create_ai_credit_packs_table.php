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
        Schema::create('ai_credit_packs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('credits');
            $table->decimal('price', 10, 2);
            $table->string('currency', 10)->default('NGN');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // Seed default starter packs
        DB::table('ai_credit_packs')->insert([
            [
                'name'       => 'Starter Pack (500 Credits)',
                'credits'    => 500,
                'price'      => 2500.00,
                'currency'   => 'NGN',
                'is_active'  => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'       => 'Growth Pack (1,500 Credits)',
                'credits'    => 1500,
                'price'      => 6500.00,
                'currency'   => 'NGN',
                'is_active'  => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'       => 'Pro Pack (5,000 Credits)',
                'credits'    => 5000,
                'price'      => 18000.00,
                'currency'   => 'NGN',
                'is_active'  => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'       => 'Enterprise Booster (10,000 Credits)',
                'credits'    => 10000,
                'price'      => 32000.00,
                'currency'   => 'NGN',
                'is_active'  => true,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_credit_packs');
    }
};
