<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategoryNode;
use App\Models\AuditLog;
use App\Services\AssetTaxonomyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ArchiveAssetTaxonomyBranchController extends Controller
{
    public function __construct(private readonly AssetTaxonomyService $taxonomy) {}

    public function preview(Request $request, AssetCategoryNode $assetCategoryNode): JsonResponse
    {
        Gate::authorize('viewAny', Asset::class);
        $unitId = $this->authorizedUnitId($request);
        $assetIds = $this->assets($assetCategoryNode, $unitId)->pluck('id');
        $history = $this->historyCounts($assetIds);

        return response()->json([
            'unit_kerja_id' => $unitId,
            'assets_count' => $assetIds->count(),
            'total_units' => (int) Asset::query()->whereKey($assetIds)->sum('jumlah_unit'),
            'historical_records_count' => $history->sum(),
            'history' => $history,
        ]);
    }

    public function destroy(Request $request, AssetCategoryNode $assetCategoryNode): RedirectResponse
    {
        Gate::authorize('viewAny', Asset::class);
        $unitId = $this->authorizedUnitId($request);
        validator($request->all(), [
            'confirmation' => ['required', 'in:HAPUS ASET WILAYAH'],
        ])->validate();

        $count = DB::transaction(function () use ($request, $assetCategoryNode, $unitId): int {
            $assets = $this->assets($assetCategoryNode, $unitId)->lockForUpdate()->get();
            foreach ($assets as $asset) {
                $asset->delete();
            }

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => 'asset.branch_archived',
                'auditable_type' => $assetCategoryNode->getMorphClass(),
                'auditable_id' => $assetCategoryNode->id,
                'unit_kerja_id' => $unitId,
                'old_values' => ['active_assets' => $assets->count()],
                'new_values' => ['archived_assets' => $assets->count()],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $assets->count();
        });

        return back()->with('success', "{$count} aset wilayah berhasil diarsipkan. Riwayat laporan tetap tersimpan.");
    }

    private function authorizedUnitId(Request $request): int
    {
        if ($request->user()->isUnit()) {
            if ($request->filled('unit_kerja_id') && $request->integer('unit_kerja_id') !== $request->user()->unit_kerja_id) {
                throw ValidationException::withMessages(['unit_kerja_id' => 'Akun wilayah hanya dapat mengelola unit kerjanya sendiri.']);
            }

            return (int) $request->user()->unit_kerja_id;
        }

        $validated = validator($request->all(), [
            'unit_kerja_id' => ['required', 'integer', 'exists:unit_kerjas,id'],
        ])->validate();

        return (int) $validated['unit_kerja_id'];
    }

    private function assets(AssetCategoryNode $node, int $unitId): Builder
    {
        return Asset::query()
            ->where('unit_kerja_id', $unitId)
            ->whereIn('asset_category_node_id', $this->taxonomy->subtreeIds($node));
    }

    private function historyCounts($assetIds): Collection
    {
        return collect([
            'trouble_reports' => DB::table('failure_logs')->whereIn('asset_id', $assetIds)->count(),
            'risk_registers' => DB::table('risk_registers')->whereIn('asset_id', $assetIds)->count(),
            'risk_matrix' => DB::table('risk_matrices')->whereIn('asset_id', $assetIds)->count(),
            'reliability' => DB::table('reliability_summaries')->whereIn('asset_id', $assetIds)->count(),
            'reliability_excel' => DB::table('reliability_excel_snapshots')->whereIn('asset_id', $assetIds)->count(),
            'predictive_snapshots' => DB::table('predictive_asset_snapshots')->whereIn('asset_id', $assetIds)->count(),
        ]);
    }
}
