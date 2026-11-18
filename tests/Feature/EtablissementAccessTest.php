<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EtablissementAccessTest extends TestCase
{
    use RefreshDatabase;

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
