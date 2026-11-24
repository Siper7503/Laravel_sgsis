<?php

namespace App\Http\Requests;

use App\Models\AnnualReport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SubmitAnnualReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $annualReport = $this->route('annualReport');

        if (! $annualReport instanceof AnnualReport || ! $this->user()) {
            return false;
        }

        Gate::authorize('submit', $annualReport);

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
