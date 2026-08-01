<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private array $legacyAssetSourceKeys = [
        'b66bf26817b0de73c1b595ef0e2d315b7f7272fffb25f93f0f7f27eb13f65c68',
        'b033414794b76660aef61c18157dbaff08fd4199e00decb9e46afa0cdba63be2',
    ];

    /** @var list<string> */
    private array $legacySparePartCodes = [
        'SP-TC-001', 'SP-TC-004', 'SP-IE-001', 'SP-IE-002', 'SP-SU-001', 'SP-PM-001',
        'SP-SU-002', 'SP-PE-001', 'SP-TC-002', 'SP-TC-003', 'SP-TC-005', 'SP-AC-001',
        'SP-AC-002', 'SP-AC-003', 'SP-AC-004', 'SP-IM-001', 'SP-IM-002', 'SP-IM-003',
        'SP-IM-004', 'SP-IM-005', 'SP-IM-006', 'SP-IE-003', 'SP-IE-004', 'SP-IE-005',
        'SP-PM-002', 'SP-PM-003', 'SP-PM-004', 'SP-PE-002', 'SP-PE-003', 'SP-PE-004',
        'SP-SU-003', 'SP-SU-004', 'SP-SP-001', 'SP-SP-002', 'SP-SP-003', 'SP-PS-001',
        'SP-PS-002', 'SP-PS-003', 'SP-KD-001', 'SP-KD-002', 'SP-KD-003',
    ];

    /** @var list<array{unit: string, subsystem: string, started_at: string, event: string}> */
    private array $legacyFailures = [
        ['unit' => 'DAOP1', 'subsystem' => 'INTERLOCKING ELEKTRIK', 'started_at' => '2026-07-05 08:15:00', 'event' => 'Modul CPU PLC Error'],
        ['unit' => 'DAOP1', 'subsystem' => 'INTERLOCKING ELEKTRIK', 'started_at' => '2026-07-18 22:30:00', 'event' => 'Gangguan I/O Module'],
        ['unit' => 'DAOP1', 'subsystem' => 'Track Circuit', 'started_at' => '2026-07-12 03:00:00', 'event' => 'Track Circuit Intermittent'],
        ['unit' => 'DAOP1', 'subsystem' => 'Axle Counter', 'started_at' => '2026-07-22 15:00:00', 'event' => 'Axle Counter Reset'],
        ['unit' => 'DIVRE4', 'subsystem' => 'PERAGA SINYAL ELEKTRIK UTAMA', 'started_at' => '2026-07-15 07:00:00', 'event' => 'Relay Kontak Kotor'],
        ['unit' => 'DIVRE4', 'subsystem' => 'INTERLOCKING MEKANIK', 'started_at' => '2026-07-02 06:00:00', 'event' => 'Handle Interlocking Macet'],
        ['unit' => 'DIVRE4', 'subsystem' => 'INTERLOCKING MEKANIK', 'started_at' => '2026-07-10 09:00:00', 'event' => 'Handle Macet'],
        ['unit' => 'DIVRE4', 'subsystem' => 'INTERLOCKING MEKANIK', 'started_at' => '2026-07-20 04:00:00', 'event' => 'Kawat Interlocking Putus'],
    ];

    public function up(): void
    {
        $now = now();

        DB::table('assets')
            ->whereIn('source_key', $this->legacyAssetSourceKeys)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('predictive_asset_snapshots')
                    ->whereColumn('predictive_asset_snapshots.asset_id', 'assets.id');
            })
            ->update(['deleted_at' => $now, 'updated_at' => $now]);

        DB::table('spare_parts')
            ->whereIn('code', $this->legacySparePartCodes)
            ->update(['is_active' => false, 'updated_at' => $now]);

        DB::table('failure_logs')
            ->whereIn('source_key', $this->legacyFailureSourceKeys())
            ->delete();

        DB::table('reliability_summaries')
            ->where('formula_version', 'kai-rams-v1.0.0')
            ->where('calculated_at', '2026-07-31 23:59:59')
            ->delete();
    }

    public function down(): void
    {
        $now = now();

        DB::table('assets')
            ->whereIn('source_key', $this->legacyAssetSourceKeys)
            ->update(['deleted_at' => null, 'updated_at' => $now]);

        DB::table('spare_parts')
            ->whereIn('code', $this->legacySparePartCodes)
            ->update(['is_active' => true, 'updated_at' => $now]);

        // Failure logs and their derived summaries were generated demo records.
        // They are intentionally not recreated during rollback.
    }

    /** @return list<string> */
    private function legacyFailureSourceKeys(): array
    {
        return array_map(
            fn (array $row): string => hash('sha256', implode('|', array_map(
                fn (string $part): string => mb_strtolower(trim($part)),
                ['failure-log', $row['unit'], $row['subsystem'], $row['started_at'], $row['event']],
            ))),
            $this->legacyFailures,
        );
    }
};
