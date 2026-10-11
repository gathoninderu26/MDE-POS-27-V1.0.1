<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'country' => 'Kenya',
            'currency' => 'KES',
            'business_type' => 'Supermarket',
            'plan' => 'Pro',
            'status' => 'Active',
            'enabled_modules' => ['pos', 'sales', 'products', 'inventory', 'customers', 'suppliers', 'purchases', 'expenses', 'reports'],
        ];
    }
}
