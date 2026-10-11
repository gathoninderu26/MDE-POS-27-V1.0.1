<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Skip if super admin already exists
        if (User::where('is_super_admin', true)->exists()) {
            $this->command->info('Super admin already exists — skipping.');

            return;
        }

        User::create([
            'name' => 'MDE Platform Admin',
            'email' => 'superadmin@mdepos.co.ke',
            'password' => Hash::make('SuperAdmin@2026!'),
            'tenant_id' => null,
            'role' => 'Super Admin',
            'is_super_admin' => true,
            'status' => 'Active',
            'email_verified_at' => now(),

        ]);

        $this->command->info('✅ Super Admin created: superadmin@mdepos.co.ke / SuperAdmin@2026!');
    }
}
