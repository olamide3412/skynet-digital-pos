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
        // 1. Chart of Accounts
        if (!Schema::hasTable('accounts')) {
            Schema::create('accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('code', 50)->index();
                $table->string('name', 255);
                $table->string('type', 30)->index(); // asset, liability, equity, revenue, expense
                $table->string('sub_type', 50)->nullable();
                $table->unsignedBigInteger('parent_id')->nullable()->index();
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true)->index();
                $table->string('normal_balance', 10)->default('debit'); // debit, credit
                $table->timestamps();

                $table->index(['organization_id', 'branch_id', 'code']);
            });
        }

        // 2. Financial Periods (Month locking)
        if (!Schema::hasTable('financial_periods')) {
            Schema::create('financial_periods', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('name', 100)->nullable();
                $table->date('starts_on');
                $table->date('ends_on');
                $table->string('status', 20)->default('open')->index(); // open, closed
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->index(['branch_id', 'starts_on', 'ends_on']);
            });
        }

        // 3. Journal Entries Header
        if (!Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('entry_number', 50)->index();
                $table->date('entry_date')->index();
                $table->text('description')->nullable();
                $table->string('source_type', 100)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('event', 100)->nullable();
                $table->string('status', 20)->default('posted')->index(); // posted, reversed, draft
                $table->unsignedBigInteger('reversal_of_id')->nullable()->index();
                $table->unsignedBigInteger('financial_period_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamps();

                $table->index(['branch_id', 'entry_date']);
                $table->index(['source_type', 'source_id', 'event'], 'idx_journal_source_event');
            });
        }

        // 4. Journal Lines (Debits and Credits)
        if (!Schema::hasTable('journal_lines')) {
            Schema::create('journal_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('journal_entry_id')->index();
                $table->unsignedBigInteger('account_id')->index();
                $table->decimal('debit', 15, 2)->default(0.00);
                $table->decimal('credit', 15, 2)->default(0.00);
                $table->string('memo', 255)->nullable();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->unsignedBigInteger('vendor_id')->nullable()->index();
                $table->timestamps();

                $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('cascade');
                $table->foreign('account_id')->references('id')->on('accounts')->onDelete('restrict');
            });
        }

        // 5. Bank Accounts
        if (!Schema::hasTable('bank_accounts')) {
            Schema::create('bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('account_id')->index();
                $table->string('name', 255);
                $table->string('bank_name', 255)->nullable();
                $table->string('account_number', 100)->nullable();
                $table->string('type', 30)->default('cash'); // cash, bank, pos_clearing
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            });
        }

        // 6. Posting Rules
        if (!Schema::hasTable('posting_rules')) {
            Schema::create('posting_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('event', 100)->index();
                $table->string('payment_method', 50)->nullable()->index();
                $table->unsignedBigInteger('debit_account_id');
                $table->unsignedBigInteger('credit_account_id');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('debit_account_id')->references('id')->on('accounts')->onDelete('cascade');
                $table->foreign('credit_account_id')->references('id')->on('accounts')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posting_rules');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('financial_periods');
        Schema::dropIfExists('accounts');
    }
};
