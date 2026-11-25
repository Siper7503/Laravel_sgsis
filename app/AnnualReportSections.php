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
        return match ($reportType) {
            AnnualReportType::Rentree => self::KEYS,
            AnnualReportType::Sortie => [...self::KEYS, 'resultats_examens'],
        };
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function draftValidationRules(
        AnnualReportType $reportType,
        ?EtablissementType $etablissementType = null,
    ): array {
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
        ];

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
    public static function missing(array $data, AnnualReportType $reportType = AnnualReportType::Rentree): array
    {
        return array_values(array_diff(self::keysForType($reportType), array_keys($data)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function hasValidStructure(
        array $data,
        AnnualReportType $reportType = AnnualReportType::Rentree,
        ?EtablissementType $etablissementType = null,
    ): bool {
        $allowedKeys = self::keysForType($reportType);

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

        return $reportType !== AnnualReportType::Sortie
            || self::hasValidExamResults($data['resultats_examens']);
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
}
