<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Models\UnitKerja;
use App\Models\UnitSubsystemOpening;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

class MasterAssetWorkbookImporter
{
    private const SHEET = 'Predictive Data Asset';

    /** @var array<string, string> */
    private const REQUIRED_HEADERS = [
        'aset prasarana sintel' => 'group',
        'system' => 'system',
        'subsystem' => 'subsystem',
        'total' => 'total',
        'sparepart in' => 'sparepart_in',
        'sparepart out' => 'sparepart_out',
        'tanggal pemasangan' => 'installed_at',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AssetCategoryResolver $categoryResolver,
    ) {}

    /**
     * @return array{created: int, updated: int, skipped: int, openings_created: int, openings_updated: int}
     */
    public function import(string $workbookPath, UnitKerja $unit): array
    {
        if (! is_file($workbookPath)) {
            throw new RuntimeException("File workbook tidak ditemukan: {$workbookPath}");
        }

        $reader = IOFactory::createReaderForFile($workbookPath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($workbookPath);

        try {
            $sheet = $spreadsheet->getSheetByName(self::SHEET);

            if (! $sheet) {
                throw new RuntimeException('Sheet "'.self::SHEET.'" tidak ditemukan.');
            }

            $columns = $this->headerColumns($sheet);

            return DB::transaction(fn (): array => $this->importRows(
                $sheet,
                $columns,
                $unit,
                basename($workbookPath),
            ));
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    /**
     * @return array<string, int>
     */
    private function headerColumns(Worksheet $sheet): array
    {
        $columns = [];
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn(2));

        for ($column = 1; $column <= $highestColumn; $column++) {
            $header = $this->text($sheet->getCell([$column, 2])->getValue());
            $key = mb_strtolower($header);

            if (isset(self::REQUIRED_HEADERS[$key])) {
                $columns[self::REQUIRED_HEADERS[$key]] = $column;
            }
        }

        $missing = array_diff(array_values(self::REQUIRED_HEADERS), array_keys($columns));

        if ($missing !== []) {
            throw new RuntimeException('Header workbook tidak valid. Kolom wajib yang hilang: '.implode(', ', $missing).'.');
        }

        return $columns;
    }

    /**
     * @param  array<string, int>  $columns
     * @return array{created: int, updated: int, skipped: int, openings_created: int, openings_updated: int}
     */
    private function importRows(Worksheet $sheet, array $columns, UnitKerja $unit, string $workbookName): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'openings_created' => 0,
            'openings_updated' => 0,
        ];
        $currentGroup = '';
        $currentSystem = '';
        $legacyCurrentSystem = '';

        for ($row = 3; $row <= $sheet->getHighestDataRow(); $row++) {
            $group = $this->cellText($sheet, $columns['group'], $row);
            $system = $this->cellText($sheet, $columns['system'], $row);
            $subsystem = $this->cellText($sheet, $columns['subsystem'], $row);
            $legacySystem = $this->legacyCellText($sheet, $columns['system'], $row);
            $legacySubsystem = $this->legacyCellText($sheet, $columns['subsystem'], $row);

            $currentGroup = $group !== '' ? $group : $currentGroup;
            $currentSystem = $system !== '' ? $system : $currentSystem;
            $legacyCurrentSystem = $legacySystem !== '' ? $legacySystem : $legacyCurrentSystem;

            if ($currentGroup === '' || $currentSystem === '' || $subsystem === '') {
                $result['skipped']++;

                continue;
            }

            $categories = $this->categoryResolver->resolve(
                $currentGroup,
                $currentSystem,
                $subsystem,
                $workbookName,
                self::SHEET,
                $row,
            );
            $this->lockAndRevalidateCategories(
                $categories['group'],
                $categories['system'],
                $categories['subsystem'],
                $workbookName,
                $row,
            );

            $sourceKey = hash('sha256', implode('|', [
                $unit->code,
                self::SHEET,
                $categories['subsystem']->id,
            ]));
            $legacySourceKey = hash('sha256', implode('|', [
                $unit->code,
                self::SHEET,
                $legacyCurrentSystem,
                $legacySubsystem,
            ]));
            $sourceValues = [
                'unit_kerja_id' => $unit->getKey(),
                'asset_subsystem_id' => $categories['subsystem']->id,
                'aset_prasarana_sintel' => $currentGroup,
                'system' => $currentSystem,
                'subsystem' => $subsystem,
                'jumlah_unit' => $this->quantity($sheet->getCell([$columns['total'], $row])->getCalculatedValue()),
                'tanggal_pemasangan' => $this->date($sheet->getCell([$columns['installed_at'], $row])->getCalculatedValue()),
                'source_key' => $sourceKey,
            ];

            $matchingAssets = Asset::withTrashed()
                ->whereIn('source_key', [$sourceKey, $legacySourceKey])
                ->lockForUpdate()
                ->get();

            if ($matchingAssets->contains(fn (Asset $candidate): bool => $candidate->trashed())) {
                $result['skipped']++;

                continue;
            }

            if ($matchingAssets->count() > 1) {
                throw new RuntimeException(
                    "Konflik asset source key pada workbook {$workbookName}, sheet ".self::SHEET.", row {$row}.",
                );
            }

            /** @var Asset|null $asset */
            $asset = $matchingAssets->first();

            if ($asset) {
                $before = $this->auditValues($asset);
                $asset->update($sourceValues);
                $this->auditLogger->record('asset.import_updated', $asset, $before, $this->auditValues($asset->refresh()));
                $result['updated']++;
            } else {
                $asset = Asset::query()->create([
                    ...$sourceValues,
                    'nama_aset' => $subsystem,
                    'lokasi' => null,
                    'status' => AssetStatus::Aktif,
                ]);
                $this->auditLogger->record('asset.import_created', $asset, [], $this->auditValues($asset));
                $result['created']++;
            }

            $this->importOpening($sheet, $columns, $row, $unit, $categories['subsystem'], $result);
        }

        return $result;
    }

    private function lockAndRevalidateCategories(
        AssetGroup $group,
        AssetSystem $system,
        AssetSubsystem $subsystem,
        string $workbookName,
        int $row,
    ): void {
        $lockedGroup = AssetGroup::withTrashed()->lockForUpdate()->find($group->id);
        $lockedSystem = AssetSystem::withTrashed()->lockForUpdate()->find($system->id);
        $lockedSubsystem = AssetSubsystem::withTrashed()->lockForUpdate()->find($subsystem->id);

        if (
            ! $lockedGroup || $lockedGroup->trashed()
            || ! $lockedSystem || $lockedSystem->trashed() || $lockedSystem->asset_group_id !== $lockedGroup->id
            || ! $lockedSubsystem || $lockedSubsystem->trashed() || $lockedSubsystem->asset_system_id !== $lockedSystem->id
        ) {
            throw new RuntimeException(
                "Asset category resolution conflict in workbook {$workbookName}, sheet ".self::SHEET.", row {$row}.",
            );
        }
    }

    /**
     * @param  array<string, int>  $columns
     * @param  array{created: int, updated: int, skipped: int, openings_created: int, openings_updated: int}  $result
     */
    private function importOpening(
        Worksheet $sheet,
        array $columns,
        int $row,
        UnitKerja $unit,
        AssetSubsystem $subsystem,
        array &$result,
    ): void {
        $sourceKey = hash('sha256', implode('|', [
            $unit->code,
            self::SHEET,
            $subsystem->id,
            'opening',
        ]));
        $values = [
            'unit_kerja_id' => $unit->id,
            'asset_subsystem_id' => $subsystem->id,
            'source_key' => $sourceKey,
            'sparepart_in' => $this->quantity($sheet->getCell([$columns['sparepart_in'], $row])->getCalculatedValue()),
            'sparepart_out' => $this->quantity($sheet->getCell([$columns['sparepart_out'], $row])->getCalculatedValue()),
        ];

        $opening = UnitSubsystemOpening::query()
            ->where('unit_kerja_id', $unit->id)
            ->where('asset_subsystem_id', $subsystem->id)
            ->lockForUpdate()
            ->first();

        if (! $opening) {
            $opening = UnitSubsystemOpening::query()->create($values);
            $this->auditLogger->record(
                'unit_subsystem_opening.imported',
                $opening,
                [],
                $this->openingAuditValues($opening),
            );
            $result['openings_created']++;

            return;
        }

        $before = $this->openingAuditValues($opening);
        $opening->fill($values);
        if (! $opening->isDirty()) {
            return;
        }

        $opening->save();
        $this->auditLogger->record(
            'unit_subsystem_opening.imported',
            $opening,
            $before,
            $this->openingAuditValues($opening->refresh()),
        );
        $result['openings_updated']++;
    }

    /** @return array<string, int|string> */
    private function openingAuditValues(UnitSubsystemOpening $opening): array
    {
        return $opening->only([
            'unit_kerja_id',
            'asset_subsystem_id',
            'sparepart_in',
            'sparepart_out',
            'source_key',
        ]);
    }

    private function cellText(Worksheet $sheet, int $column, int $row): string
    {
        return $this->text($sheet->getCell([$column, $row])->getCalculatedValue());
    }

    private function legacyCellText(Worksheet $sheet, int $column, int $row): string
    {
        return trim((string) ($sheet->getCell([$column, $row])->getCalculatedValue() ?? ''));
    }

    private function text(mixed $value): string
    {
        $trimmed = preg_replace('/^\s+|\s+$/u', '', (string) ($value ?? '')) ?? trim((string) ($value ?? ''));

        return preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed;
    }

    private function quantity(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 0;
        }

        $quantity = (float) $value;
        if ($quantity < 0) {
            throw new RuntimeException('Jumlah tidak boleh negatif.');
        }

        if (floor($quantity) !== $quantity || $quantity > 4294967295) {
            throw new RuntimeException('Jumlah harus berupa bilangan bulat unsigned integer.');
        }

        return (int) $quantity;
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (Throwable $exception) {
                throw new RuntimeException('Nilai tanggal pemasangan tidak valid.', previous: $exception);
            }
        }

        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, trim((string) $value));

                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            } catch (Throwable) {
                // Try the next supported format.
            }
        }

        throw new RuntimeException('Tanggal pemasangan "'.trim((string) $value).'" tidak menggunakan format yang didukung.');
    }

    /** @return array<string, mixed> */
    private function auditValues(Asset $asset): array
    {
        return [
            ...$asset->only([
                'id',
                'unit_kerja_id',
                'nama_aset',
                'aset_prasarana_sintel',
                'system',
                'subsystem',
                'lokasi',
                'jumlah_unit',
                'source_key',
            ]),
            'tanggal_pemasangan' => $asset->tanggal_pemasangan?->toDateString(),
            'status' => $asset->status->value,
        ];
    }
}
