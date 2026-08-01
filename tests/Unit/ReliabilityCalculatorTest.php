<?php

namespace Tests\Unit;

use App\Services\ReliabilityCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ReliabilityCalculatorTest extends TestCase
{
    public function test_it_calculates_metrics_from_failure_intervals(): void
    {
        $calculator = new ReliabilityCalculator;
        $start = CarbonImmutable::parse('2026-07-01 00:00:00');
        $end = CarbonImmutable::parse('2026-08-01 00:00:00');

        $result = $calculator->calculate(2, $start, $end, [
            [
                'started_at' => CarbonImmutable::parse('2026-07-05 08:00:00'),
                'resolved_at' => CarbonImmutable::parse('2026-07-05 10:00:00'),
            ],
            [
                'started_at' => CarbonImmutable::parse('2026-07-18 22:30:00'),
                'resolved_at' => CarbonImmutable::parse('2026-07-19 02:30:00'),
            ],
        ]);

        $this->assertSame(89280, $result['operating_minutes']);
        $this->assertSame(360, $result['downtime_minutes']);
        $this->assertSame(2, $result['failure_count']);
        $this->assertEqualsWithDelta(326.5, $result['mttf_hours'], 0.0001);
        $this->assertEqualsWithDelta(741, $result['mtbf_hours'], 0.0001);
        $this->assertEqualsWithDelta(3, $result['mttr_hours'], 0.0001);
        $this->assertEqualsWithDelta(1 / 741, $result['failure_rate'], 0.0000001);
        $this->assertEqualsWithDelta(exp(-1 / 741), $result['reliability'], 0.0000001);
        $this->assertEqualsWithDelta(88920 / 89280, $result['availability'], 0.0000001);
        $this->assertSame('calculated', $result['calculation_status']);
        $this->assertSame('kai-rams-v1.0.0', $result['formula_version']);
    }

    public function test_it_returns_safe_defaults_without_operating_time_or_failures(): void
    {
        $calculator = new ReliabilityCalculator;
        $instant = CarbonImmutable::parse('2026-07-01 00:00:00');

        $result = $calculator->calculate(0, $instant, $instant, []);

        $this->assertSame(0, $result['operating_minutes']);
        $this->assertSame(0, $result['downtime_minutes']);
        $this->assertSame(0, $result['failure_count']);
        $this->assertNull($result['mttf_hours']);
        $this->assertNull($result['mtbf_hours']);
        $this->assertNull($result['mttr_hours']);
        $this->assertNull($result['failure_rate']);
        $this->assertNull($result['reliability']);
        $this->assertNull($result['availability']);
        $this->assertSame('insufficient_data', $result['calculation_status']);
    }

    public function test_it_reports_perfect_operation_when_time_exists_without_failures(): void
    {
        $result = (new ReliabilityCalculator)->calculate(
            1,
            CarbonImmutable::parse('2026-07-01 00:00:00'),
            CarbonImmutable::parse('2026-07-02 00:00:00'),
            [],
        );

        $this->assertSame(1440, $result['operating_minutes']);
        $this->assertNull($result['mttf_hours']);
        $this->assertNull($result['mtbf_hours']);
        $this->assertNull($result['failure_rate']);
        $this->assertSame(1.0, $result['reliability']);
        $this->assertSame(1.0, $result['availability']);
        $this->assertSame('calculated', $result['calculation_status']);
    }
}
