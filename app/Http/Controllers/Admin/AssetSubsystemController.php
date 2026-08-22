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
        $systemId = $request->integer('asset_system_id');

        try {
            $system = DB::transaction(function () use ($request, $systemId): AssetSystem {
                // Every dependency creator must lock and revalidate its active parent.
                // Asset creation must do the same for its subsystem.
                $system = AssetSystem::query()->where('is_active', true)->lockForUpdate()->findOrFail($systemId);
                $subsystem = AssetSubsystem::query()->create([
                    'asset_system_id' => $system->id,
                    'name' => $request->validated('name'),
                    'sort_order' => $request->validated('sort_order'),
                    'dashboard_color' => $request->validated('dashboard_color'),
                    'dashboard_color_source' => $request->validated('dashboard_color') ? 'manual' : null,
                ]);
                $this->auditLogger->record('asset_category.created', $subsystem, [], $this->auditValues($subsystem));

                return $system;
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicate($exception);
            throw $exception;
        }

        return redirect()
            ->route('admin.asset-categories.index', [
                'unit_kerja_id' => $system->assetGroup()->value('unit_kerja_id'),
                'group' => $system->asset_group_id,
                'system' => $system->id,
            ])
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
                    'dashboard_color' => $request->validated('dashboard_color'),
                    'dashboard_color_source' => $request->validated('dashboard_color') ? 'manual' : null,
                ]);
                $this->auditLogger->record(
                    'asset_category.updated',
                    $assetSubsystem,
                    $before,
                    $this->auditValues($assetSubsystem->fresh()),
                );
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicate($exception);
            throw $exception;
        }

        return redirect()
            ->route('admin.asset-categories.index', $this->selection($assetSubsystem))
            ->with('success', 'Subsistem aset berhasil diperbarui.');
    }

    public function status(UpdateAssetCategoryStatusRequest $request, AssetSubsystem $assetSubsystem): RedirectResponse
    {
        $subsystemId = $assetSubsystem->id;
        $selection = $this->selection($assetSubsystem);
        $requestedStatus = (bool) $request->validated('is_active');

        $changed = DB::transaction(function () use ($subsystemId, $requestedStatus): bool {
            $subsystem = AssetSubsystem::query()->lockForUpdate()->findOrFail($subsystemId);
            if ($subsystem->is_active === $requestedStatus) {
                return false;
            }

            $before = $this->auditValues($subsystem);
            $subsystem->update(['is_active' => $requestedStatus]);
            $this->auditLogger->record(
                'asset_category.status_changed',
                $subsystem,
                $before,
                $this->auditValues($subsystem->fresh()),
            );

            return true;
        });

        return redirect()
            ->route('admin.asset-categories.index', $selection)
            ->with(
                'success',
                $changed ? 'Status subsistem aset berhasil diperbarui.' : 'Status subsistem aset tidak berubah.',
            );
    }

    public function destroy(AssetSubsystem $assetSubsystem): RedirectResponse
    {
        Gate::authorize('delete', $assetSubsystem);
        $selection = $this->selection($assetSubsystem);
        $subsystemId = $assetSubsystem->id;

        $blockers = DB::transaction(function () use ($subsystemId): array {
            $subsystem = AssetSubsystem::query()->lockForUpdate()->findOrFail($subsystemId);
            $blockers = [
                'aset' => $subsystem->assets()->withTrashed()->count(),
                'alias sumber' => AssetCategorySourceAlias::query()
                    ->where('category_type', 'subsystem')
                    ->where('category_id', $subsystem->id)
                    ->count(),
            ];

            foreach (
                ['unit_subsystem_openings' => 'pembukaan unit', 'spare_parts' => 'suku cadang'] as $table => $label
            ) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'asset_subsystem_id')) {
                    $blockers[$label] = DB::table($table)->where('asset_subsystem_id', $subsystem->id)->count();
                }
            }

            $blockers = array_filter($blockers);
            if ($blockers !== []) {
                return $blockers;
            }

            $before = $this->auditValues($subsystem);
            $subsystem->delete();
            $this->auditLogger->record('asset_category.deleted', $subsystem, $before, []);

            return [];
        });

        if ($blockers !== []) {
            return redirect()
                ->back()
                ->withErrors(['category' => $this->blockedMessage($blockers)]);
        }

        return redirect()
            ->route('admin.asset-categories.index', $selection)
            ->with('success', 'Subsistem aset berhasil dihapus.');
    }

    private function auditValues(AssetSubsystem $subsystem): array
    {
        return [
            'level' => 'subsystem',
            'id' => $subsystem->id,
            'parent_id' => $subsystem->asset_system_id,
            'name' => $subsystem->name,
            'sort_order' => $subsystem->sort_order,
            'dashboard_color' => $subsystem->dashboard_color,
            'dashboard_color_source' => $subsystem->dashboard_color_source,
            'is_active' => $subsystem->is_active,
        ];
    }

    /** @return array{unit_kerja_id: ?int, group: int, system: int} */
    private function selection(AssetSubsystem $subsystem): array
    {
        $group = $subsystem->assetSystem()->firstOrFail()->assetGroup()->firstOrFail();

        return [
            'unit_kerja_id' => $group->unit_kerja_id,
            'group' => $group->id,
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
