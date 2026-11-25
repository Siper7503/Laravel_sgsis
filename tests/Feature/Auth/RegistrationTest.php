<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_public_registration_cannot_choose_a_privileged_role_or_disable_the_account(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Usager',
            'email' => 'public-usager@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'superadmin',
            'is_active' => false,
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'public-usager@example.com',
            'role' => 'usager',
            'is_active' => true,
            'email_verified_at' => null,
        ]);
    }
}
