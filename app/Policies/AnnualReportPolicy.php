<?php

namespace App\Policies;

use App\Models\AnnualReport;
use App\Models\Etablissement;
use App\Models\User;
use App\EtablissementType;
use App\UserFunction;
use App\UserRole;
use Illuminate\Auth\Access\Response;

class AnnualReportPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::Agent,
            UserRole::Admin,
            UserRole::Superadmin,
        ], true);
    }

    public function view(User $user, AnnualReport $annualReport): Response|bool
    {
        if ($user->role === UserRole::Agent) {
            return $this->isAssignedTo($user, $annualReport->etablissement)
                ? true
                : Response::denyAsNotFound();
        }

        return in_array($user->role, [
            UserRole::Admin,
            UserRole::Superadmin,
        ], true);
    }

    public function create(User $user, Etablissement $etablissement): Response|bool
    {
        if ($user->role !== UserRole::Agent) {
            return false;
        }

        return $this->isAssignedTo($user, $etablissement)
            && $this->hasMatchingEstablishmentFunction($user, $etablissement)
            ? true
            : Response::denyAsNotFound();
    }

    public function update(User $user, AnnualReport $annualReport): Response|bool
    {
        if ($user->role !== UserRole::Agent) {
            return false;
        }

        if (! $this->isAssignedTo($user, $annualReport->etablissement)) {
            return Response::denyAsNotFound();
        }

        if (! $this->hasMatchingEstablishmentFunction($user, $annualReport->etablissement)) {
            return Response::denyAsNotFound();
        }

        return $annualReport->status->canBeEditedByAgent();
    }

    public function submit(User $user, AnnualReport $annualReport): Response|bool
    {
        return $this->update($user, $annualReport);
    }

    public function reviewByDse(User $user, AnnualReport $annualReport): bool
    {
        return $user->role === UserRole::Admin
            && $user->organizational_function === UserFunction::DirecteurDse
            && $annualReport->status->canBeReviewedByDse();
    }

    public function reviewByDgss(User $user, AnnualReport $annualReport): bool
    {
        return $user->role === UserRole::Admin
            && $user->organizational_function === UserFunction::DirecteurDgss
            && $annualReport->status->canBeReviewedByDgss();
    }

    private function hasMatchingEstablishmentFunction(User $user, Etablissement $etablissement): bool
    {
        return match ($etablissement->type) {
            EtablissementType::Primaire => $user->organizational_function === UserFunction::DirecteurEcole,
            EtablissementType::Secondaire => $user->organizational_function === UserFunction::Proviseur,
        };
    }

    private function isAssignedTo(User $user, Etablissement $etablissement): bool
    {
        return $user->etablissements()
            ->whereKey($etablissement->getKey())
            ->exists();
    }
}
