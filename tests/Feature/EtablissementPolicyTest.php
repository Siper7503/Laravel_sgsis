<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\User;
use App\Policies\EtablissementPolicy;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EtablissementPolicyTest extends TestCase
{
    use RefreshDatabase;

    public static function administrativeRoles(): array
    {
        return [
            'superadmin' => [UserRole::Superadmin],
            'admin' => [UserRole::Admin],
        ];
    }

    #[DataProvider('administrativeRoles')]
    public function test_administrators_can_manage_every_establishment(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $etablissement = Etablissement::factory()->create();
        $policy = new EtablissementPolicy;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->viewMap($user));
        $this->assertTrue($policy->view($user, $etablissement));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $etablissement));
        $this->assertTrue($policy->updateLocation($user, $etablissement));
        $this->assertTrue($policy->delete($user, $etablissement));
    }

    public function test_usagers_can_view_but_cannot_change_establishments(): void
    {
        $user = User::factory()->create(['role' => UserRole::Usager]);
        $etablissement = Etablissement::factory()->create();
        $policy = new EtablissementPolicy;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $etablissement));
        $this->assertTrue($policy->viewMap($user));
        $this->assertFalse($policy->create($user));
        $this->assertFalse($policy->update($user, $etablissement));
        $this->assertFalse($policy->updateLocation($user, $etablissement));
        $this->assertFalse($policy->delete($user, $etablissement));
    }

    public function test_usagers_cannot_view_inactive_establishments(): void
    {
        $user = User::factory()->create(['role' => UserRole::Usager]);
        $inactive = Etablissement::factory()->create(['actif' => false]);
        $policy = new EtablissementPolicy;

        $this->assertFalse($policy->view($user, $inactive));
    }

    public function test_agents_can_view_and_update_only_assigned_establishments(): void
    {
        $agent = User::factory()->create(['role' => UserRole::Agent]);
        $assigned = Etablissement::factory()->create();
        $unassigned = Etablissement::factory()->create();
        $agent->etablissements()->attach($assigned);
        $policy = new EtablissementPolicy;

        $this->assertTrue($policy->viewAny($agent));
        $this->assertTrue($policy->viewMap($agent));
        $this->assertTrue($policy->view($agent, $assigned));
        $this->assertTrue($policy->update($agent, $assigned));
        $this->assertFalse($policy->updateLocation($agent, $assigned));
        $this->assertFalse($policy->view($agent, $unassigned));
        $this->assertFalse($policy->update($agent, $unassigned));
        $this->assertFalse($policy->create($agent));
        $this->assertFalse($policy->delete($agent, $assigned));
    }
}
