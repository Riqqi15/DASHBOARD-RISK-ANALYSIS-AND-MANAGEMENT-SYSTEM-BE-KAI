<?php

namespace Database\Seeders;

use App\Enums\AssetStatus;
use App\Enums\RiskRegisterStatus;
use App\Enums\StockDirection;
use App\Enums\StockMovementType;
use App\Models\Asset;
use App\Models\AssetSubsystem;
use App\Models\FailureLog;
use App\Models\InventoryStock;
use App\Models\ReliabilitySummary;
use App\Models\RiskMatrix;
use App\Models\RiskRegister;
use App\Models\SparePart;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\AssetCategoryResolver;
use App\Services\StockMovementService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RamsOperationalDataSeeder extends Seeder
{
    /** @var array<string, Asset> */
    private array $assetsByKey = [];

    /** @var array<string, SparePart> */
    private array $partsByCode = [];

    public function run(AssetCategoryResolver $categoryResolver, StockMovementService $stockMovementService): void
    {
        $this->call(UnitKerjaSeeder::class);

        $actor = User::query()->where('role', 'pusat')->where('is_active', true)->first();
        if (! $actor) {
            throw new RuntimeException('RamsOperationalDataSeeder requires an active pusat user for auditable opening stock.');
        }

        DB::transaction(function () use ($categoryResolver, $stockMovementService, $actor): void {
            $this->seedAssets($categoryResolver);
            $this->seedRiskMatrices();
            $this->seedRiskRegisters();
            $this->seedReliabilitySummaries();
            $this->seedSpareParts($categoryResolver, $stockMovementService, $actor);
            $this->seedFailureLogs($actor);
        }, 3);
    }

    private function seedAssets(AssetCategoryResolver $categoryResolver): void
    {
        $unitCodes = ['DAOP1' => 'DAOP-1', 'DIVRE4' => 'DIVRE-IV'];

        foreach ($this->assetRows() as $row) {
            $unit = UnitKerja::query()->where('code', $unitCodes[$row['unit']])->firstOrFail();
            $category = $categoryResolver->resolve(
                $row['group'],
                $row['system'],
                $row['subsystem'],
                'RAMS frontend seed',
                'dummy-rams.repository.js',
            );
            $sourceKey = $this->sourceKey('asset', $row['unit'], $row['group'], $row['system'], $row['subsystem'], $row['location']);
            $asset = Asset::query()->updateOrCreate(
                ['source_key' => $sourceKey],
                [
                    'unit_kerja_id' => $unit->id,
                    'asset_subsystem_id' => $category['subsystem']->id,
                    'nama_aset' => $row['subsystem'],
                    'aset_prasarana_sintel' => $row['group'],
                    'system' => $row['system'],
                    'subsystem' => $row['subsystem'],
                    'jumlah_unit' => $row['quantity'],
                    'tanggal_pemasangan' => $row['installed_at'],
                    'status' => AssetStatus::Aktif,
                ],
            );

            $this->assetsByKey[$this->assetKey($row['unit'], $row['subsystem'])] = $asset;
        }
    }

    private function seedRiskMatrices(): void
    {
        foreach ([
            ['DAOP1', 'INTERLOCKING ELEKTRIK', 3, 4],
            ['DAOP1', 'Track Circuit', 3, 3],
            ['DAOP1', 'PENGGERAK WESEL ELEKTRIK', 2, 3],
            ['DAOP1', 'PERAGA SINYAL ELEKTRIK UTAMA', 2, 2],
            ['DAOP1', 'Axle Counter', 1, 2],
            ['DIVRE4', 'INTERLOCKING MEKANIK', 4, 4],
            ['DIVRE4', 'KONTAK DETEKSI', 4, 3],
            ['DIVRE4', 'PENGGERAK WESEL ELEKTRIK', 3, 3],
            ['DIVRE4', 'PERAGA SINYAL ELEKTRIK UTAMA', 2, 2],
            ['DIVRE4', 'CATU DAYA SINYAL', 1, 3],
        ] as [$unit, $subsystem, $likelihood, $consequence]) {
            $asset = $this->asset($unit, $subsystem);
            RiskMatrix::query()->updateOrCreate(
                ['asset_id' => $asset->id],
                [
                    'likelihood' => $likelihood,
                    'consequence' => $consequence,
                    'assessed_at' => '2026-07-31 23:59:59',
                ],
            );
        }
    }

    private function seedRiskRegisters(): void
    {
        foreach ([
            ['DAOP1', 'INTERLOCKING ELEKTRIK', 'SIL-01', 'Interlocking Failure', 'PLC Failure, usia komponen > 5 tahun', 'Penggantian modul PLC', RiskRegisterStatus::Open],
            ['DAOP1', 'PERAGA SINYAL ELEKTRIK UTAMA', 'PSE-01', 'Sinyal Padam', 'Kabel putus akibat korosi', 'Perbaikan kabel instalasi', RiskRegisterStatus::InProgress],
            ['DAOP1', 'PENGGERAK WESEL ELEKTRIK', 'PWE-01', 'Wesel Tidak Mengunci', 'Clamp lock terganjal batu kerikil', 'Pembersihan area wesel rutin', RiskRegisterStatus::Closed],
            ['DAOP1', 'Track Circuit', 'TCR-01', 'Track Circuit Failure', 'Isolasi rel rusak, arus bocor', 'Ganti isolasi sambungan rel', RiskRegisterStatus::Open],
            ['DAOP1', 'CATU DAYA SINYAL', 'CDS-01', 'Power Supply Failure', 'Baterai UPS drop, usia > 3 tahun', 'Penggantian baterai UPS', RiskRegisterStatus::InProgress],
            ['DIVRE4', 'INTERLOCKING MEKANIK', 'SIM-01', 'Interlocking Mekanik Macet', 'Pelumasan kurang, komponen berkarat', 'Overhaul mekanisme interlocking', RiskRegisterStatus::Open],
            ['DIVRE4', 'KONTAK DETEKSI', 'KTD-01', 'Kontak Deteksi Tidak Respon', 'Kontak kotor/aus', 'Pembersihan dan penggantian pedal kontak', RiskRegisterStatus::InProgress],
            ['DIVRE4', 'PENGGERAK WESEL ELEKTRIK', 'PWE-02', 'Wesel Tidak Mengunci', 'Penggerak wesel elektrik aus', 'Penggantian motor penggerak', RiskRegisterStatus::Open],
        ] as [$unit, $subsystem, $partNumber, $event, $cause, $recommendation, $status]) {
            $asset = $this->asset($unit, $subsystem);
            RiskRegister::query()->updateOrCreate(
                ['asset_id' => $asset->id, 'risk_event' => $event],
                [
                    'part_number' => $partNumber,
                    'risk_cause' => $cause,
                    'recommendation' => $recommendation,
                    'status' => $status,
                ],
            );
        }
    }

    private function seedReliabilitySummaries(): void
    {
        foreach ([
            ['DAOP1', 'INTERLOCKING ELEKTRIK', 1488, 12, 2, 738, 6, 0.0014, 0.9986, 0.9919],
            ['DAOP1', 'Track Circuit', 60264, 8, 1, 60256, 8, 0.00002, 0.99998, 0.9999],
            ['DAOP1', 'Axle Counter', 88536, 4, 1, 88532, 4, 0.00001, 0.99999, 0.99995],
            ['DIVRE4', 'PERAGA SINYAL ELEKTRIK UTAMA', 744, 3, 1, 741, 3, 0.0013, 0.9987, 0.9960],
            ['DIVRE4', 'INTERLOCKING MEKANIK', 14136, 48, 3, 4696, 16, 0.0002, 0.9998, 0.9966],
        ] as [$unit, $subsystem, $operatingHours, $downtimeHours, $failures, $mtbf, $mttr, $rate, $reliability, $availability]) {
            $asset = $this->asset($unit, $subsystem);
            ReliabilitySummary::query()->updateOrCreate(
                ['asset_id' => $asset->id, 'period' => '2026-07-01'],
                [
                    'operating_minutes' => $operatingHours * 60,
                    'downtime_minutes' => $downtimeHours * 60,
                    'failure_count' => $failures,
                    'mttf_hours' => $failures > 1 ? $mtbf : null,
                    'mtbf_hours' => $mtbf,
                    'mttr_hours' => $mttr,
                    'failure_rate' => $rate,
                    'reliability' => $reliability,
                    'availability' => $availability,
                    'calculation_status' => 'calculated',
                    'formula_version' => 'kai-rams-v1.0.0',
                    'calculated_at' => '2026-07-31 23:59:59',
                ],
            );
        }
    }

    private function seedSpareParts(
        AssetCategoryResolver $categoryResolver,
        StockMovementService $stockMovementService,
        User $actor,
    ): void {
        $unit = UnitKerja::query()->where('code', 'DAOP-1')->firstOrFail();

        foreach ($this->sparePartRows() as $row) {
            $subsystem = $this->subsystemForPart($categoryResolver, $row['subsystem']);
            $part = SparePart::withTrashed()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'asset_subsystem_id' => $subsystem->id,
                    'source_key' => $this->sourceKey('spare-part', $row['code']),
                    'equipment' => $row['subsystem'],
                    'detail_equipment' => $row['name'],
                    'max_yearly_failure' => null,
                    'average_yearly_failure' => null,
                    'max_lead_time_months' => null,
                    'average_lead_time_months' => null,
                    'safety_stock' => null,
                    'lead_time_demand' => null,
                    'reorder_point' => null,
                    'reorder_calculation_status' => 'insufficient_data',
                    'reorder_formula_version' => 'kai-reorder-v1.0.0',
                    'reorder_calculated_at' => null,
                    'unit_of_measure' => 'unit',
                    'is_active' => true,
                    'deleted_at' => null,
                ],
            );
            $this->partsByCode[$row['code']] = $part;

            InventoryStock::query()->firstOrCreate(
                ['unit_kerja_id' => $unit->id, 'spare_part_id' => $part->id],
                ['quantity' => 0],
            );

            if ($row['stock'] < 1) {
                continue;
            }

            $stockMovementService->record(
                unit: $unit,
                part: $part,
                actor: $actor,
                type: StockMovementType::Opening,
                direction: StockDirection::In,
                quantity: $row['stock'],
                movementDate: CarbonImmutable::parse('2026-07-01'),
                referenceNumber: 'RAMS-SEED-OPENING',
                notes: 'Saldo awal hasil pemindahan data statis frontend.',
                idempotencyKey: $this->deterministicUuid('opening|DAOP-1|'.$row['code']),
            );
        }
    }

    private function seedFailureLogs(User $actor): void
    {
        foreach ($this->failureRows() as $row) {
            $asset = $this->asset($row['unit'], $row['subsystem']);
            $part = $row['part_code'] ? ($this->partsByCode[$row['part_code']] ?? null) : null;
            $startedAt = CarbonImmutable::parse($row['started_at']);
            $resolvedAt = CarbonImmutable::parse($row['resolved_at']);
            $sourceKey = $this->sourceKey('failure-log', $row['unit'], $row['subsystem'], $row['started_at'], $row['event']);

            FailureLog::query()->updateOrCreate(
                ['source_key' => $sourceKey],
                [
                    'asset_id' => $asset->id,
                    'spare_part_id' => $part?->id,
                    'created_by' => $actor->id,
                    'location' => $row['location'],
                    'resort' => $row['resort'],
                    'failure_event' => $row['event'],
                    'cause' => $row['cause'],
                    'action_taken' => $row['action'],
                    'started_at' => $startedAt,
                    'resolved_at' => $resolvedAt,
                    'downtime_minutes' => (int) $startedAt->diffInMinutes($resolvedAt),
                    'spare_part_replaced' => $row['part_code'] !== null,
                    'spare_part_quantity' => $row['part_code'] !== null ? 1 : null,
                    'vandalism' => $row['vandalism'],
                ],
            );
        }
    }

    private function subsystemForPart(AssetCategoryResolver $resolver, string $subsystemName): AssetSubsystem
    {
        foreach ($this->assetRows() as $row) {
            if (mb_strtolower($row['subsystem']) !== mb_strtolower($subsystemName)) {
                continue;
            }

            return $resolver->resolve(
                $row['group'],
                $row['system'],
                $row['subsystem'],
                'RAMS frontend seed',
                'inventory-static-data',
            )['subsystem'];
        }

        throw new RuntimeException("No seeded asset subsystem matches spare part subsystem {$subsystemName}.");
    }

    private function asset(string $unit, string $subsystem): Asset
    {
        return $this->assetsByKey[$this->assetKey($unit, $subsystem)]
            ?? throw new RuntimeException("Seed asset not found for {$unit} / {$subsystem}.");
    }

    private function assetKey(string $unit, string $subsystem): string
    {
        return mb_strtolower($unit.'|'.$subsystem);
    }

    private function sourceKey(string ...$parts): string
    {
        return hash('sha256', implode('|', array_map(fn (string $part): string => mb_strtolower(trim($part)), $parts)));
    }

    private function deterministicUuid(string $value): string
    {
        $hex = md5($value);

        return sprintf(
            '%s-%s-5%s-%s%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 13, 3),
            dechex((hexdec($hex[16]) & 0x3) | 0x8),
            substr($hex, 17, 3),
            substr($hex, 20, 12),
        );
    }

    /** @return array<int, array<string, int|string>> */
    private function assetRows(): array
    {
        $definitions = [
            ['1. PERALATAN DALAM SINYAL ELEKTRIK', 'INTERLOCKING ELEKTRIK', 'INTERLOCKING ELEKTRIK', 2, '2018-01-01'],
            ['2. PERALATAN LUAR SINYAL ELEKTRIK', 'PERAGA SINYAL ELEKTRIK', 'PERAGA SINYAL ELEKTRIK UTAMA', 51, '2015-06-01'],
            ['2. PERALATAN LUAR SINYAL ELEKTRIK', 'PERAGA SINYAL ELEKTRIK', 'PERAGA SINYAL ELEKTRIK PEMBANTU', 12, '2015-06-01'],
            ['2. PERALATAN LUAR SINYAL ELEKTRIK', 'PERAGA SINYAL ELEKTRIK', 'PERAGA SINYAL ELEKTRIK PELENGKAP', 0, '2015-06-01'],
            ['2. PERALATAN LUAR SINYAL ELEKTRIK', 'PERAGA SINYAL ELEKTRIK', 'PENGGERAK WESEL ELEKTRIK', 63, '2016-03-01'],
            ['2. PERALATAN LUAR SINYAL ELEKTRIK', 'PERAGA SINYAL ELEKTRIK', 'Track Circuit', 81, '2018-01-01'],
            ['2. PERALATAN LUAR SINYAL ELEKTRIK', 'PERAGA SINYAL ELEKTRIK', 'Axle Counter', 119, '2019-05-01'],
            ['2. PERALATAN LUAR SINYAL ELEKTRIK', 'PERAGA SINYAL ELEKTRIK', 'PENGAMAN WESEL SETEMPAT ELEKTRIK', 0, '2017-08-01'],
            ['3. PERALATAN DALAM SINYAL MEKANIK', 'INTERLOCKING MEKANIK', 'INTERLOCKING MEKANIK', 0, '1990-01-01'],
            ['4. PERALATAN LUAR SINYAL MEKANIK', 'PERAGA SINYAL MEKANIK', 'PERAGA SINYAL MEKANIK UTAMA', 0, '1992-01-01'],
            ['4. PERALATAN LUAR SINYAL MEKANIK', 'PERAGA SINYAL MEKANIK', 'PERAGA SINYAL MEKANIK PEMBANTU', 0, '1992-01-01'],
            ['4. PERALATAN LUAR SINYAL MEKANIK', 'PERAGA SINYAL MEKANIK', 'PERAGA SINYAL MEKANIK PELENGKAP', 0, '1992-01-01'],
            ['4. PERALATAN LUAR SINYAL MEKANIK', 'PENGGERAK WESEL MEKANIK', 'PENGGERAK WESEL MEKANIK', 0, '1995-01-01'],
            ['4. PERALATAN LUAR SINYAL MEKANIK', 'PENGONTROL DAN PETUNJUK KEDUDUKAN WESEL MEKANIK', 'PENGONTROL DAN PETUNJUK KEDUDUKAN WESEL MEKANIK', 0, '1995-01-01'],
            ['4. PERALATAN LUAR SINYAL MEKANIK', 'PENGAMAN WESEL SETEMPAT MEKANIK', 'PENGAMAN WESEL SETEMPAT MEKANIK', 0, '1995-01-01'],
            ['4. PERALATAN LUAR SINYAL MEKANIK', 'KONTAK DETEKSI', 'KONTAK DETEKSI', 0, '2005-01-01'],
            ['5. CATU DAYA SINTEL', 'CATU DAYA SINYAL', 'CATU DAYA SINYAL', 3, '2019-01-01'],
        ];

        $rows = [];
        foreach ([
            ['unit' => 'DAOP1', 'location' => 'Daop 1 Jakarta', 'quantities' => null],
            ['unit' => 'DIVRE4', 'location' => 'Divre IV Tanjungkarang', 'quantities' => [1, 7, 23, 9, 10, 13, 12, 11, 19, 22, 23, 24, 25, 26, 27, 28, 84]],
        ] as $area) {
            foreach ($definitions as $index => [$group, $system, $subsystem, $quantity, $installedAt]) {
                $rows[] = [
                    'unit' => $area['unit'],
                    'group' => $group,
                    'system' => $system,
                    'subsystem' => $subsystem,
                    'location' => $area['location'],
                    'quantity' => $area['quantities'][$index] ?? $quantity,
                    'installed_at' => $area['unit'] === 'DIVRE4' && $subsystem === 'CATU DAYA SINYAL' ? '2010-01-01' : $installedAt,
                ];
            }
        }

        return $rows;
    }

    /** @return array<int, array{code: string, name: string, subsystem: string, stock: int}> */
    private function sparePartRows(): array
    {
        return [
            ['code' => 'SP-TC-001', 'name' => 'Relay Track', 'subsystem' => 'Track Circuit', 'stock' => 12],
            ['code' => 'SP-TC-004', 'name' => 'Resistor 10 Ohm', 'subsystem' => 'Track Circuit', 'stock' => 120],
            ['code' => 'SP-IE-001', 'name' => 'Modul CPU Interlocking', 'subsystem' => 'INTERLOCKING ELEKTRIK', 'stock' => 1],
            ['code' => 'SP-IE-002', 'name' => 'Relay 24V DC', 'subsystem' => 'INTERLOCKING ELEKTRIK', 'stock' => 3],
            ['code' => 'SP-SU-001', 'name' => 'Lampu LED Sinyal', 'subsystem' => 'PERAGA SINYAL ELEKTRIK UTAMA', 'stock' => 35],
            ['code' => 'SP-PM-001', 'name' => 'Kawat Tarik Wesel', 'subsystem' => 'PENGGERAK WESEL MEKANIK', 'stock' => 50],
            ['code' => 'SP-SU-002', 'name' => 'Lensa Sinyal', 'subsystem' => 'PERAGA SINYAL ELEKTRIK UTAMA', 'stock' => 2],
            ['code' => 'SP-PE-001', 'name' => 'Motor Point Wesel', 'subsystem' => 'PENGGERAK WESEL ELEKTRIK', 'stock' => 2],
            ['code' => 'SP-TC-002', 'name' => 'Kabel Konektor', 'subsystem' => 'Track Circuit', 'stock' => 45],
            ['code' => 'SP-TC-003', 'name' => 'Isolasi Rel', 'subsystem' => 'Track Circuit', 'stock' => 8],
            ['code' => 'SP-TC-005', 'name' => 'Kapasitor', 'subsystem' => 'Track Circuit', 'stock' => 3],
            ['code' => 'SP-AC-001', 'name' => 'Sensor Roda', 'subsystem' => 'Axle Counter', 'stock' => 5],
            ['code' => 'SP-AC-002', 'name' => 'Modul Evaluator', 'subsystem' => 'Axle Counter', 'stock' => 2],
            ['code' => 'SP-AC-003', 'name' => 'Kabel Transmisi', 'subsystem' => 'Axle Counter', 'stock' => 30],
            ['code' => 'SP-AC-004', 'name' => 'Surge Protector', 'subsystem' => 'Axle Counter', 'stock' => 15],
            ['code' => 'SP-IM-001', 'name' => 'Kawat Tarik', 'subsystem' => 'INTERLOCKING MEKANIK', 'stock' => 50],
            ['code' => 'SP-IM-002', 'name' => 'Handle Mekanik', 'subsystem' => 'INTERLOCKING MEKANIK', 'stock' => 4],
            ['code' => 'SP-IM-003', 'name' => 'Roda Gigi', 'subsystem' => 'INTERLOCKING MEKANIK', 'stock' => 8],
            ['code' => 'SP-IM-004', 'name' => 'Rantai', 'subsystem' => 'INTERLOCKING MEKANIK', 'stock' => 12],
            ['code' => 'SP-IM-005', 'name' => 'Tuas Sentral', 'subsystem' => 'INTERLOCKING MEKANIK', 'stock' => 2],
            ['code' => 'SP-IM-006', 'name' => 'Bandul', 'subsystem' => 'INTERLOCKING MEKANIK', 'stock' => 6],
            ['code' => 'SP-IE-003', 'name' => 'Power Supply', 'subsystem' => 'INTERLOCKING ELEKTRIK', 'stock' => 3],
            ['code' => 'SP-IE-004', 'name' => 'Konektor I/O', 'subsystem' => 'INTERLOCKING ELEKTRIK', 'stock' => 40],
            ['code' => 'SP-IE-005', 'name' => 'Fuse', 'subsystem' => 'INTERLOCKING ELEKTRIK', 'stock' => 100],
            ['code' => 'SP-PM-002', 'name' => 'Roda Kawat', 'subsystem' => 'PENGGERAK WESEL MEKANIK', 'stock' => 15],
            ['code' => 'SP-PM-003', 'name' => 'Pena Wesel', 'subsystem' => 'PENGGERAK WESEL MEKANIK', 'stock' => 7],
            ['code' => 'SP-PM-004', 'name' => 'Pelumas', 'subsystem' => 'PENGGERAK WESEL MEKANIK', 'stock' => 20],
            ['code' => 'SP-PE-002', 'name' => 'Kontak Wesel', 'subsystem' => 'PENGGERAK WESEL ELEKTRIK', 'stock' => 10],
            ['code' => 'SP-PE-003', 'name' => 'Kabel Motor', 'subsystem' => 'PENGGERAK WESEL ELEKTRIK', 'stock' => 25],
            ['code' => 'SP-PE-004', 'name' => 'Limit Switch', 'subsystem' => 'PENGGERAK WESEL ELEKTRIK', 'stock' => 14],
            ['code' => 'SP-SU-003', 'name' => 'Kabel Sinyal', 'subsystem' => 'PERAGA SINYAL ELEKTRIK UTAMA', 'stock' => 60],
            ['code' => 'SP-SU-004', 'name' => 'Tiang Sinyal', 'subsystem' => 'PERAGA SINYAL ELEKTRIK UTAMA', 'stock' => 2],
            ['code' => 'SP-SP-001', 'name' => 'Lampu LED', 'subsystem' => 'PERAGA SINYAL ELEKTRIK PEMBANTU', 'stock' => 40],
            ['code' => 'SP-SP-002', 'name' => 'Kabel', 'subsystem' => 'PERAGA SINYAL ELEKTRIK PEMBANTU', 'stock' => 60],
            ['code' => 'SP-SP-003', 'name' => 'Reflektor', 'subsystem' => 'PERAGA SINYAL ELEKTRIK PEMBANTU', 'stock' => 22],
            ['code' => 'SP-PS-001', 'name' => 'Gembok Wesel', 'subsystem' => 'PENGAMAN WESEL SETEMPAT MEKANIK', 'stock' => 15],
            ['code' => 'SP-PS-002', 'name' => 'Rantai', 'subsystem' => 'PENGAMAN WESEL SETEMPAT MEKANIK', 'stock' => 12],
            ['code' => 'SP-PS-003', 'name' => 'Kunci Sentral', 'subsystem' => 'PENGAMAN WESEL SETEMPAT MEKANIK', 'stock' => 4],
            ['code' => 'SP-KD-001', 'name' => 'Limit Switch', 'subsystem' => 'KONTAK DETEKSI', 'stock' => 14],
            ['code' => 'SP-KD-002', 'name' => 'Kabel Kontak', 'subsystem' => 'KONTAK DETEKSI', 'stock' => 25],
            ['code' => 'SP-KD-003', 'name' => 'Tuas Kontak', 'subsystem' => 'KONTAK DETEKSI', 'stock' => 8],
        ];
    }

    /** @return array<int, array<string, bool|string|null>> */
    private function failureRows(): array
    {
        return [
            ['unit' => 'DAOP1', 'subsystem' => 'INTERLOCKING ELEKTRIK', 'location' => 'Stasiun Manggarai', 'resort' => 'Resor 1.1 Manggarai', 'event' => 'Modul CPU PLC Error', 'cause' => 'Overheat pada ruang sintel', 'action' => 'Reset PLC dan perbaikan ventilasi', 'started_at' => '2026-07-05 08:15:00', 'resolved_at' => '2026-07-05 14:15:00', 'part_code' => null, 'vandalism' => false],
            ['unit' => 'DAOP1', 'subsystem' => 'INTERLOCKING ELEKTRIK', 'location' => 'Stasiun Manggarai', 'resort' => 'Resor 1.1 Manggarai', 'event' => 'Gangguan I/O Module', 'cause' => 'Konektor longgar', 'action' => 'Pemasangan ulang konektor', 'started_at' => '2026-07-18 22:30:00', 'resolved_at' => '2026-07-19 04:30:00', 'part_code' => null, 'vandalism' => false],
            ['unit' => 'DAOP1', 'subsystem' => 'Track Circuit', 'location' => 'Stasiun Gambir', 'resort' => 'Resor 1.2 Gambir', 'event' => 'Track Circuit Intermittent', 'cause' => 'Isolasi rel retak', 'action' => 'Penggantian isolasi', 'started_at' => '2026-07-12 03:00:00', 'resolved_at' => '2026-07-12 11:00:00', 'part_code' => 'SP-TC-003', 'vandalism' => false],
            ['unit' => 'DAOP1', 'subsystem' => 'Axle Counter', 'location' => 'Stasiun Jatinegara', 'resort' => 'Resor 1.3 Jatinegara', 'event' => 'Axle Counter Reset', 'cause' => 'Petir menyambar jalur kabel', 'action' => 'Reset sistem dan penggantian surge protector', 'started_at' => '2026-07-22 15:00:00', 'resolved_at' => '2026-07-22 19:00:00', 'part_code' => 'SP-AC-004', 'vandalism' => false],
            ['unit' => 'DIVRE4', 'subsystem' => 'PERAGA SINYAL ELEKTRIK UTAMA', 'location' => 'Stasiun Tanjungkarang', 'resort' => 'Resor 4.1 Tanjungkarang', 'event' => 'Relay Kontak Kotor', 'cause' => 'Debu dan kelembaban tinggi', 'action' => 'Pembersihan dan pelumasan relay', 'started_at' => '2026-07-15 07:00:00', 'resolved_at' => '2026-07-15 10:00:00', 'part_code' => null, 'vandalism' => false],
            ['unit' => 'DIVRE4', 'subsystem' => 'INTERLOCKING MEKANIK', 'location' => 'Stasiun Tanjungkarang', 'resort' => 'Resor 4.1 Tanjungkarang', 'event' => 'Handle Interlocking Macet', 'cause' => 'Pelumasan kurang, karat', 'action' => 'Overhaul dan pelumasan ulang', 'started_at' => '2026-07-02 06:00:00', 'resolved_at' => '2026-07-02 22:00:00', 'part_code' => null, 'vandalism' => false],
            ['unit' => 'DIVRE4', 'subsystem' => 'INTERLOCKING MEKANIK', 'location' => 'Stasiun Kedaton', 'resort' => 'Resor 4.2 Kedaton', 'event' => 'Handle Macet', 'cause' => 'Komponen berkarat, usia > 30 tahun', 'action' => 'Penggantian komponen mekanik', 'started_at' => '2026-07-10 09:00:00', 'resolved_at' => '2026-07-10 23:00:00', 'part_code' => 'SP-IM-002', 'vandalism' => false],
            ['unit' => 'DIVRE4', 'subsystem' => 'INTERLOCKING MEKANIK', 'location' => 'Stasiun Rejosari', 'resort' => 'Resor 4.3 Rejosari', 'event' => 'Kawat Interlocking Putus', 'cause' => 'Tindak vandalisme - pencurian kawat', 'action' => 'Pemasangan kawat baru dan pelaporan', 'started_at' => '2026-07-20 04:00:00', 'resolved_at' => '2026-07-20 22:00:00', 'part_code' => 'SP-IM-001', 'vandalism' => true],
        ];
    }
}
