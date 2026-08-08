<?php

namespace App\Http\Controllers;

use App\Http\Requests\RamsAreaRequest;
use App\Services\RamsDashboardQuery;
use Inertia\Inertia;
use Inertia\Response;

class RamsDashboardController extends Controller
{
    public function dashboard(RamsAreaRequest $request, RamsDashboardQuery $query): Response
    {
        return Inertia::render('dashboard/Dashboard', $query->dashboard($request->user(), $request->selectedUnit()));
    }

    public function overview(RamsAreaRequest $request, RamsDashboardQuery $query): Response
    {
        return Inertia::render('dashboard/Overview', $query->overview($request->user(), $request->selectedUnit()));
    }

    public function riskMatrix(RamsAreaRequest $request, RamsDashboardQuery $query): Response
    {
        return Inertia::render('dashboard/RiskMatrix', $query->riskMatrix($request->user(), $request->selectedUnit()));
    }

    public function inventory(RamsAreaRequest $request, RamsDashboardQuery $query): Response
    {
        return Inertia::render('master-data/inventory/Inventory', $query->inventory($request->user(), $request->selectedUnit()));
    }

    public function troubleReport(RamsAreaRequest $request, RamsDashboardQuery $query): Response
    {
        $subsystem = $request->validated('subsystem') ?? 'Subsystem Tidak Diketahui';

        return Inertia::render(
            'input-data/TroubleReport',
            $query->troubleReport($request->user(), $request->selectedUnit(), $subsystem),
        );
    }
}
