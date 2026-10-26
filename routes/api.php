<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EtablissementController;

// Vérifier que l'API fonctionne
Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API SGSIS opérationnelle',
    ]);
});

// Connexion : accessible sans jeton
Route::post('/login', [AuthController::class, 'login']);

// Routes protégées : un jeton Sanctum valide est nécessaire
Route::middleware('auth:sanctum')->group(function () {

    // Informations de l'utilisateur connecté
    Route::get('/user', [AuthController::class, 'me']);

    // Déconnexion
    Route::post('/logout', [AuthController::class, 'logout']);

    // Gestion des établissements scolaires
    Route::apiResource(
        'etablissements',
        EtablissementController::class
    );
});
