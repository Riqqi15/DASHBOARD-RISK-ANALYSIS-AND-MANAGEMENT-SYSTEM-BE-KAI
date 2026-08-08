<?php

declare(strict_types=1);

namespace App\Services;

final class ReliabilityFormulaProfileResolver
{
    /** @return array<string, string|int|bool|null> */
    public function resolve(array $profile, string $fallbackBaseline): array
    {
        return [
            'downtime_mode' => $this->allowed($profile['downtime_mode'] ?? null, ['minutes', 'hours', 'excel_day_fraction'], 'minutes'),
            'interval_baseline_date' => (string) ($profile['interval_baseline_date'] ?? $fallbackBaseline),
            'failure_count_mode' => $this->allowed($profile['failure_count_mode'] ?? null, ['counta', 'counta_all_minus_1'], 'counta'),
            'spare_part_count_mode' => $this->allowed($profile['spare_part_count_mode'] ?? null, ['counta', 'countif_ya'], 'countif_ya'),
            'vandalism_count_mode' => $this->allowed($profile['vandalism_count_mode'] ?? null, ['counta', 'countif_ya'], 'countif_ya'),
            'empty_mttf_mode' => $this->allowed($profile['empty_mttf_mode'] ?? null, ['zero', 'null'], 'null'),
            'failure_interval_row_count' => is_numeric($profile['failure_interval_row_count'] ?? null)
                ? max(0, (int) $profile['failure_interval_row_count'])
                : null,
            'is_fallback' => $profile === [],
        ];
    }

    private function allowed(mixed $value, array $allowed, string $fallback): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $fallback;
    }
}
