<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Models\Asset;
use App\Models\UnitKerja;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MasterAssetController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Asset::class);

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $unitId = $request->filled('unit_kerja_id') ? $request->integer('unit_kerja_id') : null;
        $query = $this->filteredQuery($request, $search, $status, $unitId);

        $stats = [
            'total_assets' => (clone $query)->count(),
            'total_units' => (int) (clone $query)->sum('jumlah_unit'),
            'active_assets' => (clone $query)->where('status', AssetStatus::Aktif->value)->count(),
            'unique_subsystems' => (clone $query)->distinct()->count('subsystem'),
        ];

        $assets = $query
            ->with('unitKerja:id,code,name')
            ->orderBy('unit_kerja_id')
            ->orderBy('system')
            ->orderBy('subsystem')
            ->orderBy('nama_aset')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('master-data/assets/MasterAsset', [
            'assets' => $assets,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'unit_kerja_id' => $unitId ? (string) $unitId : '',
            ],
            'units' => $request->user()->isPusat() ? $this->activeUnits() : [],
            'statusOptions' => $this->statusOptions(),
            'can' => ['choose_unit' => $request->user()->isPusat()],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Asset::class);

        return Inertia::render('master-data/assets/Create', $this->formProps($request));
    }

    public function store(StoreAssetRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $asset = Asset::query()->create($request->assetData());
            $this->auditLogger->record('asset.created', $asset, [], $this->auditValues($asset));
        });

        return redirect()->route('master-assets.index')->with('success', 'Aset berhasil ditambahkan.');
    }

    public function edit(Request $request, int $asset): Response
    {
        $asset = $this->visibleAsset($request, $asset);
        Gate::authorize('update', $asset);

        return Inertia::render('master-data/assets/Edit', [
            ...$this->formProps($request),
            'asset' => $this->assetPayload($asset),
        ]);
    }

    public function update(UpdateAssetRequest $request, int $asset): RedirectResponse
    {
        $asset = $this->visibleAsset($request, $asset);

        DB::transaction(function () use ($request, $asset): void {
            $before = $this->auditValues($asset);
            $asset->update($request->assetData());
            $this->auditLogger->record('asset.updated', $asset, $before, $this->auditValues($asset->fresh()));
        });

        return redirect()->route('master-assets.index')->with('success', 'Aset berhasil diperbarui.');
    }

    public function destroy(Request $request, int $asset): RedirectResponse
    {
        $asset = $this->visibleAsset($request, $asset);
        Gate::authorize('delete', $asset);

        DB::transaction(function () use ($asset): void {
            $before = $this->auditValues($asset);
            $asset->delete();
            $this->auditLogger->record('asset.deleted', $asset, $before, []);
        });

        return redirect()->route('master-assets.index')->with('success', 'Aset berhasil dihapus.');
    }

    private function filteredQuery(Request $request, string $search, string $status, ?int $unitId): Builder
    {
        return Asset::query()
            ->visibleTo($request->user())
            ->search($search)
            ->when(
                AssetStatus::tryFrom($status),
                fn (Builder $query, AssetStatus $validStatus): Builder => $query->where('status', $validStatus->value),
            )
            ->when(
                $request->user()->isPusat() && $unitId,
                fn (Builder $query): Builder => $query->where('unit_kerja_id', $unitId),
            );
    }

    private function visibleAsset(Request $request, int $id): Asset
    {
        return Asset::query()->visibleTo($request->user())->findOrFail($id);
    }

    private function formProps(Request $request): array
    {
        return [
            'units' => $request->user()->isPusat() ? $this->activeUnits() : [],
            'statusOptions' => $this->statusOptions(),
            'can' => ['choose_unit' => $request->user()->isPusat()],
        ];
    }

    private function activeUnits()
    {
        return UnitKerja::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
    }

    private function statusOptions(): array
    {
        return array_map(
            fn (AssetStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            AssetStatus::cases(),
        );
    }

    private function assetPayload(Asset $asset): array
    {
        return [
            ...$asset->only([
                'id',
                'unit_kerja_id',
                'nama_aset',
                'aset_prasarana_sintel',
                'system',
                'subsystem',
                'lokasi',
                'jumlah_unit',
            ]),
            'status' => $asset->status->value,
            'tanggal_pemasangan' => $asset->tanggal_pemasangan?->toDateString(),
        ];
    }

    private function auditValues(Asset $asset): array
    {
        return $this->assetPayload($asset);
    }
}
