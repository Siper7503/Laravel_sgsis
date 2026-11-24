<?php

namespace App\Http\Requests;

use App\AnnualReportSections;
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
        $rules = [
            'data' => ['required', 'array:'.implode(',', AnnualReportSections::KEYS)],
        ];

        foreach (AnnualReportSections::KEYS as $section) {
            $rules["data.$section"] = ['sometimes', 'array'];
        }

        return $rules;
    }
}
