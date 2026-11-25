<?php

namespace Tests\Feature;

use App\Models\AnnualReport;
use App\Models\Etablissement;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_aggregated_establishment_and_report_statistics(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $primary = Etablissement::factory()->create([
            'type' => 'primaire',
            'actif' => true,
            'latitude' => 12.34,
            'longitude' => -1.52,
        ]);
        Etablissement::factory()->create([
            'type' => 'secondaire',
            'actif' => false,
            'latitude' => null,
            'longitude' => null,
        ]);
        User::factory()->create(['role' => UserRole::Agent]);
        User::factory()->create(['role' => UserRole::Usager]);
        User::factory()->create(['role' => UserRole::Superadmin]);
        AnnualReport::create([
            'etablissement_id' => $primary->id,
            'created_by' => $admin->id,
            'school_year' => '2026-2027',
            'report_type' => 'rentree',
            'form_version' => 1,
            'status' => 'approved',
            'data' => [],
        ]);

        $this->actingAs($admin)
            ->getJson('/api/statistiques')
            ->assertOk()
            ->assertJsonPath('data.etablissements.total', 2)
            ->assertJsonPath('data.etablissements.actifs', 1)
            ->assertJsonPath('data.etablissements.inactifs', 1)
            ->assertJsonPath('data.etablissements.geolocalises', 1)
            ->assertJsonPath('data.etablissements.sans_coordonnees', 1)
            ->assertJsonPath('data.etablissements.par_type.primaire', 1)
            ->assertJsonPath('data.etablissements.par_type.secondaire', 1)
            ->assertJsonPath('data.rapports_annuels.total', 1)
            ->assertJsonPath('data.rapports_annuels.par_statut.approved', 1)
            ->assertJsonPath('data.rapports_annuels.par_type.rentree', 1)
            ->assertJsonPath('data.rapports_annuels.par_annee_scolaire.0.school_year', '2026-2027')
            ->assertJsonPath('data.utilisateurs.total', 4)
            ->assertJsonPath('data.utilisateurs.par_role.admin', 1)
            ->assertJsonPath('data.utilisateurs.par_role.agent', 1)
            ->assertJsonPath('data.utilisateurs.par_role.usager', 1)
            ->assertJsonPath('data.utilisateurs.par_role.superadmin', 1);
    }

    public function test_agent_cannot_view_administrative_statistics(): void
    {
        $agent = User::factory()->create(['role' => UserRole::Agent]);

        $this->actingAs($agent)
            ->getJson('/api/statistiques')
            ->assertForbidden();
    }

    public function test_superadmin_can_view_statistics_when_no_records_exist(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);

        $this->actingAs($superadmin)
            ->getJson('/api/statistiques')
            ->assertOk()
            ->assertJsonPath('data.etablissements.total', 0)
            ->assertJsonPath('data.rapports_annuels.total', 0)
            ->assertJsonPath('data.rapports_annuels.par_statut.draft', 0)
            ->assertJsonPath('data.rapports_annuels.par_type.sortie', 0)
            ->assertJsonPath('data.utilisateurs.total', 1)
            ->assertJsonPath('data.utilisateurs.par_role.superadmin', 1)
            ->assertJsonPath('data.utilisateurs.par_role.admin', 0)
            ->assertJsonPath('data.utilisateurs.par_role.agent', 0)
            ->assertJsonPath('data.utilisateurs.par_role.usager', 0);
    }
}
