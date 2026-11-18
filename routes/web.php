<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('dashboard', function (Request $request) {
        return to_route($request->user()->role->dashboardRouteName());
    })->name('dashboard');

    Route::inertia('admin/dashboard', 'dashboard')
        ->middleware('role:superadmin,admin')
        ->name('admin.dashboard');

    Route::inertia('agent/dashboard', 'dashboard')
        ->middleware('role:agent')
        ->name('agent.dashboard');

    Route::inertia('usager/dashboard', 'dashboard')
        ->middleware('role:usager')
        ->name('usager.dashboard');
});

require __DIR__.'/settings.php';
