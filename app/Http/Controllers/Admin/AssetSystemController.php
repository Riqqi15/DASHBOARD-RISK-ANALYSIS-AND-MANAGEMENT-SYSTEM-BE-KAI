<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAssetSystemRequest;
use App\Http\Requests\Admin\UpdateAssetCategoryStatusRequest;
use App\Http\Requests\Admin\UpdateAssetSystemRequest;
use App\Models\AssetCategorySourceAlias;
use App\Models\AssetGroup;
use App\Models\AssetSystem;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssetSystemController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function store(StoreAssetSystemRequest $request): RedirectResponse
    {
        $groupId = $request->integer('asset_group_id');

        try {
            $group = DB::transaction(function () use ($request, $groupId): AssetGroup {
                // Every dependency creator must lock/revalidate its active parent; Task 4 assets must do this for their subsystem.
                $group = AssetGroup::query()->where('is_active', true)->lockForUpdate()->findOrFail($groupId);
                $system = AssetSystem::query()->create([
                    'asset_group_id' => $group->id,
                    'name' => $request->validated('name'),
                    'sort_order' => $request->validated('sort_order'),
                ]);
                $this->auditLogger->record('asset_category.created', $system, [], $this->auditValues($system));

                return $group;
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicate($exception);
            throw $exception;
        }

        return redirect()->route('admin.asset-categories.index', ['group' => $group->id])
            ->with('success', 'Sistem aset berhasil ditambahkan.');
    }

    public function update(UpdateAssetSystemRequest $request, AssetSystem $assetSystem): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $assetSystem): void {
                $before = $this->auditValues($assetSystem);
                $assetSystem->update([
                    'name' => $request->validated('name'),
                    'sort_order' => $request->validated('sort_order'),
                ]);
                $this->auditLogger->record('asset_category.updated', $assetSystem, $before, $this->auditValues($assetSystem->fresh()));
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicate($exception);
            throw $exception;
        }

        return redirect()->route('admin.asset-categories.index', ['group' => $assetSystem->asset_group_id, 'system' => $assetSystem->id])
            ->with('success', 'Sistem aset berhasil diperbarui.');
    }

    public function status(UpdateAssetCategoryStatusRequest $request, AssetSystem $assetSystem): RedirectResponse
    {
        $systemId = $assetSystem->id;
        $groupId = $assetSystem->asset_group_id;
        $requestedStatus = (bool) $request->validated('is_active');

        $changed = DB::transaction(function () use ($systemId, $requestedStatus): bool {
            $system = AssetSystem::query()->lockForUpdate()->findOrFail($systemId);
            if ($system->is_active === $requestedStatus) {
                return false;
            }

            $before = $this->auditValues($system);
            $system->update(['is_active' => $requestedStatus]);
            $this->auditLogger->record('asset_category.status_changed', $system, $before, $this->auditValues($system->fresh()));

            return true;
        });

        return redirect()->route('admin.asset-categories.index', ['group' => $groupId, 'system' => $systemId])
            ->with('success', $changed ? 'Status sistem aset berhasil diperbarui.' : 'Status sistem aset tidak berubah.');
    }

    public function destroy(AssetSystem $assetSystem): RedirectResponse
    {
        Gate::authorize('delete', $assetSystem);
        $groupId = $assetSystem->asset_group_id;
        $systemId = $assetSystem->id;

        $blockers = DB::transaction(function () use ($systemId): array {
            $system = AssetSystem::query()->lockForUpdate()->findOrFail($systemId);
            $blockers = array_filter([
                'subsistem' => $system->subsystems()->withTrashed()->count(),
                'alias sumber' => AssetCategorySourceAlias::query()->where('category_type', 'system')->where('category_id', $system->id)->count(),
            ]);

            if ($blockers !== []) {
                return $blockers;
            }

            $before = $this->auditValues($system);
            $system->delete();
            $this->auditLogger->record('asset_category.deleted', $system, $before, []);

            return [];
        });

        if ($blockers !== []) {
            return redirect()->back()->withErrors(['category' => $this->blockedMessage($blockers)]);
        }

        return redirect()->route('admin.asset-categories.index', ['group' => $groupId])->with('success', 'Sistem aset berhasil dihapus.');
    }

    private function auditValues(AssetSystem $system): array
    {
        return [
            'level' => 'system',
            'id' => $system->id,
            'parent_id' => $system->asset_group_id,
            'name' => $system->name,
            'sort_order' => $system->sort_order,
            'is_active' => $system->is_active,
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
