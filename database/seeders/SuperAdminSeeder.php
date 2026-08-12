<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'superadmin@eewtechpos.com'],
            [
                'name' => 'Super Admin',
                'full_name' => 'EEWTECH Super Administrator',
                'username' => 'superadmin',
                'email' => 'superadmin@eewtechpos.com',
                'password' => Hash::make('SuperAdmin@2026!'),
                'is_active' => true,
                'is_super_admin' => true,
                'branch_id' => null, // Super Admin has no branch
            ]
        );

        $this->command->info('Super Admin account created: superadmin@skynetpos.com');
        $this->command->warn('⚠ Remember to change the Super Admin password after first login!');
    }
}
