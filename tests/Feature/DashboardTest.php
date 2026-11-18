<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_agent_is_redirected_to_the_agent_dashboard(): void
    {
        $agent = User::factory()->create(['role' => UserRole::Agent]);

        $this->actingAs($agent)
            ->get(route('dashboard'))
            ->assertRedirectToRoute('agent.dashboard');

        $this->get(route('agent.dashboard'))->assertOk();
    }

    public function test_admins_share_the_admin_dashboard(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertRedirectToRoute('admin.dashboard');

        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_inactive_accounts_cannot_open_the_dashboard(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_unverified_accounts_are_sent_to_email_verification(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }
}
