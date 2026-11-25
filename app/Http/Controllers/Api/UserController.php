<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use App\UserFunction;
use App\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = new User;

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Str::random(64),
            'role' => UserRole::from($validated['role']),
            'organizational_function' => isset($validated['organizational_function'])
                ? UserFunction::from($validated['organizational_function'])
                : null,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        Password::sendResetLink(['email' => $user->email]);

        return response()->json([
            'message' => 'Compte cree et active. Un lien de definition du mot de passe a ete envoye.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'organizational_function' => $user->organizational_function?->value,
                'is_active' => $user->is_active,
            ],
        ], 201);
    }
}
