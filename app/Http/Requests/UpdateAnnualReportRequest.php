<?php

namespace App\Http\Requests;

use App\AnnualReportSections;
use App\AnnualReportType;
use App\Models\AnnualReport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateAnnualReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $annualReport = $this->route('annualReport');

        if (! $annualReport instanceof AnnualReport || ! $this->user()) {
            return false;
        }

        Gate::authorize('update', $annualReport);

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $annualReport = $this->route('annualReport');
        $reportType = $annualReport instanceof AnnualReport
            ? $annualReport->report_type
            : AnnualReportType::Rentree;
        $etablissementType = $annualReport instanceof AnnualReport
            ? $annualReport->etablissement->type
            : null;
        $etablissementStatus = $annualReport instanceof AnnualReport
            ? $annualReport->etablissement->statut
            : null;
        $rules = [
            'data' => ['required', 'array:'.implode(',', AnnualReportSections::keysForType($reportType, $etablissementType, $etablissementStatus))],
        ];

        foreach (AnnualReportSections::keysForType($reportType, $etablissementType, $etablissementStatus) as $section) {
            $rules["data.$section"] = ['sometimes', 'array'];
        }

        return array_merge($rules, AnnualReportSections::draftValidationRules($reportType, $etablissementType, $etablissementStatus));
    }
}
