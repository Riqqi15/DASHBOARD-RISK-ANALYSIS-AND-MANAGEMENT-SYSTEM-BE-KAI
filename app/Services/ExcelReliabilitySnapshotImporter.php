<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReliabilityExcelSnapshot;
use App\Models\UnitKerja;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

final class ExcelReliabilitySnapshotImporter
{
    /** @var array<string, string> */
    private const SUMMARY_MAP = [
        'subsystem' => 'subsystem',
        'jumlah unit' => 'unit_count',
        'total operating hour' => 'operating_hours',
        'total uptime' => 'uptime_hours',
        'total downtime' => 'downtime_value',
        'jumlah failure' => 'failure_count',
        'mttf' => 'mttf_hours',
        'mtbf' => 'mtbf_hours',
        'failure rate' => 'failure_rate',
        'failure rate lambda' => 'failure_rate',
        'failure rate λ' => 'failure_rate',
        'reliability' => 'reliability',
        'availability' => 'availability',
        'jumlah penggantian sparepart' => 'spare_part_replacement_count',
        'jumlah tindak vandalisme' => 'vandalism_count',
    ];

    public function __construct(private readonly RamsWorkbookAssetResolver $assetResolver) {}

    /** @return array<string, mixed> */
    public function import(string $workbookPath, UnitKerja $unit, ?string $workbookName = null): array
    {
        $workbookHash = hash_file('sha256', $workbookPath);
        if ($workbookHash === false) {
            throw new RuntimeException('Fingerprint workbook gagal dibuat.');
        }

        $reader = IOFactory::createReaderForFile($workbookPath);
        $reader->setReadDataOnly(false);
        $reader->setReadEmptyCells(false);
        $spreadsheet = $reader->load($workbookPath);
        $baselineDate = $this->baselineDate($spreadsheet->getSheetByName('Dashboard'));
        $calculationDate = $this->calculationDate($spreadsheet->getSheetByName('Dashboard'), $baselineDate);
        $result = ['snapshots' => 0, 'skipped' => 0, 'issues' => []];

        try {
            DB::transaction(function () use ($spreadsheet, $unit, $workbookPath, $workbookName, $workbookHash, $baselineDate, $calculationDate, &$result): void {
                foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                    $sheetName = $sheet->getTitle();
                    if (mb_strtolower($sheetName) === 'dashboard') {
                        continue;
                    }

                    $headers = $this->summaryHeaders($sheet);
                    if ($headers === null) {
                        continue;
                    }

                    try {
                        $asset = $this->assetResolver->resolve($sheet, $unit, $sheetName);
                        $values = $this->summaryValues($sheet, $headers['row'], $headers['columns']);
                        $formulas = $this->summaryFormulas($sheet, $headers['row'], $headers['columns']);
                        $errors = $this->summaryErrors($sheet, $headers['row'], $headers['columns']);
                        ReliabilityExcelSnapshot::query()->updateOrCreate(
                            [
                                'asset_id' => $asset->id,
                                'workbook_hash' => $workbookHash,
                                'sheet_name' => $sheetName,
                            ],
                            [
                                'workbook_name' => $workbookName ?? basename($workbookPath),
                                'source_row' => $headers['row'] + 1,
                                'baseline_date' => $baselineDate?->toDateString(),
                                'calculation_date' => $calculationDate?->toDateString(),
                                'summary_values' => $values,
                                'summary_formulas' => $formulas,
                                'summary_errors' => $errors,
                                'formula_profile' => $this->formulaProfile($sheet, $formulas, $values, $errors),
                                'imported_at' => now(),
                            ],
                        );
                        $result['snapshots']++;
                    } catch (RuntimeException $exception) {
                        $result['skipped']++;
                        $result['issues'][] = [
                            'sheet_name' => $sheetName,
                            'source_row' => $headers['row'] + 1,
                            'source_column' => null,
                            'message' => $exception->getMessage(),
                        ];
                    }
                }
            }, 3);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        return $result;
    }

    /** @return array{row: int, columns: array<string, int>}|null */
    private function summaryHeaders(Worksheet $sheet): ?array
    {
        for ($row = 1; $row <= min(10, $sheet->getHighestDataRow()); $row++) {
            $columns = [];
            $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn($row));
            for ($column = 1; $column <= min(20, $highestColumn); $column++) {
                $header = $this->normalize($sheet->getCell([$column, $row])->getValue());
                if (isset(self::SUMMARY_MAP[$header])) {
                    $columns[self::SUMMARY_MAP[$header]] = $column;
                }
            }

            if (isset($columns['unit_count'], $columns['operating_hours'], $columns['failure_count'])) {
                return ['row' => $row, 'columns' => $columns];
            }
        }

        return null;
    }

    /** @param array<string, int> $columns
     * @return array<string, mixed>
     */
    private function summaryValues(Worksheet $sheet, int $headerRow, array $columns): array
    {
        $values = [];
        foreach ($columns as $key => $column) {
            $cell = $sheet->getCell([$column, $headerRow + 1]);
            $values[$key] = $this->cellValue($cell);
        }

        return $values;
    }

    /** @param array<string, int> $columns
     * @return array<string, string|null>
     */
    private function summaryFormulas(Worksheet $sheet, int $headerRow, array $columns): array
    {
        $formulas = [];
        foreach ($columns as $key => $column) {
            $valueCell = $sheet->getCell([$column, $headerRow + 1]);
            $fallbackCell = $sheet->getCell([$column, $headerRow + 2]);
            $formula = $valueCell->isFormula() ? $valueCell->getValue() : null;
            if (! is_string($formula) || ! str_starts_with($formula, '=')) {
                $fallback = $fallbackCell->getValue();
                $formula = is_string($fallback) && str_starts_with($fallback, '=') ? $fallback : null;
            }
            $formulas[$key] = $formula;
        }

        return $formulas;
    }

    /** @param array<string, int> $columns
     * @return array<string, string>
     */
    private function summaryErrors(Worksheet $sheet, int $headerRow, array $columns): array
    {
        $errors = [];
        foreach ($columns as $key => $column) {
            $cell = $sheet->getCell([$column, $headerRow + 1]);
            $raw = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
            if ($cell->getDataType() === DataType::TYPE_ERROR || $this->isExcelError($raw)) {
                $errors[$key] = (string) $raw;
            }
        }

        return $errors;
    }

    /** @param array<string, string|null> $formulas
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $errors
     * @return array<string, string|null>
     */
    private function formulaProfile(Worksheet $sheet, array $formulas, array $values, array $errors): array
    {
        $downtimeFormula = mb_strtolower((string) ($formulas['downtime_value'] ?? ''));
        $failureCountFormula = mb_strtolower((string) ($formulas['failure_count'] ?? ''));
        $sparePartFormula = mb_strtolower((string) ($formulas['spare_part_replacement_count'] ?? ''));
        $vandalismFormula = mb_strtolower((string) ($formulas['vandalism_count'] ?? ''));
        $intervalBaseline = $this->cellValue($sheet->getCell('P8'));

        return [
            'downtime_mode' => str_contains($downtimeFormula, 'downtime') && ! str_contains($downtimeFormula, 'konversi')
                ? 'excel_day_fraction'
                : 'minutes',
            'interval_baseline_date' => is_numeric($intervalBaseline)
                ? CarbonImmutable::instance(Date::excelToDateTimeObject((float) $intervalBaseline))->toDateString()
                : null,
            'empty_mttf_mode' => ! array_key_exists('mttf_hours', $errors)
                && (float) ($values['failure_count'] ?? 0) === 0.0
                && is_numeric($values['mttf_hours'] ?? null)
                ? 'zero'
                : 'null',
            'failure_count_mode' => str_contains($failureCountFormula, '#all') ? 'counta_all_minus_1' : 'counta',
            'spare_part_count_mode' => str_contains($sparePartFormula, 'counta') ? 'counta' : 'countif_ya',
            'vandalism_count_mode' => str_contains($vandalismFormula, 'counta') ? 'counta' : 'countif_ya',
        ];
    }

    private function baselineDate(?Worksheet $dashboard): ?CarbonImmutable
    {
        if (! $dashboard) {
            return null;
        }
        $value = $this->cellValue($dashboard->getCell('W4'));

        return is_numeric($value)
            ? CarbonImmutable::instance(Date::excelToDateTimeObject((float) $value))->startOfDay()
            : null;
    }

    private function calculationDate(?Worksheet $dashboard, ?CarbonImmutable $baselineDate): ?CarbonImmutable
    {
        if (! $dashboard || ! $baselineDate) {
            return null;
        }
        $days = $this->cellValue($dashboard->getCell('R4'));

        return is_numeric($days) ? $baselineDate->addDays((int) $days) : null;
    }

    private function cellValue(Cell $cell): mixed
    {
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

    private function normalize(mixed $value): string
    {
        $text = preg_replace('/\s+/u', ' ', trim((string) ($value ?? ''))) ?? '';
        $text = str_replace('λ', 'lambda', $text);

        return mb_strtolower($text);
    }
}
