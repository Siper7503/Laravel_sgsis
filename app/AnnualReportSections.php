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
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public static function missing(array $data): array
    {
        return array_values(array_diff(self::KEYS, array_keys($data)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function hasValidStructure(array $data): bool
    {
        foreach (self::KEYS as $key) {
            if (array_key_exists($key, $data) && ! is_array($data[$key])) {
                return false;
            }
        }

        return true;
    }
}
