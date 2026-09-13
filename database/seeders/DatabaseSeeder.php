<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Runs only required data — super admin + demo tenants.
     * Safe to run multiple times (all seeders are idempotent).
     */
    public function run(): void
    {
        $this->call([
            SuperAdminSeeder::class,
            TenantSeeder::class,
            BusinessDataSeeder::class,
        ]);
    }
}
