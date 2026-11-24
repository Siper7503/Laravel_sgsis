<?php

namespace App\Actions\AnnualReports;

use App\AnnualReportSections;
use App\AnnualReportStatus;
use App\AnnualReportType;
use App\Models\AnnualReport;
use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessAnnualReportAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(
        Etablissement $etablissement,
        User $actor,
        string $schoolYear,
        array $data,
    ): AnnualReport {
        return DB::transaction(function () use ($etablissement, $actor, $schoolYear, $data): AnnualReport {
            $report = AnnualReport::create([
                'etablissement_id' => $etablissement->getKey(),
                'created_by' => $actor->getKey(),
                'school_year' => $schoolYear,
                'report_type' => AnnualReportType::Rentree,
                'form_version' => 1,
                'status' => AnnualReportStatus::Draft,
                'data' => $data,
            ]);

            $this->recordEvent($report, $actor, 'draft_created', null, AnnualReportStatus::Draft);

            return $report;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(AnnualReport $annualReport, User $actor, array $data): AnnualReport
    {
        return DB::transaction(function () use ($annualReport, $actor, $data): AnnualReport {
            $report = $this->lockReport($annualReport);

            if (! $report->status->canBeEditedByAgent()) {
                throw ValidationException::withMessages([
                    'status' => ['Ce rapport ne peut plus être modifié à cette étape.'],
                ]);
            }

            $report->update(['data' => $data]);
            $this->recordEvent($report, $actor, 'draft_updated', $report->status, $report->status);

            return $report;
        });
    }

    public function submitToDse(AnnualReport $annualReport, User $actor): AnnualReport
    {
        return DB::transaction(function () use ($annualReport, $actor): AnnualReport {
            $report = $this->lockReport($annualReport);

            if (! $report->status->canBeEditedByAgent()) {
                throw ValidationException::withMessages([
                    'status' => ['Ce rapport ne peut pas être soumis à cette étape.'],
                ]);
            }

            $missingSections = AnnualReportSections::missing($report->data ?? []);

            if ($missingSections !== [] || ! AnnualReportSections::hasValidStructure($report->data ?? [])) {
                throw ValidationException::withMessages([
                    'data' => [
                        'Toutes les sections du rapport doivent être présentes sous forme de tableaux.',
                    ],
                ]);
            }

            $this->transition(
                $report,
                $actor,
                AnnualReportStatus::SubmittedToDse,
                'submitted_to_dse',
            );

            return $report;
        });
    }

    public function reviewByDse(
        AnnualReport $annualReport,
        User $actor,
        string $decision,
        ?string $comment,
    ): AnnualReport {
        return DB::transaction(function () use ($annualReport, $actor, $decision, $comment): AnnualReport {
            $report = $this->lockReport($annualReport);

            if (! $report->status->canBeReviewedByDse()) {
                throw ValidationException::withMessages([
                    'status' => ['Ce rapport n’est pas en attente de vérification par la DSE.'],
                ]);
            }

            $this->transition(
                $report,
                $actor,
                $decision === 'forward'
                    ? AnnualReportStatus::SubmittedToDgss
                    : AnnualReportStatus::ReturnedToAgent,
                $decision === 'forward' ? 'forwarded_to_dgss' : 'returned_to_agent',
                $comment,
            );

            return $report;
        });
    }

    public function reviewByDgss(
        AnnualReport $annualReport,
        User $actor,
        string $decision,
        ?string $comment,
    ): AnnualReport {
        return DB::transaction(function () use ($annualReport, $actor, $decision, $comment): AnnualReport {
            $report = $this->lockReport($annualReport);

            if (! $report->status->canBeReviewedByDgss()) {
                throw ValidationException::withMessages([
                    'status' => ['Ce rapport n’est pas en attente de validation finale par la DGSS.'],
                ]);
            }

            $this->transition(
                $report,
                $actor,
                $decision === 'approve'
                    ? AnnualReportStatus::Approved
                    : AnnualReportStatus::ReturnedToDse,
                $decision === 'approve' ? 'approved_by_dgss' : 'returned_to_dse',
                $comment,
            );

            return $report;
        });
    }

    private function lockReport(AnnualReport $annualReport): AnnualReport
    {
        return AnnualReport::query()
            ->lockForUpdate()
            ->whereKey($annualReport->getKey())
            ->firstOrFail();
    }

    private function transition(
        AnnualReport $report,
        User $actor,
        AnnualReportStatus $newStatus,
        string $action,
        ?string $comment = null,
    ): void {
        $oldStatus = $report->status;
        $report->update(['status' => $newStatus]);
        $this->recordEvent($report, $actor, $action, $oldStatus, $newStatus, $comment);
    }

    private function recordEvent(
        AnnualReport $report,
        User $actor,
        string $action,
        ?AnnualReportStatus $fromStatus,
        AnnualReportStatus $toStatus,
        ?string $comment = null,
    ): void {
        $report->events()->create([
            'actor_id' => $actor->getKey(),
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'comment' => $comment,
        ]);
    }
}
