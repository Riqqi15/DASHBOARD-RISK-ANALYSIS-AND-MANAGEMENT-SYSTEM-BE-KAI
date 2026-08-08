<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\InventoryStock;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class InventoryReconciliationService
{
    /** @param array<string, string> $filters
     * @return array{rows: list<array<string, mixed>>, stats: array<string, int>}
     */
    public function reconcile(User $user, array $filters): array
    {
        $assets = Asset::query()
            ->visibleTo($user)
            ->whereHas('latestPredictiveAssetSnapshot')
            ->with(['unitKerja:id,code,name', 'assetSubsystem.assetSystem.assetGroup', 'latestPredictiveAssetSnapshot'])
            ->when($filters['unit_kerja_id'] ?? '', fn (Builder $query, string $id): Builder => $query->where('unit_kerja_id', (int) $id))
            ->when($filters['asset_subsystem_id'] ?? '', fn (Builder $query, string $id): Builder => $query->where('asset_subsystem_id', (int) $id))
            ->when($filters['asset_group_id'] ?? '', fn (Builder $query, string $id): Builder => $query->whereHas(
                'assetSubsystem.assetSystem',
                fn (Builder $systems): Builder => $systems->where('asset_group_id', (int) $id),
            ))
            ->search($filters['search'] ?? '')
            ->orderBy('unit_kerja_id')
            ->orderBy('nama_aset')
            ->get();

        $stocks = InventoryStock::query()
            ->visibleTo($user)
            ->with(['unitKerja:id,code,name', 'sparePart.assetSubsystem.assetSystem.assetGroup'])
            ->when($filters['unit_kerja_id'] ?? '', fn (Builder $query, string $id): Builder => $query->where('unit_kerja_id', (int) $id))
            ->when($filters['asset_subsystem_id'] ?? '', fn (Builder $query, string $id): Builder => $query->whereHas(
                'sparePart', fn (Builder $parts): Builder => $parts->where('asset_subsystem_id', (int) $id),
            ))
            ->when($filters['asset_group_id'] ?? '', fn (Builder $query, string $id): Builder => $query->whereHas(
                'sparePart.assetSubsystem.assetSystem',
                fn (Builder $systems): Builder => $systems->where('asset_group_id', (int) $id),
            ))
            ->when($filters['search'] ?? '', function (Builder $query, string $search): Builder {
                return $query->whereHas('sparePart', fn (Builder $parts): Builder => $parts
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('equipment', 'like', "%{$search}%")
                    ->orWhere('detail_equipment', 'like', "%{$search}%"));
            })
            ->get();

        $stocksByScope = $stocks->groupBy(fn (InventoryStock $stock): string => $this->scopeKey(
            $stock->unit_kerja_id,
            $stock->sparePart->asset_subsystem_id,
        ));
        $usedStockIds = [];
        $rows = [];

        foreach ($assets as $asset) {
            $snapshot = $asset->latestPredictiveAssetSnapshot;
            $candidates = $stocksByScope->get($this->scopeKey($asset->unit_kerja_id, $asset->asset_subsystem_id), collect());
            $exact = $this->exactCandidates($candidates, $asset->nama_aset);
            $matched = $exact->count() === 1
                ? $exact->first()
                : ($exact->isEmpty() && $candidates->count() === 1 ? $candidates->first() : null);

            if ($matched instanceof InventoryStock) {
                $usedStockIds[$matched->id] = true;
                $excel = (int) $snapshot->current_stock;
                $ledger = (int) $matched->quantity;
                $rows[] = $this->matchedRow($asset, $matched, $excel, $ledger, $exact->count() === 1 ? 'exact' : 'subsystem_only');

                continue;
            }

            $rows[] = $this->assetOnlyRow($asset, (int) $snapshot->current_stock, $candidates->isEmpty() ? 'missing_ledger' : 'ambiguous', $candidates->count());
        }

        foreach ($stocks as $stock) {
            if (isset($usedStockIds[$stock->id])) {
                continue;
            }
            $rows[] = $this->stockOnlyRow($stock);
        }

        $status = $filters['reconciliation_status'] ?? 'all';
        if ($status !== 'all') {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $row['status'] === $status));
        }

        $counts = collect($rows)->countBy('status');

        return [
            'rows' => array_values($rows),
            'stats' => [
                'total' => count($rows),
                'matched' => (int) $counts->get('matched', 0),
                'difference' => (int) $counts->get('difference', 0),
                'missing_ledger' => (int) $counts->get('missing_ledger', 0),
                'missing_excel' => (int) $counts->get('missing_excel', 0),
                'ambiguous' => (int) $counts->get('ambiguous', 0),
            ],
        ];
    }

    /** @param Collection<int, InventoryStock> $candidates
     * @return Collection<int, InventoryStock>
     */
    private function exactCandidates(Collection $candidates, string $assetName): Collection
    {
        $needle = $this->normalize($assetName);

        return $candidates->filter(fn (InventoryStock $stock): bool => in_array($needle, [
            $this->normalize($stock->sparePart->detail_equipment),
            $this->normalize($stock->sparePart->equipment),
        ], true));
    }

    /** @return array<string, mixed> */
    private function matchedRow(Asset $asset, InventoryStock $stock, int $excel, int $ledger, string $strategy): array
    {
        return [
            ...$this->assetFields($asset),
            'spare_part_id' => $stock->spare_part_id,
            'part_code' => $stock->sparePart->code,
            'part_name' => $stock->sparePart->detail_equipment,
            'excel_stock' => $excel,
            'ledger_stock' => $ledger,
            'difference' => $excel - $ledger,
            'status' => $excel === $ledger ? 'matched' : 'difference',
            'match_strategy' => $strategy,
            'candidate_count' => 1,
        ];
    }

    /** @return array<string, mixed> */
    private function assetOnlyRow(Asset $asset, int $excel, string $status, int $candidateCount): array
    {
        return [
            ...$this->assetFields($asset),
            'spare_part_id' => null,
            'part_code' => null,
            'part_name' => null,
            'excel_stock' => $excel,
            'ledger_stock' => null,
            'difference' => null,
            'status' => $status,
            'match_strategy' => null,
            'candidate_count' => $candidateCount,
        ];
    }

    /** @return array<string, mixed> */
    private function stockOnlyRow(InventoryStock $stock): array
    {
        $part = $stock->sparePart;
        $subsystem = $part->assetSubsystem;

        return [
            'id' => "stock-{$stock->id}",
            'asset_id' => null,
            'asset_name' => null,
            'unit' => $stock->unitKerja?->only(['id', 'code', 'name']),
            'category' => [
                'group' => $subsystem?->assetSystem?->assetGroup?->name,
                'system' => $subsystem?->assetSystem?->name,
                'subsystem' => $subsystem?->name,
            ],
            'spare_part_id' => $stock->spare_part_id,
            'part_code' => $part->code,
            'part_name' => $part->detail_equipment,
            'excel_stock' => null,
            'ledger_stock' => (int) $stock->quantity,
            'difference' => null,
            'status' => 'missing_excel',
            'match_strategy' => null,
            'candidate_count' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private function assetFields(Asset $asset): array
    {
        $subsystem = $asset->assetSubsystem;

        return [
            'id' => "asset-{$asset->id}",
            'asset_id' => $asset->id,
            'asset_name' => $asset->nama_aset,
            'unit' => $asset->unitKerja?->only(['id', 'code', 'name']),
            'category' => [
                'group' => $subsystem?->assetSystem?->assetGroup?->name,
                'system' => $subsystem?->assetSystem?->name,
                'subsystem' => $subsystem?->name,
            ],
        ];
    }

    private function scopeKey(int $unitId, int $subsystemId): string
    {
        return "{$unitId}:{$subsystemId}";
    }

    private function normalize(?string $value): string
    {
        return Str::of($value ?? '')->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '')->toString();
    }
}
