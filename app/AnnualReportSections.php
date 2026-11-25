<?php

namespace App;

final class AnnualReportSections
{
    public const KEYS = [
        'identification',
        'localisation',
        'informations_generales',
        'partenariats',
        'organisation_etablissement',
        'effectifs_eleves',
        'affectations_etat',
        'effectifs_edi',
        'eleves_metiers',
        'personnel_enseignant',
        'personnel_direction',
        'personnel_administration',
        'personnels_specifiques',
        'personnels_appui_soutien',
        'preparation_rentree',
        'deroulement_rentree',
        'organes_gestion',
        'organisation_pedagogique',
        'fiches_professeurs',
        'professeurs_principaux',
        'matieres_specifiques',
        'cours_soir',
        'situation_financiere',
        'resultats_concours',
        'difficultes_solutions',
    ];

    /**
     * @return array<int, string>
     */
    public static function keysForType(AnnualReportType $reportType): array
    {
        return AnnualReportDataValidator::keysForType($reportType);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function draftValidationRules(
        AnnualReportType $reportType,
        ?EtablissementType $etablissementType = null,
    ): array {
        return AnnualReportDataValidator::draftValidationRules($reportType, $etablissementType);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public static function missing(array $data, AnnualReportType $reportType = AnnualReportType::Rentree): array
    {
        return AnnualReportDataValidator::missing($data, $reportType);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function hasValidStructure(
        array $data,
        AnnualReportType $reportType = AnnualReportType::Rentree,
        ?EtablissementType $etablissementType = null,
    ): bool {
        return AnnualReportDataValidator::hasValidStructure($data, $reportType, $etablissementType);
    }
}
