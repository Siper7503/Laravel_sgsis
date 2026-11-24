<?php

namespace App\Policies;

use App\Models\Etablissement;
use App\Models\User;
use App\UserRole;

class EtablissementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function viewMap(User $user): bool
    {
        return in_array($user->role, [
            UserRole::Superadmin,
            UserRole::Admin,
            UserRole::Agent,
            UserRole::Usager,
        ], true);
    }

    public function view(User $user, Etablissement $etablissement): bool
    {
        return match ($user->role) {
            UserRole::Superadmin, UserRole::Admin => true,
            UserRole::Usager => $etablissement->actif,
            UserRole::Agent => $etablissement->agents()
                ->whereKey($user->getKey())
                ->exists(),
        };
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Superadmin, UserRole::Admin], true);
    }

    public function update(User $user, Etablissement $etablissement): bool
    {
        return in_array($user->role, [UserRole::Superadmin, UserRole::Admin], true)
            || (
                $user->role === UserRole::Agent
                && $etablissement->agents()->whereKey($user->getKey())->exists()
            );
    }

    public function updateLocation(User $user, Etablissement $etablissement): bool
    {
        return in_array($user->role, [UserRole::Superadmin, UserRole::Admin], true);
    }

    public function delete(User $user, Etablissement $etablissement): bool
    {
        return in_array($user->role, [UserRole::Superadmin, UserRole::Admin], true);
    }

    public function restore(User $user, Etablissement $etablissement): bool
    {
        return false;
    }

    public function forceDelete(User $user, Etablissement $etablissement): bool
    {
        return false;
    }
}
