<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonInterface;
use InvalidArgumentException;

final class ReliabilityCalculator
{
    public const FORMULA_VERSION = 'kai-rams-v1.0.0';

    /**
     * @param  iterable<int, array{started_at: CarbonInterface, resolved_at: CarbonInterface}>  $failures
     * @return array<string, int|float|null>
     */
    public function calculate(
        int $unitCount,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        iterable $failures,
    ): array {
        $periodMinutes = max(0, (int) $periodStart->diffInMinutes($periodEnd, false));
        $operatingMinutes = $periodMinutes * max(0, $unitCount);
        $downtimeMinutes = 0;
        $failureStarts = [];

        foreach ($failures as $failure) {
            $startedAt = $failure['started_at'];
            $resolvedAt = $failure['resolved_at'];

            if ($resolvedAt->lessThan($startedAt)) {
                throw new InvalidArgumentException('Waktu selesai gangguan tidak boleh sebelum waktu mulai.');
            }

            if ($startedAt->greaterThanOrEqualTo($periodStart) && $startedAt->lessThan($periodEnd)) {
                $failureStarts[] = $startedAt;
            }

            $overlapStart = $startedAt->greaterThan($periodStart) ? $startedAt : $periodStart;
            $overlapEnd = $resolvedAt->lessThan($periodEnd) ? $resolvedAt : $periodEnd;
            if ($overlapEnd->greaterThan($overlapStart)) {
                $downtimeMinutes += (int) $overlapStart->diffInMinutes($overlapEnd);
            }
        }

        $downtimeMinutes = min($downtimeMinutes, $operatingMinutes);
        usort(
            $failureStarts,
            fn (CarbonInterface $left, CarbonInterface $right): int => $left->getTimestamp() <=> $right->getTimestamp(),
        );
        $failureCount = count($failureStarts);
        $uptimeHours = ($operatingMinutes - $downtimeMinutes) / 60;
        $intervalHours = [];
        for ($index = 1; $index < $failureCount; $index++) {
            $intervalHours[] = $failureStarts[$index - 1]->diffInMinutes($failureStarts[$index]) / 60;
        }
        $mttfHours = $intervalHours === [] ? null : array_sum($intervalHours) / count($intervalHours);
        $mtbfHours = $failureCount > 0 ? $uptimeHours / $failureCount : null;
        $mttrHours = $failureCount > 0 ? ($downtimeMinutes / 60) / $failureCount : null;
        $failureRate = $mtbfHours !== null && $mtbfHours > 0 ? 1 / $mtbfHours : null;
        $hasOperatingTime = $operatingMinutes > 0;

        return [
            'operating_minutes' => $operatingMinutes,
            'downtime_minutes' => $downtimeMinutes,
            'failure_count' => $failureCount,
            'mttf_hours' => $mttfHours,
            'mtbf_hours' => $mtbfHours,
            'mttr_hours' => $mttrHours,
            'failure_rate' => $failureRate,
            'reliability' => ! $hasOperatingTime
                ? null
                : (float) ($failureRate === null ? 1.0 : exp(-$failureRate)),
            'availability' => $hasOperatingTime
                ? (float) (($operatingMinutes - $downtimeMinutes) / $operatingMinutes)
                : null,
            'calculation_status' => $hasOperatingTime ? 'calculated' : 'insufficient_data',
            'formula_version' => self::FORMULA_VERSION,
        ];
    }
}
