<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\User;
use App\UserFunction;
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
            'organizational_function' => 'proviseur',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'agent')
            ->assertJsonPath('data.organizational_function', 'proviseur')
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
                'organizational_function' => 'directeur_dse',
            ])
            ->assertUnprocessable();
    }

    public function test_agent_cannot_create_users_or_assign_other_agents(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
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
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);

        $this->actingAs($admin)
            ->postJson('/api/etablissements/'.$etablissement->id.'/agents/'.$agent->id)
            ->assertCreated();

        $this->assertDatabaseHas('etablissement_agent', [
            'etablissement_id' => $etablissement->id,
            'agent_id' => $agent->id,
        ]);
    }

    public function test_superadmin_can_create_admin_accounts_with_administrative_functions(): void
    {
        Notification::fake();
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);

        $response = $this->actingAs($superadmin)->postJson('/api/users', [
            'name' => 'Directrice DSE',
            'email' => 'dse@example.com',
            'role' => 'admin',
            'organizational_function' => 'directeur_dse',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.organizational_function', 'directeur_dse');

        $this->assertDatabaseHas('users', [
            'email' => 'dse@example.com',
            'role' => 'admin',
            'organizational_function' => 'directeur_dse',
        ]);
    }

    public function test_admin_cannot_assign_an_administrative_function_to_an_agent(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'Agent SGSIS',
                'email' => 'agent-dse@example.com',
                'role' => 'agent',
                'organizational_function' => 'directeur_dse',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('organizational_function');

        $this->assertDatabaseMissing('users', [
            'email' => 'agent-dse@example.com',
        ]);
    }

    public function test_agent_function_must_match_establishment_type_when_assigned(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::DirecteurEcole,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);

        $this->actingAs($admin)
            ->postJson('/api/etablissements/'.$etablissement->id.'/agents/'.$agent->id)
            ->assertUnprocessable();

        $this->assertDatabaseMissing('etablissement_agent', [
            'etablissement_id' => $etablissement->id,
            'agent_id' => $agent->id,
        ]);
    }
}
