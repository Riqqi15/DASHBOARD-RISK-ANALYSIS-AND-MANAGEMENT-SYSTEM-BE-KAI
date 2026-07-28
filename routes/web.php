<?php

use App\Http\Controllers\Admin\AssetCategoryController;
use App\Http\Controllers\Admin\AssetGroupController;
use App\Http\Controllers\Admin\AssetSubsystemController;
use App\Http\Controllers\Admin\AssetSystemController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\RegionalAccountController;
use App\Http\Controllers\Admin\UnitKerjaController;
use App\Http\Controllers\Admin\UnitSubsystemOpeningController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\MasterAssetController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::redirect('/', '/dashboard');

    Route::get('/dashboard', fn () => Inertia::render('dashboard/Dashboard'))->name('dashboard');
    Route::get('/overview', fn () => Inertia::render('dashboard/Overview'))->name('overview');
    Route::get('/trouble-report', fn () => Inertia::render('input-data/TroubleReport', [
        'subsystem' => request()->query('subsystem', 'Subsystem Tidak Diketahui'),
    ]))->name('trouble-report');
    Route::resource('master-asset', MasterAssetController::class)
        ->parameters(['master-asset' => 'asset'])
        ->except(['show'])
        ->names([
            'index' => 'master-assets.index',
            'create' => 'master-assets.create',
            'store' => 'master-assets.store',
            'edit' => 'master-assets.edit',
            'update' => 'master-assets.update',
            'destroy' => 'master-assets.destroy',
        ]);
    Route::get('/risk-matrix', fn () => Inertia::render('dashboard/RiskMatrix'))->name('risk-matrix');
    Route::get('/inventory', fn () => Inertia::render('master-data/inventory/Inventory'))->name('inventory');
    Route::get('/reorder-stock', fn () => Inertia::render('master-data/inventory/ReorderStock'))->name('reorder-stock');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::middleware(['auth', 'active', 'pusat'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('asset-categories', AssetCategoryController::class)->name('asset-categories.index');
    Route::patch('asset-groups/{asset_group}/status', [AssetGroupController::class, 'status'])->name('asset-groups.status');
    Route::resource('asset-groups', AssetGroupController::class)->only(['store', 'update', 'destroy']);
    Route::patch('asset-systems/{asset_system}/status', [AssetSystemController::class, 'status'])->name('asset-systems.status');
    Route::resource('asset-systems', AssetSystemController::class)->only(['store', 'update', 'destroy']);
    Route::patch('asset-subsystems/{asset_subsystem}/status', [AssetSubsystemController::class, 'status'])->name('asset-subsystems.status');
    Route::resource('asset-subsystems', AssetSubsystemController::class)->only(['store', 'update', 'destroy']);
    Route::resource('units', UnitKerjaController::class)
        ->parameters(['units' => 'unit'])
        ->except(['show', 'destroy']);
    Route::resource('accounts', RegionalAccountController::class)->except(['show', 'destroy']);
    Route::patch('accounts/{account}/status', [RegionalAccountController::class, 'status'])->name('accounts.status');
    Route::get('accounts/{account}/password', [RegionalAccountController::class, 'editPassword'])->name('accounts.password.edit');
    Route::put('accounts/{account}/password', [RegionalAccountController::class, 'updatePassword'])->name('accounts.password.update');
    Route::get('audit-logs', AuditLogController::class)->name('audit-logs.index');
    Route::put('unit-subsystem-openings/{opening}', [UnitSubsystemOpeningController::class, 'update'])
        ->name('unit-subsystem-openings.update');
});
