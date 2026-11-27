<?php

namespace App\Http\Requests;

use App\AnnualReportSections;
use App\AnnualReportType;
use App\Models\AnnualReport;
use App\Models\Etablissement;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'data' => ['required', 'array:'.implode(',', AnnualReportSections::keysForType($reportType, $etablissement instanceof Etablissement ? $etablissement->type : null, $etablissement instanceof Etablissement ? $etablissement->statut : null))],
        ];

        $etablissementType = $etablissement instanceof Etablissement ? $etablissement->type : null;
        $etablissementStatus = $etablissement instanceof Etablissement ? $etablissement->statut : null;

        foreach (AnnualReportSections::keysForType($reportType, $etablissementType, $etablissementStatus) as $section) {
            $rules["data.$section"] = ['sometimes', 'array'];
        }

        return array_merge($rules, AnnualReportSections::draftValidationRules($reportType, $etablissementType, $etablissementStatus));
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('school_year')) {
                return;
            }

            $schoolYear = $this->input('school_year');

            if (! is_string($schoolYear)) {
                return;
            }

            [$startYear, $endYear] = array_map('intval', explode('-', $schoolYear));

            if ($endYear !== $startYear + 1) {
                $validator->errors()->add(
                    'school_year',
                    'L’année scolaire doit couvrir deux années consécutives.',
                );
            }
        }];
    }
}
