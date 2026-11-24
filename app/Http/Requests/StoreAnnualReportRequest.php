<?php

namespace App\Http\Requests;

use App\AnnualReportSections;
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
        $uniqueSchoolYear = Rule::unique('annual_reports', 'school_year')
            ->where('etablissement_id', $etablissement instanceof Etablissement ? $etablissement->getKey() : null)
            ->where('report_type', 'rentree');

        $rules = [
            'school_year' => [
                'required',
                'string',
                'regex:/^\d{4}-\d{4}$/',
                $uniqueSchoolYear,
            ],
            'data' => ['required', 'array:'.implode(',', AnnualReportSections::KEYS)],
        ];

        foreach (AnnualReportSections::KEYS as $section) {
            $rules["data.$section"] = ['sometimes', 'array'];
        }

        return $rules;
    }
}
