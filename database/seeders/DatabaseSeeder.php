<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,  // Must be first: creates Spatie permissions
            BranchSeeder::class,          // Creates sample branches
            SuperAdminSeeder::class,      // Creates the platform Super Admin
            GlobalItemSeeder::class,      // Seeds 50 items into Global Master Item Pool
        ]);
    }
}
