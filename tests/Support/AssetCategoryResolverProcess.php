<?php

declare(strict_types=1);

use App\Models\Asset;
use App\Models\AssetCategorySourceAlias;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Models\UnitKerja;
use App\Services\AssetCategoryBackfill;
use App\Services\AssetCategoryResolver;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\DeadlockException;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 2);

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    if (config('database.default') !== 'mysql' || (int) config('database.connections.mysql.port') !== 3307) {
        throw new RuntimeException('Concurrency helper requires the MySQL testing connection on port 3307.');
    }

    $action = $argv[1] ?? '';
    $groupName = $argv[2] ?? '';
    $systemName = $argv[3] ?? '';
    $subsystemName = $argv[4] ?? '';

    if ($action === 'resolve') {
        $barrier = $argv[5] ?? '';
        $worker = $argv[6] ?? '';

        if ($barrier === '' || $worker === '') {
            throw new InvalidArgumentException('Resolve mode requires a barrier and worker identifier.');
        }

        file_put_contents("{$barrier}.{$worker}.ready", 'ready');
        $deadline = microtime(true) + 15;

        while (! is_file("{$barrier}.go")) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Timed out waiting for the concurrency barrier.');
            }

            usleep(10_000);
        }

        $resolved = app(AssetCategoryResolver::class)->resolve(
            $groupName,
            $systemName,
            $subsystemName,
            "concurrency-worker-{$worker}.xlsx",
            'Assets',
            (int) $worker,
        );

        echo json_encode([
            'group' => $resolved['group']->id,
            'system' => $resolved['system']->id,
            'subsystem' => $resolved['subsystem']->id,
        ], JSON_THROW_ON_ERROR);

        exit(0);
    }

    if ($action === 'retry-backfill') {
        $sourceKey = hash('sha256', 'retry-'.$groupName);
        $unitCode = 'RTY-'.substr($sourceKey, 0, 12);
        $unit = UnitKerja::query()->create([
            'code' => $unitCode,
            'name' => "Retry Unit {$unitCode}",
            'type' => 'daop',
            'is_active' => true,
        ]);
        $asset = Asset::query()->create([
            'unit_kerja_id' => $unit->id,
            'asset_subsystem_id' => null,
            'nama_aset' => 'Retry Asset',
            'aset_prasarana_sintel' => $groupName,
            'system' => $systemName,
            'subsystem' => $subsystemName,
            'jumlah_unit' => 1,
            'status' => 'aktif',
            'source_key' => $sourceKey,
        ]);
        $resolver = new class extends AssetCategoryResolver
        {
            public int $attempts = 0;

            public function resolve(
                string $groupName,
                string $systemName,
                string $subsystemName,
                string $workbookName,
                string $sheetName,
                ?int $sourceRow = null,
            ): array {
                $this->attempts++;

                if ($this->attempts === 1) {
                    throw new DeadlockException('Deadlock found when trying to get lock', 1213);
                }

                return parent::resolve(
                    $groupName,
                    $systemName,
                    $subsystemName,
                    $workbookName,
                    $sheetName,
                    $sourceRow,
                );
            }
        };
        $result = (new AssetCategoryBackfill($resolver))->run();

        echo json_encode([
            'attempts' => $resolver->attempts,
            'linked' => $result['linked'],
            'skipped' => $result['skipped'],
            'asset_subsystem_id' => $asset->fresh()->asset_subsystem_id,
        ], JSON_THROW_ON_ERROR);

        exit(0);
    }

    if ($action === 'cleanup') {
        $resolver = app(AssetCategoryResolver::class);
        $groupPath = $resolver->normalize($groupName);
        $systemPath = $groupPath.'|'.$resolver->normalize($systemName);
        $subsystemPath = $systemPath.'|'.$resolver->normalize($subsystemName);
        $sourceKey = hash('sha256', 'retry-'.$groupName);
        $unitCode = 'RTY-'.substr($sourceKey, 0, 12);

        DB::transaction(function () use ($groupName, $systemName, $subsystemName, $resolver, $groupPath, $systemPath, $subsystemPath, $sourceKey, $unitCode): void {
            Asset::withTrashed()
                ->where('source_key', $sourceKey)
                ->get()
                ->each->forceDelete();

            UnitKerja::withTrashed()
                ->where('code', $unitCode)
                ->get()
                ->each->forceDelete();

            AssetCategorySourceAlias::query()
                ->where(function ($aliases) use ($groupPath, $systemPath, $subsystemPath): void {
                    $aliases
                        ->where(fn ($alias) => $alias
                            ->where('category_type', 'group')
                            ->where('normalized_source_path', $groupPath))
                        ->orWhere(fn ($alias) => $alias
                            ->where('category_type', 'system')
                            ->where('normalized_source_path', $systemPath))
                        ->orWhere(fn ($alias) => $alias
                            ->where('category_type', 'subsystem')
                            ->where('normalized_source_path', $subsystemPath));
                })
                ->delete();

            AssetGroup::withTrashed()
                ->where('normalized_name', $resolver->normalize($groupName))
                ->get()
                ->each(function (AssetGroup $group) use ($systemName, $subsystemName, $resolver): void {
                    AssetSystem::withTrashed()
                        ->where('asset_group_id', $group->id)
                        ->where('normalized_name', $resolver->normalize($systemName))
                        ->get()
                        ->each(function (AssetSystem $system) use ($subsystemName, $resolver): void {
                            AssetSubsystem::withTrashed()
                                ->where('asset_system_id', $system->id)
                                ->where('normalized_name', $resolver->normalize($subsystemName))
                                ->get()
                                ->each->forceDelete();

                            $system->forceDelete();
                        });

                    $group->forceDelete();
                });
        });

        echo '{"cleaned":true}';

        exit(0);
    }

    throw new InvalidArgumentException('Unknown concurrency helper action.');
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage());

    exit(1);
}
