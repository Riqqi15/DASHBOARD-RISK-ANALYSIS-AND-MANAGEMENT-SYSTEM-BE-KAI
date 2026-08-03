<?php

namespace Tests\Feature;

use App\Models\Asset;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RamsOperationalSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_tables_contain_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('risk_matrices', [
            'id', 'asset_id', 'likelihood', 'consequence', 'assessed_at', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('risk_registers', [
            'id', 'asset_id', 'part_number', 'sub', 'risk_event', 'risk_cause', 'impact', 'part_name',
            'recommendation', 'likelihood', 'consequence', 'status', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('reliability_summaries', [
            'id', 'asset_id', 'period', 'operating_minutes', 'downtime_minutes', 'failure_count',
            'mttf_hours', 'mtbf_hours', 'mttr_hours', 'failure_rate', 'reliability', 'availability',
            'calculation_status', 'formula_version', 'calculated_at', 'created_at', 'updated_at',
            'excel_snapshot_id', 'baseline_date', 'calculation_date', 'unit_count', 'operating_hours',
            'downtime_value', 'uptime_hours', 'spare_part_replacement_count', 'vandalism_count',
            'calculation_profile', 'parity_status', 'parity_differences',
        ]));
        $this->assertTrue(Schema::hasColumns('failure_logs', [
            'id', 'asset_id', 'spare_part_id', 'created_by', 'source_key', 'idempotency_key', 'location',
            'resort', 'qc', 'failure_event', 'cause', 'action_taken', 'started_at', 'resolved_at',
            'downtime_minutes', 'spare_part_replaced', 'spare_part_quantity', 'vandalism', 'created_at', 'updated_at',
            'spare_part_marker', 'vandalism_marker', 'workbook_hash', 'workbook_name', 'sheet_name', 'source_row',
        ]));
        $this->assertTrue(Schema::hasColumns('reliability_excel_snapshots', [
            'id', 'asset_id', 'workbook_hash', 'workbook_name', 'sheet_name', 'source_row',
            'baseline_date', 'calculation_date', 'summary_values', 'summary_formulas',
            'summary_errors', 'formula_profile', 'imported_at', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('predictive_asset_snapshots', [
            'id', 'asset_id', 'source_key', 'workbook_hash', 'workbook_name', 'sheet_name', 'source_row',
            'function_criterion', 'production_impact', 'lead_time_months', 'price_category', 'current_stock',
            'total_assets', 'average_yearly_usage', 'sla_percentage', 'failure_safety_stock',
            'item_classification', 'repairable', 'installed_at', 'lifetime_years', 'vandalism_count',
            'likelihood', 'consequence', 'criticality', 'lead_time_category', 'inventory_policy',
            'needed_stock', 'proposal_quantity', 'proposal_reasonableness', 'safety_stock_usage',
            'safety_stock_mca', 'safety_stock_failure', 'final_safety_stock', 'age_years', 'age_condition',
            'lifetime_status', 'risk_rating', 'risk_level', 'calculation_status', 'formula_version',
            'calculated_at', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('unit_spare_part_policies', [
            'id', 'unit_kerja_id', 'spare_part_id', 'source_key', 'workbook_hash', 'workbook_name',
            'source_row', 'max_yearly_failure', 'average_yearly_failure', 'max_lead_time_months',
            'average_lead_time_months', 'safety_stock', 'lead_time_demand', 'reorder_point', 'severity',
            'calculation_status', 'formula_version', 'calculated_at', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('rams_import_batches', [
            'id', 'unit_kerja_id', 'fingerprint', 'import_version', 'workbook_name', 'file_size', 'status', 'dry_run',
            'summary', 'error_message', 'started_at', 'finished_at', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('rams_import_issues', [
            'id', 'rams_import_batch_id', 'sheet_name', 'source_row', 'source_column', 'severity',
            'message', 'context', 'created_at', 'updated_at',
        ]));
    }

    public function test_risk_matrix_and_reliability_summary_are_unique_per_asset_context(): void
    {
        $asset = Asset::factory()->create();
        $now = now();

        DB::table('risk_matrices')->insert([
            'asset_id' => $asset->id,
            'likelihood' => 2,
            'consequence' => 3,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->assertDuplicateRejected(fn () => DB::table('risk_matrices')->insert([
            'asset_id' => $asset->id,
            'likelihood' => 3,
            'consequence' => 4,
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        DB::table('reliability_summaries')->insert([
            'asset_id' => $asset->id,
            'period' => '2026-07-01',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->assertDuplicateRejected(fn () => DB::table('reliability_summaries')->insert([
            'asset_id' => $asset->id,
            'period' => '2026-07-01',
            'created_at' => $now,
            'updated_at' => $now,
        ]));
    }

    public function test_database_rejects_invalid_risk_values_and_failure_intervals(): void
    {
        $asset = Asset::factory()->create();
        $now = now();

        $this->assertConstraintRejected(fn () => DB::table('risk_matrices')->insert([
            'asset_id' => $asset->id,
            'likelihood' => 0,
            'consequence' => 6,
            'created_at' => $now,
            'updated_at' => $now,
        ]));
        $this->assertConstraintRejected(fn () => DB::table('failure_logs')->insert([
            'asset_id' => $asset->id,
            'location' => 'Stasiun Uji',
            'failure_event' => 'Gangguan',
            'cause' => 'Pengujian',
            'action_taken' => 'Perbaikan',
            'started_at' => '2026-07-02 10:00:00',
            'resolved_at' => '2026-07-02 09:00:00',
            'downtime_minutes' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]));
    }

    private function assertDuplicateRejected(callable $operation): void
    {
        try {
            $operation();
        } catch (QueryException $exception) {
            $this->assertSame(1062, $exception->errorInfo[1] ?? null);

            return;
        }

        $this->fail('Expected duplicate data to be rejected.');
    }

    private function assertConstraintRejected(callable $operation): void
    {
        try {
            $operation();
        } catch (QueryException $exception) {
            $this->assertSame(3819, $exception->errorInfo[1] ?? null);

            return;
        }

        $this->fail('Expected invalid data to be rejected.');
    }
}
