<?php

namespace App\Console\Commands;

use App\Models\User;
use App\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateSuperadminCommand extends Command
{
    protected $signature = 'sgsis:create-superadmin';

    protected $description = 'Create the initial SGSIS superadmin account';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Run this command in an interactive terminal.');

            return self::FAILURE;
        }

        if (User::query()->where('role', UserRole::Superadmin->value)->exists()) {
            $this->error('A superadmin account already exists.');

            return self::FAILURE;
        }

        $name = $this->ask('Full name');
        $email = $this->ask('Email address');
        $password = $this->secret('Password');
        $passwordConfirmation = $this->secret('Confirm password');

        if ($password !== $passwordConfirmation) {
            $this->error('The password confirmation does not match.');

            return self::FAILURE;
        }

        $validated = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::default()],
        ])->validate();

        $user = new User;
        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => UserRole::Superadmin,
            'is_active' => true,
        ])->save();

        $user->sendEmailVerificationNotification();

        $this->info('Superadmin created. Verify the email address before using the dashboard.');

        return self::SUCCESS;
    }
}
