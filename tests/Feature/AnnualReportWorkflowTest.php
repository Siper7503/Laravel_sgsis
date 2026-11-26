<?php

namespace Tests\Feature;

use App\AnnualReportSections;
use App\AnnualReportType;
use App\EtablissementType;
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
        $data = $this->primaryReportData();

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

    public function test_partial_draft_update_preserves_sections_not_in_the_request(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::DirecteurEcole,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'primaire']);
        $agent->etablissements()->attach($etablissement);
        $data = $this->primaryReportData();

        $report = $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $data,
            ])
            ->assertCreated()
            ->json('data');

        $changedIdentification = $data['identification'];
        $changedIdentification['administrative_code'] = 'ETB-UPDATED';

        $this->putJson('/api/annual-reports/'.$report['id'], [
            'data' => ['identification' => $changedIdentification],
        ])
            ->assertOk()
            ->assertJsonPath('data.data.identification.administrative_code', 'ETB-UPDATED')
            ->assertJsonPath('data.data.effectifs_eleves', $data['effectifs_eleves']);

        $this->assertSame(
            $data['effectifs_eleves'],
            AnnualReport::query()->findOrFail($report['id'])->data['effectifs_eleves'],
        );
    }

    public function test_agent_can_create_one_rentree_and_one_sortie_report_for_the_same_school_year(): void
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
                'data' => $this->completeReportData(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.report_type', 'rentree');

        $this->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
            'report_type' => 'sortie',
            'school_year' => '2026-2027',
            'data' => $this->completeReportData(AnnualReportType::Sortie),
        ])
            ->assertCreated()
            ->assertJsonPath('data.report_type', 'sortie')
            ->assertJsonPath('data.data.resultats_examens.examens.0.candidats.filles', 45);

        $this->assertDatabaseCount('annual_reports', 2);
    }

    public function test_agent_cannot_create_a_report_for_non_consecutive_school_years(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);
        $agent->etablissements()->attach($etablissement);

        $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2029',
                'data' => $this->completeReportData(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('school_year');

        $this->assertDatabaseCount('annual_reports', 0);
    }

    public function test_primary_draft_accepts_only_relevant_preparation_activities(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::DirecteurEcole,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'primaire']);
        $agent->etablissements()->attach($etablissement);
        $data = $this->primaryReportData();
        $data['preparation_rentree'] = [
            'activites' => [
                'rencontre_autorites' => [
                    'realisee' => true,
                    'date_periode' => 'Septembre 2026',
                    'observations' => null,
                ],
            ],
            'autres' => [],
        ];

        $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $data,
            ])
            ->assertCreated()
            ->assertJsonPath('data.data.preparation_rentree.activites.rencontre_autorites.realisee', true);
    }

    public function test_primary_draft_accepts_declared_management_bodies_and_members(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::DirecteurEcole,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'primaire']);
        $agent->etablissements()->attach($etablissement);
        $data = $this->primaryReportData();
        $data['organes_gestion'] = [
            'instances' => [[
                'nom' => 'Conseil de l’école',
                'existe' => true,
                'membres' => [[
                    'nom_complet' => 'Aminata Sawadogo',
                    'titre' => 'Présidente',
                    'statut' => 'Parent d’élève',
                    'contact' => '70000000',
                ]],
            ], [
                'nom' => 'Comité local',
                'existe' => false,
                'motif_absence' => 'En cours de mise en place',
            ]],
        ];

        $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $data,
            ])
            ->assertCreated()
            ->assertJsonPath('data.data.organes_gestion.instances.0.membres.0.nom_complet', 'Aminata Sawadogo')
            ->assertJsonPath('data.data.organes_gestion.instances.1.existe', false);
    }

    public function test_primary_draft_rejects_secondary_only_sections(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::DirecteurEcole,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'primaire']);
        $agent->etablissements()->attach($etablissement);
        $data = $this->primaryReportData();
        $data['effectifs_edi'] = [];

        $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $data,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data');
    }

    public function test_secondary_draft_rejects_primary_level_shape(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);
        $agent->etablissements()->attach($etablissement);
        $data = $this->completeReportData();
        $data['effectifs_eleves']['niveaux'] = [];

        $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $data,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data.effectifs_eleves');
    }

    public function test_primary_report_cannot_be_submitted_before_its_form_is_defined(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::DirecteurEcole,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'primaire']);
        $agent->etablissements()->attach($etablissement);
        $reportData = $this->primaryReportData();
        $reportData['effectifs_eleves'] = [
            'niveaux' => [
                [
                    'niveau' => 'CP1',
                    'classes_pedagogiques' => 2,
                    'filles' => 31,
                    'garcons' => 28,
                    'salles_classe' => 2,
                ],
            ],
            'infrastructures' => ['batiments' => 3],
        ];
        $reportData['personnel_enseignant'] = [
            'effectif_total' => ['enseignantes' => 4, 'enseignants' => 3],
            'par_niveau' => [
                ['niveau' => 'CP1', 'enseignantes' => 1, 'enseignants' => 1],
            ],
        ];
        $reportData['personnel_direction'] = [
            'directeur' => [
                'nom_complet' => 'Awa Sawadogo',
                'sexe' => 'femme',
                'qualification' => 'CAP primaire',
                'assure_classe' => true,
            ],
        ];

        $reportId = $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $reportData,
            ])
            ->assertCreated()
            ->assertJsonPath('data.data.effectifs_eleves.infrastructures.batiments', 3)
            ->assertJsonPath('data.data.personnel_enseignant.effectif_total.enseignantes', 4)
            ->assertJsonPath('data.data.personnel_enseignant.par_niveau.0.enseignantes', 1)
            ->assertJsonPath('data.data.personnel_direction.directeur.nom_complet', 'Awa Sawadogo')
            ->assertJsonPath('data.data.personnel_direction.directeur.assure_classe', true)
            ->assertJsonPath('data.data.personnel_administration.personnels.0.fonction', 'Secrétaire')
            ->json('data.id');

        $this->postJson('/api/annual-reports/'.$reportId.'/submit')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data')
            ->assertJsonPath(
                'errors.data.0',
                'La soumission des rapports primaires sera activée après la définition et la validation de leur formulaire.',
            );

        $this->assertDatabaseHas('annual_reports', [
            'id' => $reportId,
            'status' => 'draft',
        ]);
    }

    public function test_primary_draft_rejects_negative_level_staff_and_infrastructure_counts(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::DirecteurEcole,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'primaire']);
        $agent->etablissements()->attach($etablissement);
        $reportData = $this->primaryReportData();
        $reportData['effectifs_eleves'] = [
            'niveaux' => [[
                'niveau' => 'CP1',
                'classes_pedagogiques' => 1,
                'filles' => 10,
                'garcons' => 12,
                'salles_classe' => 1,
            ]],
            'infrastructures' => ['batiments' => 1],
        ];
        $reportData['personnel_enseignant'] = [
            'effectif_total' => ['enseignantes' => 4, 'enseignants' => 3],
            'par_niveau' => [[
                'niveau' => 'CP1',
                'enseignantes' => -1,
                'enseignants' => 1,
            ]],
        ];
        $reportData['personnels_appui_soutien']['fonctions'][0]['femmes'] = -1;

        $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $reportData,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'data.personnel_enseignant.par_niveau.0.enseignantes',
                'data.personnels_appui_soutien.fonctions.0.femmes',
            ]);

        $this->assertDatabaseCount('annual_reports', 0);
    }

    public function test_primary_draft_rejects_duplicate_level_rows(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::DirecteurEcole,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'primaire']);
        $agent->etablissements()->attach($etablissement);
        $reportData = $this->primaryReportData();
        $reportData['effectifs_eleves']['niveaux'][] = $reportData['effectifs_eleves']['niveaux'][0];
        $reportData['personnel_enseignant']['par_niveau'][] = $reportData['personnel_enseignant']['par_niveau'][0];

        $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $reportData,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'data.effectifs_eleves.niveaux.1.niveau',
                'data.personnel_enseignant.par_niveau.1.niveau',
            ]);

        $this->assertDatabaseCount('annual_reports', 0);
    }

    public function test_primary_sortie_draft_rejects_negative_cep_results(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::DirecteurEcole,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'primaire']);
        $agent->etablissements()->attach($etablissement);
        $reportData = $this->primaryReportData();
        $reportData['resultats_examens'] = [
            'examens' => [[
                'examen' => 'CEP',
                'candidats' => ['filles' => 20, 'garcons' => 18],
                'admis' => ['filles' => -1, 'garcons' => 16],
            ]],
        ];

        $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'report_type' => 'sortie',
                'school_year' => '2026-2027',
                'data' => $reportData,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data.resultats_examens.examens.0.admis.filles');

        $this->assertDatabaseCount('annual_reports', 0);
    }

    public function test_sortie_report_cannot_be_submitted_when_admitted_count_exceeds_candidates(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);
        $agent->etablissements()->attach($etablissement);
        $reportData = $this->completeReportData(AnnualReportType::Sortie);
        $reportData['resultats_examens']['examens'][0]['admis']['filles'] = 46;

        $reportId = $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'report_type' => 'sortie',
                'school_year' => '2026-2027',
                'data' => $reportData,
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
    }

    public function test_rentree_report_cannot_be_submitted_when_contest_admissions_exceed_attendance(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);
        $agent->etablissements()->attach($etablissement);
        $reportData = $this->completeReportData();
        $reportData['resultats_concours']['concours'][0]['admis']['filles'] = 5;
        $reportData['resultats_concours']['concours'][0]['presents']['filles'] = 4;

        $reportId = $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $reportData,
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
    }

    public function test_agent_cannot_save_negative_teaching_staff_counts(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);
        $agent->etablissements()->attach($etablissement);
        $reportData = $this->completeReportData();
        $reportData['personnel_enseignant']['public'][0]['permanents']['femmes'] = -1;

        $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $reportData,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data.personnel_enseignant.public.0.permanents.femmes');

        $this->assertDatabaseCount('annual_reports', 0);
    }

    public function test_agent_cannot_save_management_staff_with_an_unsupported_sex_value(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);
        $agent->etablissements()->attach($etablissement);
        $reportData = $this->completeReportData();
        $reportData['personnel_direction']['public'][0]['sexe'] = 'X';

        $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $reportData,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data.personnel_direction.public.0.sexe');

        $this->assertDatabaseCount('annual_reports', 0);
    }

    public function test_agent_cannot_save_negative_administration_staff_counts(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);
        $agent->etablissements()->attach($etablissement);
        $reportData = $this->completeReportData();
        $reportData['personnel_administration']['public'][0]['snd']['hommes'] = -1;

        $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $reportData,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data.personnel_administration.public.0.snd.hommes');

        $this->assertDatabaseCount('annual_reports', 0);
    }

    public function test_agent_cannot_save_negative_specific_staff_counts(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);
        $agent->etablissements()->attach($etablissement);
        $reportData = $this->completeReportData();
        $reportData['personnels_specifiques']['public']['bibliothecaires']['femmes'] = -1;

        $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $reportData,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data.personnels_specifiques.public.bibliothecaires.femmes');

        $this->assertDatabaseCount('annual_reports', 0);
    }

    public function test_agent_cannot_save_negative_support_staff_counts(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);
        $agent->etablissements()->attach($etablissement);
        $reportData = $this->completeReportData();
        $reportData['personnels_appui_soutien']['public']['agent_public']['gardien_vigile']['femmes'] = -1;

        $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $reportData,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'data.personnels_appui_soutien.public.agent_public.gardien_vigile.femmes',
            );

        $this->assertDatabaseCount('annual_reports', 0);
    }

    public function test_submission_requires_a_reason_when_a_start_of_year_activity_was_not_realized(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);
        $agent->etablissements()->attach($etablissement);
        $reportData = $this->completeReportData();
        $reportData['preparation_rentree']['activites']['elaboration_budget']['realisee'] = false;
        $reportData['preparation_rentree']['activites']['elaboration_budget']['observations'] = null;

        $reportId = $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $reportData,
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
    }

    public function test_submission_requires_the_motto_text_when_the_establishment_has_a_motto(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);
        $agent->etablissements()->attach($etablissement);
        $reportData = $this->completeReportData();
        $reportData['organisation_etablissement']['devise']['texte'] = null;

        $reportId = $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $reportData,
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
    }

    public function test_submission_rejects_a_partnership_without_its_contact_information(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);
        $agent->etablissements()->attach($etablissement);
        $reportData = $this->completeReportData();
        $reportData['partenariats'] = [[
            'type' => 'Partenaire local',
            'denomination' => 'Association scolaire',
            'domaines_action' => 'Appui pédagogique',
            'date_mise_en_oeuvre' => 'Septembre 2026',
            'responsable' => 'Mariam Sawadogo',
        ]];

        $reportId = $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $reportData,
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
    }

    public function test_agent_cannot_save_numeric_text_in_a_difficulty_description(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);
        $agent->etablissements()->attach($etablissement);
        $reportData = $this->completeReportData();
        $reportData['difficultes_solutions']['domaines']['pedagogique']['difficultes'] = ['Erreur'];

        $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $reportData,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data.difficultes_solutions.domaines.pedagogique.difficultes');

        $this->assertDatabaseCount('annual_reports', 0);
    }

    public function test_agent_cannot_save_negative_vocational_student_counts(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);
        $agent->etablissements()->attach($etablissement);
        $reportData = $this->completeReportData();
        $reportData['eleves_metiers']['metiers'] = [[
            'metier' => 'Mécanique',
            'filles' => 2,
            'garcons' => -1,
        ]];

        $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $reportData,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data.eleves_metiers.metiers.0.garcons');

        $this->assertDatabaseCount('annual_reports', 0);
    }

    public function test_agent_cannot_save_negative_edi_counts(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);
        $agent->etablissements()->attach($etablissement);
        $reportData = $this->completeReportData();
        $reportData['effectifs_edi']['post_primaire'] = [[
            'niveau' => '3e',
            'filles' => -1,
            'garcons' => 2,
        ]];

        $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $reportData,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data.effectifs_edi.post_primaire.0.filles');

        $this->assertDatabaseCount('annual_reports', 0);
    }

    public function test_submission_rejects_duplicate_teaching_discipline_rows_in_one_category(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);
        $agent->etablissements()->attach($etablissement);
        $reportData = $this->completeReportData();
        $duplicateRow = $reportData['personnel_enseignant']['public'][0];
        $duplicateRow['discipline'] = mb_strtolower($duplicateRow['discipline']);
        $reportData['personnel_enseignant']['public'][] = $duplicateRow;

        $reportId = $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $reportData,
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
    }

    public function test_agent_cannot_save_negative_student_enrollment_counts(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);
        $agent->etablissements()->attach($etablissement);
        $reportData = $this->completeReportData();
        $reportData['effectifs_eleves']['secondaire'][0]['filles'] = -1;

        $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $reportData,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data.effectifs_eleves.secondaire.0.filles');

        $this->assertDatabaseCount('annual_reports', 0);
    }

    public function test_submission_rejects_duplicate_level_series_enrollment_rows(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'organizational_function' => UserFunction::Proviseur,
        ]);
        $etablissement = Etablissement::factory()->create(['type' => 'secondaire']);
        $agent->etablissements()->attach($etablissement);
        $reportData = $this->completeReportData();
        $reportData['effectifs_eleves']['secondaire'][] = $reportData['effectifs_eleves']['secondaire'][0];

        $reportId = $this->actingAs($agent)
            ->postJson('/api/etablissements/'.$etablissement->id.'/annual-reports', [
                'school_year' => '2026-2027',
                'data' => $reportData,
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
     * @return array<string, mixed>
     */
    private function completeReportData(AnnualReportType $reportType = AnnualReportType::Rentree): array
    {
        $data = array_fill_keys(AnnualReportSections::keysForType($reportType), []);
        $data['identification'] = ['administrative_code' => 'ETB-001'];
        $data['effectifs_eleves'] = [
            'post_primaire' => [
                [
                    'niveau' => '3e',
                    'nombre_classes' => 4,
                    'filles' => 52,
                    'garcons' => 48,
                ],
            ],
            'secondaire' => [
                [
                    'niveau' => '2nde',
                    'serie' => 'A',
                    'nombre_classes' => 2,
                    'filles' => 45,
                    'garcons' => 38,
                ],
            ],
            'infrastructures' => [
                'salles_abri_precaire' => 0,
                'salles_emprunt' => 1,
                'salles_non_utilisees' => 0,
            ],
        ];
        $data['effectifs_edi'] = [
            'post_primaire' => [
                ['niveau' => '3e', 'filles' => 1, 'garcons' => 2],
            ],
            'secondaire' => [
                ['niveau' => '2nde', 'serie' => 'A', 'filles' => 0, 'garcons' => 0],
            ],
        ];
        $data['resultats_concours'] = [
            'concours' => array_map(
                fn (string $contest): array => [
                    'concours' => $contest,
                    'inscrits' => ['filles' => 0, 'garcons' => 0],
                    'presents' => ['filles' => 0, 'garcons' => 0],
                    'admis' => ['filles' => 0, 'garcons' => 0],
                ],
                ['entree_2nde', 'entree_1ere_bep', 'entree_1ere_bac_pro'],
            ),
        ];
        $data['personnel_enseignant'] = [
            'public' => [
                [
                    'discipline' => 'Français',
                    'permanents' => ['femmes' => 3, 'hommes' => 5],
                    'non_permanents' => ['femmes' => 1, 'hommes' => 0],
                    'vacataires' => ['femmes' => 0, 'hommes' => 0],
                    'postes_a_pourvoir' => 0,
                ],
            ],
            'prive' => [],
        ];
        $data['personnel_direction'] = [
            'public' => [
                [
                    'fonction' => 'Proviseur',
                    'nom_complet' => 'Aminata Ouédraogo',
                    'sexe' => 'femme',
                    'titre_capacite' => 'CAPES',
                    'disciplines_enseignees' => ['Français'],
                    'observations' => null,
                ],
            ],
            'prive' => [],
        ];
        $data['personnel_administration'] = [
            'public' => [
                [
                    'fonction' => 'Conseiller d’éducation',
                    'fonctionnaire' => ['femmes' => 1, 'hommes' => 1],
                    'snd' => ['femmes' => 0, 'hommes' => 0],
                    'cooperant' => ['femmes' => 0, 'hommes' => 0],
                    'benevole' => ['femmes' => 0, 'hommes' => 0],
                    'contractuel_etablissement' => ['femmes' => 0, 'hommes' => 0],
                    'autres' => ['femmes' => 0, 'hommes' => 0],
                    'postes_a_pourvoir' => 0,
                ],
            ],
            'prive' => [
                [
                    'fonction' => 'Secrétaire',
                    'permanent' => ['femmes' => 1, 'hommes' => 0],
                    'cooperant' => ['femmes' => 0, 'hommes' => 0],
                    'benevole' => ['femmes' => 0, 'hommes' => 0],
                    'contractuel_etablissement' => ['femmes' => 0, 'hommes' => 0],
                    'autres' => ['femmes' => 0, 'hommes' => 0],
                    'postes_a_pourvoir' => 0,
                ],
            ],
        ];
        $specificStaffCategories = [
            'bibliothecaires',
            'techniciens_superieurs_labo_atelier',
            'laborantins',
            'agents_action_sociale',
            'personnel_medico_scolaire',
        ];
        $data['personnels_specifiques'] = [
            'public' => array_fill_keys($specificStaffCategories, ['femmes' => 0, 'hommes' => 0]),
            'prive' => array_fill_keys($specificStaffCategories, ['femmes' => 0, 'hommes' => 0]),
        ];
        $supportStaffJobs = [
            'gardien_vigile',
            'agent_liaison',
            'cantiniere',
            'chauffeur',
            'reprographe',
            'manoeuvre',
        ];
        $supportEmploymentRows = array_fill_keys(
            ['agent_public', 'agent_communal', 'contractuel_volontaire'],
            array_fill_keys($supportStaffJobs, ['femmes' => 0, 'hommes' => 0]) + ['autres' => []],
        );
        $data['personnels_appui_soutien'] = [
            'public' => $supportEmploymentRows,
            'prive' => $supportEmploymentRows,
        ];
        $preparationActivityKeys = [
            'elaboration_budget',
            'rencontre_conseil_ecole',
            'rencontre_autorites',
            'preparation_emplois_temps',
            'affectation_eleves_concours',
            'affectation_personnels_convenances',
        ];
        $data['preparation_rentree'] = [
            'activites' => array_fill_keys($preparationActivityKeys, [
                'realisee' => true,
                'date_periode' => 'Septembre 2026',
                'observations' => null,
            ]),
            'autres' => [],
        ];
        $startOfYearActivityKeys = [
            'conseil_direction',
            'entretien_reparations',
            'nettoyage_domaine_scolaire',
            'conseil_gestion',
            'commission_recrutement',
            'remise_emplois_temps',
            'preparation_cahiers_registres',
            'conseil_etablissement',
            'conseil_professeurs',
            'conseil_discipline',
            'conseils_enseignement',
            'accueil_installation_eleves',
        ];
        $data['deroulement_rentree'] = [
            'activites' => array_fill_keys($startOfYearActivityKeys, [
                'realisee' => true,
                'date_periode' => 'Octobre 2026',
                'observations' => null,
            ]),
            'autres' => [],
        ];
        $data['organisation_etablissement'] = [
            'devise' => ['existe' => true, 'texte' => 'Travail et discipline'],
            'charte' => true,
            'reglement_interieur_specifique' => true,
            'projet_etablissement' => false,
        ];
        $difficultyDomains = [
            'pedagogique',
            'ressources_humaines',
            'financier',
            'matieres_oeuvre',
            'infrastructures_equipements',
        ];
        $data['difficultes_solutions'] = [
            'domaines' => array_fill_keys($difficultyDomains, [
                'difficultes' => null,
                'solutions_envisagees' => null,
            ]),
            'autres' => [],
        ];
        $data['eleves_metiers'] = ['metiers' => []];

        if ($reportType === AnnualReportType::Sortie) {
            $data['resultats_examens'] = [
                'examens' => [
                    [
                        'examen' => 'Examen de fin d’année',
                        'candidats' => ['filles' => 45, 'garcons' => 38],
                        'admis' => ['filles' => 40, 'garcons' => 34],
                    ],
                ],
            ];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function primaryReportData(): array
    {
        $data = $this->completeReportData();
        $data['effectifs_eleves'] = [
            'niveaux' => [[
                'niveau' => 'CP1',
                'classes_pedagogiques' => 2,
                'filles' => 31,
                'garcons' => 28,
                'salles_classe' => 2,
            ]],
            'infrastructures' => ['batiments' => 3],
        ];
        $data['personnel_enseignant'] = [
            'effectif_total' => ['enseignantes' => 4, 'enseignants' => 3],
            'par_niveau' => [[
                'niveau' => 'CP1',
                'enseignantes' => 1,
                'enseignants' => 1,
            ]],
        ];
        $data['personnel_direction'] = [
            'directeur' => [
                'nom_complet' => 'Awa Sawadogo',
                'sexe' => 'femme',
                'qualification' => 'CAP primaire',
                'assure_classe' => true,
            ],
        ];
        $data['personnel_administration'] = [
            'personnels' => [[
                'fonction' => 'Secrétaire',
                'femmes' => 1,
                'hommes' => 0,
            ]],
        ];
        $data['personnels_appui_soutien'] = [
            'fonctions' => [[
                'fonction' => 'gardien_vigile',
                'femmes' => 0,
                'hommes' => 1,
            ]],
            'autres' => [[
                'designation' => 'Aide communautaire',
                'femmes' => 1,
                'hommes' => 0,
            ]],
        ];

        return array_intersect_key(
            $data,
            array_flip(AnnualReportSections::keysForType(AnnualReportType::Rentree, EtablissementType::Primaire)),
        );
    }
}
