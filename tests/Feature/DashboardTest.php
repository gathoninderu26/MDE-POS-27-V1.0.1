<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $tenant = Tenant::factory()->create(['status' => 'Active']);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
    }
}
