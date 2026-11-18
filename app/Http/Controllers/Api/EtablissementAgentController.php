<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Etablissement;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class EtablissementAgentController extends Controller
{
    public function store(Etablissement $etablissement, User $agent): JsonResponse
    {
        $this->ensureAgentRole($agent);

        $etablissement->agents()->syncWithoutDetaching([$agent->getKey()]);

        return response()->json([
            'message' => 'Agent affecte a l etablissement.',
        ], 201);
    }

    public function destroy(Etablissement $etablissement, User $agent): JsonResponse
    {
        $this->ensureAgentRole($agent);

        $etablissement->agents()->detach($agent->getKey());

        return response()->json([
            'message' => 'Affectation de l agent retiree.',
        ]);
    }

    private function ensureAgentRole(User $user): void
    {
        if ($user->role !== UserRole::Agent) {
            throw ValidationException::withMessages([
                'agent' => ['Le compte selectionne ne possede pas le role Agent.'],
            ]);
        }
    }
}
