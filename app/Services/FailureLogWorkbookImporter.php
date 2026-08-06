<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\FailureLog;
use App\Models\UnitKerja;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class FailureLogWorkbookImporter
{
    private const IMPORT_VERSION = 'failure-log-import-v1';

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
        'tanggal jam kejadian' => 'started_at',
        'tanggal jam penanganan' => 'resolved_at',
    ];

    public function __construct(private readonly RamsWorkbookAssetResolver $assetResolver) {}

    /** @return array<string, mixed> */
    public function import(string $workbookPath, UnitKerja $unit, ?string $workbookHash = null, ?string $workbookName = null): array
    {
        $workbookHash ??= hash_file('sha256', $workbookPath) ?: null;
        $workbookName ??= basename($workbookPath);
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

        DB::transaction(function () use (
            $reader,
            $sheetNames,
            $workbookPath,
            $unit,
            $workbookHash,
            $workbookName,
            &$result,
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
                    $result['sheets']++;
                    try {
                        $asset = $this->resolveAsset($sheet, $unit, $sheetName);
                        if ($asset === null) {
                            $result['skipped']++;
                            $result['issues'][] = [
                                'sheet_name' => $sheetName,
                                'source_row' => null,
                                'source_column' => null,
                                'message' => "Aset (AssetGroup/System/Subsystem) untuk sheet {$sheetName} tidak ditemukan atau ambigu.",
                            ];
                            continue;
                        }

                        $this->importRows(
                            $sheet,
                            $headers['row'],
                            $headers['columns'],
                            $asset,
                            $sheetName,
                            $workbookHash,
                            $workbookName,
                            $result,
                        );
                    } catch (RuntimeException $exception) {
                        $result['skipped']++;
                        $result['issues'][] = [
                            'sheet_name' => $sheetName,
                            'source_row' => null,
                            'source_column' => null,
                            'message' => $exception->getMessage(),
                        ];
                    }
                } finally {
                    $spreadsheet->disconnectWorksheets();
                    unset($spreadsheet);
                }
            }

        }, 3);

        return $result;
    }

    /** @return array{row: int, columns: array<string, int>}|null */
    private function failureHeaders(Worksheet $sheet): ?array
    {
        for ($row = 1; $row <= min(30, $sheet->getHighestDataRow()); $row++) {
            $columns = [];
            $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn($row));
            for ($column = 1; $column <= min(30, $highestColumn); $column++) {
                $header = $this->normalize($this->cellValue($sheet, $column, $row));
                if (isset(self::HEADER_MAP[$header])) {
                    $columns[self::HEADER_MAP[$header]] = $column;
                }
            }
            $hasStartedAt = isset($columns['started_at'])
                || isset($columns['event_date'], $columns['start_time']);
            if (isset($columns['location'], $columns['failure_event']) && $hasStartedAt) {
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
        string $sheetName,
        ?string $workbookHash,
        string $workbookName,
        array &$result,
    ): void {
        foreach (['cause', 'action_taken'] as $required) {
            if (! isset($columns[$required])) {
                throw new RuntimeException("Kolom {$required} tidak ditemukan pada sheet {$sheetName}.");
            }
        }
        if (! isset($columns['resolved_at']) && ! isset($columns['handled_date'], $columns['end_time'])) {
            throw new RuntimeException("Kolom tanggal dan waktu penanganan tidak ditemukan pada sheet {$sheetName}.");
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
                    'source_column' => $cause === '' ? 'Penyebab' : 'Tindakan',
                    'message' => 'Penyebab atau tindakan kosong.',
                ];

                continue;
            }
            try {
                $startedAt = $this->rowDateTime(
                    $sheet,
                    $columns,
                    $row,
                    'started_at',
                    'event_date',
                    'start_time',
                    $sheetName,
                );
                $resolvedAt = $this->rowDateTime(
                    $sheet,
                    $columns,
                    $row,
                    'resolved_at',
                    'handled_date',
                    'end_time',
                    $sheetName,
                );
            } catch (RuntimeException $exception) {
                $result['skipped']++;
                $result['issues'][] = [
                    'sheet_name' => $sheetName,
                    'source_row' => $row,
                    'source_column' => 'Tanggal/Waktu',
                    'message' => $exception->getMessage(),
                ];

                continue;
            }
            if ($resolvedAt->lessThan($startedAt) && $resolvedAt->isSameDay($startedAt)) {
                $resolvedAt = $resolvedAt->addDay();
            }
            if ($resolvedAt->lessThan($startedAt)) {
                $result['issues'][] = [
                    'sheet_name' => $sheetName,
                    'source_row' => $row,
                    'source_column' => 'Tanggal Jam Penanganan',
                    'message' => 'Tanggal penanganan sebelum tanggal kejadian; tanggal kejadian dan waktu selesai digunakan mengikuti formula Excel.',
                ];
                $resolvedAt = $this->resolvedAtFromExcelTimeFormula($sheet, $columns, $row, $startedAt);
            }

            $downtimeMinutes = $this->excelDowntimeMinutes($sheet, $columns, $row, $startedAt, $resolvedAt);

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
                'downtime_minutes' => $downtimeMinutes,
                'spare_part_replaced' => isset($columns['spare_part_replaced'])
                    && $this->yesNo($this->cellValue($sheet, $columns['spare_part_replaced'], $row)),
                'spare_part_marker' => isset($columns['spare_part_replaced'])
                    ? ($this->text($this->cellValue($sheet, $columns['spare_part_replaced'], $row)) ?: null)
                    : null,
                'spare_part_quantity' => null,
                'vandalism' => isset($columns['vandalism'])
                    && $this->yesNo($this->cellValue($sheet, $columns['vandalism'], $row)),
                'vandalism_marker' => isset($columns['vandalism'])
                    ? ($this->text($this->cellValue($sheet, $columns['vandalism'], $row)) ?: null)
                    : null,
                'workbook_hash' => $workbookHash,
                'workbook_name' => $workbookName,
                'sheet_name' => $sheetName,
                'source_row' => $row,
            ];
            $sourceKey = hash('sha256', implode('|', [
                self::IMPORT_VERSION,
                (string) $asset->unit_kerja_id,
                $this->assetResolver->comparable($sheetName),
                (string) $row,
            ]));
            $failure = FailureLog::query()->where('source_key', $sourceKey)->first()
                ?? $this->identicalManualFailure($values)
                ?? new FailureLog;
            $failure->source_key = $sourceKey;
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

    private function resolveAsset(Worksheet $sheet, UnitKerja $unit, string $sheetName): ?Asset
    {
        return $this->assetResolver->resolve($sheet, $unit, $sheetName);
    }

    /** @param array<string, mixed> $values */
    private function identicalManualFailure(array $values): ?FailureLog
    {
        return FailureLog::query()
            ->whereNull('source_key')
            ->where('asset_id', $values['asset_id'])
            ->where('location', $values['location'])
            ->where('resort', $values['resort'])
            ->where('qc', $values['qc'])
            ->where('failure_event', $values['failure_event'])
            ->where('cause', $values['cause'])
            ->where('action_taken', $values['action_taken'])
            ->oldest('id')
            ->first();
    }

    /** @param array<string, int> $columns */
    private function rowDateTime(
        Worksheet $sheet,
        array $columns,
        int $row,
        string $combinedKey,
        string $dateKey,
        string $timeKey,
        string $sheetName,
    ): CarbonImmutable {
        if (isset($columns[$combinedKey])) {
            $combined = $this->cellValue($sheet, $columns[$combinedKey], $row);
            if ($combined !== null && $this->text($combined) !== '') {
                return $this->dateTimeValue($combined, $sheetName, $row);
            }
        }

        if (! isset($columns[$dateKey], $columns[$timeKey])) {
            throw new RuntimeException("Tanggal/waktu tidak lengkap pada sheet {$sheetName}, row {$row}.");
        }

        return $this->dateTime(
            $this->cellValue($sheet, $columns[$dateKey], $row),
            $this->cellValue($sheet, $columns[$timeKey], $row),
            $sheetName,
            $row,
        );
    }

    private function dateTimeValue(mixed $value, string $sheet, int $row): CarbonImmutable
    {
        try {
            if ($value instanceof DateTimeInterface) {
                return CarbonImmutable::instance($value);
            }
            if (is_numeric($value)) {
                return CarbonImmutable::instance(Date::excelToDateTimeObject((float) $value));
            }

            $text = $this->text($value);
            foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd-m-Y H:i:s', 'd-m-Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
                try {
                    $date = CarbonImmutable::createFromFormat('!'.$format, $text);
                    if ($date !== false) {
                        return $date;
                    }
                } catch (\Throwable) {
                    // Try the next accepted workbook format.
                }
            }

            return CarbonImmutable::parse($text);
        } catch (\Throwable $exception) {
            throw new RuntimeException("Tanggal/waktu tidak valid pada sheet {$sheet}, row {$row}.", previous: $exception);
        }
    }

    /** @param array<string, int> $columns */
    private function resolvedAtFromExcelTimeFormula(
        Worksheet $sheet,
        array $columns,
        int $row,
        CarbonImmutable $startedAt,
    ): CarbonImmutable {
        if (! isset($columns['end_time'])) {
            return $startedAt;
        }

        $endTime = $this->timeString($this->cellValue($sheet, $columns['end_time'], $row));
        $resolvedAt = CarbonImmutable::parse($startedAt->toDateString().' '.$endTime);

        return $resolvedAt->lessThan($startedAt) ? $resolvedAt->addDay() : $resolvedAt;
    }

    /** @param array<string, int> $columns */
    private function excelDowntimeMinutes(
        Worksheet $sheet,
        array $columns,
        int $row,
        CarbonImmutable $startedAt,
        CarbonImmutable $resolvedAt,
    ): int {
        if (! isset($columns['start_time'], $columns['end_time'])) {
            return (int) round($startedAt->diffInSeconds($resolvedAt) / 60);
        }

        $start = $this->minutesSinceMidnight($this->cellValue($sheet, $columns['start_time'], $row));
        $end = $this->minutesSinceMidnight($this->cellValue($sheet, $columns['end_time'], $row));
        $minutes = $end - $start;

        return (int) round($minutes < 0 ? $minutes + 1440 : $minutes);
    }

    private function minutesSinceMidnight(mixed $value): float
    {
        if (is_numeric($value)) {
            $fraction = (float) $value - floor((float) $value);

            return $fraction * 1440;
        }

        $time = CarbonImmutable::parse($this->timeString($value));

        return ($time->hour * 60) + $time->minute + ($time->second / 60);
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
        if ($value === null || trim((string) $value) === '') {
            throw new RuntimeException('Tanggal kejadian/penanganan kosong atau berisi error Excel.');
        }

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
        if ($value === null || trim((string) $value) === '') {
            throw new RuntimeException('Waktu mulai/selesai kosong atau berisi error Excel.');
        }

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
        if ($cell->getDataType() === DataType::TYPE_ERROR) {
            return null;
        }

        $value = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();

        return $this->isExcelError($value) ? null : $value;
    }

    private function isExcelError(mixed $value): bool
    {
        return is_string($value) && in_array(mb_strtoupper(trim($value)), [
            '#NULL!', '#DIV/0!', '#VALUE!', '#REF!', '#NAME?', '#NUM!', '#N/A', '#GETTING_DATA', '#SPILL!',
        ], true);
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
