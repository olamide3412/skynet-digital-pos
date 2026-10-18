<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. product_groups
        if (!Schema::hasTable('product_groups')) {
            Schema::create('product_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->string('name');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['organization_id', 'name'], 'idx_pg_org_name');
            });
        }

        // 2. product_group_items
        if (!Schema::hasTable('product_group_items')) {
            Schema::create('product_group_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_group_id')->constrained('product_groups')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
                $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('link_source', 50)->default('manual'); // manual, auto_barcode, auto_name_confirmed, copy, receiving_queue
                $table->timestamps();

                // Safety rule: one item per branch per group
                $table->unique(['product_group_id', 'branch_id'], 'uq_prod_group_branch');
                // One item belongs to at most one group
                $table->unique('item_id', 'uq_prod_group_item');
                $table->index(['branch_id', 'product_group_id'], 'idx_pgi_branch_group');
            });
        }

        // 3. product_link_logs
        if (!Schema::hasTable('product_link_logs')) {
            Schema::create('product_link_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->string('action', 100); // link, unlink, merge, copy, bulk_confirm, undo_batch, branch_move_unlink
                $table->json('payload')->nullable();
                $table->string('batch_id', 100)->nullable()->index('idx_pll_batch_id');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['organization_id', 'created_at'], 'idx_pll_org_created');
            });
        }

        // 4. inter_branch_transfers
        if (!Schema::hasTable('inter_branch_transfers')) {
            Schema::create('inter_branch_transfers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->string('reference', 100)->unique('uq_ibt_reference');
                $table->foreignId('source_branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('destination_branch_id')->constrained('branches')->cascadeOnDelete();
                $table->string('from_location', 50)->default('back_store'); // back_store | front_store
                $table->string('to_location', 50)->nullable();             // back_store | front_store (set on accept)
                $table->string('status', 50)->default('pending');          // pending, blocked, accepted, cancelled
                $table->text('blocked_reason')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('sent_at')->useCurrent();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->index(['organization_id', 'status'], 'idx_ibt_org_status');
                $table->index(['source_branch_id', 'status'], 'idx_ibt_source_status');
                $table->index(['destination_branch_id', 'status'], 'idx_ibt_dest_status');
                $table->index(['status', 'sent_at'], 'idx_ibt_status_sent');
            });
        }

        // 5. inter_branch_transfer_items
        if (!Schema::hasTable('inter_branch_transfer_items')) {
            Schema::create('inter_branch_transfer_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inter_branch_transfer_id')->constrained('inter_branch_transfers')->cascadeOnDelete();
                $table->foreignId('product_group_id')->nullable()->constrained('product_groups')->nullOnDelete();
                $table->foreignId('source_item_id')->constrained('items')->cascadeOnDelete();
                $table->foreignId('destination_item_id')->nullable()->constrained('items')->nullOnDelete();
                $table->integer('quantity_sent');
                $table->integer('quantity_received')->nullable();
                $table->integer('shortfall_quantity')->default(0);
                $table->decimal('sender_cost_price', 15, 2)->default(0);
                $table->string('status', 50)->default('pending'); // pending, accepted, discrepancy
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['inter_branch_transfer_id', 'status'], 'idx_ibti_transfer_status');
            });
        }

        // 6. plan_capabilities: feature_inter_branch_transfers
        if (Schema::hasTable('plan_capabilities')) {
            if (!Schema::hasColumn('plan_capabilities', 'feature_inter_branch_transfers')) {
                Schema::table('plan_capabilities', function (Blueprint $table) {
                    $table->boolean('feature_inter_branch_transfers')->default(false)->after('feature_accounting');
                });
            }

            // Set true for Silver, Gold, and Enterprise (Decision F)
            DB::table('plan_capabilities')
                ->whereIn('pricing_plan_id', function ($query) {
                    $query->select('id')
                        ->from('pricing_plans')
                        ->whereIn('name', ['Silver', 'Gold', 'Enterprise']);
                })
                ->update(['feature_inter_branch_transfers' => true]);

            DB::table('plan_capabilities')
                ->whereIn('pricing_plan_id', function ($query) {
                    $query->select('id')
                        ->from('pricing_plans')
                        ->whereNotIn('name', ['Silver', 'Gold', 'Enterprise']);
                })
                ->update(['feature_inter_branch_transfers' => false]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('plan_capabilities') && Schema::hasColumn('plan_capabilities', 'feature_inter_branch_transfers')) {
            Schema::table('plan_capabilities', function (Blueprint $table) {
                $table->dropColumn('feature_inter_branch_transfers');
            });
        }

        Schema::dropIfExists('inter_branch_transfer_items');
        Schema::dropIfExists('inter_branch_transfers');
        Schema::dropIfExists('product_link_logs');
        Schema::dropIfExists('product_group_items');
        Schema::dropIfExists('product_groups');
    }
};
