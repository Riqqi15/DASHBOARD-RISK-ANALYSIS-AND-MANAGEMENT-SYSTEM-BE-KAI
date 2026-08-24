<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaxonomyAssetRequest;
use App\Models\Asset;
use App\Models\AssetCategoryNode;
use App\Services\AssetTaxonomyService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AssetTaxonomyAssetController extends Controller
{
    public function __construct(
        private readonly AssetTaxonomyService $taxonomy,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function store(StoreTaxonomyAssetRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $node = AssetCategoryNode::query()
                ->with('level')
                ->lockForUpdate()
                ->findOrFail($request->integer('asset_category_node_id'));
            $path = $this->taxonomy->path($node)->each->loadMissing('level')->all();
            $asset = Asset::query()->create($request->assetData($node, $path));
            $this->auditLogger->record(
                'asset.created',
                $asset,
                [],
                $asset->only([
                    'id',
                    'unit_kerja_id',
                    'asset_category_node_id',
                    'asset_subsystem_id',
                    'nama_aset',
                    'jumlah_unit',
                    'status',
                ]),
            );
        });

        return to_route('admin.asset-categories.index', [
            'unit_kerja_id' => $request->unitId(),
            'node' => $request->integer('asset_category_node_id'),
        ])->with('success', 'Aset wilayah berhasil ditambahkan.');
    }
}
