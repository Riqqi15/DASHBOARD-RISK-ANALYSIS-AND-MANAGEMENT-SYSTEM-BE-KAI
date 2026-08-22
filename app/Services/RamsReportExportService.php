<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FailureLog;
use App\Models\InventoryStock;
use App\Models\ReliabilitySummary;
use App\Models\RiskRegister;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class RamsReportExportService
{
    public const REPORTS = ['inventory', 'trouble-report', 'risk-register', 'reliability'];

    public function workbook(string $report, User $user, ?UnitKerja $unit): Spreadsheet
    {
        [$title, $headers, $rows] = $this->dataset($report, $user, $unit);

        $workbook = new Spreadsheet;
        $sheet = $workbook->getActiveSheet();
        $sheet->setTitle($title);
        $sheet->fromArray($headers, null, 'A1');
        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }
        $this->format($sheet, count($headers), count($rows) + 1);

        return $workbook;
    }

    /** @return array{string, list<string>, list<list<mixed>>} */
    public function dataset(string $report, User $user, ?UnitKerja $unit): array
    {
        return match ($report) {
            'inventory' => $this->inventory($user, $unit),
            'trouble-report' => $this->troubleReport($user, $unit),
            'risk-register' => $this->riskRegister($user, $unit),
            'reliability' => $this->reliability($user, $unit),
        };
    }

    /** @return array{string, list<string>, list<list<mixed>>} */
    private function inventory(User $user, ?UnitKerja $unit): array
    {
        $stocks = InventoryStock::query()
            ->visibleTo($user)
            ->when($unit, fn (Builder $query): Builder => $query->where('unit_kerja_id', $unit->id))
            ->with([
                'unitKerja:id,code,name',
                'sparePart.assetSubsystem.assetSystem.assetGroup',
                'sparePart.unitPolicies',
            ])
            ->orderBy('unit_kerja_id')
            ->get();

        return [
            'Inventori',
            [
                'Unit',
                'Kode',
                'Kelompok',
                'System',
                'Subsystem',
                'Suku Cadang',
                'Stok Saat Ini',
                'Safety Stock',
                'Reorder Point',
                'Proposal Pembelian',
                'Jumlah Proposal',
            ],
            $stocks
                ->map(function (InventoryStock $stock): array {
                    $part = $stock->sparePart;
                    $policy = $part->unitPolicies->firstWhere('unit_kerja_id', $stock->unit_kerja_id);
                    $reorderPoint = (int) ($policy?->reorder_point ?? ($part->reorder_point ?? 0));
                    $proposal = max(0, $reorderPoint - $stock->quantity);

                    return [
                        $stock->unitKerja?->code,
                        $part->code,
                        $part->assetSubsystem?->assetSystem?->assetGroup?->name,
                        $part->assetSubsystem?->assetSystem?->name,
                        $part->assetSubsystem?->name,
                        $part->detail_equipment,
                        $stock->quantity,
                        (int) ($policy?->safety_stock ?? ($part->safety_stock ?? 0)),
                        $reorderPoint,
                        $proposal > 0 ? 'Beli' : 'Tidak Beli',
                        $proposal,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /** @return array{string, list<string>, list<list<mixed>>} */
    private function troubleReport(User $user, ?UnitKerja $unit): array
    {
        $logs = FailureLog::query()
            ->visibleTo($user)
            ->when(
                $unit,
                fn (Builder $query): Builder => $query->whereHas(
                    'asset',
                    fn (Builder $assets): Builder => $assets->where('unit_kerja_id', $unit->id),
                ),
            )
            ->with([
                'asset.unitKerja:id,code,name',
                'asset.assetSubsystem:id,name',
                'sparePart:id,code,detail_equipment',
            ])
            ->latest('started_at')
            ->get();

        return [
            'Trouble Report',
            [
                'Unit',
                'Subsystem',
                'Lokasi',
                'Resor',
                'QC',
                'Failure Event',
                'Penyebab',
                'Tindakan',
                'Mulai',
                'Selesai',
                'Downtime (menit)',
                'Penggantian Sparepart',
                'Suku Cadang',
                'Vandalisme',
            ],
            $logs
                ->map(
                    fn (FailureLog $log): array => [
                        $log->asset->unitKerja?->code,
                        $log->asset->assetSubsystem?->name,
                        $log->location,
                        $log->resort,
                        $log->qc,
                        $log->failure_event,
                        $log->cause,
                        $log->action_taken,
                        $log->started_at?->format('Y-m-d H:i'),
                        $log->resolved_at?->format('Y-m-d H:i'),
                        $log->downtime_minutes,
                        $log->spare_part_replaced ? 'Ya' : 'Tidak',
                        $log->sparePart?->detail_equipment,
                        $log->vandalism ? 'Ya' : 'Tidak',
                    ],
                )
                ->values()
                ->all(),
        ];
    }

    /** @return array{string, list<string>, list<list<mixed>>} */
    private function riskRegister(User $user, ?UnitKerja $unit): array
    {
        $registers = RiskRegister::query()
            ->visibleTo($user)
            ->when(
                $unit,
                fn (Builder $query): Builder => $query->whereHas(
                    'asset',
                    fn (Builder $assets): Builder => $assets->where('unit_kerja_id', $unit->id),
                ),
            )
            ->with(['asset.unitKerja:id,code,name', 'asset.assetSubsystem:id,name'])
            ->latest('updated_at')
            ->get();

        return [
            'Risk Register',
            [
                'Unit',
                'Aset',
                'Subsystem',
                'Part Number',
                'Peristiwa Risiko',
                'Penyebab',
                'Dampak',
                'Rekomendasi',
                'Likelihood',
                'Consequence',
                'Rating',
                'Status',
                'Sumber',
            ],
            $registers
                ->map(
                    fn (RiskRegister $risk): array => [
                        $risk->asset->unitKerja?->code,
                        $risk->asset->nama_aset,
                        $risk->asset->assetSubsystem?->name,
                        $risk->part_number,
                        $risk->risk_event,
                        $risk->risk_cause,
                        $risk->impact,
                        $risk->recommendation,
                        $risk->likelihood,
                        $risk->consequence,
                        $risk->rating,
                        $risk->status->value,
                        $risk->source_key ? 'Excel LxC' : 'Manual',
                    ],
                )
                ->values()
                ->all(),
        ];
    }

    /** @return array{string, list<string>, list<list<mixed>>} */
    private function reliability(User $user, ?UnitKerja $unit): array
    {
        $summaries = ReliabilitySummary::query()
            ->visibleTo($user)
            ->when(
                $unit,
                fn (Builder $query): Builder => $query->whereHas(
                    'asset',
                    fn (Builder $assets): Builder => $assets->where('unit_kerja_id', $unit->id),
                ),
            )
            ->with(['asset.unitKerja:id,code,name', 'asset.assetSubsystem:id,name'])
            ->latest('period')
            ->get();

        return [
            'Reliability',
            [
                'Unit',
                'Aset',
                'Subsystem',
                'Periode',
                'Jumlah Unit',
                'Operating Hours',
                'Downtime',
                'Failure',
                'MTTF',
                'MTBF',
                'MTTR',
                'Failure Rate',
                'Reliability',
                'Availability',
                'Parity',
            ],
            $summaries
                ->map(
                    fn (ReliabilitySummary $summary): array => [
                        $summary->asset->unitKerja?->code,
                        $summary->asset->nama_aset,
                        $summary->asset->assetSubsystem?->name,
                        $summary->period?->format('Y-m'),
                        $summary->unit_count,
                        $summary->operating_hours,
                        $summary->downtime_value,
                        $summary->failure_count,
                        $summary->mttf_hours,
                        $summary->mtbf_hours,
                        $summary->mttr_hours,
                        $summary->failure_rate,
                        $summary->reliability,
                        $summary->availability,
                        $summary->parity_status,
                    ],
                )
                ->values()
                ->all(),
        ];
    }

    private function format(Worksheet $sheet, int $columnCount, int $lastRow): void
    {
        $lastColumn = $sheet->getCell([$columnCount, 1])->getColumn();
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '171650']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);
        for ($column = 1; $column <= $columnCount; $column++) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }
    }
}
