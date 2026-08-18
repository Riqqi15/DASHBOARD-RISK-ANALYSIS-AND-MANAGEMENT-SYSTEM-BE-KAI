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

    private const IMPORT_VERSION = 'risk-register-import-v2';

    public function __construct(
        private readonly AssetCategoryResolver $categoryResolver,
        private readonly RiskAssessmentCalculator $riskAssessmentCalculator,
    ) {}

    public function supports(string $workbookPath): bool
    {
        if (! is_file($workbookPath)) {
            return false;
        }

        $reader = IOFactory::createReaderForFile($workbookPath);

        return in_array(self::SHEET, $reader->listWorksheetNames($workbookPath), true);
    }

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
        $result = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'duplicates_skipped' => 0,
            'duplicate_locations' => [],
            'skipped' => 0,
            'issues' => [],
        ];

        $conflictingRows = [];
        foreach ($this->conflictingSourceRows($sheet) as $rows) {
            $locations = array_map(fn (int $row): string => $this->sourceLocation($row), $rows);
            foreach ($rows as $row) {
                $conflictingRows[$row] = true;
                $result['skipped']++;
                $result['duplicates_skipped']++;
                $result['duplicate_locations'][] = $this->sourceLocation($row);
                $result['issues'][] = [
                    'sheet_name' => self::SHEET,
                    'source_row' => $row,
                    'source_column' => 'Baris Utuh',
                    'severity' => 'error',
                    'message' => 'Konflik: '.implode(' dan ', $locations)
                        .' memiliki identitas risk register yang sama; seluruh baris konflik dilewati.',
                ];
            }
        }

        for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
            $event = $this->text($sheet->getCell('D'.$row)->getValue());
            if ($event === '') {
                continue;
            }

            if (isset($conflictingRows[$row])) {
                continue;
            }

            $cause = $this->importText($sheet->getCell('E'.$row)->getValue());
            $assetLabel = $this->text($sheet->getCell('C'.$row)->getValue())
                ?: $this->text($sheet->getCell('B'.$row)->getValue());
            $likelihood = filter_var($sheet->getCell('H'.$row)->getValue(), FILTER_VALIDATE_INT);
            $consequence = filter_var($sheet->getCell('I'.$row)->getValue(), FILTER_VALIDATE_INT);

            if ($assetLabel === '' || $likelihood === false || $consequence === false) {
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
            $sourceKey = hash('sha256', implode('|', [
                self::IMPORT_VERSION,
                (string) $unit->id,
                mb_strtolower(self::SHEET),
                (string) $row,
            ]));
            $register = RiskRegister::query()->where('source_key', $sourceKey)->first()
                ?? RiskRegister::query()
                    ->where('asset_id', $asset->id)
                    ->where('sheet_name', self::SHEET)
                    ->where('source_row', $row)
                    ->latest('updated_at')
                    ->first()
                ?? new RiskRegister;
            $values = [
                'asset_id' => $asset->id,
                'sheet_name' => self::SHEET,
                'source_row' => $row,
                'part_number' => '-',
                'sub' => $this->importText($sheet->getCell('C'.$row)->getValue()),
                'risk_event' => $event,
                'risk_cause' => $cause,
                'impact' => $this->importText($sheet->getCell('F'.$row)->getValue()),
                'part_name' => $this->importText($sheet->getCell('G'.$row)->getValue()),
                'recommendation' => '-',
                'likelihood' => $likelihood,
                'consequence' => $consequence,
                'status' => 'open',
            ];

            if (! $register->exists) {
                $register->source_key = $sourceKey;
                $register->fill([
                    ...$values,
                    'workbook_hash' => $workbookHash,
                    'workbook_name' => $workbookName,
                ]);
                $register->save();
                $result['created']++;
            } else {
                $register->source_key = $sourceKey;
                $register->fill($values);
            }

            if ($register->exists && $register->isDirty()) {
                $register->fill([
                    'workbook_hash' => $workbookHash,
                    'workbook_name' => $workbookName,
                ]);
                $register->save();
                $result['updated']++;
            } elseif ($register->exists && ! $register->wasRecentlyCreated) {
                $result['unchanged']++;
                $result['duplicates_skipped']++;
                $result['duplicate_locations'][] = $this->sourceLocation($row);
                $result['issues'][] = [
                    'sheet_name' => self::SHEET,
                    'source_row' => $row,
                    'source_column' => 'Baris Utuh',
                    'severity' => 'info',
                    'message' => 'Data duplikat atau sudah ada di sistem dan tidak ada perubahan (dilewati).',
                ];
            }
        }

        $result['duplicate_locations'] = array_values(array_unique($result['duplicate_locations']));

        return $result;
    }

    /** @return list<list<int>> */
    private function conflictingSourceRows(Worksheet $sheet): array
    {
        $rowsByIdentity = [];

        for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
            $event = $this->text($sheet->getCell('D'.$row)->getValue());
            if ($event === '') {
                continue;
            }

            $assetLabel = $this->text($sheet->getCell('C'.$row)->getValue())
                ?: $this->text($sheet->getCell('B'.$row)->getValue());
            $identity = hash('sha256', implode('|', [
                $this->comparable($assetLabel),
                $this->comparable($event),
            ]));
            $rowsByIdentity[$identity][] = $row;
        }

        return array_values(array_filter(
            $rowsByIdentity,
            fn (array $rows): bool => count($rows) > 1,
        ));
    }

    private function importText(mixed $value): string
    {
        $text = $this->text($value);

        return $text === '' ? '-' : $text;
    }

    private function sourceLocation(int $row): string
    {
        return self::SHEET.'!'.$row;
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
