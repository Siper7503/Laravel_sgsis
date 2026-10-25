<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EtablissementController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API SGSIS opérationnelle',
    ]);
});

// Gestion des établissements scolaires
Route::apiResource(
    'etablissements',
    EtablissementController::class
);
