<?php

use App\Http\Controllers\Api\AnnualReportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EtablissementAgentController;
use App\Http\Controllers\Api\EtablissementController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API SGSIS operationnelle',
    ]);
});

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:api-login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware(['active', 'verified'])->group(function () {
        Route::get('/user', [AuthController::class, 'me']);

        Route::middleware('role:superadmin,admin')->group(function () {
            Route::post('/users', [UserController::class, 'store']);

            Route::post(
                '/etablissements/{etablissement}/agents/{agent}',
                [EtablissementAgentController::class, 'store'],
            )->name('etablissements.agents.store');

            Route::delete(
                '/etablissements/{etablissement}/agents/{agent}',
                [EtablissementAgentController::class, 'destroy'],
            )->name('etablissements.agents.destroy');
        });

        Route::apiResource('etablissements', EtablissementController::class);

        Route::post(
            '/etablissements/{etablissement}/annual-reports',
            [AnnualReportController::class, 'store'],
        )->name('annual-reports.store');

        Route::apiResource('annual-reports', AnnualReportController::class)
            ->only(['index', 'show', 'update'])
            ->parameters(['annual-reports' => 'annualReport']);

        Route::post(
            '/annual-reports/{annualReport}/submit',
            [AnnualReportController::class, 'submit'],
        )->name('annual-reports.submit');

        Route::post(
            '/annual-reports/{annualReport}/dse-review',
            [AnnualReportController::class, 'reviewByDse'],
        )->name('annual-reports.dse-review');

        Route::post(
            '/annual-reports/{annualReport}/dgss-review',
            [AnnualReportController::class, 'reviewByDgss'],
        )->name('annual-reports.dgss-review');
    });
});
