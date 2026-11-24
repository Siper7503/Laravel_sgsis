<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Etablissement;
use App\Models\User;
use App\UserFunction;
use App\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class EtablissementAgentController extends Controller
{
    public function store(Etablissement $etablissement, User $agent): JsonResponse
    {
        $this->ensureAgentCanManageEstablishment($etablissement, $agent);

        $etablissement->agents()->syncWithoutDetaching([$agent->getKey()]);

        return response()->json([
            'message' => 'Agent affecte a l etablissement.',
        ], 201);
    }

    public function destroy(Etablissement $etablissement, User $agent): JsonResponse
    {
        $this->ensureAgentCanManageEstablishment($etablissement, $agent);

        $etablissement->agents()->detach($agent->getKey());

        return response()->json([
            'message' => 'Affectation de l agent retiree.',
        ]);
    }

    private function ensureAgentCanManageEstablishment(Etablissement $etablissement, User $user): void
    {
        $requiredFunction = match ($etablissement->type) {
            'primaire' => UserFunction::DirecteurEcole,
            'secondaire' => UserFunction::Proviseur,
            default => null,
        };

        if (
            $user->role !== UserRole::Agent
            || $requiredFunction === null
            || $user->organizational_function !== $requiredFunction
        ) {
            throw ValidationException::withMessages([
                'agent' => ['La fonction du compte Agent ne correspond pas au type d’etablissement.'],
            ]);
        }
    }
}
