<?php

namespace Tests\Feature;

use App\AnnualReportSections;
use App\Models\AnnualReport;
use App\Models\Etablissement;
use App\Models\User;
use App\UserFunction;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnualReportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_create_and_update_one_rentree_report_per_school_year(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::DirecteurEcole,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'primaire']);
        $agent->etablissements()->attach($etablissement);
        $data = $this->completeReportData();

        $created = $this->actingAs($agent)->postJson(
            '/api/etablissements/'.$etablissement->id.'/annual-reports',
            [
                'school_year' => '2026-2027',
                'data' => $data,
            ],
        );

        $created->assertCreated()
            ->assertJsonPath('data.report_type', 'rentree')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.data.identification.administrative_code', 'ETB-001');

        $report = AnnualReport::query()->firstOrFail();
        $this->assertDatabaseHas('annual_reports', [
            'id' => $report->id,
            'etablissement_id' => $etablissement->id,
            'school_year' => '2026-2027',
            'report_type' => 'rentree',
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('annual_report_events', [
            'annual_report_id' => $report->id,
            'actor_id' => $agent->id,
            'action' => 'draft_created',
        ]);

        $data['identification']['administrative_code'] = 'ETB-002';
        $this->putJson('/api/annual-reports/'.$report->id, ['data' => $data])
            ->assertOk()
            ->assertJsonPath('data.data.identification.administrative_code', 'ETB-002');

        $this->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
            'school_year' => '2026-2027',
            'data' => $data,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('annual_reports', 1);
        $this->assertDatabaseCount('annual_report_events', 2);
    }

    public function test_agent_cannot_create_or_view_reports_for_an_unassigned_establishment(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $otherAgent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $unassigned = Etablissement::factory()->create(['type' => 'secondaire']);
        $otherAgent->etablissements()->attach($unassigned);

        $otherReport = $this->actingAs($otherAgent)
            ->postJson('/api/etablissements/'.$unassigned->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $this->completeReportData(),
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$unassigned->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $this->completeReportData(),
            ])
            ->assertNotFound();

        $this->getJson('/api/annual-reports/'.$otherReport)->assertNotFound();
        $this->getJson('/api/annual-reports')->assertJsonCount(0, 'data');
    }

    public function test_report_requires_all_sections_before_submission(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);
        $agent->etablissements()->attach($etablissement);

        $reportId = $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => ['identification' => []],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->postJson('/api/annual-reports/'.$reportId.'/submit')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data');

        $this->assertDatabaseHas('annual_reports', [
            'id' => $reportId,
            'status' => 'draft',
        ]);
        $this->assertDatabaseCount('annual_report_events', 1);
    }

    public function test_report_moves_through_dse_and_dgss_reviews_and_becomes_read_only(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $dse = User::factory()->create([
            'role' => UserRole::Admin,
            'organizational_function' => UserFunction::DirecteurDse,
        ]);
        $dgss = User::factory()->create([
            'role' => UserRole::Admin,
            'organizational_function' => UserFunction::DirecteurDgss,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);
        $agent->etablissements()->attach($etablissement);

        $reportId = $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $this->completeReportData(),
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($agent)
            ->postJson('/api/annual-reports/'.$reportId.'/submit')
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted_to_dse');

        $this->postJson('/api/annual-reports/'.$reportId.'/dse-review', [
            'decision' => 'return',
            'comment' => 'Veuillez compléter les informations.',
        ])->assertForbidden();

        $this->actingAs($dse)
            ->postJson('/api/annual-reports/'.$reportId.'/dse-review', [
                'decision' => 'approve',
            ])
            ->assertUnprocessable();

        $this->actingAs($dse)
            ->postJson('/api/annual-reports/'.$reportId.'/dse-review', [
                'decision' => 'return',
                'comment' => 'Veuillez compléter les informations.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'returned_to_agent');

        $this->actingAs($agent)
            ->putJson('/api/annual-reports/'.$reportId, [
                'data' => $this->completeReportData(),
            ])
            ->assertOk();

        $this->postJson('/api/annual-reports/'.$reportId.'/submit')
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted_to_dse');

        $this->actingAs($dse)
            ->postJson('/api/annual-reports/'.$reportId.'/dse-review', [
                'decision' => 'forward',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted_to_dgss');

        $this->actingAs($dgss)
            ->postJson('/api/annual-reports/'.$reportId.'/dgss-review', [
                'decision' => 'return',
            ])
            ->assertUnprocessable();

        $this->actingAs($dgss)
            ->postJson('/api/annual-reports/'.$reportId.'/dgss-review', [
                'decision' => 'return',
                'comment' => 'Correction demandée avant validation finale.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'returned_to_dse');

        $this->actingAs($dse)
            ->postJson('/api/annual-reports/'.$reportId.'/dse-review', [
                'decision' => 'forward',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted_to_dgss');

        $this->actingAs($dgss)
            ->postJson('/api/annual-reports/'.$reportId.'/dgss-review', [
                'decision' => 'approve',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->actingAs($agent)
            ->putJson('/api/annual-reports/'.$reportId, [
                'data' => $this->completeReportData(),
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('annual_reports', [
            'id' => $reportId,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('annual_report_events', [
            'annual_report_id' => $reportId,
            'actor_id' => $dgss->id,
            'action' => 'approved_by_dgss',
            'to_status' => 'approved',
        ]);
        $this->assertDatabaseCount('annual_report_events', 9);
    }

    public function test_report_payload_rejects_unknown_top_level_sections(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);
        $agent->etablissements()->attach($etablissement);

        $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => ['unexpected_section' => []],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data');

        $this->assertDatabaseCount('annual_reports', 0);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function completeReportData(): array
    {
        $data = array_fill_keys(AnnualReportSections::KEYS, []);
        $data['identification'] = ['administrative_code' => 'ETB-001'];

        return $data;
    }
}
