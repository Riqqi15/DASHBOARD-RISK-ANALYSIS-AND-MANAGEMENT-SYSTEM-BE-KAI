<?php

namespace Tests\Unit;

use App\Services\ExcelParityReliabilityCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class ExcelParityReliabilityCalculatorTest extends TestCase
{
    public function test_it_matches_interlocking_elektrik_workbook_formula_with_three_failures(): void
    {
        $result = (new ExcelParityReliabilityCalculator)->calculate(
            unitCount: 2,
            baselineDate: CarbonImmutable::parse('2020-01-01 00:00:00'),
            calculationDate: CarbonImmutable::parse('2026-08-03 00:00:00'),
            failures: [
                [
                    'started_at' => CarbonImmutable::parse('2020-03-09 13:15:00'),
                    'resolved_at' => CarbonImmutable::parse('2020-03-09 14:50:00'),
                    'downtime_minutes' => 95,
                    'spare_part_marker' => '',
                    'vandalism_marker' => '',
                ],
                [
                    'started_at' => CarbonImmutable::parse('2021-07-23 08:13:00'),
                    'resolved_at' => CarbonImmutable::parse('2021-07-23 08:35:00'),
                    'downtime_minutes' => 22,
                    'spare_part_marker' => '',
                    'vandalism_marker' => '',
                ],
                [
                    'started_at' => CarbonImmutable::parse('2021-08-01 07:52:00'),
                    'resolved_at' => CarbonImmutable::parse('2021-08-01 08:13:00'),
                    'downtime_minutes' => 21,
                    'spare_part_marker' => '',
                    'vandalism_marker' => '',
                ],
            ],
            profile: [
                'downtime_mode' => 'minutes',
                'failure_count_mode' => 'counta_all_minus_1',
                'spare_part_count_mode' => 'counta',
                'vandalism_count_mode' => 'counta',
            ],
        );

        $this->assertSame(2, $result['unit_count']);
        $this->assertEqualsWithDelta(115488, $result['operating_hours'], 0.0001);
        $this->assertEqualsWithDelta(138, $result['downtime_value'], 0.0001);
        $this->assertEqualsWithDelta(115350, $result['uptime_hours'], 0.0001);
        $this->assertSame(3, $result['failure_count']);
        $this->assertEqualsWithDelta(4626.622222222213, $result['mttf_hours'], 0.000001);
        $this->assertEqualsWithDelta(38450, $result['mtbf_hours'], 0.0001);
        $this->assertEqualsWithDelta(0.000026007802340702212, $result['failure_rate'], 0.000000000001);
        $this->assertEqualsWithDelta(0.9999739925358593, $result['reliability'], 0.0000000001);
        $this->assertEqualsWithDelta(0.9988050706566916, $result['availability'], 0.0000000001);
        $this->assertSame(0, $result['spare_part_replacement_count']);
        $this->assertSame(0, $result['vandalism_count']);
        $this->assertSame('kai-rams-excel-parity-v1.1.0', $result['formula_version']);
    }

    public function test_it_uses_countif_ya_profile_for_marker_columns(): void
    {
        $result = (new ExcelParityReliabilityCalculator)->calculate(
            unitCount: 1,
            baselineDate: CarbonImmutable::parse('2020-01-01 00:00:00'),
            calculationDate: CarbonImmutable::parse('2020-01-03 00:00:00'),
            failures: [
                [
                    'started_at' => CarbonImmutable::parse('2020-01-02 00:00:00'),
                    'resolved_at' => CarbonImmutable::parse('2020-01-02 01:00:00'),
                    'downtime_minutes' => 60,
                    'spare_part_marker' => 'Ya',
                    'vandalism_marker' => 'Tidak',
                ],
                [
                    'started_at' => CarbonImmutable::parse('2020-01-02 12:00:00'),
                    'resolved_at' => CarbonImmutable::parse('2020-01-02 12:30:00'),
                    'downtime_minutes' => 30,
                    'spare_part_marker' => 'Y',
                    'vandalism_marker' => 'Ya',
                ],
            ],
            profile: [
                'downtime_mode' => 'minutes',
                'spare_part_count_mode' => 'countif_ya',
                'vandalism_count_mode' => 'countif_ya',
            ],
        );

        $this->assertEqualsWithDelta(90, $result['downtime_value'], 0.0001);
        $this->assertSame(2, $result['spare_part_replacement_count']);
        $this->assertSame(1, $result['vandalism_count']);
    }

    /**
     * Regression test for Penggerak Wesel Elektrik sheet.
     * 2 failures, downtime_mode=minutes, COUNTA sparepart/vandalism, baseline 2020-01-01.
     * Excel cached values: operating_hours=3637872, downtime=81, uptime=3637791,
     * mttf=20602.99, mtbf=1818895.5, failure_rate=5.4978e-7, reliability=0.999999450,
     * availability=0.999977734.
     */
    public function test_it_matches_penggerak_wesel_elektrik_with_two_failures(): void
    {
        // From the workbook: 63 units, 2406 operating days, P8 = 2017-01-01.
        $result = (new ExcelParityReliabilityCalculator)->calculate(
            unitCount: 63,
            baselineDate: CarbonImmutable::parse('2020-01-01 00:00:00'),
            calculationDate: CarbonImmutable::parse('2026-08-03 00:00:00'),
            failures: [
                [
                    'started_at' => CarbonImmutable::parse('2020-06-26 13:37:00'),
                    'resolved_at' => CarbonImmutable::parse('2020-06-26 14:12:00'),
                    'downtime_minutes' => 35,
                    'spare_part_marker' => '',
                    'vandalism_marker' => '',
                ],
                [
                    'started_at' => CarbonImmutable::parse('2021-09-13 21:59:00'),
                    'resolved_at' => CarbonImmutable::parse('2021-09-13 22:45:00'),
                    'downtime_minutes' => 46,
                    'spare_part_marker' => '',
                    'vandalism_marker' => '',
                ],
            ],
            profile: [
                'downtime_mode' => 'minutes',
                'interval_baseline_date' => '2017-01-01',
                'failure_count_mode' => 'counta',
                'spare_part_count_mode' => 'counta',
                'vandalism_count_mode' => 'counta',
            ],
        );

        // Operating hours: 2406 * 24 * 63 = 3,637,872
        $this->assertSame(63, $result['unit_count']);
        $this->assertEqualsWithDelta(3637872, $result['operating_hours'], 0.0001);
        $this->assertEqualsWithDelta(81, $result['downtime_value'], 0.0001);
        $this->assertEqualsWithDelta(3637791, $result['uptime_hours'], 0.0001);
        $this->assertSame(2, $result['failure_count']);
        $this->assertEqualsWithDelta(20602.991666666698, $result['mttf_hours'], 0.000001);
        // MTBF = 3637791 / 2 = 1818895.5
        $this->assertEqualsWithDelta(1818895.5, $result['mtbf_hours'], 0.0001);
        // Failure rate = 1/1818895.5
        $this->assertEqualsWithDelta(5.497841959584814e-7, $result['failure_rate'], 1e-12);
        // Reliability = exp(-failure_rate)
        $this->assertEqualsWithDelta(0.9999994502159552, $result['reliability'], 1e-10);
        // Availability = 3637791/3637872
        $this->assertEqualsWithDelta(0.999977734235839, $result['availability'], 1e-10);
        $this->assertSame(0, $result['spare_part_replacement_count']);
        $this->assertSame(0, $result['vandalism_count']);
    }

    /**
     * Regression test for Track Circuit sheet.
     * 2 failures, 81 units, COUNTA mode.
     * Excel cached: operating_hours=4677264, downtime=109, uptime=4677155,
     * mttf=21454.125, mtbf=2338577.5, failure_rate=4.276e-7, reliability=0.999999572,
     * availability=0.999976696.
     */
    public function test_it_matches_track_circuit_with_two_failures(): void
    {
        $result = (new ExcelParityReliabilityCalculator)->calculate(
            unitCount: 81,
            baselineDate: CarbonImmutable::parse('2020-01-01 00:00:00'),
            calculationDate: CarbonImmutable::parse('2026-08-03 00:00:00'),
            failures: [
                [
                    'started_at' => CarbonImmutable::parse('2023-02-28 17:10:00'),
                    'resolved_at' => CarbonImmutable::parse('2023-02-28 18:15:00'),
                    'downtime_minutes' => 65,
                    'spare_part_marker' => '',
                    'vandalism_marker' => '',
                ],
                [
                    'started_at' => CarbonImmutable::parse('2024-11-08 04:55:00'),
                    'resolved_at' => CarbonImmutable::parse('2024-11-08 05:39:00'),
                    'downtime_minutes' => 44,
                    'spare_part_marker' => '',
                    'vandalism_marker' => '',
                ],
            ],
            profile: [
                'downtime_mode' => 'minutes',
                'failure_count_mode' => 'counta',
                'spare_part_count_mode' => 'counta',
                'vandalism_count_mode' => 'counta',
            ],
        );

        $this->assertSame(81, $result['unit_count']);
        $this->assertEqualsWithDelta(4677264, $result['operating_hours'], 0.0001);
        $this->assertEqualsWithDelta(109, $result['downtime_value'], 0.0001);
        $this->assertEqualsWithDelta(4677155, $result['uptime_hours'], 0.0001);
        $this->assertSame(2, $result['failure_count']);
        $this->assertEqualsWithDelta(2338577.5, $result['mtbf_hours'], 0.0001);
        $this->assertEqualsWithDelta(4.276103742552898e-7, $result['failure_rate'], 1e-12);
        $this->assertEqualsWithDelta(0.9999995723897172, $result['reliability'], 1e-10);
        $this->assertEqualsWithDelta(0.9999766957777025, $result['availability'], 1e-10);
    }

    /**
     * Zero failure, zero unit scenario — Excel produces #DIV/0! for availability.
     * Backend should return null for availability and null for MTTF.
     */
    public function test_zero_failure_zero_unit_returns_null_availability_and_null_mttf(): void
    {
        $result = (new ExcelParityReliabilityCalculator)->calculate(
            unitCount: 0,
            baselineDate: CarbonImmutable::parse('2020-01-01 00:00:00'),
            calculationDate: CarbonImmutable::parse('2026-08-03 00:00:00'),
            failures: [],
            profile: [
                'downtime_mode' => 'minutes',
                'failure_count_mode' => 'counta',
                'spare_part_count_mode' => 'countif_ya',
                'vandalism_count_mode' => 'countif_ya',
            ],
        );

        $this->assertSame(0, $result['unit_count']);
        $this->assertEqualsWithDelta(0, $result['operating_hours'], 0.0001);
        $this->assertNull($result['mttf_hours']);
        $this->assertEqualsWithDelta(0.0, $result['mtbf_hours'], 0.0001);
        $this->assertEqualsWithDelta(0.0, $result['failure_rate'], 0.0001);
        $this->assertNull($result['availability']);
        $this->assertSame('insufficient_data', $result['calculation_status']);
    }

    /**
     * Zero failure with non-zero units — Excel MTTF produces #VALUE! or #DIV/0!.
     * Backend should return null MTTF, MTBF=0 (IFERROR fallback), availability=1.0.
     */
    public function test_zero_failure_with_units_returns_null_mttf_and_iferror_mtbf(): void
    {
        $result = (new ExcelParityReliabilityCalculator)->calculate(
            unitCount: 3,
            baselineDate: CarbonImmutable::parse('2020-01-01 00:00:00'),
            calculationDate: CarbonImmutable::parse('2026-08-03 00:00:00'),
            failures: [],
            profile: [
                'downtime_mode' => 'minutes',
                'failure_count_mode' => 'counta',
                'spare_part_count_mode' => 'countif_ya',
                'vandalism_count_mode' => 'countif_ya',
            ],
        );

        // CDS sheet: 3 units, 0 failures → operating_hours = 2406*24*3 = 173232
        $this->assertSame(3, $result['unit_count']);
        $this->assertEqualsWithDelta(173232, $result['operating_hours'], 0.0001);
        $this->assertNull($result['mttf_hours']);
        $this->assertEqualsWithDelta(0.0, $result['mtbf_hours'], 0.0001);
        $this->assertEqualsWithDelta(0.0, $result['failure_rate'], 0.0001);
        $this->assertEqualsWithDelta(1.0, exp(-0.0), 0.0001);
        $this->assertEqualsWithDelta(1.0, $result['reliability'], 1e-10);
        $this->assertEqualsWithDelta(1.0, $result['availability'], 1e-10);
        $this->assertSame('calculated', $result['calculation_status']);
    }

    public function test_excel_day_fraction_downtime_matches_sheet_that_sums_downtime_jam_column(): void
    {
        $result = (new ExcelParityReliabilityCalculator)->calculate(
            unitCount: 1,
            baselineDate: CarbonImmutable::parse('2020-01-01 00:00:00'),
            calculationDate: CarbonImmutable::parse('2020-01-02 00:00:00'),
            failures: [[
                'started_at' => CarbonImmutable::parse('2020-01-01 13:15:00'),
                'resolved_at' => CarbonImmutable::parse('2020-01-01 14:50:00'),
                'downtime_minutes' => 95,
                'spare_part_marker' => '',
                'vandalism_marker' => '',
            ]],
            profile: [
                'downtime_mode' => 'excel_day_fraction',
                'failure_count_mode' => 'counta',
                'spare_part_count_mode' => 'countif_ya',
                'vandalism_count_mode' => 'countif_ya',
            ],
        );

        $this->assertEqualsWithDelta(95 / 1440, $result['downtime_value'], 0.000000001);
        $this->assertEqualsWithDelta(24 - (95 / 1440), $result['uptime_hours'], 0.000000001);
    }

    public function test_interval_uses_workbook_row_order_when_source_rows_are_available(): void
    {
        $result = (new ExcelParityReliabilityCalculator)->calculate(
            unitCount: 1,
            baselineDate: CarbonImmutable::parse('2020-01-01 00:00:00'),
            calculationDate: CarbonImmutable::parse('2020-01-04 00:00:00'),
            failures: [
                [
                    'source_row' => 11,
                    'started_at' => CarbonImmutable::parse('2020-01-02 00:00:00'),
                    'downtime_minutes' => 60,
                ],
                [
                    'source_row' => 10,
                    'started_at' => CarbonImmutable::parse('2020-01-03 00:00:00'),
                    'downtime_minutes' => 60,
                ],
            ],
            profile: [
                'downtime_mode' => 'minutes',
                'interval_baseline_date' => '2020-01-01',
            ],
        );

        $this->assertEqualsWithDelta(12, $result['mttf_hours'], 0.0001);
    }

    /** Peraga Sinyal Elektrik Utama uses P8=2017-01-01 for its interval formula. */
    public function test_it_matches_single_failure_peraga_sinyal_elektrik_utama(): void
    {
        $result = (new ExcelParityReliabilityCalculator)->calculate(
            unitCount: 51,
            baselineDate: CarbonImmutable::parse('2020-01-01 00:00:00'),
            calculationDate: CarbonImmutable::parse('2026-08-03 00:00:00'),
            failures: [
                [
                    'started_at' => CarbonImmutable::parse('2021-03-02 20:00:00'),
                    'resolved_at' => CarbonImmutable::parse('2021-03-02 20:37:00'),
                    'downtime_minutes' => 37,
                    'spare_part_marker' => '',
                    'vandalism_marker' => '',
                ],
            ],
            profile: [
                'downtime_mode' => 'minutes',
                'interval_baseline_date' => '2017-01-01',
                'failure_count_mode' => 'counta',
                'spare_part_count_mode' => 'counta',
                'vandalism_count_mode' => 'counta',
            ],
        );

        $this->assertSame(51, $result['unit_count']);
        $this->assertEqualsWithDelta(2944944, $result['operating_hours'], 0.0001);
        $this->assertEqualsWithDelta(37, $result['downtime_value'], 0.0001);
        $this->assertEqualsWithDelta(2944907, $result['uptime_hours'], 0.0001);
        $this->assertSame(1, $result['failure_count']);
        // The workbook summary cache says 9,131, but its live detail formula is 36,524.
        $this->assertEqualsWithDelta(36524, $result['mttf_hours'], 0.000001);
        // MTBF = 2944907 / 1 = 2944907
        $this->assertEqualsWithDelta(2944907, $result['mtbf_hours'], 0.0001);
        // Failure rate = 1 / 2944907
        $this->assertEqualsWithDelta(3.395692970949507e-7, $result['failure_rate'], 1e-12);
        // Reliability = exp(-3.3957e-7)
        $this->assertEqualsWithDelta(0.9999996604307606, $result['reliability'], 1e-10);
        // Availability = 2944907 / 2944944
        $this->assertEqualsWithDelta(0.9999874360938612, $result['availability'], 1e-10);
    }
}
