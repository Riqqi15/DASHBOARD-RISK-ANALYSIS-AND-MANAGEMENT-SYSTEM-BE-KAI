<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RamsImportBatch;
use App\Models\RamsImportIssue;
use App\Models\UnitKerja;
use App\Services\FailureLogWorkbookImporter;
use App\Services\MasterAssetWorkbookImporter;
use App\Services\RamsWorkbookImportCoordinator;
use App\Services\RiskMatrixWorkbookImporter;
use App\Services\RiskRegisterWorkbookImporter;
use App\Services\SparePartWorkbookImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class RamsWorkbookImportCoordinatorTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            is_dir($path) ? @rmdir($path) : @unlink($path);
        }

        parent::tearDown();
    }

    public function test_dry_run_rolls_back_and_does_not_claim_the_fingerprint(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $path = $this->workbookPath('Risk Analysis And Management System RAMS Daop 1.xlsm');
        $master = Mockery::mock(MasterAssetWorkbookImporter::class);
        $master->shouldReceive('import')->once()->with($path, Mockery::on(fn (UnitKerja $value): bool => $value->is($unit)))
            ->andReturn(['created' => 1]);
        $spare = Mockery::mock(SparePartWorkbookImporter::class);
        $spare->shouldReceive('import')->once()->with($path, false, Mockery::on(fn (UnitKerja $value): bool => $value->is($unit)))
            ->andReturn(['created' => 2]);
        $failures = Mockery::mock(FailureLogWorkbookImporter::class);
        $failures->shouldReceive('import')->once()->andReturn(['created' => 3]);
        $risks = Mockery::mock(RiskRegisterWorkbookImporter::class);
        $risks->shouldReceive('import')->once()->andReturn(['created' => 4]);
        $matrices = Mockery::mock(RiskMatrixWorkbookImporter::class);
        $matrices->shouldReceive('import')->once()->andReturn(['created' => 1, 'issues' => []]);

        $result = (new RamsWorkbookImportCoordinator($master, $spare, $failures, $risks, $matrices))->importWorkbook($path, true);

        $this->assertSame('validated', $result['status']);
        $this->assertDatabaseCount('rams_import_batches', 0);
    }

    public function test_successful_fingerprint_is_not_imported_twice(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $path = $this->workbookPath('Risk Analysis And Management System RAMS Daop 1.xlsm');
        $master = Mockery::mock(MasterAssetWorkbookImporter::class);
        $master->shouldReceive('import')->once()->andReturn(['created' => 1]);
        $spare = Mockery::mock(SparePartWorkbookImporter::class);
        $spare->shouldReceive('import')->once()->andReturn(['created' => 2]);
        $failures = Mockery::mock(FailureLogWorkbookImporter::class);
        $failures->shouldReceive('import')->once()->andReturn(['created' => 3]);
        $risks = Mockery::mock(RiskRegisterWorkbookImporter::class);
        $risks->shouldReceive('import')->once()->andReturn(['created' => 4]);
        $matrices = Mockery::mock(RiskMatrixWorkbookImporter::class);
        $matrices->shouldReceive('import')->once()->andReturn(['created' => 1, 'issues' => []]);
        $coordinator = new RamsWorkbookImportCoordinator($master, $spare, $failures, $risks, $matrices);

        $first = $coordinator->importWorkbook($path);
        $second = $coordinator->importWorkbook($path);

        $this->assertSame('succeeded', $first['status']);
        $this->assertSame('skipped_duplicate', $second['status']);
        $this->assertSame($unit->id, RamsImportBatch::query()->sole()->unit_kerja_id);
    }

    public function test_failed_import_is_audited_with_an_issue(): void
    {
        UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $path = $this->workbookPath('Risk Analysis And Management System RAMS Daop 1.xlsm');
        $master = Mockery::mock(MasterAssetWorkbookImporter::class);
        $master->shouldReceive('import')->once()->andThrow(new RuntimeException('Header rusak.'));
        $spare = Mockery::mock(SparePartWorkbookImporter::class);
        $failures = Mockery::mock(FailureLogWorkbookImporter::class);
        $risks = Mockery::mock(RiskRegisterWorkbookImporter::class);
        $matrices = Mockery::mock(RiskMatrixWorkbookImporter::class);

        $result = (new RamsWorkbookImportCoordinator($master, $spare, $failures, $risks, $matrices))->importWorkbook($path);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('failed', RamsImportBatch::query()->sole()->status);
        $this->assertSame('Header rusak.', RamsImportIssue::query()->sole()->message);
    }

    private function workbookPath(string $filename): string
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rams-coordinator-'.bin2hex(random_bytes(5));
        mkdir($directory);
        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        file_put_contents($path, 'test workbook fingerprint');
        $this->temporaryFiles[] = $directory;
        array_unshift($this->temporaryFiles, $path);

        return $path;
    }
}
