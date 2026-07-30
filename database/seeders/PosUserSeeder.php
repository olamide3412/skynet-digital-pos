<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PosUserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator',
                'full_name' => 'System Administrator',
                'username' => 'admin',
                'email' => 'admin@skynetpos.com',
                'password' => Hash::make('admin123'),
                'is_active' => true,
                'is_admin' => true,
                'acct_tier' => 3,
                'role' => 'Administrator',
                'status' => 'Enable',
            ]
        );

        // Give admin all roles
        $roles = [
            'PosAccess',
            'PriceEdit',
            'DiscountApply',
            'SaleDelete',
            'SaleReturn',
            'StockAdjust',
            'ReportView',
            'PurchaseManage',
            'CustomerManage',
            'UserManage',
            'SettingsEdit',
        ];

        $roleIds = [];
        foreach ($roles as $roleName) {
            $role = \App\Models\Role::firstOrCreate(
                ['name' => $roleName],
                ['description' => 'System role for ' . $roleName]
            );
            $roleIds[] = $role->id;
        }

        $admin->roles()->sync($roleIds);

        // Cashier user
        User::updateOrCreate(
            ['username' => 'cashier1'],
            [
                'name' => 'Jane Cashier',
                'full_name' => 'Jane Cashier',
                'username' => 'cashier1',
                'email' => 'cashier@skynetpos.com',
                'password' => Hash::make('cashier123'),
                'is_active' => true,
                'is_admin' => false,
                'acct_tier' => 0,
                'role' => 'Staff',
                'status' => 'Enable',
            ]
        );
    }
}
