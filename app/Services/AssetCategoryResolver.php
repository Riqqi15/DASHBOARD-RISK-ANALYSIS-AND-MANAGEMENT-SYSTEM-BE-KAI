<?php

namespace App\Services;

use App\Models\AssetCategorySourceAlias;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AssetCategoryResolver
{
    private AssetTaxonomyService $assetTaxonomy;

    public function __construct(?AssetTaxonomyService $assetTaxonomy = null)
    {
        $this->assetTaxonomy = $assetTaxonomy ?? app(AssetTaxonomyService::class);
    }

    /**
     * @return array{group: AssetGroup, system: AssetSystem, subsystem: AssetSubsystem}
     */
    public function resolve(
        string $groupName,
        string $systemName,
        string $subsystemName,
        string $workbookName,
        string $sheetName,
        ?int $sourceRow = null,
        ?int $unitKerjaId = null,
    ): array {
        $sourceNames = [
            'group' => $groupName,
            'system' => $systemName,
            'subsystem' => $subsystemName,
        ];
        $displayNames = array_map($this->displayName(...), $sourceNames);
        $normalizedNames = array_map($this->normalize(...), $sourceNames);

        $resolve = function () use (
            $sourceNames,
            $displayNames,
            $normalizedNames,
            $workbookName,
            $sheetName,
            $sourceRow,
            $unitKerjaId,
        ): array {
            $groupPath = $sourceNames['group'];
            $normalizedGroupPath = $normalizedNames['group'];
            $group = $this->resolveGroup(
                $displayNames['group'],
                $normalizedNames['group'],
                $groupPath,
                $normalizedGroupPath,
                $workbookName,
                $sheetName,
                $sourceRow,
                $unitKerjaId,
            );

            $systemPath = $groupPath.'|'.$sourceNames['system'];
            $normalizedSystemPath = $normalizedGroupPath.'|'.$normalizedNames['system'];
            $system = $this->resolveSystem(
                $group,
                $displayNames['system'],
                $normalizedNames['system'],
                $systemPath,
                $normalizedSystemPath,
                $workbookName,
                $sheetName,
                $sourceRow,
            );

            $subsystemPath = $systemPath.'|'.$sourceNames['subsystem'];
            $normalizedSubsystemPath = $normalizedSystemPath.'|'.$normalizedNames['subsystem'];
            $subsystem = $this->resolveSubsystem(
                $system,
                $displayNames['subsystem'],
                $normalizedNames['subsystem'],
                $subsystemPath,
                $normalizedSubsystemPath,
                $workbookName,
                $sheetName,
                $sourceRow,
            );

            $this->assetTaxonomy->syncLegacyPath($group, $system, $subsystem);

            return compact('group', 'system', 'subsystem');
        };

        if (DB::connection()->transactionLevel() > 0) {
            return $resolve();
        }

        return DB::transaction($resolve, 3);
    }

    public function normalize(string $value): string
    {
        return mb_strtolower($this->displayName($value));
    }

    private function displayName(string $value): string
    {
        $trimmed = preg_replace('/^\s+|\s+$/u', '', $value) ?? trim($value);

        return preg_replace("/\s+/u", ' ', $trimmed) ?? $trimmed;
    }

    private function resolveGroup(
        string $name,
        string $normalizedName,
        string $sourcePath,
        string $normalizedPath,
        string $workbookName,
        string $sheetName,
        ?int $sourceRow,
        ?int $unitKerjaId,
    ): AssetGroup {
        $alias = $this->findAlias('group', $normalizedPath, $unitKerjaId);
        if ($alias) {
            $group = AssetGroup::query()->whereKey($alias->category_id)->first();
            if (! $group) {
                throw $this->resolverConflict($workbookName, $sheetName, $sourceRow, $normalizedPath);
            }

            $this->refreshAlias($alias, $group, $sourcePath, $workbookName, $sheetName);

            return $group;
        }

        /** @var AssetGroup $group */
        $group = $this->findOrCreateCategory(
            AssetGroup::class,
            ['unit_kerja_id' => $unitKerjaId],
            $name,
            $normalizedName,
            $normalizedPath,
            $workbookName,
            $sheetName,
            $sourceRow,
        );

        return $this->storeGroupAlias($group, $sourcePath, $normalizedPath, $workbookName, $sheetName, $sourceRow);
    }

    private function resolveSystem(
        AssetGroup $group,
        string $name,
        string $normalizedName,
        string $sourcePath,
        string $normalizedPath,
        string $workbookName,
        string $sheetName,
        ?int $sourceRow,
    ): AssetSystem {
        $alias = $this->findAlias('system', $normalizedPath, $group->unit_kerja_id);
        if ($alias) {
            $system = AssetSystem::query()->whereKey($alias->category_id)->first();
            if (! $system || $system->asset_group_id !== $group->id) {
                throw $this->resolverConflict($workbookName, $sheetName, $sourceRow, $normalizedPath);
            }

            $this->refreshAlias($alias, $system, $sourcePath, $workbookName, $sheetName);

            return $system;
        }

        /** @var AssetSystem $system */
        $system = $this->findOrCreateCategory(
            AssetSystem::class,
            ['asset_group_id' => $group->id],
            $name,
            $normalizedName,
            $normalizedPath,
            $workbookName,
            $sheetName,
            $sourceRow,
        );

        return $this->storeSystemAlias(
            $system,
            $group,
            $sourcePath,
            $normalizedPath,
            $workbookName,
            $sheetName,
            $sourceRow,
        );
    }

    private function resolveSubsystem(
        AssetSystem $system,
        string $name,
        string $normalizedName,
        string $sourcePath,
        string $normalizedPath,
        string $workbookName,
        string $sheetName,
        ?int $sourceRow,
    ): AssetSubsystem {
        $alias = $this->findAlias('subsystem', $normalizedPath, $system->assetGroup()->value('unit_kerja_id'));
        if ($alias) {
            $subsystem = AssetSubsystem::query()->whereKey($alias->category_id)->first();
            if (! $subsystem || $subsystem->asset_system_id !== $system->id) {
                throw $this->resolverConflict($workbookName, $sheetName, $sourceRow, $normalizedPath);
            }

            $this->refreshAlias($alias, $subsystem, $sourcePath, $workbookName, $sheetName);

            return $subsystem;
        }

        /** @var AssetSubsystem $subsystem */
        $subsystem = $this->findOrCreateCategory(
            AssetSubsystem::class,
            ['asset_system_id' => $system->id],
            $name,
            $normalizedName,
            $normalizedPath,
            $workbookName,
            $sheetName,
            $sourceRow,
        );

        return $this->storeSubsystemAlias(
            $subsystem,
            $system,
            $sourcePath,
            $normalizedPath,
            $workbookName,
            $sheetName,
            $sourceRow,
        );
    }

    private function findAlias(string $type, string $normalizedPath, ?int $unitKerjaId): ?AssetCategorySourceAlias
    {
        return AssetCategorySourceAlias::query()
            ->where('category_type', $type)
            ->where('unit_kerja_id', $unitKerjaId)
            ->where('normalized_source_path', $normalizedPath)
            ->lockForUpdate()
            ->first();
    }

    private function storeGroupAlias(
        AssetGroup $group,
        string $sourcePath,
        string $normalizedPath,
        string $workbookName,
        string $sheetName,
        ?int $sourceRow,
    ): AssetGroup {
        $alias = $this->createAlias('group', $group, $sourcePath, $normalizedPath, $workbookName, $sheetName);
        $resolved = AssetGroup::query()->whereKey($alias->category_id)->first();

        if (! $resolved || $resolved->id !== $group->id) {
            throw $this->resolverConflict($workbookName, $sheetName, $sourceRow, $normalizedPath);
        }

        $this->refreshAlias($alias, $resolved, $sourcePath, $workbookName, $sheetName);

        return $resolved;
    }

    private function storeSystemAlias(
        AssetSystem $system,
        AssetGroup $group,
        string $sourcePath,
        string $normalizedPath,
        string $workbookName,
        string $sheetName,
        ?int $sourceRow,
    ): AssetSystem {
        $alias = $this->createAlias('system', $system, $sourcePath, $normalizedPath, $workbookName, $sheetName);
        $resolved = AssetSystem::query()->whereKey($alias->category_id)->first();

        if (! $resolved || $resolved->id !== $system->id || $resolved->asset_group_id !== $group->id) {
            throw $this->resolverConflict($workbookName, $sheetName, $sourceRow, $normalizedPath);
        }

        $this->refreshAlias($alias, $resolved, $sourcePath, $workbookName, $sheetName);

        return $resolved;
    }

    private function storeSubsystemAlias(
        AssetSubsystem $subsystem,
        AssetSystem $system,
        string $sourcePath,
        string $normalizedPath,
        string $workbookName,
        string $sheetName,
        ?int $sourceRow,
    ): AssetSubsystem {
        $alias = $this->createAlias('subsystem', $subsystem, $sourcePath, $normalizedPath, $workbookName, $sheetName);
        $resolved = AssetSubsystem::query()->whereKey($alias->category_id)->first();

        if (! $resolved || $resolved->id !== $subsystem->id || $resolved->asset_system_id !== $system->id) {
            throw $this->resolverConflict($workbookName, $sheetName, $sourceRow, $normalizedPath);
        }

        $this->refreshAlias($alias, $resolved, $sourcePath, $workbookName, $sheetName);

        return $resolved;
    }

    private function createAlias(
        string $type,
        Model $category,
        string $sourcePath,
        string $normalizedPath,
        string $workbookName,
        string $sheetName,
    ): AssetCategorySourceAlias {
        $now = now();
        $unitKerjaId = $this->categoryUnitKerjaId($category);

        return AssetCategorySourceAlias::query()->firstOrCreate(
            [
                'category_type' => $type,
                'unit_kerja_id' => $unitKerjaId,
                'normalized_source_path' => $normalizedPath,
            ],
            [
                'category_id' => $category->getKey(),
                'source_path' => $sourcePath,
                'workbook_name' => $workbookName,
                'sheet_name' => $sheetName,
                'first_imported_at' => $now,
                'last_imported_at' => $now,
            ],
        );
    }

    private function categoryUnitKerjaId(Model $category): ?int
    {
        if ($category instanceof AssetGroup) {
            return $category->unit_kerja_id;
        }

        if ($category instanceof AssetSystem) {
            return $category->assetGroup()->value('unit_kerja_id');
        }

        if ($category instanceof AssetSubsystem) {
            return $category->assetSystem()->firstOrFail()->assetGroup()->value('unit_kerja_id');
        }

        return null;
    }

    private function refreshAlias(
        AssetCategorySourceAlias $alias,
        Model $category,
        string $sourcePath,
        string $workbookName,
        string $sheetName,
    ): void {
        $alias
            ->fill([
                'category_id' => $category->getKey(),
                'source_path' => $sourcePath,
                'workbook_name' => $workbookName,
                'sheet_name' => $sheetName,
                'last_imported_at' => now(),
            ])
            ->save();
    }

    /**
     * @template TCategory of Model
     *
     * @param  class-string<TCategory>  $modelClass
     * @param  array<string, int>  $scope
     * @return TCategory
     */
    private function findOrCreateCategory(
        string $modelClass,
        array $scope,
        string $name,
        string $normalizedName,
        string $normalizedPath,
        string $workbookName,
        string $sheetName,
        ?int $sourceRow,
    ): Model {
        $identity = [...$scope, 'normalized_name' => $normalizedName];

        $active = $modelClass::query()->where($identity)->where('is_active', true)->lockForUpdate()->first();

        if ($active) {
            return $active;
        }

        $existing = $modelClass::withTrashed()->where($identity)->lockForUpdate()->first();

        if ($existing) {
            if ($existing->deleted_at === null && $existing->is_active) {
                return $existing;
            }

            throw $this->resolverConflict($workbookName, $sheetName, $sourceRow, $normalizedPath);
        }

        try {
            return $modelClass::query()
                ->create([...$scope, 'name' => $name, 'normalized_name' => $normalizedName, 'is_active' => true]);
        } catch (UniqueConstraintViolationException) {
            $active = $modelClass::query()->where($identity)->where('is_active', true)->lockForUpdate()->first();

            if ($active) {
                return $active;
            }

            $existing = $modelClass::withTrashed()->where($identity)->lockForUpdate()->first();

            if ($existing && $existing->deleted_at === null && $existing->is_active) {
                return $existing;
            }

            throw $this->resolverConflict($workbookName, $sheetName, $sourceRow, $normalizedPath);
        }
    }

    private function resolverConflict(
        string $workbookName,
        string $sheetName,
        ?int $sourceRow,
        string $normalizedPath,
    ): RuntimeException {
        $row = $sourceRow === null ? '' : ", row {$sourceRow}";

        return new RuntimeException(
            "Asset category resolution conflict in workbook {$workbookName}, "
                ."sheet {$sheetName}{$row}, normalized path {$normalizedPath}.",
        );
    }
}
