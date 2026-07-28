<?php

declare(strict_types=1);

use App\Enums\StockDirection;
use App\Enums\StockMovementType;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Models\AuditLog;
use App\Models\InventoryStock;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\StockMovementService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

$root = dirname(__DIR__, 2);

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    if (config('database.default') !== 'mysql' || (int) config('database.connections.mysql.port') !== 3307) {
        throw new RuntimeException('Concurrency helper requires the MySQL testing connection on port 3307.');
    }

    $action = $argv[1] ?? '';
    if (in_array($action, ['setup-out', 'setup-empty'], true)) {
        $scope = $argv[2] ?? '';
        $unit = UnitKerja::factory()->create(['code' => 'RACE-'.substr(hash('sha256', $scope), 0, 12)]);
        $part = SparePart::factory()->create(['source_key' => 'race|'.$scope, 'code' => 'RACE-'.substr(hash('sha256', $scope), 0, 12)]);
        $actor = User::factory()->pusat()->create(['email' => "{$scope}@example.test"]);
        if ($action === 'setup-out') {
            InventoryStock::factory()->for($unit)->for($part)->create(['quantity' => 5]);
            StockMovement::factory()->for($unit)->for($part)->for($actor, 'actor')->create([
                'type' => StockMovementType::Opening,
                'direction' => StockDirection::In,
                'quantity' => 5,
                'stock_before' => 0,
                'stock_after' => 5,
                'idempotency_key' => (string) Str::uuid(),
            ]);
        }
        echo json_encode(['unit_id' => $unit->id, 'part_id' => $part->id, 'actor_id' => $actor->id], JSON_THROW_ON_ERROR);
        exit(0);
    }

    if ($action === 'cleanup') {
        $scope = $argv[2] ?? '';
        DB::statement('SET @rams_allow_stock_movement_mutation = 1');
        try {
            DB::transaction(function () use ($scope): void {
                $part = SparePart::withTrashed()->where('source_key', 'race|'.$scope)->first();
                $actor = User::query()->where('email', "{$scope}@example.test")->first();
                $unit = UnitKerja::query()->where('code', 'RACE-'.substr(hash('sha256', $scope), 0, 12))->first();
                if ($part) {
                    $movementIds = StockMovement::query()->where('spare_part_id', $part->id)->pluck('id');
                    AuditLog::query()->where('auditable_type', StockMovement::class)->whereIn('auditable_id', $movementIds)->delete();
                    StockMovement::query()->where('spare_part_id', $part->id)->delete();
                    InventoryStock::query()->where('spare_part_id', $part->id)->delete();
                    $subsystem = AssetSubsystem::withTrashed()->find($part->asset_subsystem_id);
                    $part->forceDelete();
                    if ($subsystem) {
                        $system = AssetSystem::withTrashed()->find($subsystem->asset_system_id);
                        $subsystem->forceDelete();
                        if ($system) {
                            $group = AssetGroup::withTrashed()->find($system->asset_group_id);
                            $system->forceDelete();
                            $group?->forceDelete();
                        }
                    }
                }
                $actor?->delete();
                $unit?->delete();
            });
        } finally {
            DB::statement('SET @rams_allow_stock_movement_mutation = NULL');
        }
        echo '{"cleaned":true}';
        exit(0);
    }

    if ($action !== 'record') {
        throw new InvalidArgumentException('Unknown stock movement process action.');
    }

    [$unitId, $partId, $actorId] = array_map('intval', array_slice($argv, 2, 3));
    $type = StockMovementType::from($argv[5]);
    $direction = StockDirection::from($argv[6]);
    $quantity = (int) $argv[7];
    $idempotencyKey = $argv[8];
    $barrier = $argv[9];
    $worker = $argv[10];

    file_put_contents("{$barrier}.{$worker}.ready", 'ready');
    $deadline = microtime(true) + 15;
    while (! is_file("{$barrier}.go")) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Timed out waiting for stock movement barrier.');
        }
        usleep(10_000);
    }

    try {
        $movement = app(StockMovementService::class)->record(
            UnitKerja::query()->findOrFail($unitId),
            SparePart::query()->findOrFail($partId),
            User::query()->findOrFail($actorId),
            $type,
            $direction,
            $quantity,
            Carbon::parse('2026-07-28'),
            null,
            null,
            $idempotencyKey,
        );
        echo json_encode(['success' => true, 'movement_id' => $movement->id], JSON_THROW_ON_ERROR);
    } catch (ValidationException) {
        echo '{"success":false,"validation":true}';
    }
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage());
    exit(1);
}
