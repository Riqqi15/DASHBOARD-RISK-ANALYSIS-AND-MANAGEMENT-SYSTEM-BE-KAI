<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\RiskRegister;
use App\Models\UnitKerja;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class RiskRegisterWorkbookImporter
{
    private const SHEET = 'LxC';

    public function __construct(
        private readonly AssetCategoryResolver $categoryResolver,
        private readonly RiskAssessmentCalculator $riskAssessmentCalculator,
    ) {}

    /** @return array{created: int, updated: int, unchanged: int, skipped: int, issues: list<array{sheet_name: string, source_row: int, message: string}>} */
    public function import(string $workbookPath, UnitKerja $unit): array
    {
        $reader = IOFactory::createReaderForFile($workbookPath);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([self::SHEET]);
        $reader->setReadEmptyCells(false);
        $reader->setIgnoreRowsWithNoCells(true);
        $spreadsheet = $reader->load($workbookPath);

        try {
            $sheet = $spreadsheet->getSheetByName(self::SHEET);
            if (! $sheet) {
                throw new RuntimeException('Sheet LxC tidak ditemukan.');
            }

            $workbookHash = hash_file('sha256', $workbookPath);
            if ($workbookHash === false) {
                throw new RuntimeException("Fingerprint workbook gagal dibuat: {$workbookPath}");
            }

            return $this->importRows($sheet, $unit, $workbookHash, basename($workbookPath));
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    /** @return array{created: int, updated: int, unchanged: int, skipped: int, issues: list<array{sheet_name: string, source_row: int, message: string}>} */
    private function importRows(Worksheet $sheet, UnitKerja $unit, string $workbookHash, string $workbookName): array
    {
        $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'issues' => []];

        for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
            $event = $this->text($sheet->getCell('D'.$row)->getValue());
            if ($event === '') {
                continue;
            }

            $cause = $this->text($sheet->getCell('E'.$row)->getValue());
            $assetLabel = $this->text($sheet->getCell('C'.$row)->getValue())
                ?: $this->text($sheet->getCell('B'.$row)->getValue());
            $likelihood = filter_var($sheet->getCell('H'.$row)->getValue(), FILTER_VALIDATE_INT);
            $consequence = filter_var($sheet->getCell('I'.$row)->getValue(), FILTER_VALIDATE_INT);

            if ($cause === '' || $assetLabel === '' || $likelihood === false || $consequence === false) {
                $result['skipped']++;
                $result['issues'][] = [
                    'sheet_name' => self::SHEET,
                    'source_row' => $row,
                    'message' => 'Data wajib risk register tidak lengkap.',
                ];

                continue;
            }

            $this->riskAssessmentCalculator->calculate($likelihood, $consequence);
            $asset = $this->resolveAsset($unit, $assetLabel, $row);
            $sourceKey = hash('sha256', implode('|', [$workbookHash, self::SHEET, $row]));
            $register = RiskRegister::query()->firstOrNew(['source_key' => $sourceKey]);
            $register->fill([
                'asset_id' => $asset->id,
                'workbook_hash' => $workbookHash,
                'workbook_name' => $workbookName,
                'sheet_name' => self::SHEET,
                'source_row' => $row,
                'part_number' => null,
                'sub' => $this->text($sheet->getCell('C'.$row)->getValue()) ?: null,
                'risk_event' => $event,
                'risk_cause' => $cause,
                'impact' => $this->text($sheet->getCell('F'.$row)->getValue()) ?: null,
                'part_name' => $this->text($sheet->getCell('G'.$row)->getValue()) ?: null,
                'recommendation' => null,
                'likelihood' => $likelihood,
                'consequence' => $consequence,
                'status' => 'open',
            ]);

            if (! $register->exists) {
                $register->save();
                $result['created']++;
            } elseif ($register->isDirty()) {
                $register->save();
                $result['updated']++;
            } else {
                $result['unchanged']++;
            }
        }

        return $result;
    }

    private function resolveAsset(UnitKerja $unit, string $label, int $row): Asset
    {
        $comparable = $this->comparable($label);
        $matches = Asset::query()
            ->where('unit_kerja_id', $unit->id)
            ->with('assetSubsystem')
            ->get()
            ->filter(fn (Asset $asset): bool => $this->comparable($asset->assetSubsystem->name) === $comparable);

        if ($matches->count() !== 1) {
            throw new RuntimeException("LxC row {$row} tidak dapat dipetakan tepat ke satu aset {$unit->code} ({$label}).");
        }

        return $matches->first();
    }

    private function comparable(string $value): string
    {
        return str_replace(
            ['track ciruit', 'catu daya sintel'],
            ['track circuit', 'catu daya sinyal'],
            $this->categoryResolver->normalize($value),
        );
    }

    private function text(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }
}
