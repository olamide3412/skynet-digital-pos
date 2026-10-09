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
        Schema::table('ai_credit_topups', function (Blueprint $table) {
            $table->foreignId('credit_pack_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('ai_credit_packs')
                ->nullOnDelete();
            $table->decimal('amount_paid', 10, 2)->nullable()->after('credits_remaining');
            $table->string('currency', 10)->nullable()->after('amount_paid');
            $table->string('gateway', 30)->nullable()->after('currency');
            $table->string('gateway_reference')->nullable()->after('gateway');
            $table->string('status', 20)->default('successful')->after('gateway_reference');

            $table->index('gateway_reference');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_credit_topups', function (Blueprint $table) {
            $table->dropForeign(['credit_pack_id']);
            $table->dropIndex(['gateway_reference']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'credit_pack_id',
                'amount_paid',
                'currency',
                'gateway',
                'gateway_reference',
                'status',
            ]);
        });
    }
};
