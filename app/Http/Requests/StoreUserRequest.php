<?php

namespace App\Http\Requests;

use App\Models\User;
use App\UserFunction;
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

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->user();
        $creatableRoles = $user instanceof User
            ? array_map(
                fn (UserRole $role): string => $role->value,
                $user->role->creatableRoles(),
            )
            : [];
        $targetRole = UserRole::tryFrom((string) $this->input('role'));
        $assignableFunctions = $targetRole?->assignableFunctions() ?? [];

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in($creatableRoles)],
            'organizational_function' => $assignableFunctions === []
                ? ['prohibited']
                : [
                    'required',
                    Rule::in(array_map(
                        fn (UserFunction $function): string => $function->value,
                        $assignableFunctions,
                    )),
                ],
        ];
    }
}
