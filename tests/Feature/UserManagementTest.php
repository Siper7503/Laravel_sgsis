<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\User;
use App\UserRole;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_an_active_agent_and_send_setup_notifications(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Agent SGSIS',
            'email' => 'agent@example.com',
            'role' => 'agent',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'agent')
            ->assertJsonPath('data.is_active', true);

        $agent = User::query()->where('email', 'agent@example.com')->firstOrFail();

        $this->assertNull($agent->email_verified_at);
        Notification::assertSentTo($agent, VerifyEmail::class);
        Notification::assertSentTo($agent, ResetPassword::class);
    }

    public function test_admin_cannot_create_another_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'Autre admin',
                'email' => 'other-admin@example.com',
                'role' => 'admin',
            ])
            ->assertUnprocessable();
    }

    public function test_agent_cannot_create_users_or_assign_other_agents(): void
    {
        $agent = User::factory()->create(['role' => UserRole::Agent]);
        $otherAgent = User::factory()->create(['role' => UserRole::Agent]);
        $etablissement = Etablissement::factory()->create();

        $this->actingAs($agent)
            ->postJson('/api/users', [
                'name' => 'Usager',
                'email' => 'usager@example.com',
                'role' => 'usager',
            ])
            ->assertForbidden();

        $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/agents/'.$otherAgent->id)
            ->assertForbidden();
    }

    public function test_admin_can_assign_an_agent_to_an_establishment(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $agent = User::factory()->create(['role' => UserRole::Agent]);
        $etablissement = Etablissement::factory()->create();

        $this->actingAs($admin)
            ->postJson('/api/etablissements/'.$etablissement->id.'/agents/'.$agent->id)
            ->assertCreated();

        $this->assertDatabaseHas('etablissement_agent', [
            'etablissement_id' => $etablissement->id,
            'agent_id' => $agent->id,
        ]);
    }
}
