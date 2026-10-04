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
        Schema::table('commerce_integrations', function (Blueprint $table) {
            $table->string('client_id')->nullable()->unique()->after('status');
        });

        // Backfill any existing records
        $integrations = \Illuminate\Support\Facades\DB::table('commerce_integrations')->whereNull('client_id')->get();
        foreach ($integrations as $integration) {
            \Illuminate\Support\Facades\DB::table('commerce_integrations')
                ->where('id', $integration->id)
                ->update([
                    'client_id' => 'pos_cli_' . \Illuminate\Support\Str::random(24),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commerce_integrations', function (Blueprint $table) {
            $table->dropColumn('client_id');
        });
    }
};
