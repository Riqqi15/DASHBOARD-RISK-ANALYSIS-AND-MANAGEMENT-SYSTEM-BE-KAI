<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAssetCategoryLevelRequest;
use App\Models\AssetCategoryLevel;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssetCategoryLevelController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function store(StoreAssetCategoryLevelRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $position = (int) AssetCategoryLevel::query()->lockForUpdate()->max('position') + 1;
            $level = AssetCategoryLevel::withTrashed()->where('position', $position)->lockForUpdate()->first();

            if ($level) {
                $level->restore();
                $level->update(['name' => $request->validated('name'), 'is_active' => true]);
            } else {
                $level = AssetCategoryLevel::query()->create([
                    'name' => $request->validated('name'),
                    'position' => $position,
                    'is_active' => true,
                ]);
            }
            $this->auditLogger->record(
                'asset_category_level.created',
                $level,
                [],
                $level->only(['id', 'name', 'position']),
            );
        });

        return back()->with('success', 'Level kategori berhasil ditambahkan.');
    }

    public function update(Request $request, AssetCategoryLevel $assetCategoryLevel): RedirectResponse
    {
        Gate::authorize('update', $assetCategoryLevel);
        $name = preg_replace("/\s+/u", ' ', trim($request->string('name')->toString())) ?? '';
        $validated = validator(
            ['name' => $name, 'normalized_name' => mb_strtolower($name)],
            [
                'name' => ['required', 'string', 'max:100'],
                'normalized_name' => ['required', Rule::unique('asset_category_levels')->ignore($assetCategoryLevel)],
            ],
        )->validate();

        DB::transaction(function () use ($assetCategoryLevel, $validated): void {
            $level = AssetCategoryLevel::query()->lockForUpdate()->findOrFail($assetCategoryLevel->id);
            $before = $level->only(['id', 'name', 'position']);
            $level->update(['name' => $validated['name']]);
            $this->auditLogger->record(
                'asset_category_level.updated',
                $level,
                $before,
                $level->only(['id', 'name', 'position']),
            );
        });

        return back()->with('success', 'Nama level berhasil diperbarui.');
    }

    public function destroy(AssetCategoryLevel $assetCategoryLevel): RedirectResponse
    {
        Gate::authorize('delete', $assetCategoryLevel);

        DB::transaction(function () use ($assetCategoryLevel): void {
            $level = AssetCategoryLevel::query()->lockForUpdate()->findOrFail($assetCategoryLevel->id);
            $lastPosition = (int) AssetCategoryLevel::query()->max('position');
            if ($level->position <= 3 || $level->position !== $lastPosition || $level->nodes()->exists()) {
                throw ValidationException::withMessages([
                    'level' => 'Level hanya dapat dihapus jika paling akhir dan belum memiliki kategori.',
                ]);
            }
            $before = $level->only(['id', 'name', 'position']);
            $level->delete();
            $this->auditLogger->record('asset_category_level.deleted', $level, $before, []);
        });

        return to_route('admin.asset-categories.index')->with('success', 'Level kategori berhasil dihapus.');
    }
}
