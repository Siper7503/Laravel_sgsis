<?php

namespace App\Http\Requests;

use App\AnnualReportSections;
use App\AnnualReportType;
use App\Models\AnnualReport;
use App\Models\Etablissement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreAnnualReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $etablissement = $this->route('etablissement');

        if (! $etablissement instanceof Etablissement || ! $this->user()) {
            return false;
        }

        Gate::authorize('create', [AnnualReport::class, $etablissement]);

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $etablissement = $this->route('etablissement');
        $reportTypeInput = $this->input('report_type');
        $reportType = is_string($reportTypeInput)
            ? AnnualReportType::tryFrom($reportTypeInput)
            : null;
        $reportType ??= AnnualReportType::Rentree;

        $uniqueSchoolYear = Rule::unique('annual_reports', 'school_year')
            ->where('etablissement_id', $etablissement instanceof Etablissement ? $etablissement->getKey() : null)
            ->where('report_type', $reportType->value);

        $rules = [
            'report_type' => ['sometimes', Rule::enum(AnnualReportType::class)],
            'school_year' => [
                'required',
                'string',
                'regex:/^\d{4}-\d{4}$/',
                $uniqueSchoolYear,
            ],
            'data' => ['required', 'array:'.implode(',', AnnualReportSections::keysForType($reportType))],
        ];

        foreach (AnnualReportSections::keysForType($reportType) as $section) {
            $rules["data.$section"] = ['sometimes', 'array'];
        }

        $etablissementType = $etablissement instanceof Etablissement ? $etablissement->type : null;

        return $rules + AnnualReportSections::draftValidationRules($reportType, $etablissementType);
    }
}
