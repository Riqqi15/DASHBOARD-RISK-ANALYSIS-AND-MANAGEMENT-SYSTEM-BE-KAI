<?php

namespace App\Services;

use App\Models\AssetCategorySourceAlias;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Models\SparePart;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class SparePartWorkbookImporter
{
    private const SHEET = 'Reorder Stock';

    /** @var list<string> */
    private const HEADERS = [
        'System',
        'Sub-System',
        'Equipment',
        'Detail Equipment',
        'Max yearly Failure',
        'Average Yearly Failure',
        'Max Lead Time (Month)',
        'Average Lead Time (Month)',
        'Safety Stock',
        'Lead Time Demand',
        'Reorder Point',
        'Severity Equipment',
    ];

    public function __construct(private readonly AssetCategoryResolver $categoryResolver) {}

    /**
     * @return array{created: int, updated: int, unchanged: int, skipped: int}
     */
    public function import(string $workbookPath): array
    {
        if (! is_file($workbookPath)) {
            throw new RuntimeException("File workbook tidak ditemukan: {$workbookPath}");
        }

        $workbookName = basename($workbookPath);
        $reader = IOFactory::createReaderForFile($workbookPath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($workbookPath);

        try {
            $sheet = $spreadsheet->getSheetByName(self::SHEET);
            if (! $sheet) {
                throw new RuntimeException(
                    "Workbook {$workbookName}, sheet ".self::SHEET.', row 1, header: sheet tidak ditemukan.',
                );
            }

            $this->assertHeaders($sheet, $workbookName);

            return DB::transaction(fn (): array => $this->importRows($sheet, $workbookName));
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    private function assertHeaders(Worksheet $sheet, string $workbookName): void
    {
        foreach (self::HEADERS as $offset => $expected) {
            $actual = $this->text($sheet->getCell([$offset + 1, 1])->getValue());
            if ($this->normalize($actual) !== $this->normalize($expected)) {
                $actualLabel = $actual === '' ? '(kosong)' : $actual;

                throw new RuntimeException(
                    "Workbook {$workbookName}, sheet ".self::SHEET
                        .", row 1, header {$expected}: ditemukan {$actualLabel}.",
                );
            }
        }
    }

    /**
     * @return array{created: int, updated: int, unchanged: int, skipped: int}
     */
    private function importRows(Worksheet $sheet, string $workbookName): array
    {
        $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0];
        $currentGroup = '';
        $currentSystem = '';
        $currentEquipment = '';
        /** @var array<string, int> $sourceRows */
        $sourceRows = [];

        for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
            $group = $this->cellText($sheet, 1, $row);
            $system = $this->cellText($sheet, 2, $row);
            $equipment = $this->cellText($sheet, 3, $row);
            $detailEquipment = $this->cellText($sheet, 4, $row);

            $currentGroup = $group !== '' ? $group : $currentGroup;
            $currentSystem = $system !== '' ? $system : $currentSystem;
            $currentEquipment = $equipment !== '' ? $equipment : $currentEquipment;

            if ($detailEquipment === '') {
                continue;
            }

            foreach ([
                'System' => $currentGroup,
                'Sub-System' => $currentSystem,
                'Equipment' => $currentEquipment,
            ] as $header => $value) {
                if ($value === '') {
                    throw $this->rowError($workbookName, $row, $header, 'nilai hierarchy kosong.');
                }
            }

            $sourceKey = $this->sourceKey($currentGroup, $currentSystem, $currentEquipment, $detailEquipment);
            if (isset($sourceRows[$sourceKey])) {
                throw $this->rowError(
                    $workbookName,
                    $row,
                    'Detail Equipment',
                    "duplikat source key dengan row {$sourceRows[$sourceKey]} dan row {$row}.",
                );
            }
            $sourceRows[$sourceKey] = $row;

            $subsystem = $this->resolveSubsystem(
                $currentGroup,
                $currentSystem,
                $currentEquipment,
                $workbookName,
                $row,
            );
            $sourceValues = [
                'asset_subsystem_id' => $subsystem->id,
                'equipment' => $currentEquipment,
                'detail_equipment' => $detailEquipment,
                'max_yearly_failure' => $this->nullableDecimal($sheet, 5, $row, $workbookName, self::HEADERS[4]),
                'average_yearly_failure' => $this->nullableDecimal($sheet, 6, $row, $workbookName, self::HEADERS[5]),
                'max_lead_time_months' => $this->nullableDecimal($sheet, 7, $row, $workbookName, self::HEADERS[6]),
                'average_lead_time_months' => $this->nullableDecimal($sheet, 8, $row, $workbookName, self::HEADERS[7]),
                'safety_stock' => $this->nullableQuantity($sheet, 9, $row, $workbookName, self::HEADERS[8]),
                'lead_time_demand' => $this->nullableQuantity($sheet, 10, $row, $workbookName, self::HEADERS[9]),
                'reorder_point' => $this->nullableQuantity($sheet, 11, $row, $workbookName, self::HEADERS[10]),
                'severity' => $this->nullableText($sheet->getCell([12, $row])->getCalculatedValue()),
            ];

            /** @var SparePart|null $part */
            $part = SparePart::withTrashed()
                ->where('source_key', $sourceKey)
                ->lockForUpdate()
                ->first();

            if ($part?->trashed()) {
                $result['skipped']++;

                continue;
            }

            if ($part) {
                $part->fill($sourceValues);
                if ($part->isDirty()) {
                    $part->save();
                    $result['updated']++;
                } else {
                    $result['unchanged']++;
                }

                continue;
            }

            SparePart::query()->create([
                ...$sourceValues,
                'source_key' => $sourceKey,
                'code' => 'SP-'.strtoupper(substr($sourceKey, 0, 10)),
                'unit_of_measure' => 'unit',
                'is_active' => true,
            ]);
            $result['created']++;
        }

        return $result;
    }

    private function resolveSubsystem(
        string $groupName,
        string $systemName,
        string $subsystemName,
        string $workbookName,
        int $row,
    ): AssetSubsystem {
        $normalizedPath = implode('|', array_map($this->categoryResolver->normalize(...), [
            $groupName,
            $systemName,
            $subsystemName,
        ]));
        $alias = AssetCategorySourceAlias::query()
            ->where('category_type', 'subsystem')
            ->where('normalized_source_path', $normalizedPath)
            ->first();

        if ($alias) {
            $subsystem = AssetSubsystem::query()->whereKey($alias->category_id)->first();
            if ($subsystem) {
                return $subsystem;
            }
        }

        $groups = AssetGroup::query()
            ->with('systems.subsystems')
            ->get()
            ->filter(fn (AssetGroup $group): bool => $this->categoryNameMatches($group->name, $groupName));

        if ($groups->count() !== 1) {
            throw $this->rowError(
                $workbookName,
                $row,
                'Sub-System',
                "hierarchy {$groupName}|{$systemName}|{$subsystemName} tidak cocok dengan kategori global.",
            );
        }

        /** @var AssetGroup $group */
        $group = $groups->first();
        /** @var Collection<int, AssetSystem> $systems */
        $systems = $group->systems;
        $matchingSystems = $systems->filter(
            fn (AssetSystem $system): bool => $this->categoryNameMatches($system->name, $systemName),
        );

        if ($matchingSystems->count() === 1) {
            /** @var AssetSystem $system */
            $system = $matchingSystems->first();
            $matchingSubsystems = $system->subsystems->filter(
                fn (AssetSubsystem $subsystem): bool => $this->categoryNameMatches($subsystem->name, $subsystemName),
            );
            if ($matchingSubsystems->count() === 1) {
                return $matchingSubsystems->first();
            }
            if ($system->subsystems->count() === 1) {
                return $system->subsystems->first();
            }
        }

        $subsystems = $systems->flatMap->subsystems;
        $matchingSubsystems = $subsystems->filter(fn (AssetSubsystem $subsystem): bool => $this->categoryNameMatches($subsystem->name, $systemName)
            || $this->categoryNameMatches($subsystem->name, $subsystemName));

        if ($matchingSubsystems->count() === 1) {
            return $matchingSubsystems->first();
        }
        if ($subsystems->count() === 1) {
            return $subsystems->first();
        }

        throw $this->rowError(
            $workbookName,
            $row,
            'Sub-System',
            "hierarchy {$groupName}|{$systemName}|{$subsystemName} tidak cocok dengan kategori global.",
        );
    }

    private function categoryNameMatches(string $categoryName, string $sourceName): bool
    {
        return $this->categoryComparable($categoryName) === $this->categoryComparable($sourceName);
    }

    private function categoryComparable(string $value): string
    {
        $value = preg_replace('/^\s*\d+\s*[.)-]?\s*/u', '', $value) ?? $value;
        $value = str_ireplace(['electric', 'ciruit'], ['elektrik', 'circuit'], $value);

        return $this->categoryResolver->normalize($value);
    }

    private function sourceKey(string ...$parts): string
    {
        return hash('sha256', implode('|', [
            self::SHEET,
            ...array_map($this->categoryResolver->normalize(...), $parts),
        ]));
    }

    private function nullableDecimal(
        Worksheet $sheet,
        int $column,
        int $row,
        string $workbookName,
        string $header,
    ): ?string {
        $value = $sheet->getCell([$column, $row])->getCalculatedValue();
        if ($this->isBlank($value)) {
            return null;
        }
        if (! is_numeric($value) || (float) $value < 0) {
            throw $this->rowError($workbookName, $row, $header, 'harus berupa angka nonnegatif atau kosong.');
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function nullableQuantity(
        Worksheet $sheet,
        int $column,
        int $row,
        string $workbookName,
        string $header,
    ): ?int {
        $value = $sheet->getCell([$column, $row])->getCalculatedValue();
        if ($this->isBlank($value)) {
            return null;
        }
        if (! is_numeric($value) || (float) $value < 0 || floor((float) $value) !== (float) $value) {
            throw $this->rowError($workbookName, $row, $header, 'harus berupa bilangan bulat nonnegatif atau kosong.');
        }

        return (int) $value;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = $this->text($value);

        return $value === '' ? null : $value;
    }

    private function cellText(Worksheet $sheet, int $column, int $row): string
    {
        return $this->text($sheet->getCell([$column, $row])->getCalculatedValue());
    }

    private function text(mixed $value): string
    {
        $value = preg_replace('/^\s+|\s+$/u', '', (string) ($value ?? '')) ?? trim((string) ($value ?? ''));

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower($this->text($value));
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || $this->text($value) === '' || in_array($this->text($value), ['-', '–'], true);
    }

    private function rowError(string $workbookName, int $row, string $header, string $message): RuntimeException
    {
        return new RuntimeException(
            "Workbook {$workbookName}, sheet ".self::SHEET.", row {$row}, header {$header}: {$message}",
        );
    }
}
