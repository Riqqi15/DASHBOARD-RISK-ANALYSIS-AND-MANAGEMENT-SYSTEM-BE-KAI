<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\ReliabilityExcelSnapshot;
use App\Models\UnitKerja;
use App\Services\ReliabilityParityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReliabilityBaselinePrecedenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_unit_override_takes_precedence_over_imported_excel_baseline(): void
    {
        $unit = UnitKerja::factory()->create(['operating_start_date' => '2019-01-01']);
        $asset = Asset::factory()->for($unit)->create(['tanggal_pemasangan' => '2022-01-01']);
        $this->snapshot($asset, '2020-01-01');

        $summary = app(ReliabilityParityService::class)->recalculateAsset(
            $asset,
            CarbonImmutable::parse('2021-01-01'),
        );

        $this->assertSame('2019-01-01', $summary?->baseline_date?->toDateString());
    }

    public function test_imported_excel_baseline_is_used_when_no_override_exists(): void
    {
        $unit = UnitKerja::factory()->create(['operating_start_date' => null]);
        $asset = Asset::factory()->for($unit)->create(['tanggal_pemasangan' => '2022-01-01']);
        $this->snapshot($asset, '2020-01-01');

        $summary = app(ReliabilityParityService::class)->recalculateAsset(
            $asset,
            CarbonImmutable::parse('2021-01-01'),
        );

        $this->assertSame('2020-01-01', $summary?->baseline_date?->toDateString());
    }

    public function test_missing_override_and_imported_baseline_does_not_fall_back_to_asset_date(): void
    {
        $unit = UnitKerja::factory()->create(['operating_start_date' => null]);
        $asset = Asset::factory()->for($unit)->create(['tanggal_pemasangan' => '2020-01-01']);

        $summary = app(ReliabilityParityService::class)->recalculateAsset($asset);

        $this->assertNull($summary);
        $this->assertDatabaseCount('reliability_summaries', 0);
    }

    private function snapshot(Asset $asset, string $baseline): ReliabilityExcelSnapshot
    {
        return ReliabilityExcelSnapshot::query()->create([
            'asset_id' => $asset->id,
            'workbook_hash' => str_repeat('a', 64),
            'workbook_name' => 'RAMS.xlsm',
            'sheet_name' => 'Interlocking Elektrik',
            'source_row' => 4,
            'baseline_date' => $baseline,
            'calculation_date' => '2021-01-01',
            'summary_values' => [],
            'formula_profile' => [
                'downtime_mode' => 'minutes',
                'interval_baseline_date' => $baseline,
                'empty_mttf_mode' => 'null',
                'spare_part_count_mode' => 'countif_ya',
                'vandalism_count_mode' => 'countif_ya',
            ],
            'imported_at' => now(),
        ]);
    }
}
