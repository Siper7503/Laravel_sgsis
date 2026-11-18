<?php

namespace App\Http\Requests;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && in_array($user->role, [UserRole::Superadmin, UserRole::Admin], true);
    }

    public function rules(): array
    {
        $user = $this->user();
        $creatableRoles = $user instanceof User
            ? array_map(
                fn (UserRole $role): string => $role->value,
                $user->role->creatableRoles(),
            )
            : [];

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in($creatableRoles)],
        ];
    }
}
