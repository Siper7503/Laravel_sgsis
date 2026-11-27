<?php

namespace App;

use Illuminate\Validation\Rule;

final class AnnualReportDataValidator
{
    private const CONCOURS = [
        'entree_2nde',
        'entree_1ere_bep',
        'entree_1ere_bac_pro',
    ];

    private const PUBLIC_ADMINISTRATION_STATUSES = [
        'fonctionnaire',
        'snd',
        'cooperant',
        'benevole',
        'contractuel_etablissement',
        'autres',
    ];

    private const PRIVATE_ADMINISTRATION_STATUSES = [
        'permanent',
        'cooperant',
        'benevole',
        'contractuel_etablissement',
        'autres',
    ];

    private const SPECIFIC_STAFF_CATEGORIES = [
        'bibliothecaires',
        'techniciens_superieurs_labo_atelier',
        'laborantins',
        'agents_action_sociale',
        'personnel_medico_scolaire',
    ];

    private const SUPPORT_STAFF_STATUSES = [
        'agent_public',
        'agent_communal',
        'contractuel_volontaire',
    ];

    private const SUPPORT_STAFF_JOBS = [
        'gardien_vigile',
        'agent_liaison',
        'cantiniere',
        'chauffeur',
        'reprographe',
        'manoeuvre',
    ];

    private const PREPARATION_ACTIVITIES = [
        'elaboration_budget',
        'rencontre_conseil_ecole',
        'rencontre_autorites',
        'preparation_emplois_temps',
        'affectation_eleves_concours',
        'affectation_personnels_convenances',
    ];

    private const START_OF_YEAR_ACTIVITIES = [
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

    private const DIFFICULTY_DOMAINS = [
        'pedagogique',
        'ressources_humaines',
        'financier',
        'matieres_oeuvre',
        'infrastructures_equipements',
    ];

    private const PRIMARY_LEVELS = ['CP1', 'CP2', 'CE1', 'CE2', 'CM1', 'CM2'];

    private const SCHOOL_DAYS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

    private const PRIMARY_SCHOOL_PERIODS = [
        '7h-8h', '8h-9h', '9h-10h', '10h-11h', '11h-12h', '14h-15h', '15h-16h', '16h-17h30',
    ];

    /**
     * @return array<int, string>
     */
    public static function keysForType(
        AnnualReportType $reportType,
        ?EtablissementType $etablissementType = null,
        ?string $etablissementStatus = null,
    ): array {
        $keys = $etablissementType === EtablissementType::Primaire
            ? [
                'identification', 'localisation', 'informations_generales', 'partenariats',
                'organisation_etablissement', 'effectifs_eleves', 'personnel_enseignant',
                'personnel_direction', 'personnel_administration', 'personnels_appui_soutien',
                'preparation_rentree', 'deroulement_rentree', 'organes_gestion',
                'organisation_pedagogique', 'difficultes_solutions',
            ]
            : AnnualReportSections::KEYS;

        if ($reportType === AnnualReportType::Sortie) {
            $keys[] = 'resultats_examens';
        }

        if ($etablissementType === EtablissementType::Primaire && $etablissementStatus === 'public') {
            $keys[] = 'situation_financiere';
        }

        return $keys;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function draftValidationRules(
        AnnualReportType $reportType,
        ?EtablissementType $etablissementType = null,
        ?string $etablissementStatus = null,
    ): array {
        if ($etablissementType === EtablissementType::Primaire) {
            $rules = [
                'data.effectifs_eleves' => ['sometimes', 'array:niveaux,infrastructures'],
                'data.effectifs_eleves.niveaux' => ['sometimes', 'array'],
                'data.effectifs_eleves.niveaux.*' => [
                    'sometimes', 'array:niveau,classes_pedagogiques,filles,garcons,salles_classe',
                ],
                'data.effectifs_eleves.niveaux.*.niveau' => [
                    'sometimes', 'string', Rule::in(self::PRIMARY_LEVELS), 'distinct',
                ],
                'data.effectifs_eleves.niveaux.*.classes_pedagogiques' => ['sometimes', 'integer', 'min:0'],
                'data.effectifs_eleves.niveaux.*.filles' => ['sometimes', 'integer', 'min:0'],
                'data.effectifs_eleves.niveaux.*.garcons' => ['sometimes', 'integer', 'min:0'],
                'data.effectifs_eleves.niveaux.*.salles_classe' => ['sometimes', 'integer', 'min:0'],
                'data.effectifs_eleves.infrastructures' => ['sometimes', 'array:batiments'],
                'data.effectifs_eleves.infrastructures.batiments' => ['sometimes', 'integer', 'min:0'],
                'data.personnel_enseignant' => ['sometimes', 'array:effectif_total,par_niveau'],
                'data.personnel_enseignant.effectif_total' => ['sometimes', 'array:enseignantes,enseignants'],
                'data.personnel_enseignant.effectif_total.enseignantes' => ['sometimes', 'integer', 'min:0'],
                'data.personnel_enseignant.effectif_total.enseignants' => ['sometimes', 'integer', 'min:0'],
                'data.personnel_enseignant.par_niveau' => ['sometimes', 'array'],
                'data.personnel_enseignant.par_niveau.*' => ['sometimes', 'array:niveau,enseignantes,enseignants'],
                'data.personnel_enseignant.par_niveau.*.niveau' => [
                    'sometimes', 'string', Rule::in(self::PRIMARY_LEVELS), 'distinct',
                ],
                'data.personnel_enseignant.par_niveau.*.enseignantes' => ['sometimes', 'integer', 'min:0'],
                'data.personnel_enseignant.par_niveau.*.enseignants' => ['sometimes', 'integer', 'min:0'],
                'data.personnel_direction' => ['sometimes', 'array:directeur'],
                'data.personnel_direction.directeur' => [
                    'sometimes', 'array:nom_complet,sexe,qualification,assure_classe',
                ],
                'data.personnel_direction.directeur.nom_complet' => ['sometimes', 'string', 'max:200'],
                'data.personnel_direction.directeur.sexe' => ['sometimes', Rule::in(['femme', 'homme'])],
                'data.personnel_direction.directeur.qualification' => ['sometimes', 'string', 'max:150'],
                'data.personnel_direction.directeur.assure_classe' => ['sometimes', 'boolean'],
                'data.partenariats' => ['sometimes', 'array', 'max:100'],
                'data.partenariats.*' => [
                    'sometimes', 'array:type,denomination,domaines_action,date_mise_en_oeuvre,responsable,contact',
                ],
                'data.partenariats.*.type' => ['sometimes', 'string', 'max:100'],
                'data.partenariats.*.denomination' => ['sometimes', 'string', 'max:200'],
                'data.partenariats.*.domaines_action' => ['sometimes', 'string', 'max:500'],
                'data.partenariats.*.date_mise_en_oeuvre' => ['sometimes', 'string', 'max:100'],
                'data.partenariats.*.responsable' => ['sometimes', 'string', 'max:200'],
                'data.partenariats.*.contact' => ['sometimes', 'string', 'max:100'],
                'data.organisation_etablissement' => [
                    'sometimes', 'array:devise,charte,reglement_interieur_specifique,projet_etablissement',
                ],
                'data.organisation_etablissement.devise' => ['sometimes', 'array:existe,texte'],
                'data.organisation_etablissement.devise.existe' => ['sometimes', 'boolean'],
                'data.organisation_etablissement.devise.texte' => ['sometimes', 'nullable', 'string', 'max:255'],
                'data.organisation_etablissement.charte' => ['sometimes', 'boolean'],
                'data.organisation_etablissement.reglement_interieur_specifique' => ['sometimes', 'boolean'],
                'data.organisation_etablissement.projet_etablissement' => ['sometimes', 'boolean'],
                'data.personnel_administration' => ['sometimes', 'array:personnels'],
                'data.personnel_administration.personnels' => ['sometimes', 'array', 'max:50'],
                'data.personnel_administration.personnels.*' => [
                    'sometimes', 'array:fonction,femmes,hommes',
                ],
                'data.personnel_administration.personnels.*.fonction' => [
                    'sometimes', 'string', 'max:120', 'distinct',
                ],
                'data.personnel_administration.personnels.*.femmes' => ['sometimes', 'integer', 'min:0'],
                'data.personnel_administration.personnels.*.hommes' => ['sometimes', 'integer', 'min:0'],
                'data.personnels_appui_soutien' => ['sometimes', 'array:fonctions,autres'],
                'data.personnels_appui_soutien.fonctions' => ['sometimes', 'array'],
                'data.personnels_appui_soutien.fonctions.*' => [
                    'sometimes', 'array:fonction,femmes,hommes', 'distinct:fonction',
                ],
                'data.personnels_appui_soutien.fonctions.*.fonction' => [
                    'sometimes', 'string', Rule::in(self::SUPPORT_STAFF_JOBS), 'distinct',
                ],
                'data.personnels_appui_soutien.fonctions.*.femmes' => ['sometimes', 'integer', 'min:0'],
                'data.personnels_appui_soutien.fonctions.*.hommes' => ['sometimes', 'integer', 'min:0'],
                'data.personnels_appui_soutien.autres' => ['sometimes', 'array'],
                'data.personnels_appui_soutien.autres.*' => ['sometimes', 'array:designation,femmes,hommes'],
                'data.personnels_appui_soutien.autres.*.designation' => ['sometimes', 'string', 'max:120'],
                'data.personnels_appui_soutien.autres.*.femmes' => ['sometimes', 'integer', 'min:0'],
                'data.personnels_appui_soutien.autres.*.hommes' => ['sometimes', 'integer', 'min:0'],
                'data.organes_gestion' => ['sometimes', 'array:instances'],
                'data.organes_gestion.instances' => ['sometimes', 'array', 'max:50'],
                'data.organes_gestion.instances.*' => [
                    'sometimes', 'array:nom,existe,motif_absence,membres',
                ],
                'data.organes_gestion.instances.*.nom' => ['sometimes', 'string', 'max:150'],
                'data.organes_gestion.instances.*.existe' => ['sometimes', 'boolean'],
                'data.organes_gestion.instances.*.motif_absence' => ['sometimes', 'nullable', 'string', 'max:1000'],
                'data.organes_gestion.instances.*.membres' => ['sometimes', 'array', 'max:100'],
                'data.organes_gestion.instances.*.membres.*' => [
                    'sometimes', 'array:nom_complet,titre,statut,contact',
                ],
                'data.organes_gestion.instances.*.membres.*.nom_complet' => ['sometimes', 'string', 'max:200'],
                'data.organes_gestion.instances.*.membres.*.titre' => ['sometimes', 'string', 'max:150'],
                'data.organes_gestion.instances.*.membres.*.statut' => ['sometimes', 'string', 'max:120'],
                'data.organes_gestion.instances.*.membres.*.contact' => ['sometimes', 'string', 'max:100'],
                'data.organisation_pedagogique' => ['sometimes', 'array:emplois_du_temps'],
                'data.organisation_pedagogique.emplois_du_temps' => ['sometimes', 'array', 'max:100'],
                'data.organisation_pedagogique.emplois_du_temps.*' => [
                    'sometimes', 'array:niveau,classe,grille,heures_dues,heures_assurees',
                ],
                'data.organisation_pedagogique.emplois_du_temps.*.niveau' => [
                    'sometimes', 'string', Rule::in(self::PRIMARY_LEVELS),
                ],
                'data.organisation_pedagogique.emplois_du_temps.*.classe' => ['sometimes', 'string', 'max:100'],
                'data.organisation_pedagogique.emplois_du_temps.*.grille' => [
                    'sometimes', 'array:'.implode(',', self::SCHOOL_DAYS),
                ],
                'data.organisation_pedagogique.emplois_du_temps.*.heures_dues' => [
                    'sometimes', 'integer', 'min:0', 'max:60',
                ],
                'data.organisation_pedagogique.emplois_du_temps.*.heures_assurees' => [
                    'sometimes', 'integer', 'min:0', 'max:60',
                ],
                'data.difficultes_solutions' => ['sometimes', 'array:domaines,autres'],
                'data.difficultes_solutions.domaines' => [
                    'sometimes', 'array:'.implode(',', self::DIFFICULTY_DOMAINS),
                ],
                'data.difficultes_solutions.autres' => ['sometimes', 'array', 'max:50'],
                'data.difficultes_solutions.autres.*' => [
                    'sometimes', 'array:domaine,difficultes,solutions_envisagees',
                ],
                'data.difficultes_solutions.autres.*.domaine' => ['sometimes', 'string', 'max:200'],
                'data.difficultes_solutions.autres.*.difficultes' => [
                    'sometimes', 'nullable', 'string', 'max:5000',
                ],
                'data.difficultes_solutions.autres.*.solutions_envisagees' => [
                    'sometimes', 'nullable', 'string', 'max:5000',
                ],
            ];

            foreach (self::SCHOOL_DAYS as $day) {
                $dayPath = "data.organisation_pedagogique.emplois_du_temps.*.grille.$day";
                $rules[$dayPath] = ['sometimes', 'array:'.implode(',', self::PRIMARY_SCHOOL_PERIODS)];

                foreach (self::PRIMARY_SCHOOL_PERIODS as $period) {
                    $rules["$dayPath.$period"] = ['sometimes', 'nullable', 'string', 'max:120'];
                }
            }

            foreach (self::DIFFICULTY_DOMAINS as $domain) {
                $domainPath = "data.difficultes_solutions.domaines.$domain";
                $rules[$domainPath] = ['sometimes', 'array:difficultes,solutions_envisagees'];
                $rules["$domainPath.difficultes"] = ['sometimes', 'nullable', 'string', 'max:5000'];
                $rules["$domainPath.solutions_envisagees"] = [
                    'sometimes', 'nullable', 'string', 'max:5000',
                ];
            }

            if ($etablissementStatus === 'public') {
                $rules += self::financialRules();
            }

            foreach ([
                'preparation_rentree' => self::PREPARATION_ACTIVITIES,
                'deroulement_rentree' => self::START_OF_YEAR_ACTIVITIES,
            ] as $section => $activityKeys) {
                $sectionPath = "data.$section";
                $rules[$sectionPath] = ['sometimes', 'array:activites,autres'];
                $rules["$sectionPath.activites"] = ['sometimes', 'array:'.implode(',', $activityKeys)];
                $rules["$sectionPath.autres"] = ['sometimes', 'array', 'max:50'];
                $rules["$sectionPath.autres.*"] = ['sometimes', 'array:activite,realisee,date_periode,observations'];
                $rules["$sectionPath.autres.*.activite"] = ['sometimes', 'string', 'max:200'];
                $rules["$sectionPath.autres.*.realisee"] = ['sometimes', 'boolean'];
                $rules["$sectionPath.autres.*.date_periode"] = ['sometimes', 'nullable', 'string', 'max:100'];
                $rules["$sectionPath.autres.*.observations"] = ['sometimes', 'nullable', 'string', 'max:1000'];

                foreach ($activityKeys as $activityKey) {
                    $activityPath = "$sectionPath.activites.$activityKey";
                    $rules[$activityPath] = ['sometimes', 'array:realisee,date_periode,observations'];
                    $rules["$activityPath.realisee"] = ['sometimes', 'boolean'];
                    $rules["$activityPath.date_periode"] = ['sometimes', 'nullable', 'string', 'max:100'];
                    $rules["$activityPath.observations"] = ['sometimes', 'nullable', 'string', 'max:1000'];
                }
            }

            if ($reportType === AnnualReportType::Sortie) {
                $rules += [
                    'data.resultats_examens' => ['sometimes', 'array:examens'],
                    'data.resultats_examens.examens' => ['sometimes', 'array'],
                    'data.resultats_examens.examens.*' => ['sometimes', 'array:examen,candidats,admis'],
                    'data.resultats_examens.examens.*.examen' => ['sometimes', 'string', 'in:CEP'],
                    'data.resultats_examens.examens.*.candidats' => ['sometimes', 'array:filles,garcons'],
                    'data.resultats_examens.examens.*.candidats.filles' => ['sometimes', 'integer', 'min:0'],
                    'data.resultats_examens.examens.*.candidats.garcons' => ['sometimes', 'integer', 'min:0'],
                    'data.resultats_examens.examens.*.admis' => ['sometimes', 'array:filles,garcons'],
                    'data.resultats_examens.examens.*.admis.filles' => ['sometimes', 'integer', 'min:0'],
                    'data.resultats_examens.examens.*.admis.garcons' => ['sometimes', 'integer', 'min:0'],
                ];
            }

            return $rules;
        }

        if ($etablissementType !== EtablissementType::Secondaire) {
            return [];
        }

        $rules = [
            'data.effectifs_eleves' => ['sometimes', 'array:post_primaire,secondaire,infrastructures'],
            'data.effectifs_eleves.post_primaire' => ['sometimes', 'array'],
            'data.effectifs_eleves.post_primaire.*' => ['sometimes', 'array:niveau,nombre_classes,filles,garcons'],
            'data.effectifs_eleves.post_primaire.*.niveau' => ['sometimes', 'string', 'in:6e,5e,4e,3e'],
            'data.effectifs_eleves.post_primaire.*.nombre_classes' => ['sometimes', 'integer', 'min:0'],
            'data.effectifs_eleves.post_primaire.*.filles' => ['sometimes', 'integer', 'min:0'],
            'data.effectifs_eleves.post_primaire.*.garcons' => ['sometimes', 'integer', 'min:0'],
            'data.effectifs_eleves.secondaire' => ['sometimes', 'array'],
            'data.effectifs_eleves.secondaire.*' => ['sometimes', 'array:niveau,serie,nombre_classes,filles,garcons'],
            'data.effectifs_eleves.secondaire.*.niveau' => ['sometimes', 'string', 'in:2nde,1ere,bac_pro,tle'],
            'data.effectifs_eleves.secondaire.*.serie' => [
                'sometimes', 'string', 'in:A,A_prime,C,C_prime,D,D_prime,1,2',
            ],
            'data.effectifs_eleves.secondaire.*.nombre_classes' => ['sometimes', 'integer', 'min:0'],
            'data.effectifs_eleves.secondaire.*.filles' => ['sometimes', 'integer', 'min:0'],
            'data.effectifs_eleves.secondaire.*.garcons' => ['sometimes', 'integer', 'min:0'],
            'data.effectifs_eleves.infrastructures' => [
                'sometimes', 'array:salles_abri_precaire,salles_emprunt,salles_non_utilisees',
            ],
            'data.effectifs_eleves.infrastructures.salles_abri_precaire' => ['sometimes', 'integer', 'min:0'],
            'data.effectifs_eleves.infrastructures.salles_emprunt' => ['sometimes', 'integer', 'min:0'],
            'data.effectifs_eleves.infrastructures.salles_non_utilisees' => ['sometimes', 'integer', 'min:0'],
            'data.effectifs_edi' => ['sometimes', 'array:post_primaire,secondaire'],
            'data.effectifs_edi.post_primaire' => ['sometimes', 'array'],
            'data.effectifs_edi.post_primaire.*' => ['sometimes', 'array:niveau,filles,garcons'],
            'data.effectifs_edi.post_primaire.*.niveau' => ['sometimes', 'string', 'in:6e,5e,4e,3e'],
            'data.effectifs_edi.post_primaire.*.filles' => ['sometimes', 'integer', 'min:0'],
            'data.effectifs_edi.post_primaire.*.garcons' => ['sometimes', 'integer', 'min:0'],
            'data.effectifs_edi.secondaire' => ['sometimes', 'array'],
            'data.effectifs_edi.secondaire.*' => ['sometimes', 'array:niveau,serie,filles,garcons'],
            'data.effectifs_edi.secondaire.*.niveau' => ['sometimes', 'string', 'in:2nde,1ere,bac_pro,tle'],
            'data.effectifs_edi.secondaire.*.serie' => [
                'sometimes', 'string', 'in:A,A_prime,C,C_prime,D,D_prime,1,2',
            ],
            'data.effectifs_edi.secondaire.*.filles' => ['sometimes', 'integer', 'min:0'],
            'data.effectifs_edi.secondaire.*.garcons' => ['sometimes', 'integer', 'min:0'],
            'data.resultats_concours' => ['sometimes', 'array:concours'],
            'data.resultats_concours.concours' => ['sometimes', 'array'],
            'data.resultats_concours.concours.*' => ['sometimes', 'array:concours,inscrits,presents,admis'],
            'data.resultats_concours.concours.*.concours' => [
                'sometimes', 'string', 'in:entree_2nde,entree_1ere_bep,entree_1ere_bac_pro',
            ],
            'data.resultats_concours.concours.*.inscrits' => ['sometimes', 'array:filles,garcons'],
            'data.resultats_concours.concours.*.inscrits.filles' => ['sometimes', 'integer', 'min:0'],
            'data.resultats_concours.concours.*.inscrits.garcons' => ['sometimes', 'integer', 'min:0'],
            'data.resultats_concours.concours.*.presents' => ['sometimes', 'array:filles,garcons'],
            'data.resultats_concours.concours.*.presents.filles' => ['sometimes', 'integer', 'min:0'],
            'data.resultats_concours.concours.*.presents.garcons' => ['sometimes', 'integer', 'min:0'],
            'data.resultats_concours.concours.*.admis' => ['sometimes', 'array:filles,garcons'],
            'data.resultats_concours.concours.*.admis.filles' => ['sometimes', 'integer', 'min:0'],
            'data.resultats_concours.concours.*.admis.garcons' => ['sometimes', 'integer', 'min:0'],
            'data.personnel_enseignant' => ['sometimes', 'array:public,prive'],
            'data.personnel_enseignant.*' => ['sometimes', 'array'],
            'data.personnel_enseignant.*.*' => [
                'sometimes', 'array:discipline,permanents,non_permanents,vacataires,postes_a_pourvoir',
            ],
            'data.personnel_enseignant.*.*.discipline' => ['sometimes', 'string', 'max:100'],
            'data.personnel_enseignant.*.*.permanents' => ['sometimes', 'array:femmes,hommes'],
            'data.personnel_enseignant.*.*.permanents.femmes' => ['sometimes', 'integer', 'min:0'],
            'data.personnel_enseignant.*.*.permanents.hommes' => ['sometimes', 'integer', 'min:0'],
            'data.personnel_enseignant.*.*.non_permanents' => ['sometimes', 'array:femmes,hommes'],
            'data.personnel_enseignant.*.*.non_permanents.femmes' => ['sometimes', 'integer', 'min:0'],
            'data.personnel_enseignant.*.*.non_permanents.hommes' => ['sometimes', 'integer', 'min:0'],
            'data.personnel_enseignant.*.*.vacataires' => ['sometimes', 'array:femmes,hommes'],
            'data.personnel_enseignant.*.*.vacataires.femmes' => ['sometimes', 'integer', 'min:0'],
            'data.personnel_enseignant.*.*.vacataires.hommes' => ['sometimes', 'integer', 'min:0'],
            'data.personnel_enseignant.*.*.postes_a_pourvoir' => ['sometimes', 'integer', 'min:0'],
            'data.personnel_direction' => ['sometimes', 'array:public,prive'],
            'data.personnel_direction.*' => ['sometimes', 'array'],
            'data.personnel_direction.*.*' => [
                'sometimes', 'array:fonction,nom_complet,sexe,titre_capacite,disciplines_enseignees,observations',
            ],
            'data.personnel_direction.*.*.fonction' => ['sometimes', 'string', 'max:100'],
            'data.personnel_direction.*.*.nom_complet' => ['sometimes', 'string', 'max:200'],
            'data.personnel_direction.*.*.sexe' => ['sometimes', Rule::in(['femme', 'homme'])],
            'data.personnel_direction.*.*.titre_capacite' => ['sometimes', 'string', 'max:150'],
            'data.personnel_direction.*.*.disciplines_enseignees' => ['sometimes', 'array'],
            'data.personnel_direction.*.*.disciplines_enseignees.*' => ['sometimes', 'string', 'max:100'],
            'data.personnel_direction.*.*.observations' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];

        $administrationStatusesByCategory = [
            'public' => self::PUBLIC_ADMINISTRATION_STATUSES,
            'prive' => self::PRIVATE_ADMINISTRATION_STATUSES,
        ];

        $rules['data.personnel_administration'] = ['sometimes', 'array:public,prive'];

        foreach ($administrationStatusesByCategory as $category => $statuses) {
            $categoryPath = "data.personnel_administration.$category";
            $rules[$categoryPath] = ['sometimes', 'array'];
            $rules[$categoryPath.'.*'] = [
                'sometimes',
                'array:'.implode(',', ['fonction', ...$statuses, 'postes_a_pourvoir']),
            ];
            $rules[$categoryPath.'.*.fonction'] = ['sometimes', 'string', 'max:100'];
            $rules[$categoryPath.'.*.postes_a_pourvoir'] = ['sometimes', 'integer', 'min:0'];

            foreach ($statuses as $status) {
                $rules["$categoryPath.*.$status"] = ['sometimes', 'array:femmes,hommes'];
                $rules["$categoryPath.*.$status.femmes"] = ['sometimes', 'integer', 'min:0'];
                $rules["$categoryPath.*.$status.hommes"] = ['sometimes', 'integer', 'min:0'];
            }
        }

        $rules['data.personnels_specifiques'] = ['sometimes', 'array:public,prive'];
        $rules['data.personnels_specifiques.*'] = [
            'sometimes',
            'array:'.implode(',', self::SPECIFIC_STAFF_CATEGORIES),
        ];
        $rules['data.personnels_specifiques.*.*'] = ['sometimes', 'array:femmes,hommes'];
        $rules['data.personnels_specifiques.*.*.femmes'] = ['sometimes', 'integer', 'min:0'];
        $rules['data.personnels_specifiques.*.*.hommes'] = ['sometimes', 'integer', 'min:0'];

        $rules['data.personnels_appui_soutien'] = ['sometimes', 'array:public,prive'];
        $rules['data.personnels_appui_soutien.*'] = [
            'sometimes',
            'array:'.implode(',', self::SUPPORT_STAFF_STATUSES),
        ];

        foreach (self::SUPPORT_STAFF_STATUSES as $status) {
            $statusPath = "data.personnels_appui_soutien.*.$status";
            $rules[$statusPath] = [
                'sometimes',
                'array:'.implode(',', [...self::SUPPORT_STAFF_JOBS, 'autres']),
            ];

            foreach (self::SUPPORT_STAFF_JOBS as $job) {
                $rules["$statusPath.$job"] = ['sometimes', 'array:femmes,hommes'];
                $rules["$statusPath.$job.femmes"] = ['sometimes', 'integer', 'min:0'];
                $rules["$statusPath.$job.hommes"] = ['sometimes', 'integer', 'min:0'];
            }

            $rules["$statusPath.autres"] = ['sometimes', 'array'];
            $rules["$statusPath.autres.*"] = ['sometimes', 'array:designation,femmes,hommes'];
            $rules["$statusPath.autres.*.designation"] = ['sometimes', 'string', 'max:100'];
            $rules["$statusPath.autres.*.femmes"] = ['sometimes', 'integer', 'min:0'];
            $rules["$statusPath.autres.*.hommes"] = ['sometimes', 'integer', 'min:0'];
        }

        $activitiesBySection = [
            'preparation_rentree' => self::PREPARATION_ACTIVITIES,
            'deroulement_rentree' => self::START_OF_YEAR_ACTIVITIES,
        ];

        foreach ($activitiesBySection as $section => $activityKeys) {
            $sectionPath = "data.$section";
            $rules[$sectionPath] = ['sometimes', 'array:activites,autres'];
            $rules["$sectionPath.activites"] = ['sometimes', 'array:'.implode(',', $activityKeys)];
            $rules["$sectionPath.autres"] = ['sometimes', 'array'];
            $rules["$sectionPath.autres.*"] = ['sometimes', 'array:activite,realisee,date_periode,observations'];
            $rules["$sectionPath.autres.*.activite"] = ['sometimes', 'string', 'max:200'];
            $rules["$sectionPath.autres.*.realisee"] = ['sometimes', 'boolean'];
            $rules["$sectionPath.autres.*.date_periode"] = ['sometimes', 'nullable', 'string', 'max:100'];
            $rules["$sectionPath.autres.*.observations"] = ['sometimes', 'nullable', 'string', 'max:1000'];

            foreach ($activityKeys as $activityKey) {
                $activityPath = "$sectionPath.activites.$activityKey";
                $rules[$activityPath] = ['sometimes', 'array:realisee,date_periode,observations'];
                $rules["$activityPath.realisee"] = ['sometimes', 'boolean'];
                $rules["$activityPath.date_periode"] = ['sometimes', 'nullable', 'string', 'max:100'];
                $rules["$activityPath.observations"] = ['sometimes', 'nullable', 'string', 'max:1000'];
            }
        }

        $rules['data.organisation_etablissement'] = [
            'sometimes',
            'array:devise,charte,reglement_interieur_specifique,projet_etablissement',
        ];
        $rules['data.organisation_etablissement.devise'] = ['sometimes', 'array:existe,texte'];
        $rules['data.organisation_etablissement.devise.existe'] = ['sometimes', 'boolean'];
        $rules['data.organisation_etablissement.devise.texte'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['data.organisation_etablissement.charte'] = ['sometimes', 'boolean'];
        $rules['data.organisation_etablissement.reglement_interieur_specifique'] = ['sometimes', 'boolean'];
        $rules['data.organisation_etablissement.projet_etablissement'] = ['sometimes', 'boolean'];
        $rules['data.partenariats'] = ['sometimes', 'array'];
        $rules['data.partenariats.*'] = [
            'sometimes',
            'array:type,denomination,domaines_action,date_mise_en_oeuvre,responsable,contact',
        ];
        $rules['data.partenariats.*.type'] = ['sometimes', 'string', 'max:100'];
        $rules['data.partenariats.*.denomination'] = ['sometimes', 'string', 'max:200'];
        $rules['data.partenariats.*.domaines_action'] = ['sometimes', 'string', 'max:500'];
        $rules['data.partenariats.*.date_mise_en_oeuvre'] = ['sometimes', 'string', 'max:100'];
        $rules['data.partenariats.*.responsable'] = ['sometimes', 'string', 'max:200'];
        $rules['data.partenariats.*.contact'] = ['sometimes', 'string', 'max:100'];
        $rules['data.difficultes_solutions'] = ['sometimes', 'array:domaines,autres'];
        $rules['data.difficultes_solutions.domaines'] = [
            'sometimes',
            'array:'.implode(',', self::DIFFICULTY_DOMAINS),
        ];
        $rules['data.difficultes_solutions.autres'] = ['sometimes', 'array'];
        $rules['data.difficultes_solutions.autres.*'] = [
            'sometimes',
            'array:domaine,difficultes,solutions_envisagees',
        ];
        $rules['data.difficultes_solutions.autres.*.domaine'] = ['sometimes', 'string', 'max:200'];
        $rules['data.difficultes_solutions.autres.*.difficultes'] = ['sometimes', 'nullable', 'string', 'max:5000'];
        $rules['data.difficultes_solutions.autres.*.solutions_envisagees'] = [
            'sometimes', 'nullable', 'string', 'max:5000',
        ];

        foreach (self::DIFFICULTY_DOMAINS as $domain) {
            $domainPath = "data.difficultes_solutions.domaines.$domain";
            $rules[$domainPath] = ['sometimes', 'array:difficultes,solutions_envisagees'];
            $rules["$domainPath.difficultes"] = ['sometimes', 'nullable', 'string', 'max:5000'];
            $rules["$domainPath.solutions_envisagees"] = ['sometimes', 'nullable', 'string', 'max:5000'];
        }

        $rules['data.eleves_metiers'] = ['sometimes', 'array:metiers'];
        $rules['data.eleves_metiers.metiers'] = ['sometimes', 'array'];
        $rules['data.eleves_metiers.metiers.*'] = ['sometimes', 'array:metier,filles,garcons'];
        $rules['data.eleves_metiers.metiers.*.metier'] = ['sometimes', 'string', 'max:150'];
        $rules['data.eleves_metiers.metiers.*.filles'] = ['sometimes', 'integer', 'min:0'];
        $rules['data.eleves_metiers.metiers.*.garcons'] = ['sometimes', 'integer', 'min:0'];

        if ($reportType !== AnnualReportType::Sortie) {
            return $rules;
        }

        return $rules + [
            'data.resultats_examens' => ['sometimes', 'array:examens'],
            'data.resultats_examens.examens' => ['sometimes', 'array'],
            'data.resultats_examens.examens.*' => ['sometimes', 'array:examen,candidats,admis'],
            'data.resultats_examens.examens.*.examen' => ['sometimes', 'string', 'max:80'],
            'data.resultats_examens.examens.*.candidats' => ['sometimes', 'array:filles,garcons'],
            'data.resultats_examens.examens.*.candidats.filles' => ['sometimes', 'integer', 'min:0'],
            'data.resultats_examens.examens.*.candidats.garcons' => ['sometimes', 'integer', 'min:0'],
            'data.resultats_examens.examens.*.admis' => ['sometimes', 'array:filles,garcons'],
            'data.resultats_examens.examens.*.admis.filles' => ['sometimes', 'integer', 'min:0'],
            'data.resultats_examens.examens.*.admis.garcons' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public static function missing(
        array $data,
        AnnualReportType $reportType = AnnualReportType::Rentree,
        ?EtablissementType $etablissementType = null,
        ?string $etablissementStatus = null,
    ): array {
        return array_values(array_diff(self::keysForType($reportType, $etablissementType, $etablissementStatus), array_keys($data)));
    }

    /**
     * @return array<string, array<int, string>>
     */
    private static function financialRules(): array
    {
        $financialRows = [
            'recettes' => [
                'frais_participation_scolarite',
                'autres_contributions_ce',
                'subvention_vacation',
                'subventions_collectivites_territoriales',
                'autres_recettes',
            ],
            'depenses' => [
                'reversement_10_pourcent_dr_dp',
                'salaires_personnel',
                'frais_vacation',
                'investissements_equipements',
            ],
        ];
        $rules = [
            'data.situation_financiere' => ['sometimes', 'array:exercice_precedent,exercice_en_cours'],
        ];

        foreach (['exercice_precedent', 'exercice_en_cours'] as $year) {
            $rules["data.situation_financiere.$year"] = ['sometimes', 'array:recettes,depenses'];

            foreach ($financialRows as $category => $rows) {
                $rules["data.situation_financiere.$year.$category"] = [
                    'sometimes', 'array:'.implode(',', $rows),
                ];

                foreach ($rows as $row) {
                    $rules["data.situation_financiere.$year.$category.$row"] = [
                        'sometimes', 'numeric', 'min:0',
                    ];
                }
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function hasValidStructure(
        array $data,
        AnnualReportType $reportType = AnnualReportType::Rentree,
        ?EtablissementType $etablissementType = null,
        ?string $etablissementStatus = null,
    ): bool {
        $allowedKeys = self::keysForType($reportType, $etablissementType, $etablissementStatus);

        if (array_diff(array_keys($data), $allowedKeys) !== []) {
            return false;
        }

        foreach ($allowedKeys as $key) {
            if (! array_key_exists($key, $data) || ! is_array($data[$key])) {
                return false;
            }
        }

        if ($etablissementType !== EtablissementType::Secondaire) {
            return true;
        }

        if (! self::hasValidEnrollmentData($data['effectifs_eleves'])) {
            return false;
        }

        if (! self::hasValidEdiData($data['effectifs_edi'])) {
            return false;
        }

        if (! self::hasValidTeachingStaffData($data['personnel_enseignant'])) {
            return false;
        }

        if (! self::hasValidManagementStaffData($data['personnel_direction'])) {
            return false;
        }

        if (! self::hasValidAdministrationStaffData($data['personnel_administration'])) {
            return false;
        }

        if (! self::hasValidSpecificStaffData($data['personnels_specifiques'])) {
            return false;
        }

        if (! self::hasValidSupportStaffData($data['personnels_appui_soutien'])) {
            return false;
        }

        if (! self::hasValidActivitySection($data['preparation_rentree'], self::PREPARATION_ACTIVITIES)) {
            return false;
        }

        if (! self::hasValidActivitySection($data['deroulement_rentree'], self::START_OF_YEAR_ACTIVITIES)) {
            return false;
        }

        if (! self::hasValidEstablishmentOrganization($data['organisation_etablissement'])) {
            return false;
        }

        if (! self::hasValidPartnerships($data['partenariats'])) {
            return false;
        }

        if (! self::hasValidDifficulties($data['difficultes_solutions'])) {
            return false;
        }

        if (! self::hasValidVocationalEnrollment($data['eleves_metiers'])) {
            return false;
        }

        if (! self::hasValidContestResults($data['resultats_concours'])) {
            return false;
        }

        return $reportType !== AnnualReportType::Sortie || self::hasValidExamResults($data['resultats_examens']);
    }

    /**
     * @param  array<string, mixed>  $enrollment
     */
    private static function hasValidEnrollmentData(array $enrollment): bool
    {
        $allowedKeys = ['post_primaire', 'secondaire', 'infrastructures'];

        if (
            array_diff(array_keys($enrollment), $allowedKeys) !== []
            || ! isset($enrollment['post_primaire'], $enrollment['secondaire'], $enrollment['infrastructures'])
            || ! is_array($enrollment['post_primaire'])
            || ! array_is_list($enrollment['post_primaire'])
            || ! is_array($enrollment['secondaire'])
            || ! array_is_list($enrollment['secondaire'])
            || ! is_array($enrollment['infrastructures'])
        ) {
            return false;
        }

        if ($enrollment['post_primaire'] === [] && $enrollment['secondaire'] === []) {
            return false;
        }

        $postPrimaryLevels = [];

        foreach ($enrollment['post_primaire'] as $level) {
            if (
                ! is_array($level)
                || array_diff(array_keys($level), ['niveau', 'nombre_classes', 'filles', 'garcons']) !== []
                || ! in_array($level['niveau'] ?? null, ['6e', '5e', '4e', '3e'], true)
                || isset($postPrimaryLevels[$level['niveau']])
                || ! self::hasValidStudentCounts($level)
            ) {
                return false;
            }

            $postPrimaryLevels[$level['niveau']] = true;
        }

        $secondarySeries = [];

        foreach ($enrollment['secondaire'] as $level) {
            $grade = is_array($level) ? ($level['niveau'] ?? null) : null;
            $series = is_array($level) ? ($level['serie'] ?? null) : null;
            $seriesKey = is_string($grade) && is_string($series) ? $grade.'|'.$series : null;

            if (
                ! is_array($level)
                || array_diff(array_keys($level), ['niveau', 'serie', 'nombre_classes', 'filles', 'garcons']) !== []
                || ! self::isValidSecondarySeries($grade, $series)
                || $seriesKey === null
                || isset($secondarySeries[$seriesKey])
                || ! self::hasValidStudentCounts($level)
            ) {
                return false;
            }

            $secondarySeries[$seriesKey] = true;
        }

        $infrastructureKeys = [
            'salles_abri_precaire',
            'salles_emprunt',
            'salles_non_utilisees',
        ];

        if (
            array_diff(array_keys($enrollment['infrastructures']), $infrastructureKeys) !== []
            || array_diff($infrastructureKeys, array_keys($enrollment['infrastructures'])) !== []
        ) {
            return false;
        }

        foreach ($infrastructureKeys as $key) {
            if (! self::isNonNegativeInteger($enrollment['infrastructures'][$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $ediData
     */
    private static function hasValidEdiData(array $ediData): bool
    {
        $categories = ['post_primaire', 'secondaire'];

        if (
            array_diff(array_keys($ediData), $categories) !== []
            || array_diff($categories, array_keys($ediData)) !== []
            || ! is_array($ediData['post_primaire'])
            || ! array_is_list($ediData['post_primaire'])
            || ! is_array($ediData['secondaire'])
            || ! array_is_list($ediData['secondaire'])
        ) {
            return false;
        }

        $postPrimaryLevels = [];

        foreach ($ediData['post_primaire'] as $level) {
            if (
                ! is_array($level)
                || array_diff(array_keys($level), ['niveau', 'filles', 'garcons']) !== []
                || ! in_array($level['niveau'] ?? null, ['6e', '5e', '4e', '3e'], true)
                || isset($postPrimaryLevels[$level['niveau']])
                || ! self::isNonNegativeInteger($level['filles'] ?? null)
                || ! self::isNonNegativeInteger($level['garcons'] ?? null)
            ) {
                return false;
            }

            $postPrimaryLevels[$level['niveau']] = true;
        }

        $secondarySeries = [];

        foreach ($ediData['secondaire'] as $level) {
            $grade = is_array($level) ? ($level['niveau'] ?? null) : null;
            $series = is_array($level) ? ($level['serie'] ?? null) : null;
            $seriesKey = is_string($grade) && is_string($series) ? $grade.'|'.$series : null;

            if (
                ! is_array($level)
                || array_diff(array_keys($level), ['niveau', 'serie', 'filles', 'garcons']) !== []
                || ! self::isValidSecondarySeries($grade, $series)
                || $seriesKey === null
                || isset($secondarySeries[$seriesKey])
                || ! self::isNonNegativeInteger($level['filles'] ?? null)
                || ! self::isNonNegativeInteger($level['garcons'] ?? null)
            ) {
                return false;
            }

            $secondarySeries[$seriesKey] = true;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $level
     */
    private static function hasValidStudentCounts(array $level): bool
    {
        foreach (['nombre_classes', 'filles', 'garcons'] as $key) {
            if (! array_key_exists($key, $level) || ! self::isNonNegativeInteger($level[$key])) {
                return false;
            }
        }

        return ((int) $level['filles'] + (int) $level['garcons']) === 0
            || (int) $level['nombre_classes'] > 0;
    }

    private static function isValidSecondarySeries(mixed $grade, mixed $series): bool
    {
        if (! is_string($grade) || ! is_string($series)) {
            return false;
        }

        $allowedSeries = match ($grade) {
            '2nde' => ['A', 'A_prime', 'C', 'C_prime'],
            '1ere' => ['A', 'A_prime', 'C', 'D', 'D_prime'],
            'bac_pro' => ['1', '2'],
            'tle' => ['A', 'C', 'D'],
            default => [],
        };

        return in_array($series, $allowedSeries, true);
    }

    private static function isNonNegativeInteger(mixed $value): bool
    {
        if (is_int($value)) {
            return $value >= 0;
        }

        return is_string($value) && preg_match('/^\d+$/', $value) === 1;
    }

    /**
     * @param  array<string, mixed>  $examResults
     */
    private static function hasValidExamResults(array $examResults): bool
    {
        if (
            array_diff(array_keys($examResults), ['examens']) !== []
            || ! isset($examResults['examens'])
            || ! is_array($examResults['examens'])
            || $examResults['examens'] === []
        ) {
            return false;
        }

        foreach ($examResults['examens'] as $examResult) {
            if (
                ! is_array($examResult)
                || array_diff(array_keys($examResult), ['examen', 'candidats', 'admis']) !== []
                || ! is_string($examResult['examen'] ?? null)
                || trim($examResult['examen']) === ''
            ) {
                return false;
            }

            foreach (['candidats', 'admis'] as $countType) {
                $counts = $examResult[$countType] ?? null;

                if (
                    ! is_array($counts)
                    || array_diff(array_keys($counts), ['filles', 'garcons']) !== []
                    || ! array_key_exists('filles', $counts)
                    || ! array_key_exists('garcons', $counts)
                ) {
                    return false;
                }

                foreach ($counts as $count) {
                    if (! self::isNonNegativeInteger($count)) {
                        return false;
                    }
                }
            }

            foreach (['filles', 'garcons'] as $gender) {
                if (
                    (int) $examResult['admis'][$gender] > (int) $examResult['candidats'][$gender]
                ) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $contestResults
     */
    private static function hasValidContestResults(array $contestResults): bool
    {
        if (
            array_diff(array_keys($contestResults), ['concours']) !== []
            || ! isset($contestResults['concours'])
            || ! is_array($contestResults['concours'])
            || ! array_is_list($contestResults['concours'])
            || count($contestResults['concours']) !== count(self::CONCOURS)
        ) {
            return false;
        }

        $seenContests = [];

        foreach ($contestResults['concours'] as $contestResult) {
            if (
                ! is_array($contestResult)
                || array_diff(array_keys($contestResult), ['concours', 'inscrits', 'presents', 'admis']) !== []
                || ! in_array($contestResult['concours'] ?? null, self::CONCOURS, true)
                || isset($seenContests[$contestResult['concours']])
            ) {
                return false;
            }

            $seenContests[$contestResult['concours']] = true;

            foreach (['inscrits', 'presents', 'admis'] as $countType) {
                $counts = $contestResult[$countType] ?? null;

                if (
                    ! is_array($counts)
                    || array_diff(array_keys($counts), ['filles', 'garcons']) !== []
                    || ! array_key_exists('filles', $counts)
                    || ! array_key_exists('garcons', $counts)
                ) {
                    return false;
                }

                foreach ($counts as $count) {
                    if (! self::isNonNegativeInteger($count)) {
                        return false;
                    }
                }
            }

            foreach (['filles', 'garcons'] as $gender) {
                if (
                    (int) $contestResult['presents'][$gender] > (int) $contestResult['inscrits'][$gender]
                    || (int) $contestResult['admis'][$gender] > (int) $contestResult['presents'][$gender]
                ) {
                    return false;
                }
            }
        }

        return count($seenContests) === count(self::CONCOURS);
    }

    /**
     * @param  array<string, mixed>  $teachingStaff
     */
    private static function hasValidTeachingStaffData(array $teachingStaff): bool
    {
        $categories = ['public', 'prive'];

        if (
            array_diff(array_keys($teachingStaff), $categories) !== []
            || array_diff($categories, array_keys($teachingStaff)) !== []
        ) {
            return false;
        }

        foreach ($categories as $category) {
            if (! is_array($teachingStaff[$category]) || ! array_is_list($teachingStaff[$category])) {
                return false;
            }

            $disciplines = [];

            foreach ($teachingStaff[$category] as $staffRow) {
                $discipline = is_array($staffRow) ? ($staffRow['discipline'] ?? null) : null;

                if (
                    ! is_array($staffRow)
                    || array_diff(
                        array_keys($staffRow),
                        ['discipline', 'permanents', 'non_permanents', 'vacataires', 'postes_a_pourvoir'],
                    ) !== []
                    || ! is_string($discipline)
                    || trim($discipline) === ''
                    || mb_strlen($discipline) > 100
                ) {
                    return false;
                }

                $disciplineKey = mb_strtolower(trim($discipline));

                if (isset($disciplines[$disciplineKey])) {
                    return false;
                }

                $disciplines[$disciplineKey] = true;

                foreach (['permanents', 'non_permanents', 'vacataires'] as $employmentStatus) {
                    $counts = $staffRow[$employmentStatus] ?? null;

                    if (
                        ! is_array($counts)
                        || array_diff(array_keys($counts), ['femmes', 'hommes']) !== []
                        || ! array_key_exists('femmes', $counts)
                        || ! array_key_exists('hommes', $counts)
                        || ! self::isNonNegativeInteger($counts['femmes'])
                        || ! self::isNonNegativeInteger($counts['hommes'])
                    ) {
                        return false;
                    }
                }

                if (! self::isNonNegativeInteger($staffRow['postes_a_pourvoir'] ?? null)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $managementStaff
     */
    private static function hasValidManagementStaffData(array $managementStaff): bool
    {
        $categories = ['public', 'prive'];

        if (
            array_diff(array_keys($managementStaff), $categories) !== []
            || array_diff($categories, array_keys($managementStaff)) !== []
        ) {
            return false;
        }

        foreach ($categories as $category) {
            if (! is_array($managementStaff[$category]) || ! array_is_list($managementStaff[$category])) {
                return false;
            }

            foreach ($managementStaff[$category] as $staffMember) {
                if (
                    ! is_array($staffMember)
                    || array_diff(
                        array_keys($staffMember),
                        ['fonction', 'nom_complet', 'sexe', 'titre_capacite', 'disciplines_enseignees', 'observations'],
                    ) !== []
                    || ! self::isNonEmptyString($staffMember['fonction'] ?? null, 100)
                    || ! self::isNonEmptyString($staffMember['nom_complet'] ?? null, 200)
                    || ! in_array($staffMember['sexe'] ?? null, ['femme', 'homme'], true)
                    || ! self::isNonEmptyString($staffMember['titre_capacite'] ?? null, 150)
                ) {
                    return false;
                }

                if (array_key_exists('disciplines_enseignees', $staffMember)) {
                    if (
                        ! is_array($staffMember['disciplines_enseignees'])
                        || ! array_is_list($staffMember['disciplines_enseignees'])
                    ) {
                        return false;
                    }

                    foreach ($staffMember['disciplines_enseignees'] as $discipline) {
                        if (! self::isNonEmptyString($discipline, 100)) {
                            return false;
                        }
                    }
                }

                $observations = $staffMember['observations'] ?? null;

                if ($observations !== null && (! is_string($observations) || mb_strlen($observations) > 1000)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $administrationStaff
     */
    private static function hasValidAdministrationStaffData(array $administrationStaff): bool
    {
        $statusesByCategory = [
            'public' => self::PUBLIC_ADMINISTRATION_STATUSES,
            'prive' => self::PRIVATE_ADMINISTRATION_STATUSES,
        ];

        if (
            array_diff(array_keys($administrationStaff), array_keys($statusesByCategory)) !== []
            || array_diff(array_keys($statusesByCategory), array_keys($administrationStaff)) !== []
        ) {
            return false;
        }

        foreach ($statusesByCategory as $category => $statuses) {
            $staffRows = $administrationStaff[$category];

            if (! is_array($staffRows) || ! array_is_list($staffRows)) {
                return false;
            }

            $functions = [];

            foreach ($staffRows as $staffRow) {
                $function = is_array($staffRow) ? ($staffRow['fonction'] ?? null) : null;

                if (
                    ! is_array($staffRow)
                    || array_diff(array_keys($staffRow), ['fonction', ...$statuses, 'postes_a_pourvoir']) !== []
                    || ! is_string($function)
                    || ! self::isNonEmptyString($function, 100)
                    || ! self::isNonNegativeInteger($staffRow['postes_a_pourvoir'] ?? null)
                ) {
                    return false;
                }

                $functionKey = mb_strtolower(trim($function));

                if (isset($functions[$functionKey])) {
                    return false;
                }

                $functions[$functionKey] = true;

                foreach ($statuses as $status) {
                    $counts = $staffRow[$status] ?? null;

                    if (
                        ! is_array($counts)
                        || array_diff(array_keys($counts), ['femmes', 'hommes']) !== []
                        || ! array_key_exists('femmes', $counts)
                        || ! array_key_exists('hommes', $counts)
                        || ! self::isNonNegativeInteger($counts['femmes'])
                        || ! self::isNonNegativeInteger($counts['hommes'])
                    ) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $specificStaff
     */
    private static function hasValidSpecificStaffData(array $specificStaff): bool
    {
        $categories = ['public', 'prive'];

        if (
            array_diff(array_keys($specificStaff), $categories) !== []
            || array_diff($categories, array_keys($specificStaff)) !== []
        ) {
            return false;
        }

        foreach ($categories as $category) {
            $staffCounts = $specificStaff[$category];

            if (
                ! is_array($staffCounts)
                || array_diff(array_keys($staffCounts), self::SPECIFIC_STAFF_CATEGORIES) !== []
                || array_diff(self::SPECIFIC_STAFF_CATEGORIES, array_keys($staffCounts)) !== []
            ) {
                return false;
            }

            foreach (self::SPECIFIC_STAFF_CATEGORIES as $staffCategory) {
                $counts = $staffCounts[$staffCategory];

                if (
                    ! is_array($counts)
                    || array_diff(array_keys($counts), ['femmes', 'hommes']) !== []
                    || ! array_key_exists('femmes', $counts)
                    || ! array_key_exists('hommes', $counts)
                    || ! self::isNonNegativeInteger($counts['femmes'])
                    || ! self::isNonNegativeInteger($counts['hommes'])
                ) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $supportStaff
     */
    private static function hasValidSupportStaffData(array $supportStaff): bool
    {
        $categories = ['public', 'prive'];

        if (
            array_diff(array_keys($supportStaff), $categories) !== []
            || array_diff($categories, array_keys($supportStaff)) !== []
        ) {
            return false;
        }

        foreach ($categories as $category) {
            $employmentRows = $supportStaff[$category];

            if (
                ! is_array($employmentRows)
                || array_diff(array_keys($employmentRows), self::SUPPORT_STAFF_STATUSES) !== []
                || array_diff(self::SUPPORT_STAFF_STATUSES, array_keys($employmentRows)) !== []
            ) {
                return false;
            }

            foreach (self::SUPPORT_STAFF_STATUSES as $status) {
                $jobCounts = $employmentRows[$status];

                if (
                    ! is_array($jobCounts)
                    || array_diff(array_keys($jobCounts), [...self::SUPPORT_STAFF_JOBS, 'autres']) !== []
                    || array_diff([...self::SUPPORT_STAFF_JOBS, 'autres'], array_keys($jobCounts)) !== []
                ) {
                    return false;
                }

                foreach (self::SUPPORT_STAFF_JOBS as $job) {
                    $counts = $jobCounts[$job];

                    if (! self::hasValidGenderCounts($counts)) {
                        return false;
                    }
                }

                $otherJobs = $jobCounts['autres'];

                if (! is_array($otherJobs) || ! array_is_list($otherJobs)) {
                    return false;
                }

                $otherJobNames = [];

                foreach ($otherJobs as $otherJob) {
                    $designation = is_array($otherJob) ? ($otherJob['designation'] ?? null) : null;

                    if (
                        ! is_array($otherJob)
                        || array_diff(array_keys($otherJob), ['designation', 'femmes', 'hommes']) !== []
                        || ! is_string($designation)
                        || ! self::isNonEmptyString($designation, 100)
                        || ! self::isNonNegativeInteger($otherJob['femmes'] ?? null)
                        || ! self::isNonNegativeInteger($otherJob['hommes'] ?? null)
                    ) {
                        return false;
                    }

                    $designationKey = mb_strtolower(trim($designation));

                    if (isset($otherJobNames[$designationKey])) {
                        return false;
                    }

                    $otherJobNames[$designationKey] = true;
                }
            }
        }

        return true;
    }

    private static function hasValidGenderCounts(mixed $counts): bool
    {
        return is_array($counts)
            && array_diff(array_keys($counts), ['femmes', 'hommes']) === []
            && array_key_exists('femmes', $counts)
            && array_key_exists('hommes', $counts)
            && self::isNonNegativeInteger($counts['femmes'])
            && self::isNonNegativeInteger($counts['hommes']);
    }

    /**
     * @param  array<string, mixed>  $activitySection
     * @param  array<int, string>  $requiredActivities
     */
    private static function hasValidActivitySection(array $activitySection, array $requiredActivities): bool
    {
        if (
            array_diff(array_keys($activitySection), ['activites', 'autres']) !== []
            || ! array_key_exists('activites', $activitySection)
            || ! array_key_exists('autres', $activitySection)
            || ! is_array($activitySection['activites'])
            || ! is_array($activitySection['autres'])
            || array_diff(array_keys($activitySection['activites']), $requiredActivities) !== []
            || array_diff($requiredActivities, array_keys($activitySection['activites'])) !== []
            || ! array_is_list($activitySection['autres'])
        ) {
            return false;
        }

        foreach ($requiredActivities as $activityKey) {
            if (! self::hasValidActivityEntry($activitySection['activites'][$activityKey])) {
                return false;
            }
        }

        $otherActivityNames = [];

        foreach ($activitySection['autres'] as $otherActivity) {
            $activityName = is_array($otherActivity) ? ($otherActivity['activite'] ?? null) : null;

            if (
                ! is_array($otherActivity)
                || array_diff(array_keys($otherActivity), ['activite', 'realisee', 'date_periode', 'observations']) !== []
                || ! is_string($activityName)
                || ! self::isNonEmptyString($activityName, 200)
                || ! self::hasValidActivityEntry($otherActivity, true)
            ) {
                return false;
            }

            $activityNameKey = mb_strtolower(trim($activityName));

            if (isset($otherActivityNames[$activityNameKey])) {
                return false;
            }

            $otherActivityNames[$activityNameKey] = true;
        }

        return true;
    }

    private static function hasValidActivityEntry(mixed $activity, bool $hasName = false): bool
    {
        $allowedKeys = ['realisee', 'date_periode', 'observations'];

        if ($hasName) {
            $allowedKeys[] = 'activite';
        }

        if (
            ! is_array($activity)
            || array_diff(array_keys($activity), $allowedKeys) !== []
            || ! array_key_exists('realisee', $activity)
            || ! self::isBooleanValue($activity['realisee'])
        ) {
            return false;
        }

        $datePeriod = $activity['date_periode'] ?? null;
        $observations = $activity['observations'] ?? null;

        if (
            ($datePeriod !== null && (! is_string($datePeriod) || mb_strlen($datePeriod) > 100))
            || ($observations !== null && (! is_string($observations) || mb_strlen($observations) > 1000))
        ) {
            return false;
        }

        $wasRealized = in_array($activity['realisee'], [true, 1, '1'], true);

        return $wasRealized
            ? self::isNonEmptyString($datePeriod, 100)
            : self::isNonEmptyString($observations, 1000);
    }

    /**
     * @param  array<string, mixed>  $organization
     */
    private static function hasValidEstablishmentOrganization(array $organization): bool
    {
        $allowedKeys = ['devise', 'charte', 'reglement_interieur_specifique', 'projet_etablissement'];

        if (
            array_diff(array_keys($organization), $allowedKeys) !== []
            || array_diff($allowedKeys, array_keys($organization)) !== []
        ) {
            return false;
        }

        $motto = $organization['devise'];

        if (
            ! is_array($motto)
            || array_diff(array_keys($motto), ['existe', 'texte']) !== []
            || ! array_key_exists('existe', $motto)
            || ! array_key_exists('texte', $motto)
            || ! self::isBooleanValue($motto['existe'])
        ) {
            return false;
        }

        $hasMotto = in_array($motto['existe'], [true, 1, '1'], true);

        if ($hasMotto) {
            if (! self::isNonEmptyString($motto['texte'], 255)) {
                return false;
            }
        } elseif ($motto['texte'] !== null && (! is_string($motto['texte']) || trim($motto['texte']) !== '')) {
            return false;
        }

        foreach (['charte', 'reglement_interieur_specifique', 'projet_etablissement'] as $key) {
            if (! self::isBooleanValue($organization[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int|string, mixed>  $partnerships
     */
    private static function hasValidPartnerships(array $partnerships): bool
    {
        if (! array_is_list($partnerships)) {
            return false;
        }

        $requiredFields = [
            'type' => 100,
            'denomination' => 200,
            'domaines_action' => 500,
            'date_mise_en_oeuvre' => 100,
            'responsable' => 200,
            'contact' => 100,
        ];

        foreach ($partnerships as $partnership) {
            if (
                ! is_array($partnership)
                || array_diff(array_keys($partnership), array_keys($requiredFields)) !== []
                || array_diff(array_keys($requiredFields), array_keys($partnership)) !== []
            ) {
                return false;
            }

            foreach ($requiredFields as $field => $maximumLength) {
                if (! self::isNonEmptyString($partnership[$field], $maximumLength)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $difficulties
     */
    private static function hasValidDifficulties(array $difficulties): bool
    {
        if (
            array_diff(array_keys($difficulties), ['domaines', 'autres']) !== []
            || ! array_key_exists('domaines', $difficulties)
            || ! array_key_exists('autres', $difficulties)
            || ! is_array($difficulties['domaines'])
            || ! is_array($difficulties['autres'])
            || array_diff(array_keys($difficulties['domaines']), self::DIFFICULTY_DOMAINS) !== []
            || array_diff(self::DIFFICULTY_DOMAINS, array_keys($difficulties['domaines'])) !== []
            || ! array_is_list($difficulties['autres'])
        ) {
            return false;
        }

        foreach (self::DIFFICULTY_DOMAINS as $domain) {
            if (! self::hasValidDifficultyEntry($difficulties['domaines'][$domain])) {
                return false;
            }
        }

        $otherDomains = [];

        foreach ($difficulties['autres'] as $otherDomain) {
            $domainName = is_array($otherDomain) ? ($otherDomain['domaine'] ?? null) : null;

            if (
                ! is_array($otherDomain)
                || array_diff(array_keys($otherDomain), ['domaine', 'difficultes', 'solutions_envisagees']) !== []
                || ! is_string($domainName)
                || ! self::isNonEmptyString($domainName, 200)
                || ! self::hasValidDifficultyEntry($otherDomain)
            ) {
                return false;
            }

            $domainNameKey = mb_strtolower(trim($domainName));

            if (isset($otherDomains[$domainNameKey])) {
                return false;
            }

            $otherDomains[$domainNameKey] = true;
        }

        return true;
    }

    private static function hasValidDifficultyEntry(mixed $entry): bool
    {
        return is_array($entry)
            && array_diff(array_keys($entry), ['difficultes', 'solutions_envisagees', 'domaine']) === []
            && array_key_exists('difficultes', $entry)
            && array_key_exists('solutions_envisagees', $entry)
            && self::isNullableText($entry['difficultes'], 5000)
            && self::isNullableText($entry['solutions_envisagees'], 5000);
    }

    private static function isNullableText(mixed $value, int $maximumLength): bool
    {
        return $value === null || (is_string($value) && mb_strlen($value) <= $maximumLength);
    }

    /**
     * @param  array<string, mixed>  $vocationalEnrollment
     */
    private static function hasValidVocationalEnrollment(array $vocationalEnrollment): bool
    {
        if (
            array_diff(array_keys($vocationalEnrollment), ['metiers']) !== []
            || ! isset($vocationalEnrollment['metiers'])
            || ! is_array($vocationalEnrollment['metiers'])
            || ! array_is_list($vocationalEnrollment['metiers'])
        ) {
            return false;
        }

        $trades = [];

        foreach ($vocationalEnrollment['metiers'] as $tradeRow) {
            $tradeName = is_array($tradeRow) ? ($tradeRow['metier'] ?? null) : null;

            if (
                ! is_array($tradeRow)
                || array_diff(array_keys($tradeRow), ['metier', 'filles', 'garcons']) !== []
                || ! is_string($tradeName)
                || ! self::isNonEmptyString($tradeName, 150)
                || ! self::isNonNegativeInteger($tradeRow['filles'] ?? null)
                || ! self::isNonNegativeInteger($tradeRow['garcons'] ?? null)
            ) {
                return false;
            }

            $tradeKey = mb_strtolower(trim($tradeName));

            if (isset($trades[$tradeKey])) {
                return false;
            }

            $trades[$tradeKey] = true;
        }

        return true;
    }

    private static function isBooleanValue(mixed $value): bool
    {
        return in_array($value, [true, false, 0, 1, '0', '1'], true);
    }

    private static function isNonEmptyString(mixed $value, int $maximumLength): bool
    {
        return is_string($value)
            && trim($value) !== ''
            && mb_strlen($value) <= $maximumLength;
    }
}
