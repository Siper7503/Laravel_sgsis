<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\User;
use App\UserFunction;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EtablissementAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_map_search_returns_all_matching_geolocated_establishments(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $matching = Etablissement::factory()->create([
            'nom' => 'École de Karpala',
            'arrondissement' => 'Bogodogo',
            'type' => 'primaire',
            'latitude' => 12.34,
            'longitude' => -1.52,
            'email' => 'private@example.com',
        ]);
        Etablissement::factory()->create([
            'nom' => 'Établissement secondaire de Karpala',
            'arrondissement' => 'Bogodogo',
            'type' => 'primaire',
            'actif' => false,
        ]);
        Etablissement::factory()->create([
            'nom' => 'École sans position',
            'latitude' => null,
            'longitude' => null,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/etablissements/map?q=karpala&arrondissement=Bogodogo&type=primaire')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('data.0.latitude', 12.34)
            ->assertJsonPath('data.0.longitude', -1.52)
            ->assertJsonMissing(['email' => 'private@example.com']);
    }

    public function test_usager_map_only_returns_active_geolocated_establishments(): void
    {
        $usager = User::factory()->create(['role' => UserRole::Usager]);
        $active = Etablissement::factory()->create(['nom' => 'École active']);
        Etablissement::factory()->create(['nom' => 'École inactive', 'actif' => false]);
        Etablissement::factory()->create([
            'nom' => 'École sans position',
            'latitude' => null,
            'longitude' => null,
        ]);

        $this->actingAs($usager)
            ->getJson('/api/etablissements/map')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);
    }

    public function test_usager_establishment_list_excludes_inactive_records(): void
    {
        $usager = User::factory()->create(['role' => UserRole::Usager]);
        $active = Etablissement::factory()->create(['actif' => true]);
        Etablissement::factory()->create(['actif' => false]);

        $this->actingAs($usager)
            ->getJson('/api/etablissements')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);
    }

    public function test_admin_cannot_create_an_establishment_without_both_coordinates(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->postJson('/api/etablissements', [
                'code' => 'ETB-001',
                'nom' => 'École de test',
                'type' => 'primaire',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);

        $this->assertDatabaseMissing('etablissements', ['code' => 'ETB-001']);
    }

    public function test_admin_can_create_an_establishment_with_valid_coordinates(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->postJson('/api/etablissements', [
                'code' => 'ETB-002',
                'nom' => 'École de test',
                'type' => 'primaire',
                'latitude' => 12.34,
                'longitude' => -1.52,
            ])
            ->assertCreated()
            ->assertJsonPath('data.latitude', 12.34)
            ->assertJsonPath('data.longitude', -1.52);

        $this->assertDatabaseHas('etablissements', [
            'code' => 'ETB-002',
            'latitude' => 12.34,
            'longitude' => -1.52,
        ]);
    }

    public function test_admin_cannot_update_only_one_coordinate(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $etablissement = Etablissement::factory()->create([
            'latitude' => 12.0,
            'longitude' => -1.0,
        ]);

        $this->actingAs($admin)
            ->putJson('/api/etablissements/'.$etablissement->id, ['latitude' => 13.0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('longitude');

        $this->assertDatabaseHas('etablissements', [
            'id' => $etablissement->id,
            'latitude' => 12.0,
            'longitude' => -1.0,
        ]);
    }

    public function test_agent_cannot_be_assigned_to_an_establishment_without_coordinates(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create([
            'type' => 'secondaire',
            'latitude' => null,
            'longitude' => null,
        ]);

        $this->actingAs($admin)
            ->postJson('/api/etablissements/'.$etablissement->id.'/agents/'.$agent->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('etablissement');

        $this->assertDatabaseMissing('etablissement_agent', [
            'etablissement_id' => $etablissement->id,
            'agent_id' => $agent->id,
        ]);
    }

    public function test_admin_can_remove_an_agent_assignment_when_legacy_establishment_has_no_coordinates(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create([
            'type' => 'secondaire',
            'latitude' => null,
            'longitude' => null,
        ]);
        $etablissement->agents()->attach($agent);

        $this->actingAs($admin)
            ->deleteJson('/api/etablissements/'.$etablissement->id.'/agents/'.$agent->id)
            ->assertOk();

        $this->assertDatabaseMissing('etablissement_agent', [
            'etablissement_id' => $etablissement->id,
            'agent_id' => $agent->id,
        ]);
    }

    public function test_agent_cannot_change_establishment_coordinates(): void
    {
        $agent = User::factory()->create(['role' => UserRole::Agent]);
        $etablissement = Etablissement::factory()->create();
        $agent->etablissements()->attach($etablissement);

        $this->actingAs($agent)
            ->putJson('/api/etablissements/'.$etablissement->id, [
                'latitude' => 12.34,
                'longitude' => -1.52,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('etablissements', [
            'id' => $etablissement->id,
            'latitude' => $etablissement->latitude,
            'longitude' => $etablissement->longitude,
        ]);
    }

    public function test_usager_can_list_establishments_but_cannot_create_them(): void
    {
        $usager = User::factory()->create(['role' => UserRole::Usager]);
        Etablissement::factory()->count(2)->create();

        $this->actingAs($usager)
            ->getJson('/api/etablissements')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($usager)
            ->postJson('/api/etablissements', [
                'code' => 'ETB-001',
                'nom' => 'Ecole Exemple',
                'type' => 'primaire',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('etablissements', ['code' => 'ETB-001']);
    }

    public function test_agent_only_sees_and_updates_assigned_establishments(): void
    {
        $agent = User::factory()->create(['role' => UserRole::Agent]);
        $assigned = Etablissement::factory()->create();
        $unassigned = Etablissement::factory()->create();
        $agent->etablissements()->attach($assigned);

        $this->actingAs($agent)
            ->getJson('/api/etablissements')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assigned->id);

        $this->actingAs($agent)
            ->getJson('/api/etablissements/'.$unassigned->id)
            ->assertNotFound();

        $this->actingAs($agent)
            ->putJson('/api/etablissements/'.$assigned->id, ['nom' => 'Ecole Modifiee'])
            ->assertOk();

        $this->assertDatabaseHas('etablissements', [
            'id' => $assigned->id,
            'nom' => 'Ecole Modifiee',
        ]);

        $this->actingAs($agent)
            ->putJson('/api/etablissements/'.$unassigned->id, ['nom' => 'Interdit'])
            ->assertNotFound();
    }

    public function test_agent_cannot_delete_an_assigned_establishment(): void
    {
        $agent = User::factory()->create(['role' => UserRole::Agent]);
        $etablissement = Etablissement::factory()->create();
        $agent->etablissements()->attach($etablissement);

        $this->actingAs($agent)
            ->deleteJson('/api/etablissements/'.$etablissement->id)
            ->assertForbidden();

        $this->assertModelExists($etablissement);
    }
}
