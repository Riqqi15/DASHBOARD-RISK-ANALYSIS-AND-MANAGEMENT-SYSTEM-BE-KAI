<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RamsAreaRequest;
use App\Http\Requests\StoreRiskRegisterRequest;
use App\Http\Requests\UpdateRiskRegisterRequest;
use App\Models\Asset;
use App\Models\RiskRegister;
use App\Models\UnitKerja;
use App\Services\RiskRegisterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class RiskRegisterController extends Controller
{
    public function index(RamsAreaRequest $request): Response
    {
        $user = $request->user();
        $unit = $request->selectedUnit();
        $assets = Asset::query()
            ->visibleTo($user)
            ->when($unit, fn (Builder $query): Builder => $query->where('unit_kerja_id', $unit->id))
            ->with(['unitKerja:id,code,name', 'assetSubsystem:id,name'])
            ->orderBy('nama_aset')
            ->get();
        $registers = RiskRegister::query()
            ->visibleTo($user)
            ->when($unit, fn (Builder $query): Builder => $query->whereHas(
                'asset',
                fn (Builder $assets): Builder => $assets->where('unit_kerja_id', $unit->id),
            ))
            ->with(['asset.unitKerja:id,code,name', 'asset.assetSubsystem:id,name'])
            ->latest('updated_at')
            ->get();

        return Inertia::render('risk-register/Index', [
            'selected_area' => $unit?->code,
            'can_choose_unit' => $user->isPusat(),
            'units' => $user->isPusat()
                ? UnitKerja::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])
                : [],
            'assets' => $assets->map(fn (Asset $asset): array => [
                'id' => $asset->id,
                'name' => $asset->nama_aset,
                'location' => $asset->lokasi,
                'subsystem' => $asset->assetSubsystem?->name,
                'unit' => $asset->unitKerja?->only(['id', 'code', 'name']),
            ])->values(),
            'registers' => $registers->map(fn (RiskRegister $register): array => [
                'id' => $register->id,
                'asset_id' => $register->asset_id,
                'part_number' => $register->part_number,
                'sub' => $register->sub,
                'risk_event' => $register->risk_event,
                'risk_cause' => $register->risk_cause,
                'impact' => $register->impact,
                'part_name' => $register->part_name,
                'recommendation' => $register->recommendation,
                'likelihood' => $register->likelihood,
                'consequence' => $register->consequence,
                'rating' => $register->rating,
                'status' => $register->status->value,
                'source' => $register->source_key ? 'excel' : 'manual',
                'asset' => [
                    'name' => $register->asset->nama_aset,
                    'location' => $register->asset->lokasi,
                    'subsystem' => $register->asset->assetSubsystem?->name,
                    'unit' => $register->asset->unitKerja?->only(['id', 'code', 'name']),
                ],
                'updated_at' => $register->updated_at->toIso8601String(),
            ])->values(),
        ]);
    }

    public function store(StoreRiskRegisterRequest $request, RiskRegisterService $service): RedirectResponse
    {
        $service->create($request->validated(), $request->user());

        return to_route('risk-register.index')->with('success', 'Risk Register berhasil ditambahkan.');
    }

    public function update(UpdateRiskRegisterRequest $request, RiskRegister $riskRegister, RiskRegisterService $service): RedirectResponse
    {
        $service->update($riskRegister, $request->validated(), $request->user());

        return back()->with('success', 'Risk Register berhasil diperbarui.');
    }

    public function destroy(RamsAreaRequest $request, RiskRegister $riskRegister, RiskRegisterService $service): RedirectResponse
    {
        $service->delete($riskRegister, $request->user());

        return back()->with('success', 'Risk Register berhasil dihapus.');
    }
}
