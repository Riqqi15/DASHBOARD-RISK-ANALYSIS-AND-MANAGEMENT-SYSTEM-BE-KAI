<?php

use App\Http\Controllers\Admin\AssetCategoryController;
use App\Http\Controllers\Admin\AssetCategoryLevelController;
use App\Http\Controllers\Admin\AssetCategoryNodeController;
use App\Http\Controllers\Admin\AssetGroupController;
use App\Http\Controllers\Admin\AssetSubsystemController;
use App\Http\Controllers\Admin\AssetSystemController;
use App\Http\Controllers\Admin\RegionalAccountController;
use App\Http\Controllers\Admin\SparePartController;
use App\Http\Controllers\Admin\UnitKerjaController;
use App\Http\Controllers\Admin\UnitSubsystemOpeningController;
use App\Http\Controllers\ArchiveAssetTaxonomyBranchController;
use App\Http\Controllers\AssetTaxonomyAssetController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\FailureLogController;
use App\Http\Controllers\FailureLogImportController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MasterAssetController;
use App\Http\Controllers\RamsDashboardController;
use App\Http\Controllers\RamsImportRollbackController;
use App\Http\Controllers\RamsReportController;
use App\Http\Controllers\RiskRegisterController;
use App\Http\Controllers\StockMovementController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::redirect('/', '/dashboard');
    Route::redirect('/overview', '/dashboard');

    Route::get('/dashboard', [RamsDashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/trouble-report/import', [FailureLogImportController::class, 'index'])->name('failure-logs.import.index');
    Route::post('/trouble-report/import', [FailureLogImportController::class, 'store'])->name('failure-logs.import.store');
    Route::get('/trouble-report/import/batch/{batch}', [FailureLogImportController::class, 'show'])->name('failure-logs.import.show');
    Route::post('/trouble-report/import/batch/{batch}/rollback', RamsImportRollbackController::class)->name('failure-logs.import.rollback');
    Route::get('/trouble-report/import/batch/{batchId}/issues/csv', [FailureLogImportController::class, 'downloadIssues'])->name('failure-logs.import.issues.csv');
    Route::get('/trouble-report', [RamsDashboardController::class, 'troubleReport'])->name('trouble-report');
    Route::post('/trouble-report', [FailureLogController::class, 'store'])->name('failure-logs.store');
    Route::put('/trouble-report/{log}', [FailureLogController::class, 'update'])->name('failure-logs.update');
    Route::delete('/trouble-report/{log}', [FailureLogController::class, 'destroy'])->name('failure-logs.destroy');
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
    Route::get('/risk-matrix', [RamsDashboardController::class, 'riskMatrix'])->name('risk-matrix');
    Route::resource('risk-register', RiskRegisterController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['risk-register' => 'riskRegister'])
        ->names('risk-register');
    Route::get('/reports', [RamsReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{report}/xlsx', [RamsReportController::class, 'download'])->name('reports.download');
    Route::get('/reports/{report}/pdf', [RamsReportController::class, 'downloadPdf'])->name('reports.download.pdf');
    Route::get('/inventory', InventoryController::class)->name('inventory');
    Route::get('/inventory/stock-state', [StockMovementController::class, 'state'])->name('stock-movements.state');
    Route::post('/inventory/movements', [StockMovementController::class, 'store'])->name('stock-movements.store');
    Route::post('/inventory/movements/{movement}/corrections', [StockMovementController::class, 'correct'])->name('stock-movements.correct');
    Route::redirect('/reorder-stock', '/inventory?tab=master')->name('reorder-stock');

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('asset-categories', AssetCategoryController::class)->name('asset-categories.index');
        Route::post('asset-category-levels', [AssetCategoryLevelController::class, 'store'])->name('asset-category-levels.store');
        Route::put('asset-category-levels/{asset_category_level}', [AssetCategoryLevelController::class, 'update'])->name('asset-category-levels.update');
        Route::delete('asset-category-levels/{asset_category_level}', [AssetCategoryLevelController::class, 'destroy'])->name('asset-category-levels.destroy');
        Route::post('asset-category-nodes', [AssetCategoryNodeController::class, 'store'])->name('asset-category-nodes.store');
        Route::put('asset-category-nodes/{asset_category_node}', [AssetCategoryNodeController::class, 'update'])->name('asset-category-nodes.update');
        Route::delete('asset-category-nodes/{asset_category_node}', [AssetCategoryNodeController::class, 'destroy'])->name('asset-category-nodes.destroy');
        Route::post('asset-category-assets', [AssetTaxonomyAssetController::class, 'store'])->name('asset-category-assets.store');
        Route::get('asset-category-nodes/{asset_category_node}/archive-preview', [ArchiveAssetTaxonomyBranchController::class, 'preview'])->name('asset-category-nodes.archive-preview');
        Route::delete('asset-category-nodes/{asset_category_node}/assets', [ArchiveAssetTaxonomyBranchController::class, 'destroy'])->name('asset-category-nodes.assets.destroy');
    });

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::middleware(['auth', 'active', 'pusat'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::patch('asset-groups/{asset_group}/status', [AssetGroupController::class, 'status'])->name('asset-groups.status');
    Route::resource('asset-groups', AssetGroupController::class)->only(['store', 'update', 'destroy']);
    Route::patch('asset-systems/{asset_system}/status', [AssetSystemController::class, 'status'])->name('asset-systems.status');
    Route::resource('asset-systems', AssetSystemController::class)->only(['store', 'update', 'destroy']);
    Route::patch('asset-subsystems/{asset_subsystem}/status', [AssetSubsystemController::class, 'status'])->name('asset-subsystems.status');
    Route::resource('asset-subsystems', AssetSubsystemController::class)->only(['store', 'update', 'destroy']);
    Route::resource('spare-parts', SparePartController::class)->only(['store', 'update', 'destroy']);
    Route::resource('units', UnitKerjaController::class)
        ->parameters(['units' => 'unit'])
        ->except(['show', 'destroy']);
    Route::resource('accounts', RegionalAccountController::class)->except(['index', 'show', 'destroy']);
    Route::patch('accounts/{account}/status', [RegionalAccountController::class, 'status'])->name('accounts.status');
    Route::get('accounts/{account}/password', [RegionalAccountController::class, 'editPassword'])->name('accounts.password.edit');
    Route::put('accounts/{account}/password', [RegionalAccountController::class, 'updatePassword'])->name('accounts.password.update');
    Route::put('unit-subsystem-openings/{opening}', [UnitSubsystemOpeningController::class, 'update'])
        ->name('unit-subsystem-openings.update');
});
