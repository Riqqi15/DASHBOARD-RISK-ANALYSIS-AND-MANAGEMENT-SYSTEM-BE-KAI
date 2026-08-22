<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AssetGroupController;
use App\Http\Controllers\Admin\AssetSystemController;
use App\Http\Requests\Admin\StoreAssetSystemRequest;
use App\Models\AssetGroup;
use App\Models\AssetSystem;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
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
    $scope = $action === 'setup' || $action === 'cleanup' ? $argv[2] ?? '' : $argv[4] ?? '';

    if ($scope === '') {
        throw new InvalidArgumentException('A non-empty race scope is required.');
    }

    if ($action === 'setup') {
        $user = User::factory()
            ->pusat()
            ->create(['email' => "{$scope}@example.test"]);
        $group = AssetGroup::factory()->create(['name' => $scope]);

        echo json_encode(['group_id' => $group->id, 'user_id' => $user->id], JSON_THROW_ON_ERROR);

        exit(0);
    }

    if ($action === 'cleanup') {
        DB::transaction(function () use ($scope): void {
            AssetGroup::withTrashed()
                ->where('normalized_name', mb_strtolower($scope))
                ->get()
                ->each(function (AssetGroup $group): void {
                    AuditLog::query()
                        ->where('auditable_type', $group->getMorphClass())
                        ->where('auditable_id', $group->id)
                        ->delete();
                    AssetSystem::withTrashed()
                        ->where('asset_group_id', $group->id)
                        ->get()
                        ->each(function (AssetSystem $system): void {
                            AuditLog::query()
                                ->where('auditable_type', $system->getMorphClass())
                                ->where('auditable_id', $system->id)
                                ->delete();
                            $system->forceDelete();
                        });
                    $group->forceDelete();
                });
            User::query()
                ->where('email', "{$scope}@example.test")
                ->delete();
        });

        echo '{"cleaned":true}';

        exit(0);
    }

    $groupId = (int) ($argv[2] ?? 0);
    $userId = (int) ($argv[3] ?? 0);
    $barrier = $argv[5] ?? '';
    $worker = $argv[6] ?? '';

    if ($groupId < 1 || $userId < 1 || $barrier === '' || $worker === '') {
        throw new InvalidArgumentException('Mutation mode requires category, actor, barrier, and worker identifiers.');
    }

    $user = User::query()->findOrFail($userId);
    $group = AssetGroup::query()->findOrFail($groupId);
    Auth::login($user);

    $storeRequest = null;
    if ($action === 'create-system') {
        $storeRequest = StoreAssetSystemRequest::create('/admin/asset-systems', 'POST', [
            'asset_group_id' => $groupId,
            'name' => "Race System {$scope}",
        ]);
        $storeRequest->setContainer($app);
        $storeRequest->setRedirector($app->make(Redirector::class));
        $storeRequest->setUserResolver(fn (): User => $user);
        $storeRequest->validateResolved();
    }

    file_put_contents("{$barrier}.{$worker}.ready", 'ready');
    $deadline = microtime(true) + 15;
    while (! is_file("{$barrier}.go")) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Timed out waiting for the category mutation barrier.');
        }
        usleep(10_000);
    }

    try {
        if ($action === 'create-system') {
            app(AssetSystemController::class)->store($storeRequest);
            $success = AssetSystem::query()
                ->where('asset_group_id', $groupId)
                ->where('normalized_name', mb_strtolower("Race System {$scope}"))
                ->exists();
        } elseif ($action === 'delete-group') {
            app(AssetGroupController::class)->destroy($group);
            $success = AssetGroup::withTrashed()->findOrFail($groupId)->trashed();
        } else {
            throw new InvalidArgumentException('Unknown category mutation action.');
        }

        echo json_encode(['success' => $success], JSON_THROW_ON_ERROR);
    } catch (ModelNotFoundException) {
        echo '{"success":false}';
    }

    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage());

    exit(1);
}
