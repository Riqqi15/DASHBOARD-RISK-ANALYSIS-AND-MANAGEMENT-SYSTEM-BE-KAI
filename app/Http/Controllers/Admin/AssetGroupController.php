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
                    'name' => $request->validated('name'),
                    'sort_order' => $request->validated('sort_order'),
                ]);
                $this->auditLogger->record('asset_category.created', $group, [], $this->auditValues($group));
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicate($exception);
            throw $exception;
        }

        return redirect()->route('admin.asset-categories.index')->with('success', 'Kelompok aset berhasil ditambahkan.');
    }

    public function update(UpdateAssetGroupRequest $request, AssetGroup $assetGroup): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $assetGroup): void {
                $before = $this->auditValues($assetGroup);
                $assetGroup->update([
                    'name' => $request->validated('name'),
                    'sort_order' => $request->validated('sort_order'),
                ]);
                $this->auditLogger->record('asset_category.updated', $assetGroup, $before, $this->auditValues($assetGroup->fresh()));
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicate($exception);
            throw $exception;
        }

        return redirect()->route('admin.asset-categories.index', ['group' => $assetGroup->id])
            ->with('success', 'Kelompok aset berhasil diperbarui.');
    }

    public function status(UpdateAssetCategoryStatusRequest $request, AssetGroup $assetGroup): RedirectResponse
    {
        DB::transaction(function () use ($request, $assetGroup): void {
            $before = $this->auditValues($assetGroup);
            $assetGroup->update(['is_active' => $request->validated('is_active')]);
            $this->auditLogger->record('asset_category.status_changed', $assetGroup, $before, $this->auditValues($assetGroup->fresh()));
        });

        return redirect()->route('admin.asset-categories.index', ['group' => $assetGroup->id])
            ->with('success', 'Status kelompok aset berhasil diperbarui.');
    }

    public function destroy(AssetGroup $assetGroup): RedirectResponse
    {
        Gate::authorize('delete', $assetGroup);

        $blockers = DB::transaction(function () use ($assetGroup): array {
            $blockers = array_filter([
                'sistem' => $assetGroup->systems()->withTrashed()->count(),
                'alias sumber' => AssetCategorySourceAlias::query()->where('category_type', 'group')->where('category_id', $assetGroup->id)->count(),
            ]);

            if ($blockers !== []) {
                return $blockers;
            }

            $before = $this->auditValues($assetGroup);
            $assetGroup->delete();
            $this->auditLogger->record('asset_category.deleted', $assetGroup, $before, []);

            return [];
        });

        if ($blockers !== []) {
            return redirect()->back()->withErrors(['category' => $this->blockedMessage($blockers)]);
        }

        return redirect()->route('admin.asset-categories.index')->with('success', 'Kelompok aset berhasil dihapus.');
    }

    private function auditValues(AssetGroup $group): array
    {
        return [
            'level' => 'group',
            'id' => $group->id,
            'parent_id' => null,
            'name' => $group->name,
            'sort_order' => $group->sort_order,
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
