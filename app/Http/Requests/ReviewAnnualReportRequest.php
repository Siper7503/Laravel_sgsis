<?php

namespace App\Http\Requests;

use App\Models\AnnualReport;
use App\Models\User;
use App\UserFunction;
use App\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReviewAnnualReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $annualReport = $this->route('annualReport');
        $user = $this->user();

        if (! $annualReport instanceof AnnualReport || ! $user instanceof User) {
            return false;
        }

        if (
            $user->role === UserRole::Admin
            && $user->organizational_function === UserFunction::DirecteurDse
        ) {
            Gate::authorize('reviewByDse', $annualReport);
        } elseif (
            $user->role === UserRole::Admin
            && $user->organizational_function === UserFunction::DirecteurDgss
        ) {
            Gate::authorize('reviewByDgss', $annualReport);
        } else {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $decisionRule = $this->user()?->organizational_function === UserFunction::DirecteurDse
            ? Rule::in(['forward', 'return'])
            : Rule::in(['approve', 'return']);

        return [
            'decision' => ['required', 'string', $decisionRule],
            'comment' => ['required_if:decision,return', 'nullable', 'string', 'max:5000'],
        ];
    }
}
