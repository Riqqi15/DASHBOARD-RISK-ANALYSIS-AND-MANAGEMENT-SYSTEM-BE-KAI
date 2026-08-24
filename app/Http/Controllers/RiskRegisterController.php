<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RamsAreaRequest;
use App\Http\Requests\StoreRiskRegisterRequest;
use App\Http\Requests\UpdateRiskRegisterRequest;
use App\Models\Asset;
use App\Models\RiskRegister;
use App\Models\UnitKerja;
use App\Services\RamsUnitContext;
use App\Services\RiskRegisterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class RiskRegisterController extends Controller
{
    public function __construct(private readonly RamsUnitContext $unitContext) {}

    public function index(RamsAreaRequest $request): Response
    {
        $user = $request->user();
        $unit = $request->selectedUnit();
        $assets = Asset::query()
            ->visibleTo($user)
            ->when(
                $unit,
                fn (Builder $query): Builder => $query->where('unit_kerja_id', $unit->id),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            )
            ->with(['unitKerja:id,code,name', 'assetSubsystem:id,name'])
            ->orderBy('nama_aset')
            ->get();
        $registers = RiskRegister::query()
            ->visibleTo($user)
            ->when(
                $unit,
                fn (Builder $query): Builder => $query->whereHas(
                    'asset',
                    fn (Builder $assets): Builder => $assets->where('unit_kerja_id', $unit->id),
                ),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            )
            ->with(['asset.unitKerja:id,code,name', 'asset.assetSubsystem:id,name'])
            ->latest('updated_at')
            ->get();

        return Inertia::render('risk-register/Index', [
            'selected_area' => $unit?->code,
            'can_choose_unit' => $user->isPusat(),
            'units' => $user->isPusat()
                ? UnitKerja::query()
                    ->where('is_active', true)
                    ->orderBy('code')
                    ->get(['id', 'code', 'name'])
                : [],
            'assets' => $assets
                ->map(
                    fn (Asset $asset): array => [
                        'id' => $asset->id,
                        'name' => $asset->nama_aset,
                        'subsystem' => $asset->assetSubsystem?->name,
                        'unit' => $asset->unitKerja?->only(['id', 'code', 'name']),
                    ],
                )
                ->values(),
            'registers' => $registers
                ->map(
                    fn (RiskRegister $register): array => [
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
                            'subsystem' => $register->asset->assetSubsystem?->name,
                            'unit' => $register->asset->unitKerja?->only(['id', 'code', 'name']),
                        ],
                        'updated_at' => $register->updated_at->toIso8601String(),
                    ],
                )
                ->values(),
        ]);
    }

    public function store(StoreRiskRegisterRequest $request, RiskRegisterService $service): RedirectResponse
    {
        $unit = $this->mutationUnit($request);
        $service->create($request->safe()->except('unit_kerja_id'), $request->user(), $unit->id);

        return $this->scopedRedirect($request, $unit)->with('success', 'Risk Register berhasil ditambahkan.');
    }

    public function update(
        UpdateRiskRegisterRequest $request,
        RiskRegister $riskRegister,
        RiskRegisterService $service,
    ): RedirectResponse {
        $unit = $this->mutationUnit($request);
        $service->update($riskRegister, $request->safe()->except('unit_kerja_id'), $request->user(), $unit->id);

        return $this->scopedRedirect($request, $unit)->with('success', 'Risk Register berhasil diperbarui.');
    }

    public function destroy(
        RamsAreaRequest $request,
        RiskRegister $riskRegister,
        RiskRegisterService $service,
    ): RedirectResponse {
        $unit = $request->selectedUnit();
        abort_if($unit === null, 404);
        $service->delete($riskRegister, $request->user(), $unit->id);

        return $this->scopedRedirect($request, $unit)->with('success', 'Risk Register berhasil dihapus.');
    }

    private function mutationUnit(StoreRiskRegisterRequest|UpdateRiskRegisterRequest $request): UnitKerja
    {
        $unit = $this->unitContext->resolve($request) ?? abort(404);

        if ($request->user()->isPusat() && $request->integer('unit_kerja_id') !== $unit->id) {
            throw ValidationException::withMessages([
                'unit_kerja_id' => 'Unit kerja harus sama dengan wilayah RAMS yang sedang aktif.',
            ]);
        }

        return $unit;
    }

    private function scopedRedirect(Request $request, UnitKerja $unit): RedirectResponse
    {
        return to_route('risk-register.index', $request->user()->isPusat() ? ['area' => $unit->code] : []);
    }
}
