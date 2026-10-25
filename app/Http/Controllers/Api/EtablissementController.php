<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Etablissement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EtablissementController extends Controller
{
    public function index()
    {
        return response()->json(
            Etablissement::orderBy('nom')->paginate(20)
        );
    }
    public function store(Request $request)
    {
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
            'message' => 'Établissement créé avec succès.',
            'data' => $etablissement,
        ], 201);
    }

    public function show(Etablissement $etablissement)
    {
        return response()->json($etablissement);
    }

    public function update(Request $request, Etablissement $etablissement)
    {
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
            'message' => 'Établissement mis à jour avec succès.',
            'data' => $etablissement->fresh(),
        ]);
    }

    public function destroy(Etablissement $etablissement)
    {
        $etablissement->delete();

        return response()->json([
            'message' => 'Établissement supprimé avec succès.',
        ]);
    }
}
