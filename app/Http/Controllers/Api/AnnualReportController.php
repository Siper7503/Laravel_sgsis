<?php

namespace App\Http\Controllers\Api;

use App\Actions\AnnualReports\ProcessAnnualReportAction;
use App\AnnualReportStatus;
use App\AnnualReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewAnnualReportRequest;
use App\Http\Requests\StoreAnnualReportRequest;
use App\Http\Requests\SubmitAnnualReportRequest;
use App\Http\Requests\UpdateAnnualReportRequest;
use App\Models\AnnualReport;
use App\Models\Etablissement;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AnnualReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AnnualReport::class);

        $validated = $request->validate([
            'school_year' => ['sometimes', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'status' => ['sometimes', Rule::enum(AnnualReportStatus::class)],
            'report_type' => ['sometimes', Rule::enum(AnnualReportType::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $query = AnnualReport::query()
            ->select([
                'id',
                'etablissement_id',
                'created_by',
                'school_year',
                'report_type',
                'form_version',
                'status',
                'created_at',
                'updated_at',
            ])
            ->with([
                'etablissement:id,nom,code,type',
                'creator:id,name',
            ]);

        if ($user instanceof User && $user->role === UserRole::Agent) {
            $query->whereHas('etablissement.agents', function ($agents) use ($user): void {
                $agents->whereKey($user->getKey());
            });
        }

        $query
            ->when(
                isset($validated['school_year']),
                fn ($query) => $query->where('school_year', $validated['school_year']),
            )
            ->when(
                isset($validated['status']),
                fn ($query) => $query->where('status', $validated['status']),
            )
            ->when(
                isset($validated['report_type']),
                fn ($query) => $query->where('report_type', $validated['report_type']),
            )
            ->orderByDesc('school_year')
            ->orderByDesc('id');

        return response()->json($query->paginate($validated['per_page'] ?? 20));
    }

    public function store(
        StoreAnnualReportRequest $request,
        Etablissement $etablissement,
        ProcessAnnualReportAction $processAnnualReport,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $reportTypeInput = $request->validated('report_type');
        $reportType = is_string($reportTypeInput)
            ? AnnualReportType::tryFrom($reportTypeInput)
            : null;

        $report = $processAnnualReport->createDraft(
            $etablissement,
            $actor,
            $request->validated('school_year'),
            $reportType ?? AnnualReportType::Rentree,
            $request->validated('data'),
        );

        return response()->json([
            'data' => $report->load(['etablissement', 'creator', 'events.actor']),
        ], 201);
    }

    public function show(AnnualReport $annualReport): JsonResponse
    {
        Gate::authorize('view', $annualReport);

        return response()->json([
            'data' => $annualReport->load([
                'etablissement',
                'creator',
                'events.actor',
            ]),
        ]);
    }

    public function update(
        UpdateAnnualReportRequest $request,
        AnnualReport $annualReport,
        ProcessAnnualReportAction $processAnnualReport,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $report = $processAnnualReport->updateDraft(
            $annualReport,
            $actor,
            $request->validated('data'),
        );

        return response()->json([
            'data' => $report->load(['etablissement', 'creator', 'events.actor']),
        ]);
    }

    public function submit(
        SubmitAnnualReportRequest $request,
        AnnualReport $annualReport,
        ProcessAnnualReportAction $processAnnualReport,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $report = $processAnnualReport->submitToDse($annualReport, $actor);

        return response()->json([
            'data' => $report->load(['etablissement', 'creator', 'events.actor']),
        ]);
    }

    public function reviewByDse(
        ReviewAnnualReportRequest $request,
        AnnualReport $annualReport,
        ProcessAnnualReportAction $processAnnualReport,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $report = $processAnnualReport->reviewByDse(
            $annualReport,
            $actor,
            $request->validated('decision'),
            $request->validated('comment'),
        );

        return response()->json([
            'data' => $report->load(['etablissement', 'creator', 'events.actor']),
        ]);
    }

    public function reviewByDgss(
        ReviewAnnualReportRequest $request,
        AnnualReport $annualReport,
        ProcessAnnualReportAction $processAnnualReport,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $report = $processAnnualReport->reviewByDgss(
            $annualReport,
            $actor,
            $request->validated('decision'),
            $request->validated('comment'),
        );

        return response()->json([
            'data' => $report->load(['etablissement', 'creator', 'events.actor']),
        ]);
    }
}
