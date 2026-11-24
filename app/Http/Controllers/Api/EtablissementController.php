<?php

namespace App\Http\Controllers\Api;

use App\EtablissementType;
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
        } elseif ($request->user()->role === UserRole::Usager) {
            $query->where('actif', true);
        }

        return response()->json($query->paginate(20));
    }

    public function map(Request $request): JsonResponse
    {
        Gate::authorize('viewMap', Etablissement::class);

        $validated = $request->validate([
            'q' => ['sometimes', 'string', 'max:200'],
            'arrondissement' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', Rule::enum(EtablissementType::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);

        $user = $request->user();
        $query = Etablissement::query()
            ->select([
                'id',
                'code',
                'nom',
                'type',
                'arrondissement',
                'adresse',
                'latitude',
                'longitude',
            ])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($user->role === UserRole::Agent) {
            $query->whereHas('agents', function (Builder $agents) use ($user): void {
                $agents->whereKey($user->getKey());
            });
        } elseif ($user->role === UserRole::Usager) {
            $query->where('actif', true);
        }

        $query
            ->when(
                isset($validated['q']),
                fn (Builder $query) => $query->whereRaw(
                    'LOWER(nom) LIKE ?',
                    ['%'.mb_strtolower($validated['q']).'%'],
                ),
            )
            ->when(
                isset($validated['arrondissement']),
                fn (Builder $query) => $query->where('arrondissement', $validated['arrondissement']),
            )
            ->when(
                isset($validated['type']),
                fn (Builder $query) => $query->where('type', $validated['type']),
            )
            ->orderBy('nom');

        return response()->json($query->paginate($validated['per_page'] ?? 100));
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
            'latitude' => ['required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['required_with:latitude', 'numeric', 'between:-180,180'],
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

        if ($request->exists('latitude') || $request->exists('longitude')) {
            Gate::authorize('updateLocation', $etablissement);
        }

        $validated = $request->validate([
            'code' => [
                'sometimes', 'required', 'string', 'max:30',
                Rule::unique('etablissements', 'code')->ignore($etablissement->id),
            ],
            'nom' => ['sometimes', 'required', 'string', 'max:200'],
            'type' => ['sometimes', 'required', Rule::in(['primaire', 'secondaire'])],
            'arrondissement' => ['nullable', 'string', 'max:100'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['required_with:latitude', 'numeric', 'between:-180,180'],
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
