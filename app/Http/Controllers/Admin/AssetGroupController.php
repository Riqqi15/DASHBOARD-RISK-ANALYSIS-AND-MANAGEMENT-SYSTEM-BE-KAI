<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAssetGroupRequest;
use App\Http\Requests\Admin\UpdateAssetCategoryStatusRequest;
use App\Http\Requests\Admin\UpdateAssetGroupRequest;
use App\Models\AssetCategorySourceAlias;
use App\Models\AssetGroup;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssetGroupController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function store(StoreAssetGroupRequest $request): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request): void {
                $group = AssetGroup::query()->create([
                    'unit_kerja_id' => $request->validated('unit_kerja_id'),
                    'name' => $request->validated('name'),
                    'sort_order' => $request->validated('sort_order'),
                    'dashboard_color' => $request->validated('dashboard_color'),
                    'dashboard_color_source' => $request->validated('dashboard_color') ? 'manual' : null,
                ]);
                $this->auditLogger->record('asset_category.created', $group, [], $this->auditValues($group));
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicate($exception);
            throw $exception;
        }

        return redirect()
            ->route('admin.asset-categories.index', ['unit_kerja_id' => $request->validated('unit_kerja_id')])
            ->with('success', 'Kelompok aset berhasil ditambahkan.');
    }

    public function update(UpdateAssetGroupRequest $request, AssetGroup $assetGroup): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $assetGroup): void {
                $before = $this->auditValues($assetGroup);
                $assetGroup->update([
                    'name' => $request->validated('name'),
                    'sort_order' => $request->validated('sort_order'),
                    'dashboard_color' => $request->validated('dashboard_color'),
                    'dashboard_color_source' => $request->validated('dashboard_color') ? 'manual' : null,
                ]);
                $this->auditLogger->record(
                    'asset_category.updated',
                    $assetGroup,
                    $before,
                    $this->auditValues($assetGroup->fresh()),
                );
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicate($exception);
            throw $exception;
        }

        return redirect()
            ->route('admin.asset-categories.index', [
                'unit_kerja_id' => $assetGroup->unit_kerja_id,
                'group' => $assetGroup->id,
            ])
            ->with('success', 'Kelompok aset berhasil diperbarui.');
    }

    public function status(UpdateAssetCategoryStatusRequest $request, AssetGroup $assetGroup): RedirectResponse
    {
        $groupId = $assetGroup->id;
        $requestedStatus = (bool) $request->validated('is_active');

        $changed = DB::transaction(function () use ($groupId, $requestedStatus): bool {
            $group = AssetGroup::query()->lockForUpdate()->findOrFail($groupId);
            if ($group->is_active === $requestedStatus) {
                return false;
            }

            $before = $this->auditValues($group);
            $group->update(['is_active' => $requestedStatus]);
            $this->auditLogger->record(
                'asset_category.status_changed',
                $group,
                $before,
                $this->auditValues($group->fresh()),
            );

            return true;
        });

        return redirect()
            ->route('admin.asset-categories.index', [
                'unit_kerja_id' => $assetGroup->unit_kerja_id,
                'group' => $groupId,
            ])
            ->with(
                'success',
                $changed ? 'Status kelompok aset berhasil diperbarui.' : 'Status kelompok aset tidak berubah.',
            );
    }

    public function destroy(AssetGroup $assetGroup): RedirectResponse
    {
        Gate::authorize('delete', $assetGroup);
        $groupId = $assetGroup->id;

        $blockers = DB::transaction(function () use ($groupId): array {
            $group = AssetGroup::query()->lockForUpdate()->findOrFail($groupId);
            $blockers = array_filter([
                'sistem' => $group->systems()->withTrashed()->count(),
                'alias sumber' => AssetCategorySourceAlias::query()
                    ->where('category_type', 'group')
                    ->where('category_id', $group->id)
                    ->count(),
            ]);

            if ($blockers !== []) {
                return $blockers;
            }

            $before = $this->auditValues($group);
            $group->delete();
            $this->auditLogger->record('asset_category.deleted', $group, $before, []);

            return [];
        });

        if ($blockers !== []) {
            return redirect()
                ->back()
                ->withErrors(['category' => $this->blockedMessage($blockers)]);
        }

        return redirect()
            ->route('admin.asset-categories.index', ['unit_kerja_id' => $assetGroup->unit_kerja_id])
            ->with('success', 'Kelompok aset berhasil dihapus.');
    }

    private function auditValues(AssetGroup $group): array
    {
        return [
            'level' => 'group',
            'id' => $group->id,
            'unit_kerja_id' => $group->unit_kerja_id,
            'parent_id' => null,
            'name' => $group->name,
            'sort_order' => $group->sort_order,
            'dashboard_color' => $group->dashboard_color,
            'dashboard_color_source' => $group->dashboard_color_source,
            'is_active' => $group->is_active,
        ];
    }

    /** @param array<string, int> $blockers */
    private function blockedMessage(array $blockers): string
    {
        $details = collect($blockers)->map(fn (int $count, string $type): string => "{$count} {$type}")->implode(', ');

        return "Kategori tidak dapat dihapus karena masih digunakan oleh {$details}. Silakan nonaktifkan kategori ini.";
    }

    private function throwIfDuplicate(QueryException $exception): void
    {
        if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
            throw ValidationException::withMessages(['normalized_name' => 'Nama kategori sudah digunakan.']);
        }
    }
}
