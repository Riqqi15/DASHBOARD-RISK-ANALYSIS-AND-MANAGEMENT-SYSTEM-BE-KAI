<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\FailureLog;
use App\Models\ReliabilityExcelSnapshot;
use App\Models\ReliabilitySummary;
use App\Models\UnitKerja;
use Carbon\CarbonImmutable;

final class ReliabilityParityService
{
    /** @var array<string, float> */
    private const TOLERANCES = [
        'unit_count' => 0.0,
        'operating_hours' => 0.0001,
        'downtime_value' => 0.0001,
        'uptime_hours' => 0.0001,
        'failure_count' => 0.0,
        'mttf_hours' => 0.001,
        'mtbf_hours' => 0.001,
        'failure_rate' => 0.00000001,
        'reliability' => 0.00000001,
        'availability' => 0.00000001,
        'spare_part_replacement_count' => 0.0,
        'vandalism_count' => 0.0,
    ];

    public function __construct(private readonly ExcelParityReliabilityCalculator $calculator) {}

    /** @return array{counts: array<string, int>, issues: list<array<string, mixed>>} */
    public function recalculateUnit(UnitKerja $unit): array
    {
        $counts = [
            'calculated' => 0,
            'matched' => 0,
            'mismatch' => 0,
            'excel_data_missing' => 0,
            'not_compared' => 0,
        ];
        $issues = [];

        Asset::query()
            ->where('unit_kerja_id', $unit->id)
            ->with('assetSubsystem')
            ->get()
            ->each(function (Asset $asset) use (&$counts, &$issues): void {
                $summary = $this->recalculateAsset($asset);
                if (! $summary) {
                    return;
                }
                $counts['calculated']++;
                $counts[$summary->parity_status] = ($counts[$summary->parity_status] ?? 0) + 1;

                if ($summary->parity_status === 'mismatch' && $summary->parity_differences) {
                    $mismatches = [];
                    $causes = [];
                    foreach ($summary->parity_differences as $key => $diff) {
                        $mismatches[] = "{$key} (Sistem: {$diff['backend']}, Excel: {$diff['excel']})";
                        if (in_array($key, ['failure_count', 'spare_part_replacement_count', 'vandalism_count'])) {
                            $causes['log_missing'] =
                'Ada log kerusakan di Excel yang dilewati sistem (format salah/kosong), '
                .'atau salah hitung manual.';
                        } elseif (in_array($key, ['downtime_value', 'uptime_hours', 'operating_hours'])) {
                            $causes['duration_error'] =
                                'Kesalahan penjumlahan durasi jam/menit (downtime/uptime) pada Excel.';
                        } elseif (
                            in_array($key, ['mttf_hours', 'mtbf_hours', 'failure_rate', 'reliability', 'availability'])
                        ) {
                            $causes['formula_error'] =
                'Kemungkinan besar rumus formula keandalan (pembagian/eksponensial) '
                .'di Excel keliru atau salah ketik.';
                        }
                    }
                    $causeText = empty($causes)
                        ? 'Periksa kembali data pada Excel.'
                        : implode(' ', array_values($causes));
                    $issues[] = [
                        'sheet_name' => 'Ringkasan Keandalan',
                        'source_row' => null,
                        'source_column' => null,
                        'message' => "Selisih parity pada aset {$asset->nama_aset}. Detail: ".
                            implode(', ', $mismatches).
                            ". Penyebab: {$causeText}",
                        'severity' => 'warning',
                    ];
                }
            });

        return ['counts' => $counts, 'issues' => $issues];
    }

    public function recalculateAsset(
        Asset $asset,
        ?CarbonImmutable $fallbackCalculationDate = null,
    ): ?ReliabilitySummary {
        $asset->loadMissing('unitKerja');
        $snapshot = ReliabilityExcelSnapshot::query()
            ->where('asset_id', $asset->id)
            ->latest('imported_at')
            ->latest('id')
            ->first();

        $baselineDate = $asset->unitKerja?->operating_start_date
            ? CarbonImmutable::instance($asset->unitKerja->operating_start_date)->startOfDay()
            : ($snapshot?->baseline_date
                ? CarbonImmutable::instance($snapshot->baseline_date)->startOfDay()
                : null);
        if (! $baselineDate) {
            return null;
        }
        $calculationDate = $fallbackCalculationDate
            ? $fallbackCalculationDate->startOfDay()
            : ($snapshot?->calculation_date
                ? CarbonImmutable::instance($snapshot->calculation_date)
                : now()->toImmutable()->startOfDay());
        $profile = $snapshot?->formula_profile ?? [
            'downtime_mode' => 'minutes',
            'interval_baseline_date' => $baselineDate->toDateString(),
            'empty_mttf_mode' => 'null',
            'spare_part_count_mode' => 'countif_ya',
            'vandalism_count_mode' => 'countif_ya',
        ];

        $failures = FailureLog::query()
            ->where('asset_id', $asset->id)
            ->where('started_at', '<', $calculationDate->addDay())
            ->orderByRaw('CASE WHEN source_row IS NULL THEN 1 ELSE 0 END')
            ->orderBy('source_row')
            ->orderBy('started_at')
            ->orderBy('id')
            ->get()
            ->map(
                fn (FailureLog $failure): array => [
                    'source_row' => $failure->source_row,
                    'started_at' => CarbonImmutable::instance($failure->started_at),
                    'resolved_at' => CarbonImmutable::instance($failure->resolved_at),
                    'downtime_minutes' => $failure->downtime_minutes,
                    'spare_part_marker' => $failure->spare_part_marker,
                    'vandalism_marker' => $failure->vandalism_marker,
                    'spare_part_replaced' => $failure->spare_part_replaced,
                    'vandalism' => $failure->vandalism,
                ],
            );

        $metrics = $this->calculator->calculate(
            unitCount: (int) $asset->jumlah_unit,
            baselineDate: $baselineDate,
            calculationDate: $calculationDate,
            failures: $failures,
            profile: $profile,
        );
        [$status, $differences] = $this->compare($metrics, $snapshot);

        return ReliabilitySummary::query()->updateOrCreate(
            ['asset_id' => $asset->id, 'period' => $calculationDate->startOfMonth()->toDateString()],
            [
                ...$metrics,
                'excel_snapshot_id' => $snapshot?->id,
                'baseline_date' => $baselineDate->toDateString(),
                'calculation_date' => $calculationDate->toDateString(),
                'calculation_profile' => $profile,
                'parity_status' => $status,
                'parity_differences' => $differences === [] ? null : $differences,
                'calculated_at' => now(),
            ],
        );
    }

    /** @param array<string, mixed> $metrics
     * @return array{string, array<string, array{backend: mixed, excel: mixed}>}
     */
    private function compare(array $metrics, ?ReliabilityExcelSnapshot $snapshot): array
    {
        if (! $snapshot) {
            return ['excel_data_missing', []];
        }

        $values = $snapshot->summary_values ?? [];
        $errors = $snapshot->summary_errors ?? [];
        $differences = [];
        $missingExcel = false;

        foreach (self::TOLERANCES as $key => $tolerance) {
            if (
                array_key_exists($key, $errors) ||
                ! array_key_exists($key, $values) ||
                $values[$key] === null ||
                $values[$key] === ''
            ) {
                $missingExcel = true;

                continue;
            }

            $backend = $metrics[$key] ?? null;
            $excel = $values[$key];
            if (is_numeric($backend) && is_numeric($excel)) {
                if (abs((float) $backend - (float) $excel) > $tolerance) {
                    $differences[$key] = ['backend' => $backend, 'excel' => $excel];
                }

                continue;
            }

            if ($backend !== $excel) {
                $differences[$key] = ['backend' => $backend, 'excel' => $excel];
            }
        }

        if ($differences !== []) {
            return ['mismatch', $differences];
        }

        return [$missingExcel ? 'excel_data_missing' : 'matched', []];
    }
}
