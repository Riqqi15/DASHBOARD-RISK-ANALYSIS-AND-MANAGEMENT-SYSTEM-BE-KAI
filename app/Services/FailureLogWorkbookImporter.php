<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\FailureLog;
use App\Models\ReliabilitySummary;
use App\Models\UnitKerja;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class FailureLogWorkbookImporter
{
    /** @var array<string, string> */
    private const HEADER_MAP = [
        'lokasi' => 'location',
        'resor' => 'resort',
        'qc' => 'qc',
        'failure event' => 'failure_event',
        'penyebab' => 'cause',
        'tindakan' => 'action_taken',
        'penggantian sparepart' => 'spare_part_replaced',
        'tindak vandalisme' => 'vandalism',
        'tanggal kejadian' => 'event_date',
        'tanggal penanganan' => 'handled_date',
        'mulai' => 'start_time',
        'selesai' => 'end_time',
    ];

    public function __construct(
        private readonly AssetCategoryResolver $categoryResolver,
        private readonly ReliabilityCalculator $reliabilityCalculator,
    ) {}

    /** @return array<string, mixed> */
    public function import(string $workbookPath, UnitKerja $unit): array
    {
        $workbookHash = hash_file('sha256', $workbookPath);
        if ($workbookHash === false) {
            throw new RuntimeException("Fingerprint workbook gagal dibuat: {$workbookPath}");
        }
        $reader = IOFactory::createReaderForFile($workbookPath);
        $sheetNames = $reader->listWorksheetNames($workbookPath);
        $result = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'sheets' => 0,
            'summaries' => 0,
            'issues' => [],
        ];
        $affectedAssetIds = [];

        DB::transaction(function () use (
            $reader,
            $sheetNames,
            $workbookPath,
            $workbookHash,
            $unit,
            &$result,
            &$affectedAssetIds,
        ): void {
            foreach ($sheetNames as $sheetName) {
                $reader->setReadDataOnly(true);
                $reader->setLoadSheetsOnly([$sheetName]);
                $reader->setReadEmptyCells(false);
                $reader->setIgnoreRowsWithNoCells(true);
                $spreadsheet = $reader->load($workbookPath);

                try {
                    $sheet = $spreadsheet->getSheetByName($sheetName);
                    if (! $sheet) {
                        continue;
                    }
                    $headers = $this->failureHeaders($sheet);
                    if ($headers === null) {
                        continue;
                    }
                    $asset = $this->resolveAsset($sheet, $unit, $sheetName);
                    $result['sheets']++;
                    $this->importRows(
                        $sheet,
                        $headers['row'],
                        $headers['columns'],
                        $asset,
                        $workbookHash,
                        $sheetName,
                        $result,
                    );
                    $affectedAssetIds[$asset->id] = true;
                } finally {
                    $spreadsheet->disconnectWorksheets();
                    unset($spreadsheet);
                }
            }

            foreach (array_keys($affectedAssetIds) as $assetId) {
                $this->recalculateReliability(Asset::query()->findOrFail($assetId));
                $result['summaries']++;
            }
        }, 3);

        return $result;
    }

    /** @return array{row: int, columns: array<string, int>}|null */
    private function failureHeaders(Worksheet $sheet): ?array
    {
        for ($row = 1; $row <= min(20, $sheet->getHighestDataRow()); $row++) {
            $columns = [];
            $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn($row));
            for ($column = 1; $column <= min(25, $highestColumn); $column++) {
                $header = $this->normalize($sheet->getCell([$column, $row])->getValue());
                if (isset(self::HEADER_MAP[$header])) {
                    $columns[self::HEADER_MAP[$header]] = $column;
                }
            }
            if (isset($columns['location'], $columns['failure_event'], $columns['event_date'], $columns['start_time'])) {
                return ['row' => $row, 'columns' => $columns];
            }
        }

        return null;
    }

    /** @param array<string, int> $columns
     * @param  array<string, mixed>  $result
     */
    private function importRows(
        Worksheet $sheet,
        int $headerRow,
        array $columns,
        Asset $asset,
        string $workbookHash,
        string $sheetName,
        array &$result,
    ): void {
        foreach (['cause', 'action_taken', 'handled_date', 'end_time'] as $required) {
            if (! isset($columns[$required])) {
                throw new RuntimeException("Kolom {$required} tidak ditemukan pada sheet {$sheetName}.");
            }
        }

        for ($row = $headerRow + 1; $row <= $sheet->getHighestDataRow(); $row++) {
            $event = $this->text($this->cellValue($sheet, $columns['failure_event'], $row));
            if ($event === '') {
                continue;
            }
            $cause = $this->text($this->cellValue($sheet, $columns['cause'], $row));
            $action = $this->text($this->cellValue($sheet, $columns['action_taken'], $row));
            if ($cause === '' || $action === '') {
                $result['skipped']++;
                $result['issues'][] = [
                    'sheet_name' => $sheetName,
                    'source_row' => $row,
                    'message' => 'Penyebab atau tindakan kosong.',
                ];

                continue;
            }
            try {
                $eventDate = $this->cellValue($sheet, $columns['event_date'], $row);
                $startedAt = $this->dateTime(
                    $eventDate,
                    $this->cellValue($sheet, $columns['start_time'], $row),
                    $sheetName,
                    $row,
                );
                $handledDate = $this->cellValue($sheet, $columns['handled_date'], $row);
                $resolvedAt = $this->dateTime(
                    $handledDate,
                    $this->cellValue($sheet, $columns['end_time'], $row),
                    $sheetName,
                    $row,
                );
            } catch (RuntimeException $exception) {
                $result['skipped']++;
                $result['issues'][] = [
                    'sheet_name' => $sheetName,
                    'source_row' => $row,
                    'message' => $exception->getMessage(),
                ];

                continue;
            }
            if ($resolvedAt->lessThan($startedAt) && $this->dateString($handledDate) === $this->dateString($eventDate)) {
                $resolvedAt = $resolvedAt->addDay();
            }
            if ($resolvedAt->lessThan($startedAt)) {
                $result['skipped']++;
                $result['issues'][] = [
                    'sheet_name' => $sheetName,
                    'source_row' => $row,
                    'message' => 'Waktu penanganan sebelum waktu kejadian.',
                ];

                continue;
            }

            $values = [
                'asset_id' => $asset->id,
                'created_by' => null,
                'location' => $this->text($this->cellValue($sheet, $columns['location'], $row)) ?: ($asset->lokasi ?: $sheetName),
                'resort' => isset($columns['resort']) ? ($this->text($this->cellValue($sheet, $columns['resort'], $row)) ?: null) : null,
                'qc' => isset($columns['qc']) ? ($this->text($this->cellValue($sheet, $columns['qc'], $row)) ?: null) : null,
                'failure_event' => $event,
                'cause' => $cause,
                'action_taken' => $action,
                'started_at' => $startedAt,
                'resolved_at' => $resolvedAt,
                'downtime_minutes' => (int) $startedAt->diffInMinutes($resolvedAt),
                'spare_part_replaced' => isset($columns['spare_part_replaced'])
                    && $this->yesNo($this->cellValue($sheet, $columns['spare_part_replaced'], $row)),
                'spare_part_quantity' => null,
                'vandalism' => isset($columns['vandalism'])
                    && $this->yesNo($this->cellValue($sheet, $columns['vandalism'], $row)),
            ];
            $sourceKey = hash('sha256', implode('|', [$workbookHash, $sheetName, $row]));
            $failure = FailureLog::query()->firstOrNew(['source_key' => $sourceKey]);
            $failure->fill($values);
            if (! $failure->exists) {
                $failure->save();
                $result['created']++;
            } elseif ($failure->isDirty()) {
                $failure->save();
                $result['updated']++;
            } else {
                $result['unchanged']++;
            }
        }
    }

    private function resolveAsset(Worksheet $sheet, UnitKerja $unit, string $sheetName): Asset
    {
        $label = $this->text($sheet->getCell('B4')->getValue()) ?: $sheetName;
        $normalizedCandidates = array_unique([
            $this->failureComparable($label),
            $this->failureComparable($sheetName),
        ]);
        $matches = Asset::query()
            ->where('unit_kerja_id', $unit->id)
            ->with('assetSubsystem')
            ->get()
            ->filter(fn (Asset $asset): bool => in_array(
                $this->failureComparable($asset->assetSubsystem->name),
                $normalizedCandidates,
                true,
            ));

        if ($matches->count() !== 1) {
            throw new RuntimeException("Sheet {$sheetName} tidak dapat dipetakan tepat ke satu aset {$unit->code} ({$label}).");
        }

        return $matches->first();
    }

    private function failureComparable(string $value): string
    {
        return str_replace(
            [
                'penunjuk',
                'kontak rel mekanik',
                'catu daya sintel',
                'track ciruit',
                'wesel terlayan setempat elektrik (s90)',
                'wesel terlayan setempat elektrik (bsg9)',
                'wesel terlayan setempat elektrik (bsg 9)',
                'wesel terlayan setempat elektrik s90',
                'wesel terlayan setempat elektrik bsg9',
                'wesel setempat elektrik s90',
                'wesel setempat elektrik bsg9',
            ],
            [
                'petunjuk',
                'kontak deteksi',
                'catu daya sinyal',
                'track circuit',
                'pengaman wesel setempat elektrik',
                'pengaman wesel setempat elektrik',
                'pengaman wesel setempat elektrik',
                'pengaman wesel setempat elektrik',
                'pengaman wesel setempat elektrik',
                'pengaman wesel setempat elektrik',
                'pengaman wesel setempat elektrik',
            ],
            $this->categoryResolver->normalize($value),
        );
    }

    private function recalculateReliability(Asset $asset): void
    {
        $failures = FailureLog::query()->where('asset_id', $asset->id)->orderBy('started_at')->get();
        $periodStart = $asset->tanggal_pemasangan
            ? CarbonImmutable::instance($asset->tanggal_pemasangan->startOfDay())
            : ($failures->isEmpty()
                ? now()->startOfMonth()->toImmutable()
                : CarbonImmutable::instance($failures->first()->started_at));
        $periodEnd = now()->toImmutable();
        $metrics = $this->reliabilityCalculator->calculate(
            $asset->jumlah_unit,
            $periodStart,
            $periodEnd,
            $failures->map(fn (FailureLog $failure): array => [
                'started_at' => $failure->started_at,
                'resolved_at' => $failure->resolved_at,
            ]),
        );

        ReliabilitySummary::query()->updateOrCreate(
            ['asset_id' => $asset->id, 'period' => $periodEnd->startOfMonth()->toDateString()],
            [...$metrics, 'calculated_at' => now()],
        );
    }

    private function dateTime(mixed $date, mixed $time, string $sheet, int $row): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($this->dateString($date).' '.$this->timeString($time));
        } catch (\Throwable $exception) {
            throw new RuntimeException("Tanggal/waktu tidak valid pada sheet {$sheet}, row {$row}.", previous: $exception);
        }
    }

    private function dateString(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_numeric($value)) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $text = trim((string) $value);
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $text);
                if ($date !== false) {
                    return $date->toDateString();
                }
            } catch (\Throwable) {
                // Try the next accepted workbook format.
            }
        }

        return CarbonImmutable::parse($text)->toDateString();
    }

    private function timeString(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s');
        }
        if (is_numeric($value)) {
            return Date::excelToDateTimeObject((float) $value)->format('H:i:s');
        }

        return trim((string) $value);
    }

    private function cellValue(Worksheet $sheet, int $column, int $row): mixed
    {
        $cell = $sheet->getCell([$column, $row]);

        return $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
    }

    private function yesNo(mixed $value): bool
    {
        return in_array(mb_strtoupper($this->text($value)), ['Y', 'YES', 'YA'], true);
    }

    private function normalize(mixed $value): string
    {
        return mb_strtolower($this->text($value));
    }

    private function text(mixed $value): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) ($value ?? ''))) ?? '';
    }
}
