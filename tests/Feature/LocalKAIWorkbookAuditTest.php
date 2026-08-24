<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AssetGroup;
use App\Models\FailureLog;
use App\Models\PredictiveAssetSnapshot;
use App\Models\RiskMatrix;
use App\Models\SparePart;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\FailureLogImportService;
use App\Services\MasterAssetWorkbookImporter;
use App\Services\RiskMatrixWorkbookImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('local-workbook')]
final class LocalKAIWorkbookAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_configured_kai_workbooks_import_without_touching_users(): void
    {
        $directory = env('RAMS_WORKBOOK_DIRECTORY');
        if (! is_string($directory) || ! is_dir($directory)) {
            $this->markTestSkipped('Set RAMS_WORKBOOK_DIRECTORY to run the local KAI workbook audit.');
        }

        $workbooks = [
            'DAOP-1' => 'Risk Analysis And Management System RAMS Daop 1.xlsm',
            'DAOP-4' => 'Risk Analysis And Management System RAMS Daop 4.xlsm',
            'DAOP-8' => 'Risk Analysis And Management System RAMS Daop 8.xlsm',
            'DIVRE-III' => 'Risk Analysis And Management System RAMS Divre III.xlsm',
            'DIVRE-IV' => 'Risk Analysis And Management System RAMS Divre IV.xlsm',
        ];
        User::factory()->pusat()->create(['username' => 'audit-pusat']);
        $usersBefore = User::query()->orderBy('id')->get()->map->getRawOriginal()->all();

        foreach ($workbooks as $code => $filename) {
            $path = rtrim($directory, '\\/').DIRECTORY_SEPARATOR.$filename;
            $this->assertFileExists($path);
            $unit = UnitKerja::factory()->create(['code' => $code, 'is_active' => true]);
            $master = app(MasterAssetWorkbookImporter::class)->import($path, $unit);
            $risk = app(RiskMatrixWorkbookImporter::class)->import($path, $unit);
            $this->assertGreaterThan(0, $master['predictive_snapshots'], $filename);
            $this->assertGreaterThan(0, $risk['created'] + $risk['updated'], $filename);
        }

        $catuDaya = AssetGroup::query()->get()->first(
            fn (AssetGroup $group): bool => str_contains(mb_strtoupper($group->name), 'CATU DAYA SINTEL'),
        );
        $this->assertNotNull($catuDaya);
        $this->assertSame('#FF0000', $catuDaya->dashboard_color);
        $this->assertGreaterThan(0, RiskMatrix::query()->count());
        $this->assertGreaterThan(0, PredictiveAssetSnapshot::query()->where('current_stock', '<', 0)->count());
        $this->assertGreaterThan(0, PredictiveAssetSnapshot::query()->where('parity_status', 'corrected')->count());
        $this->assertSame($usersBefore, User::query()->orderBy('id')->get()->map->getRawOriginal()->all());
    }

    public function test_daop_one_full_workbook_import_is_traceable_and_idempotent(): void
    {
        $directory = env('RAMS_WORKBOOK_DIRECTORY');
        if (! is_string($directory) || ! is_dir($directory)) {
            $this->markTestSkipped('Set RAMS_WORKBOOK_DIRECTORY to run the local KAI workbook audit.');
        }

        $filename = 'Risk Analysis And Management System RAMS Daop 1.xlsm';
        $path = rtrim($directory, '\\/').DIRECTORY_SEPARATOR.$filename;
        $this->assertFileExists($path);
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $actor = User::factory()->unit($unit)->create();
        $usersBefore = User::query()->orderBy('id')->get()->map->getRawOriginal()->all();
        $workbook = new UploadedFile($path, $filename, null, null, true);
        $service = app(FailureLogImportService::class);

        $first = $service->import($workbook, $unit, false, $actor);
        $firstFailureCount = FailureLog::query()->count();
        $firstSparePartCount = SparePart::query()->count();
        $second = $service->import($workbook, $unit, false, $actor);

        $this->assertSame('succeeded', $first['status']);
        $this->assertSame('succeeded', $second['status']);
        $this->assertGreaterThan(0, $first['created']);
        $this->assertGreaterThan(0, $first['spare_parts_created']);
        $this->assertSame($firstFailureCount, FailureLog::query()->count());
        $this->assertSame($firstSparePartCount, SparePart::query()->count());
        $this->assertSame(0, $second['created']);
        $this->assertSame([], FailureLog::query()
            ->selectRaw('source_key, COUNT(*) AS aggregate')
            ->whereNotNull('source_key')
            ->groupBy('source_key')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('aggregate', 'source_key')
            ->all());
        $this->assertTrue(collect($first['issues'])->every(
            fn (array $issue): bool => ($issue['workbook_name'] ?? null) === $filename,
        ));
        $this->assertSame($usersBefore, User::query()->orderBy('id')->get()->map->getRawOriginal()->all());
    }
}
