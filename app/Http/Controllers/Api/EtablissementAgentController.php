<?php

namespace App\Http\Controllers\Api;

use App\EtablissementType;
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
        $this->ensureCoordinatesExist($etablissement);
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
            EtablissementType::Primaire => UserFunction::DirecteurEcole,
            EtablissementType::Secondaire => UserFunction::Proviseur,
        };

        if (
            $user->role !== UserRole::Agent
            || $user->organizational_function !== $requiredFunction
        ) {
            throw ValidationException::withMessages([
                'agent' => ['La fonction du compte Agent ne correspond pas au type d’etablissement.'],
            ]);
        }
    }

    private function ensureCoordinatesExist(Etablissement $etablissement): void
    {
        if ($etablissement->latitude !== null && $etablissement->longitude !== null) {
            return;
        }

        throw ValidationException::withMessages([
            'etablissement' => ['La position GPS doit être renseignée avant l’affectation d’un agent.'],
        ]);
    }
}
