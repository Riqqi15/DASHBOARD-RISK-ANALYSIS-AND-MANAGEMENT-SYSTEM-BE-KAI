<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Models\RiskMatrix;
use App\Models\UnitKerja;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class RiskMatrixWorkbookImporter
{
    public const FORMULA_VERSION = 'kai-risk-matrix-v1.0.0';

    private const SHEET = 'Risk Matrix';

    public function __construct(
        private readonly AssetCategoryResolver $categoryResolver,
        private readonly RiskAssessmentCalculator $calculator,
    ) {}

    public function supports(string $workbookPath): bool
    {
        if (! is_file($workbookPath)) {
            return false;
        }

        $reader = IOFactory::createReaderForFile($workbookPath);

        return in_array(self::SHEET, $reader->listWorksheetNames($workbookPath), true);
    }

    /** @return array{created: int, updated: int, skipped: int, colors_updated: int, issues: array<int, array<string, mixed>>} */
    public function import(string $workbookPath, UnitKerja $unit): array
    {
        $reader = IOFactory::createReaderForFile($workbookPath);
        $reader->setReadDataOnly(false);
        $reader->setLoadSheetsOnly([self::SHEET]);
        $spreadsheet = $reader->load($workbookPath);

        try {
            $sheet = $spreadsheet->getSheetByName(self::SHEET);
            if (! $sheet) {
                throw new RuntimeException('Sheet "'.self::SHEET.'" tidak ditemukan.');
            }
            $headers = array_map(
                fn (int $column): string => mb_strtolower(trim((string) $sheet->getCell([$column, 1])->getValue())),
                range(1, 8),
            );
            if ($headers[0] !== 'aset prasarana sintel' || $headers[1] !== 'system'
                || $headers[2] !== 'subsystem' || $headers[3] !== 'likelihood'
                || $headers[4] !== 'consequences') {
                throw new RuntimeException('Header sheet Risk Matrix tidak sesuai format KAI.');
            }

            $hash = hash_file('sha256', $workbookPath);
            if ($hash === false) {
                throw new RuntimeException('Fingerprint workbook gagal dibuat.');
            }

            return $this->importRows($sheet, $unit, basename($workbookPath), $hash);
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    /** @return array{created: int, updated: int, skipped: int, colors_updated: int, issues: array<int, array<string, mixed>>} */
    private function importRows(Worksheet $sheet, UnitKerja $unit, string $workbookName, string $hash): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'colors_updated' => 0, 'issues' => []];
        $currentGroup = '';
        $currentSystem = '';

        for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
            $groupValue = $this->text($this->cachedValue($sheet, 1, $row));
            $systemValue = $this->text($this->cachedValue($sheet, 2, $row));
            $subsystemValue = $this->text($this->cachedValue($sheet, 3, $row));
            $currentGroup = $groupValue !== '' ? $groupValue : $currentGroup;
            $currentSystem = $systemValue !== '' ? $systemValue : $currentSystem;

            if ($currentGroup === '' || $currentSystem === '' || $subsystemValue === '') {
                $result['skipped']++;

                continue;
            }

            try {
                $categories = $this->resolveCategories(
                    $currentGroup,
                    $currentSystem,
                    $subsystemValue,
                    $workbookName,
                    $row,
                );
                if ($groupValue !== '') {
                    $result['colors_updated'] += $this->applyExcelColor($categories['group'], $this->cellColor($sheet, 1, $row));
                }
                if ($systemValue !== '') {
                    $result['colors_updated'] += $this->applyExcelColor($categories['system'], $this->cellColor($sheet, 2, $row));
                }
                $result['colors_updated'] += $this->applyExcelColor($categories['subsystem'], $this->cellColor($sheet, 3, $row));

                $assets = Asset::query()
                    ->where('unit_kerja_id', $unit->id)
                    ->where('asset_subsystem_id', $categories['subsystem']->id)
                    ->get();
                if ($assets->isEmpty()) {
                    $result['issues'][] = $this->issue($row, "Aset {$subsystemValue} belum tersedia untuk {$unit->code}.");
                    $result['skipped']++;

                    continue;
                }

                $likelihood = $this->scaleValue($this->cachedValue($sheet, 4, $row), 'Likelihood', $row);
                $consequence = $this->scaleValue($this->cachedValue($sheet, 5, $row), 'Consequences', $row);
                $backend = $this->calculator->calculate($likelihood, $consequence);
                $excelValues = [
                    'rating' => $this->cachedValue($sheet, 6, $row),
                    'level' => $this->cachedValue($sheet, 8, $row),
                ];
                $excelFormulas = array_filter([
                    'rating' => $this->formula($sheet, 6, $row),
                    'level' => $this->formula($sheet, 8, $row),
                ]);
                $differences = [];
                foreach (['rating', 'level'] as $key) {
                    if (! $this->matches($excelValues[$key], $backend[$key])) {
                        $differences[$key] = ['excel' => $excelValues[$key], 'backend' => $backend[$key]];
                    }
                }

                foreach ($assets as $asset) {
                    $matrix = RiskMatrix::query()->firstOrNew(['asset_id' => $asset->id]);
                    $matrix->fill([
                        'source_key' => hash('sha256', implode('|', [$hash, self::SHEET, $row, $asset->id])),
                        'workbook_hash' => $hash,
                        'workbook_name' => $workbookName,
                        'sheet_name' => self::SHEET,
                        'source_row' => $row,
                        'likelihood' => $likelihood,
                        'consequence' => $consequence,
                        'excel_values' => $excelValues,
                        'excel_formulas' => $excelFormulas,
                        'parity_status' => $differences === [] ? 'matched' : 'corrected',
                        'parity_differences' => $differences === [] ? null : $differences,
                        'formula_version' => self::FORMULA_VERSION,
                        'assessed_at' => now(),
                    ])->save();
                    $result[$matrix->wasRecentlyCreated ? 'created' : 'updated']++;
                }
            } catch (RuntimeException $exception) {
                $result['issues'][] = $this->issue($row, $exception->getMessage());
                $result['skipped']++;
            }
        }

        return $result;
    }

    private function applyExcelColor(AssetGroup|AssetSystem|AssetSubsystem $category, ?string $color): int
    {
        if ($color === null || $category->dashboard_color_source === 'manual') {
            return 0;
        }
        if ($category->dashboard_color === $color && $category->dashboard_color_source === 'excel') {
            return 0;
        }

        $category->update(['dashboard_color' => $color, 'dashboard_color_source' => 'excel']);

        return 1;
    }

    /** @return array{group: AssetGroup, system: AssetSystem, subsystem: AssetSubsystem} */
    private function resolveCategories(
        string $groupName,
        string $systemName,
        string $subsystemName,
        string $workbookName,
        int $row,
    ): array {
        $group = AssetGroup::query()
            ->with('systems.subsystems')
            ->get()
            ->first(fn (AssetGroup $candidate): bool => $this->canonical($candidate->name) === $this->canonical($groupName));
        $system = $group?->systems->first(
            fn (AssetSystem $candidate): bool => $this->canonical($candidate->name) === $this->canonical($systemName),
        );
        $subsystem = $system?->subsystems->first(
            fn (AssetSubsystem $candidate): bool => $this->canonical($candidate->name) === $this->canonical($subsystemName),
        );
        if ($group && $system && $subsystem) {
            return compact('group', 'system', 'subsystem');
        }

        return $this->categoryResolver->resolve(
            $groupName,
            $systemName,
            $subsystemName,
            $workbookName,
            self::SHEET,
            $row,
        );
    }

    private function canonical(string $value): string
    {
        $withoutNumber = preg_replace('/^\s*\d+\.\s*/u', '', $value) ?? $value;

        return mb_strtolower($this->text($withoutNumber));
    }

    private function cellColor(Worksheet $sheet, int $column, int $row): ?string
    {
        $fill = $sheet->getStyle([$column, $row])->getFill();
        if ($fill->getFillType() !== Fill::FILL_SOLID) {
            return null;
        }
        $argb = $fill->getStartColor()->getARGB();
        if (! is_string($argb) || preg_match('/^[0-9A-F]{8}$/i', $argb) !== 1) {
            return null;
        }

        return '#'.mb_strtoupper(substr($argb, -6));
    }

    private function cachedValue(Worksheet $sheet, int $column, int $row): mixed
    {
        $cell = $sheet->getCell([$column, $row]);

        return $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
    }

    private function formula(Worksheet $sheet, int $column, int $row): ?string
    {
        $cell = $sheet->getCell([$column, $row]);

        return $cell->isFormula() ? (string) $cell->getValue() : null;
    }

    private function scaleValue(mixed $value, string $label, int $row): int
    {
        if (! is_numeric($value) || (int) $value != (float) $value || (int) $value < 1 || (int) $value > 4) {
            throw new RuntimeException("{$label} harus bilangan 1-4 pada baris {$row}.");
        }

        return (int) $value;
    }

    private function matches(mixed $excel, mixed $backend): bool
    {
        return is_numeric($excel) && is_numeric($backend)
            ? abs((float) $excel - (float) $backend) < 0.0001
            : mb_strtolower(trim((string) $excel)) === mb_strtolower(trim((string) $backend));
    }

    private function text(mixed $value): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) ($value ?? ''))) ?? trim((string) ($value ?? ''));
    }

    /** @return array<string, mixed> */
    private function issue(int $row, string $message): array
    {
        return ['sheet_name' => self::SHEET, 'source_row' => $row, 'severity' => 'warning', 'message' => $message];
    }
}
