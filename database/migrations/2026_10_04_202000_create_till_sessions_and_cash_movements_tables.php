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
        // 1. Add till settings to pos_settings table
        Schema::table('pos_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('pos_settings', 'require_till_opening')) {
                $table->boolean('require_till_opening')->default(true)->after('is_offline_enabled');
            }
            if (!Schema::hasColumn('pos_settings', 'print_till_slip')) {
                $table->boolean('print_till_slip')->default(false)->after('require_till_opening');
            }
        });

        // 2. Create till_sessions table
        if (!Schema::hasTable('till_sessions')) {
            Schema::create('till_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('opening_amount', 15, 2)->default(0);
                $table->timestamp('opened_at')->useCurrent();
                $table->decimal('closing_amount', 15, 2)->nullable();
                $table->decimal('expected_amount', 15, 2)->nullable();
                $table->decimal('variance', 15, 2)->nullable();
                $table->string('status', 20)->default('open')->index();
                $table->timestamp('closed_at')->nullable();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('opening_note')->nullable();
                $table->text('closing_note')->nullable();
                $table->string('offline_session_id', 64)->nullable()->index();
                $table->string('sync_status', 20)->default('synced')->index();
                $table->timestamps();

                $table->index(['branch_id', 'status']);
                $table->index(['cashier_id', 'status']);
                $table->index(['branch_id', 'cashier_id', 'status']);
            });
        }

        // 3. Create till_cash_movements table
        if (!Schema::hasTable('till_cash_movements')) {
            Schema::create('till_cash_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('till_session_id')->constrained('till_sessions')->cascadeOnDelete();
                $table->string('type', 20); // 'add' | 'remove'
                $table->decimal('amount', 15, 2);
                $table->string('reason', 255)->nullable();
                $table->foreignId('performed_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->index(['till_session_id', 'type']);
            });
        }

        // 4. Add till_session_id to sales table
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'till_session_id')) {
                $table->foreignId('till_session_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('till_sessions')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'till_session_id')) {
                $table->dropForeign(['till_session_id']);
                $table->dropColumn('till_session_id');
            }
        });

        Schema::dropIfExists('till_cash_movements');
        Schema::dropIfExists('till_sessions');

        Schema::table('pos_settings', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('pos_settings', 'require_till_opening')) {
                $drop[] = 'require_till_opening';
            }
            if (Schema::hasColumn('pos_settings', 'print_till_slip')) {
                $drop[] = 'print_till_slip';
            }
            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
