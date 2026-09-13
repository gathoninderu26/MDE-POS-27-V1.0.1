<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Product;
use Tests\TestCase;

class DemoAccountIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed', ['--class' => 'TenantSeeder']);
        $this->artisan('db:seed', ['--class' => 'BusinessDataSeeder']);
    }
    public function test_mock_and_clean_demo_tenants_are_isolated()
    {
        $mockTenant = Tenant::where('slug', 'nairobi-fresh-mart')->first();
        $cleanTenant = Tenant::where('slug', 'nairobi-fresh-clean')->first();

        $this->assertNotNull($mockTenant);
        $this->assertNotNull($cleanTenant);
        $this->assertTrue((bool) $mockTenant->is_demo);
        $this->assertTrue((bool) $cleanTenant->is_demo);

        $mockProductsCount = Product::where('tenant_id', $mockTenant->id)->count();
        $cleanProductsCount = Product::where('tenant_id', $cleanTenant->id)->count();

        $this->assertGreaterThan(0, $mockProductsCount, 'Mock tenant should have seeded products');
        $this->assertEquals(0, $cleanProductsCount, 'Clean tenant must have 0 products');
    }

    public function test_demo_login_with_clean_data_mode_routes_to_clean_tenant()
    {
        $response = $this->post('/demo-login', [
            'tenant' => 'nairobi-fresh-mart',
            'data_mode' => 'own',
            'role' => 'Cashier',
            'days' => 7,
        ]);

        $response->assertRedirect('/pos');
        $response->assertSessionHas('demo_session');

        $session = session('demo_session');
        $this->assertEquals('nairobi-fresh-clean', $session['tenant_slug']);
        $this->assertEquals('own', $session['data_mode']);
        $this->assertTrue($session['is_demo']);
    }

    public function test_demo_login_with_mock_data_mode_routes_to_mock_tenant()
    {
        $response = $this->post('/demo-login', [
            'tenant' => 'nairobi-fresh-mart',
            'data_mode' => 'mock',
            'role' => 'Manager',
            'days' => 7,
        ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('demo_session');

        $session = session('demo_session');
        $this->assertEquals('nairobi-fresh-mart', $session['tenant_slug']);
        $this->assertEquals('mock', $session['data_mode']);
        $this->assertTrue($session['is_demo']);
    }

    public function test_real_merchant_accounts_cannot_be_accessed_via_demo_login()
    {
        $realMerchant = Tenant::create([
            'name' => 'Acme Commercial Supermarket',
            'slug' => 'acme-commercial-supermarket',
            'email' => 'finance@acmecommercial.co.ke',
            'is_demo' => false,
            'status' => 'Active',
        ]);

        $response = $this->post('/demo-login', [
            'tenant' => 'acme-commercial-supermarket',
            'role' => 'Cashier',
        ]);

        $realMerchant->delete();

        // Must reject with 403 Forbidden
        $response->assertStatus(403);
    }
}
