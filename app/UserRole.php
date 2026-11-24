<?php

namespace App;

enum UserRole: string
{
    case Superadmin = 'superadmin';
    case Admin = 'admin';
    case Agent = 'agent';
    case Usager = 'usager';

    public function dashboardRouteName(): string
    {
        return match ($this) {
            self::Superadmin, self::Admin => 'admin.dashboard',
            self::Agent => 'agent.dashboard',
            self::Usager => 'usager.dashboard',
        };
    }

    /** @return array<self> */
    public function creatableRoles(): array
    {
        return match ($this) {
            self::Superadmin => [
                self::Admin,
                self::Agent,
                self::Usager,
            ],
            self::Admin => [self::Agent, self::Usager],
            self::Agent, self::Usager => [],
        };
    }

    /** @return array<UserFunction> */
    public function assignableFunctions(): array
    {
        return match ($this) {
            self::Admin => [UserFunction::DirecteurDse, UserFunction::DirecteurDgss],
            self::Agent => [UserFunction::Proviseur, UserFunction::DirecteurEcole],
            self::Superadmin, self::Usager => [],
        };
    }
}
