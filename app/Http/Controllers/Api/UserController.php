<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
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
            'is_active' => true,
        ])->save();

        $user->sendEmailVerificationNotification();
        Password::sendResetLink(['email' => $user->email]);

        return response()->json([
            'message' => 'Compte cree. Des liens de verification et de definition du mot de passe ont ete envoyes.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'is_active' => $user->is_active,
            ],
        ], 201);
    }
}
