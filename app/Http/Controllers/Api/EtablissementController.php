<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Etablissement;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EtablissementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Etablissement::class);

        $query = Etablissement::query()->orderBy('nom');

        if ($request->user()->role === UserRole::Agent) {
            $query->whereHas('agents', function (Builder $query) use ($request): void {
                $query->whereKey($request->user()->getKey());
            });
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Etablissement::class);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:etablissements,code'],
            'nom' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in(['primaire', 'secondaire'])],
            'arrondissement' => ['nullable', 'string', 'max:100'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $etablissement = Etablissement::create($validated);

        return response()->json([
            'message' => 'Etablissement cree avec succes.',
            'data' => $etablissement,
        ], 201);
    }

    public function show(Request $request, Etablissement $etablissement): JsonResponse
    {
        $this->hideUnassignedEstablishmentFromAgent($request->user(), $etablissement);
        Gate::authorize('view', $etablissement);

        return response()->json($etablissement);
    }

    public function update(Request $request, Etablissement $etablissement): JsonResponse
    {
        $this->hideUnassignedEstablishmentFromAgent($request->user(), $etablissement);
        Gate::authorize('update', $etablissement);

        $validated = $request->validate([
            'code' => [
                'sometimes', 'required', 'string', 'max:30',
                Rule::unique('etablissements', 'code')->ignore($etablissement->id),
            ],
            'nom' => ['sometimes', 'required', 'string', 'max:200'],
            'type' => ['sometimes', 'required', Rule::in(['primaire', 'secondaire'])],
            'arrondissement' => ['nullable', 'string', 'max:100'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $etablissement->update($validated);

        return response()->json([
            'message' => 'Etablissement mis a jour avec succes.',
            'data' => $etablissement->fresh(),
        ]);
    }

    public function destroy(Request $request, Etablissement $etablissement): JsonResponse
    {
        $this->hideUnassignedEstablishmentFromAgent($request->user(), $etablissement);
        Gate::authorize('delete', $etablissement);

        $etablissement->delete();

        return response()->json([
            'message' => 'Etablissement supprime avec succes.',
        ]);
    }

    private function hideUnassignedEstablishmentFromAgent(
        User $user,
        Etablissement $etablissement,
    ): void {
        if (
            $user->role === UserRole::Agent
            && ! $user->etablissements()->whereKey($etablissement->getKey())->exists()
        ) {
            abort(404);
        }
    }
}
