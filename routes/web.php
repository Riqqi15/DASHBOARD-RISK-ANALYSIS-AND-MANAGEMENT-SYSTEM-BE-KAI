<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::redirect('/', '/dashboard');

    Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/overview', fn () => Inertia::render('Overview'))->name('overview');
    Route::get('/trouble-report', fn () => Inertia::render('TroubleReport', [
        'subsystem' => request()->query('subsystem', 'Subsystem Tidak Diketahui'),
    ]))->name('trouble-report');
    Route::get('/master-asset', fn () => Inertia::render('MasterAsset'))->name('master-asset');
    Route::get('/risk-matrix', fn () => Inertia::render('RiskMatrix'))->name('risk-matrix');
    Route::get('/inventory', fn () => Inertia::render('Inventory'))->name('inventory');
    Route::get('/reorder-stock', fn () => Inertia::render('ReorderStock'))->name('reorder-stock');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
