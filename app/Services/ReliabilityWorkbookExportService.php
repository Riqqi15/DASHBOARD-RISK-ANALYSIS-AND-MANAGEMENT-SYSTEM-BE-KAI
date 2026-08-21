<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\FailureLog;
use App\Models\ReliabilitySummary;
use App\Models\UnitKerja;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class ReliabilityWorkbookExportService
{
    /** @var list<string> */
    private const SUMMARY_HEADERS = [
        'Subsystem',
        'Jumlah Unit',
        'Total Operating Hour',
        'Total Uptime',
        'Total Downtime',
        'Jumlah Failure',
        'MTTF',
        'MTBF',
        'Failure Rate λ',
        'Reliability',
        'Availability',
        'Jumlah penggantian sparepart',
        'Jumlah Tindak Vandalisme',
    ];

    /** @var list<string> */
    private const FAILURE_HEADERS = [
        'Lokasi',
        'Resor',
        'QC',
        'Failure Event',
        'Penyebab',
        'Tindakan',
        'Penggantian sparepart',
        'Tindak Vandalisme',
        'Tahun Kejadian',
        'Tanggal Kejadian',
        'Tanggal Penanganan',
        'Mulai',
        'Selesai',
        'Tanggal Jam Kejadian',
        'Tanggal Jam Penanganan',
        'Downtime (jam)',
        'Konversi ke Menit',
        'Interval antar Failure (jam)',
    ];

    public function __construct(
        private readonly ReliabilitySheetNameResolver $sheetNames,
        private readonly ReliabilityFormulaProfileResolver $profiles,
    ) {}

    public function workbook(User $user, UnitKerja $unit): Spreadsheet
    {
        $groups = Asset::query()
            ->visibleTo($user)
            ->where('unit_kerja_id', $unit->id)
            ->with([
                'assetSubsystem:id,name',
                'failureLogs' => fn (HasMany $logs): HasMany => $logs
                    ->orderByRaw('CASE WHEN source_row IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('source_row')
                    ->orderBy('started_at')
                    ->orderBy('id'),
                'reliabilitySummaries' => fn (HasMany $summaries): HasMany => $summaries
                    ->with('excelSnapshot')
                    ->latest('period')
                    ->latest('id'),
            ])
            ->get()
            ->groupBy(fn (Asset $asset): string => $this->subsystemName($asset));

        $workbook = new Spreadsheet;
        $summarySheet = $workbook->getActiveSheet();
        $summarySheet->setTitle('Ringkasan Reliability');
        $subsystems = [];

        foreach ($groups as $subsystemName => $assets) {
            $title = $this->sheetNames->resolve((string) $subsystemName);
            $sheet = $workbook->createSheet();
            $sheet->setTitle($title);
            $this->writeSubsystemSheet($sheet, (string) $subsystemName, $assets);
            $subsystems[] = ['name' => (string) $subsystemName, 'title' => $title];
        }

        $this->writeSummarySheet($summarySheet, $unit, $subsystems);
        $workbook->setActiveSheetIndex(0);

        return $workbook;
    }

    /** @param list<array{name: string, title: string}> $subsystems */
    private function writeSummarySheet(Worksheet $sheet, UnitKerja $unit, array $subsystems): void
    {
        $sheet->mergeCells('A1:M1');
        $sheet->setCellValue('A1', 'Reliability & Availability — '.$unit->code);
        $sheet->setCellValue('A2', 'Unit kerja');
        $sheet->setCellValue('B2', $unit->name);
        $sheet->setCellValue('L2', 'Dibuat');
        $sheet->setCellValue('M2', now()->format('Y-m-d H:i'));
        $sheet->fromArray(self::SUMMARY_HEADERS, null, 'A4');

        foreach ($subsystems as $index => $subsystem) {
            $row = 5 + $index;
            $escapedTitle = str_replace("'", "''", $subsystem['title']);
            foreach (range('B', 'N') as $offset => $sourceColumn) {
                $targetColumn = $this->columnLetter($offset + 1);
                $sheet->setCellValue("{$targetColumn}{$row}", "='{$escapedTitle}'!{$sourceColumn}4");
            }
        }

        $lastRow = max(5, count($subsystems) + 4);
        $sheet->freezePane('A5');
        $sheet->setAutoFilter("A4:M{$lastRow}");
        $sheet->getStyle('A1:M1')->applyFromArray($this->titleStyle());
        $sheet->getStyle('A4:M4')->applyFromArray($this->headerStyle('4F81BD'));
        $sheet->getStyle("A5:M{$lastRow}")->applyFromArray($this->bodyBorderStyle());
        $sheet->getStyle("C5:D{$lastRow}")->getNumberFormat()->setFormatCode('General');
        $sheet->getStyle("E5:F{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle("G5:H{$lastRow}")->getNumberFormat()->setFormatCode('General');
        $sheet->getStyle("I5:I{$lastRow}")->getNumberFormat()->setFormatCode('0.0000000000');
        $sheet->getStyle("J5:K{$lastRow}")->getNumberFormat()->setFormatCode('0.0000%');
        $sheet->getStyle("L5:M{$lastRow}")->getNumberFormat()->setFormatCode('General');
        $sheet->getStyle('A1:M4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(4)->setRowHeight(42);
        $this->setWidths($sheet, [
            'A' => 42, 'B' => 12, 'C' => 20, 'D' => 18, 'E' => 18, 'F' => 15,
            'G' => 16, 'H' => 16, 'I' => 18, 'J' => 16, 'K' => 16, 'L' => 23, 'M' => 22,
        ]);
    }

    /** @param Collection<int, Asset> $assets */
    private function writeSubsystemSheet(
        Worksheet $sheet,
        string $subsystemName,
        Collection $assets,
    ): void {
        $summary = $this->latestSummary($assets);
        $fallbackBaseline = $summary?->baseline_date?->toDateString()
            ?? $assets->pluck('tanggal_pemasangan')->filter()->sort()->first()?->toDateString()
            ?? '2017-01-01';
        $rawProfile = array_merge(
            $summary?->excelSnapshot?->formula_profile ?? [],
            $summary?->calculation_profile ?? [],
        );
        $profile = $this->profiles->resolve($rawProfile, $fallbackBaseline);
        $baseline = CarbonImmutable::parse((string) $profile['interval_baseline_date'])->startOfDay();
        $operationBaseline = $summary?->baseline_date?->startOfDay()
            ?? CarbonImmutable::parse('2020-01-01');
        $failures = $assets
            ->flatMap(fn (Asset $asset): Collection => $asset->failureLogs)
            ->sortBy(fn (FailureLog $log): array => [
                $log->source_row === null ? 1 : 0,
                $log->source_row ?? PHP_INT_MAX,
                $log->started_at?->getTimestamp() ?? 0,
                $log->id,
            ])
            ->values();
        $firstRow = 10;
        $intervalRows = max(0, (int) ($profile['failure_interval_row_count'] ?? 0));
        $detailRowCount = max(1, $failures->count(), $intervalRows);
        $lastRow = $firstRow + $detailRowCount - 1;

        $sheet->mergeCells('B1:N1');
        $sheet->setCellValue('B1', $subsystemName);
        $sheet->fromArray(self::SUMMARY_HEADERS, null, 'B3');
        $sheet->setCellValue('B4', $subsystemName);
        $sheet->setCellValue('C4', (int) $assets->sum('jumlah_unit'));
        $sheet->setCellValue('D4', '=(TODAY()-$T$8)*24*C4');
        $sheet->setCellValue('E4', '=D4-F4');
        $sheet->setCellValue('F4', $this->downtimeFormula((string) $profile['downtime_mode'], $firstRow, $lastRow));
        $sheet->setCellValue('G4', "=COUNTA(E{$firstRow}:E{$lastRow})");
        $sheet->setCellValue('H4', "=IFERROR(AVERAGE(S{$firstRow}:S{$lastRow}),\"Data belum cukup\")");
        $sheet->setCellValue('I4', '=IFERROR(E4/G4,0)');
        $sheet->setCellValue('J4', '=IFERROR(1/I4,0)');
        $sheet->setCellValue('K4', '=EXP(-J4)');
        $sheet->setCellValue('L4', '=IFERROR(E4/D4,"Data belum cukup")');
        $sheet->setCellValue('M4', $this->markerFormula((string) $profile['spare_part_count_mode'], 'H', $firstRow, $lastRow));
        $sheet->setCellValue('N4', $this->markerFormula((string) $profile['vandalism_count_mode'], 'I', $firstRow, $lastRow));

        $sheet->setCellValue('P7', 'Tanggal baseline interval');
        $sheet->setCellValue('P8', Date::PHPToExcel($baseline));
        $sheet->setCellValue('Q7', 'Tanggal kalkulasi');
        $sheet->setCellValue('Q8', '=TODAY()');
        $sheet->setCellValue('R7', 'Formula version');
        $sheet->setCellValue('R8', $summary?->formula_version ?? 'kai-rams-excel-parity-v1.1.0');
        $sheet->setCellValue('S7', 'Profile');
        $sheet->setCellValue('T7', 'Tanggal baseline operasi');
        $sheet->setCellValue('T8', Date::PHPToExcel($operationBaseline));
        $sheet->setCellValue('S8', (bool) $profile['is_fallback'] ? 'Profile standar — snapshot Excel belum tersedia' : 'Profile snapshot Excel');
        $sheet->fromArray(self::FAILURE_HEADERS, null, 'B9');

        foreach ($failures as $index => $failure) {
            $row = $firstRow + $index;
            $asset = $assets->firstWhere('id', $failure->asset_id);
            $this->writeFailureRow($sheet, $row, $failure, $asset, $row === $firstRow);
        }

        for ($row = $firstRow + $failures->count(); $row <= $lastRow; $row++) {
            $this->writeFailureFormulas($sheet, $row, $row === $firstRow);
        }

        $this->formatSubsystemSheet($sheet, $lastRow);
    }

    private function writeFailureRow(
        Worksheet $sheet,
        int $row,
        FailureLog $failure,
        ?Asset $asset,
        bool $first,
    ): void {
        $sheet->setCellValue("B{$row}", $failure->location);
        $sheet->setCellValue("C{$row}", $failure->resort);
        $sheet->setCellValue("D{$row}", $failure->qc);
        $sheet->setCellValue("E{$row}", $failure->failure_event);
        $sheet->setCellValue("F{$row}", $failure->cause);
        $sheet->setCellValue("G{$row}", $failure->action_taken);
        $sheet->setCellValue("H{$row}", $failure->spare_part_marker ?: ($failure->spare_part_replaced ? 'Ya' : null));
        $sheet->setCellValue("I{$row}", $failure->vandalism_marker ?: ($failure->vandalism ? 'Ya' : null));

        if ($failure->started_at) {
            $sheet->setCellValue("K{$row}", Date::PHPToExcel($failure->started_at->copy()->startOfDay()));
            $sheet->setCellValue("M{$row}", $this->timeFraction($failure->started_at));
        }
        if ($failure->resolved_at) {
            $sheet->setCellValue("L{$row}", Date::PHPToExcel($failure->resolved_at->copy()->startOfDay()));
            $sheet->setCellValue("N{$row}", $this->timeFraction($failure->resolved_at));
        }

        $this->writeFailureFormulas($sheet, $row, $first);
    }

    private function writeFailureFormulas(Worksheet $sheet, int $row, bool $first): void
    {
        $sheet->setCellValue("J{$row}", "=IF(K{$row}=\"\",\"\",YEAR(K{$row}))");
        $sheet->setCellValue("O{$row}", "=IF(OR(K{$row}=\"\",M{$row}=\"\"),\"\",K{$row}+M{$row})");
        $sheet->setCellValue("P{$row}", "=IF(OR(L{$row}=\"\",N{$row}=\"\"),\"\",L{$row}+N{$row})");
        $sheet->setCellValue("Q{$row}", "=IF(OR(M{$row}=\"\",N{$row}=\"\"),\"\",N{$row}-M{$row}+IF(N{$row}<M{$row},1,0))");
        $sheet->setCellValue("R{$row}", "=IF(Q{$row}=\"\",\"\",Q{$row}*1440)");
        $previous = $first ? '$P$8' : 'O'.($row - 1);
        $sheet->setCellValue("S{$row}", "=IFERROR(IF(O{$row}=\"\",0,(O{$row}-{$previous})*24),\"\")");
    }

    private function formatSubsystemSheet(Worksheet $sheet, int $lastRow): void
    {
        $sheet->freezePane('B10');
        $sheet->setAutoFilter("B9:S{$lastRow}");
        $sheet->getStyle('B1:N1')->applyFromArray($this->titleStyle());
        $sheet->getStyle('B3:N3')->applyFromArray($this->headerStyle('4F81BD'));
        $sheet->getStyle('B4:N4')->applyFromArray([
            ...$this->bodyBorderStyle(),
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCE6F1']],
        ]);
        $sheet->getStyle('B9:S9')->applyFromArray($this->headerStyle('8064A2'));
        $sheet->getStyle("B10:S{$lastRow}")->applyFromArray($this->bodyBorderStyle());
        $sheet->getStyle('P7:T7')->applyFromArray($this->headerStyle('9BBB59'));
        $sheet->getStyle('P8:T8')->applyFromArray($this->bodyBorderStyle());
        $sheet->getStyle('P8:Q8')->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sheet->getStyle('T8')->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sheet->getStyle("K10:L{$lastRow}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sheet->getStyle("M10:N{$lastRow}")->getNumberFormat()->setFormatCode('hh:mm');
        $sheet->getStyle("O10:P{$lastRow}")->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');
        $sheet->getStyle("Q10:Q{$lastRow}")->getNumberFormat()->setFormatCode('[h]:mm');
        $sheet->getStyle("R10:S{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle('K4:L4')->getNumberFormat()->setFormatCode('0.0000%');
        $sheet->getStyle('C4:D4')->getNumberFormat()->setFormatCode('General');
        $sheet->getStyle('E4:F4')->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle('G4:I4')->getNumberFormat()->setFormatCode('General');
        $sheet->getStyle('J4')->getNumberFormat()->setFormatCode('0.0000000000');
        $sheet->getStyle('M4:N4')->getNumberFormat()->setFormatCode('General');
        $sheet->getStyle('B1:S9')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('B3:S9')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(3)->setRowHeight(48);
        $sheet->getRowDimension(9)->setRowHeight(54);
        $this->setWidths($sheet, [
            'B' => 36, 'C' => 18, 'D' => 14, 'E' => 28, 'F' => 30, 'G' => 30,
            'H' => 19, 'I' => 18, 'J' => 14, 'K' => 17, 'L' => 17, 'M' => 12,
            'N' => 12, 'O' => 21, 'P' => 21, 'Q' => 17, 'R' => 18, 'S' => 20, 'T' => 18,
        ]);
    }

    /** @param Collection<int, Asset> $assets */
    private function latestSummary(Collection $assets): ?ReliabilitySummary
    {
        return $assets
            ->flatMap(fn (Asset $asset): Collection => $asset->reliabilitySummaries)
            ->sortByDesc(fn (ReliabilitySummary $summary): string => sprintf(
                '%010d-%010d',
                $summary->period?->getTimestamp() ?? 0,
                $summary->id,
            ))
            ->first();
    }

    private function subsystemName(Asset $asset): string
    {
        return $asset->assetSubsystem?->name
            ?? $asset->subsystem
            ?? $asset->nama_aset;
    }

    private function downtimeFormula(string $mode, int $firstRow, int $lastRow): string
    {
        return match ($mode) {
            'hours' => "=SUM(Q{$firstRow}:Q{$lastRow})*24",
            'excel_day_fraction' => "=SUM(Q{$firstRow}:Q{$lastRow})",
            default => "=SUM(R{$firstRow}:R{$lastRow})",
        };
    }

    private function markerFormula(string $mode, string $column, int $firstRow, int $lastRow): string
    {
        return $mode === 'counta'
            ? "=COUNTA({$column}{$firstRow}:{$column}{$lastRow})"
            : "=COUNTIF({$column}{$firstRow}:{$column}{$lastRow},\"Ya\")";
    }

    private function timeFraction(mixed $date): float
    {
        return (($date->hour * 3600) + ($date->minute * 60) + $date->second) / 86400;
    }

    private function columnLetter(int $column): string
    {
        return chr(64 + $column);
    }

    /** @return array<string, mixed> */
    private function titleStyle(): array
    {
        return [
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '171650']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
    }

    /** @return array<string, mixed> */
    private function headerStyle(string $color): array
    {
        return [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ];
    }

    /** @return array<string, mixed> */
    private function bodyBorderStyle(): array
    {
        return [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2F3']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
        ];
    }

    /** @param array<string, int|float> $widths */
    private function setWidths(Worksheet $sheet, array $widths): void
    {
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }
}
