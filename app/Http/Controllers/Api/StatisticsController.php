<?php

namespace App\Http\Controllers\Api;

use App\AnnualReportStatus;
use App\AnnualReportType;
use App\Http\Controllers\Controller;
use App\Models\AnnualReport;
use App\Models\Etablissement;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\JsonResponse;

class StatisticsController extends Controller
{
    public function index(): JsonResponse
    {
        $establishmentsByType = Etablissement::query()
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');
        $reportsByStatus = AnnualReport::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $reportsByType = AnnualReport::query()
            ->selectRaw('report_type, COUNT(*) as total')
            ->groupBy('report_type')
            ->pluck('total', 'report_type');
        $reportsBySchoolYear = AnnualReport::query()
            ->selectRaw('school_year, COUNT(*) as total')
            ->groupBy('school_year')
            ->orderByDesc('school_year')
            ->pluck('total', 'school_year')
            ->map(fn (int|string $total, string $schoolYear): array => [
                'school_year' => $schoolYear,
                'total' => (int) $total,
            ])
            ->values();
        $usersByRole = User::query()
            ->selectRaw('role, COUNT(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        return response()->json([
            'data' => [
                'etablissements' => [
                    'total' => Etablissement::query()->count(),
                    'actifs' => Etablissement::query()->where('actif', true)->count(),
                    'inactifs' => Etablissement::query()->where('actif', false)->count(),
                    'geolocalises' => Etablissement::query()
                        ->whereNotNull('latitude')
                        ->whereNotNull('longitude')
                        ->count(),
                    'sans_coordonnees' => Etablissement::query()
                        ->where(fn ($query) => $query->whereNull('latitude')->orWhereNull('longitude'))
                        ->count(),
                    'par_type' => [
                        'primaire' => (int) ($establishmentsByType['primaire'] ?? 0),
                        'secondaire' => (int) ($establishmentsByType['secondaire'] ?? 0),
                    ],
                ],
                'rapports_annuels' => [
                    'total' => AnnualReport::query()->count(),
                    'par_statut' => collect(AnnualReportStatus::cases())
                        ->mapWithKeys(fn (AnnualReportStatus $status): array => [
                            $status->value => (int) ($reportsByStatus[$status->value] ?? 0),
                        ]),
                    'par_type' => collect(AnnualReportType::cases())
                        ->mapWithKeys(fn (AnnualReportType $type): array => [
                            $type->value => (int) ($reportsByType[$type->value] ?? 0),
                        ]),
                    'par_annee_scolaire' => $reportsBySchoolYear,
                ],
                'utilisateurs' => [
                    'total' => User::query()->count(),
                    'par_role' => collect(UserRole::cases())
                        ->mapWithKeys(fn (UserRole $role): array => [
                            $role->value => (int) ($usersByRole[$role->value] ?? 0),
                        ]),
                ],
            ],
        ]);
    }
}
