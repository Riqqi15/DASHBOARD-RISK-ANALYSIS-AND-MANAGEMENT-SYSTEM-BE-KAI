<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\UnitKerja;
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
        'tanggal pemasangan' => 'installed_at',
    ];

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @return array{created: int, updated: int, skipped: int}
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

            return DB::transaction(fn (): array => $this->importRows($sheet, $columns, $unit));
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
     * @return array{created: int, updated: int, skipped: int}
     */
    private function importRows(Worksheet $sheet, array $columns, UnitKerja $unit): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $currentGroup = '';
        $currentSystem = '';

        for ($row = 3; $row <= $sheet->getHighestDataRow(); $row++) {
            $group = $this->cellText($sheet, $columns['group'], $row);
            $system = $this->cellText($sheet, $columns['system'], $row);
            $subsystem = $this->cellText($sheet, $columns['subsystem'], $row);

            $currentGroup = $group !== '' ? $group : $currentGroup;
            $currentSystem = $system !== '' ? $system : $currentSystem;

            if ($currentGroup === '' || $currentSystem === '' || $subsystem === '') {
                $result['skipped']++;

                continue;
            }

            $sourceKey = hash('sha256', implode('|', [
                $unit->code,
                self::SHEET,
                $currentSystem,
                $subsystem,
            ]));
            $sourceValues = [
                'unit_kerja_id' => $unit->getKey(),
                'aset_prasarana_sintel' => $currentGroup,
                'system' => $currentSystem,
                'subsystem' => $subsystem,
                'jumlah_unit' => $this->quantity($sheet->getCell([$columns['total'], $row])->getCalculatedValue()),
                'tanggal_pemasangan' => $this->date($sheet->getCell([$columns['installed_at'], $row])->getCalculatedValue()),
                'source_key' => $sourceKey,
            ];

            $asset = Asset::withTrashed()->where('source_key', $sourceKey)->first();

            if ($asset?->trashed()) {
                $result['skipped']++;

                continue;
            }

            if ($asset) {
                $before = $this->auditValues($asset);
                $asset->update($sourceValues);
                $this->auditLogger->record('asset.import_updated', $asset, $before, $this->auditValues($asset->refresh()));
                $result['updated']++;

                continue;
            }

            $asset = Asset::query()->create([
                ...$sourceValues,
                'nama_aset' => $subsystem,
                'lokasi' => null,
                'status' => AssetStatus::Aktif,
            ]);
            $this->auditLogger->record('asset.import_created', $asset, [], $this->auditValues($asset));
            $result['created']++;
        }

        return $result;
    }

    private function cellText(Worksheet $sheet, int $column, int $row): string
    {
        return $this->text($sheet->getCell([$column, $row])->getCalculatedValue());
    }

    private function text(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function quantity(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
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
