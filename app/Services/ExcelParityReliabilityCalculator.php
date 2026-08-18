<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class ExcelParityReliabilityCalculator
{
    public const FORMULA_VERSION = 'kai-rams-excel-parity-v1.1.0';

    /**
     * @param  iterable<int, array<string, mixed>>  $failures
     * @param  array<string, mixed>  $profile
     * @return array<string, int|float|string|null>
     */
    public function calculate(
        int $unitCount,
        CarbonInterface $baselineDate,
        CarbonInterface $calculationDate,
        iterable $failures,
        array $profile = [],
    ): array {
        $unitCount = max(0, $unitCount);
        $baseline = CarbonImmutable::instance($baselineDate)->startOfDay();
        $calculation = CarbonImmutable::instance($calculationDate)->startOfDay();
        $operatingHours = max(0.0, $baseline->diffInDays($calculation, false) * 24.0 * $unitCount);
        $rows = collect($failures)
            ->filter(fn (array $failure): bool => isset($failure['started_at']))
            ->sortBy(function (array $failure): array {
                $sourceRow = $failure['source_row'] ?? null;

                return [
                    $sourceRow === null ? 1 : 0,
                    $sourceRow === null ? $failure['started_at']->getTimestamp() : (int) $sourceRow,
                    $failure['started_at']->getTimestamp(),
                ];
            })
            ->values();

        $downtimeMode = $profile['downtime_mode'] ?? 'minutes';
        $downtimeMinutes = $rows->sum(fn (array $failure): int => (int) ($failure['downtime_minutes'] ?? 0));
        $downtimeValue = match ($downtimeMode) {
            'excel_day_fraction' => $downtimeMinutes / 1440,
            'hours' => $downtimeMinutes / 60,
            default => $downtimeMinutes,
        };
        $failureCount = $rows->count();
        $uptimeHours = $operatingHours - $downtimeValue;
        $intervals = [];
        $previousStart = isset($profile['interval_baseline_date'])
            ? CarbonImmutable::parse((string) $profile['interval_baseline_date'])->startOfDay()
            : $baseline;

        foreach ($rows as $failure) {
            $startedAt = CarbonImmutable::instance($failure['started_at']);
            $intervals[] = $previousStart->diffInMinutes($startedAt, false) / 60;
            $previousStart = $startedAt;
        }

        $mttfHours = $intervals === []
            ? (($profile['empty_mttf_mode'] ?? 'null') === 'zero' ? 0.0 : null)
            : array_sum($intervals) / count($intervals);
        $mtbfHours = $failureCount > 0 ? $uptimeHours / $failureCount : 0.0;
        $failureRate = $mtbfHours > 0 ? 1 / $mtbfHours : 0.0;
        $availability = $operatingHours > 0 ? $uptimeHours / $operatingHours : null;

        return [
            'unit_count' => $unitCount,
            'operating_hours' => $operatingHours,
            'operating_minutes' => (int) round($operatingHours * 60),
            'downtime_value' => $downtimeValue,
            'downtime_minutes' => $downtimeMinutes,
            'uptime_hours' => $uptimeHours,
            'failure_count' => $failureCount,
            'mttf_hours' => $mttfHours,
            'mtbf_hours' => $mtbfHours,
            'mttr_hours' => $failureCount > 0 ? ($downtimeMinutes / 60) / $failureCount : null,
            'failure_rate' => $failureRate,
            'reliability' => exp(-$failureRate),
            'availability' => $availability,
            'spare_part_replacement_count' => $this->markerCount($rows->all(), $profile['spare_part_count_mode'] ?? 'countif_ya', 'spare_part_marker', 'spare_part_replaced'),
            'vandalism_count' => $this->markerCount($rows->all(), $profile['vandalism_count_mode'] ?? 'countif_ya', 'vandalism_marker', 'vandalism'),
            'calculation_status' => $operatingHours > 0 ? 'calculated' : 'insufficient_data',
            'formula_version' => self::FORMULA_VERSION,
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    private function markerCount(array $rows, string $_mode, string $markerKey, string $booleanKey): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $marker = trim((string) ($row[$markerKey] ?? ''));
            if ($this->isYes($marker) || (bool) ($row[$booleanKey] ?? false)) {
                $count++;
            }
        }

        return $count;
    }

    private function isYes(string $value): bool
    {
        return in_array(mb_strtoupper(trim($value)), ['Y', 'YA', 'YES'], true);
    }
}
