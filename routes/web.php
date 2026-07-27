<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/login', function () {
    return Inertia::render('Login');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
});

Route::get('/overview', function () {
    return Inertia::render('Overview');
});

Route::get('/trouble-report', function () {
    return Inertia::render('TroubleReport', [
        'subsystem' => request()->query('subsystem', 'Subsystem Tidak Diketahui'),
    ]);
});

Route::get('/master-asset', function () {
    return Inertia::render('MasterAsset');
});

Route::get('/risk-matrix', function () {
    return Inertia::render('RiskMatrix');
});

Route::get('/inventory', function () {
    return Inertia::render('Inventory');
});

Route::get('/reorder-stock', function () {
    return Inertia::render('ReorderStock');
});
