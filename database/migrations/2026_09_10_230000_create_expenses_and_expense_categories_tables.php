<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. expense_categories table
        if (!Schema::hasTable('expense_categories')) {
            Schema::create('expense_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('cascade');
                $table->string('name', 100);
                $table->timestamps();

                $table->index(['branch_id', 'name']);
            });

            // Seed default global categories (branch_id = null)
            $defaults = [
                'Rent',
                'Utilities',
                'Transport',
                'Salaries',
                'Repairs & Maintenance',
                'Miscellaneous',
            ];

            $now = now();
            foreach ($defaults as $catName) {
                DB::table('expense_categories')->insert([
                    'branch_id'  => null,
                    'name'       => $catName,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // 2. expenses table
        if (!Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->foreignId('expense_category_id')->constrained('expense_categories')->onDelete('cascade');
                $table->decimal('amount', 12, 2);
                $table->date('expense_date');
                $table->text('description')->nullable();
                $table->string('receipt_path')->nullable();
                $table->foreignId('logged_by')->constrained('users')->onDelete('cascade');
                $table->timestamps();

                $table->index(['branch_id', 'expense_date']);
                $table->index(['branch_id', 'expense_category_id']);
                $table->index(['branch_id', 'logged_by']);
            });
        }

        // 3. expense_revisions table (financial audit history)
        if (!Schema::hasTable('expense_revisions')) {
            Schema::create('expense_revisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('expense_id')->constrained('expenses')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->enum('action', ['created', 'updated', 'deleted']);
                $table->json('changes')->nullable();
                $table->string('reason')->nullable();
                $table->timestamps();

                $table->index(['expense_id', 'created_at']);
            });
        }

        // 4. Create Spatie Permissions
        $newPermissions = [
            'canLogExpense',
            'canManageExpenses',
            'canViewExpenseReports',
            'canViewExpiryReport',
        ];

        foreach ($newPermissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        // Assign to all existing branch-admin roles
        $adminRoles = Role::where('name', 'branch-admin')->get();
        foreach ($adminRoles as $role) {
            foreach ($newPermissions as $perm) {
                if (!$role->hasPermissionTo($perm)) {
                    $role->givePermissionTo($perm);
                }
            }
        }

        // Clear permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_revisions');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');

        $newPermissions = [
            'canLogExpense',
            'canManageExpenses',
            'canViewExpenseReports',
            'canViewExpiryReport',
        ];

        foreach ($newPermissions as $perm) {
            $record = Permission::where('name', $perm)->first();
            if ($record) {
                $record->delete();
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
