<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RamsImportBatch;
use Illuminate\Support\Facades\DB;

final class RamsImportChangeRecorder
{
    /**
     * Urutan parent ke child. Daftar eksplisit ini sengaja tidak memuat users
     * maupun tabel ledger inventori.
     *
     * @var list<string>
     */
    public const TABLES = [
        'asset_groups',
        'asset_systems',
        'asset_subsystems',
        'asset_category_source_aliases',
        'assets',
        'unit_subsystem_openings',
        'spare_parts',
        'unit_spare_part_policies',
        'predictive_asset_snapshots',
        'risk_matrices',
        'risk_registers',
        'failure_logs',
        'reliability_excel_snapshots',
        'reliability_summaries',
    ];

    /** @return array<string, array<int, array<string, mixed>>> */
    public function snapshot(): array
    {
        $snapshot = [];
        foreach (self::TABLES as $table) {
            $snapshot[$table] = DB::table($table)
                ->orderBy('id')
                ->get()
                ->mapWithKeys(function (object $row): array {
                    $values = $this->normalize((array) $row);

                    return [(int) $values['id'] => $values];
                })
                ->all();
        }

        return $snapshot;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $before
     * @param  array<string, array<int, array<string, mixed>>>  $after
     */
    public function record(RamsImportBatch $batch, array $before, array $after): void
    {
        $batch->changes()->delete();
        foreach (self::TABLES as $table) {
            $ids = array_unique([...array_keys($before[$table] ?? []), ...array_keys($after[$table] ?? [])]);
            sort($ids, SORT_NUMERIC);
            foreach ($ids as $id) {
                $old = $before[$table][$id] ?? null;
                $new = $after[$table][$id] ?? null;
                if ($old === $new) {
                    continue;
                }

                $batch->changes()->create([
                    'table_name' => $table,
                    'row_id' => $id,
                    'operation' => $old === null ? 'created' : ($new === null ? 'deleted' : 'updated'),
                    'before_values' => $old,
                    'after_values' => $new,
                    'after_hash' => $new === null ? null : $this->hash($new),
                ]);
            }
        }
    }

    /** @param array<string, mixed> $values */
    public function hash(array $values): string
    {
        return hash('sha256', json_encode($this->normalize($values), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function normalize(array $values): array
    {
        ksort($values);

        return $values;
    }
}
