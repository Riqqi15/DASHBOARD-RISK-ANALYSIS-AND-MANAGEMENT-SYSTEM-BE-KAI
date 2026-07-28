<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAssetSubsystemRequest;
use App\Http\Requests\Admin\UpdateAssetCategoryStatusRequest;
use App\Http\Requests\Admin\UpdateAssetSubsystemRequest;
use App\Models\AssetCategorySourceAlias;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AssetSubsystemController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function store(StoreAssetSubsystemRequest $request): RedirectResponse
    {
        $system = AssetSystem::query()->findOrFail($request->integer('asset_system_id'));

        try {
            DB::transaction(function () use ($request, $system): void {
                $subsystem = AssetSubsystem::query()->create([
                    'asset_system_id' => $system->id,
                    'name' => $request->validated('name'),
                    'sort_order' => $request->validated('sort_order'),
                ]);
                $this->auditLogger->record('asset_category.created', $subsystem, [], $this->auditValues($subsystem));
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicate($exception);
            throw $exception;
        }

        return redirect()->route('admin.asset-categories.index', ['group' => $system->asset_group_id, 'system' => $system->id])
            ->with('success', 'Subsistem aset berhasil ditambahkan.');
    }

    public function update(UpdateAssetSubsystemRequest $request, AssetSubsystem $assetSubsystem): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $assetSubsystem): void {
                $before = $this->auditValues($assetSubsystem);
                $assetSubsystem->update([
                    'name' => $request->validated('name'),
                    'sort_order' => $request->validated('sort_order'),
                ]);
                $this->auditLogger->record('asset_category.updated', $assetSubsystem, $before, $this->auditValues($assetSubsystem->fresh()));
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicate($exception);
            throw $exception;
        }

        return redirect()->route('admin.asset-categories.index', $this->selection($assetSubsystem))
            ->with('success', 'Subsistem aset berhasil diperbarui.');
    }

    public function status(UpdateAssetCategoryStatusRequest $request, AssetSubsystem $assetSubsystem): RedirectResponse
    {
        DB::transaction(function () use ($request, $assetSubsystem): void {
            $before = $this->auditValues($assetSubsystem);
            $assetSubsystem->update(['is_active' => $request->validated('is_active')]);
            $this->auditLogger->record('asset_category.status_changed', $assetSubsystem, $before, $this->auditValues($assetSubsystem->fresh()));
        });

        return redirect()->route('admin.asset-categories.index', $this->selection($assetSubsystem))
            ->with('success', 'Status subsistem aset berhasil diperbarui.');
    }

    public function destroy(AssetSubsystem $assetSubsystem): RedirectResponse
    {
        Gate::authorize('delete', $assetSubsystem);
        $selection = $this->selection($assetSubsystem);

        $blockers = DB::transaction(function () use ($assetSubsystem): array {
            $blockers = [
                'aset' => $assetSubsystem->assets()->withTrashed()->count(),
                'alias sumber' => AssetCategorySourceAlias::query()->where('category_type', 'subsystem')->where('category_id', $assetSubsystem->id)->count(),
            ];

            foreach (['unit_subsystem_openings' => 'pembukaan unit', 'spare_parts' => 'suku cadang'] as $table => $label) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'asset_subsystem_id')) {
                    $blockers[$label] = DB::table($table)->where('asset_subsystem_id', $assetSubsystem->id)->count();
                }
            }

            $blockers = array_filter($blockers);
            if ($blockers !== []) {
                return $blockers;
            }

            $before = $this->auditValues($assetSubsystem);
            $assetSubsystem->delete();
            $this->auditLogger->record('asset_category.deleted', $assetSubsystem, $before, []);

            return [];
        });

        if ($blockers !== []) {
            return redirect()->back()->withErrors(['category' => $this->blockedMessage($blockers)]);
        }

        return redirect()->route('admin.asset-categories.index', $selection)->with('success', 'Subsistem aset berhasil dihapus.');
    }

    private function auditValues(AssetSubsystem $subsystem): array
    {
        return [
            'level' => 'subsystem',
            'id' => $subsystem->id,
            'parent_id' => $subsystem->asset_system_id,
            'name' => $subsystem->name,
            'sort_order' => $subsystem->sort_order,
            'is_active' => $subsystem->is_active,
        ];
    }

    /** @return array{group: int, system: int} */
    private function selection(AssetSubsystem $subsystem): array
    {
        return [
            'group' => $subsystem->assetSystem()->value('asset_group_id'),
            'system' => $subsystem->asset_system_id,
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
