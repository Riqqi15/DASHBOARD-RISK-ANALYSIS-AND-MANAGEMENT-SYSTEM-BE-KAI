<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\ReliabilitySummary;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReliabilitySummary> */
class ReliabilitySummaryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'period' => now()->startOfMonth(),
            'excel_snapshot_id' => null,
            'baseline_date' => now()->subMonth()->startOfDay(),
            'calculation_date' => now()->startOfDay(),
            'unit_count' => 1,
            'operating_minutes' => 44640,
            'downtime_minutes' => 120,
            'operating_hours' => 744,
            'downtime_value' => 2,
            'uptime_hours' => 742,
            'failure_count' => 2,
            'mttf_hours' => 360,
            'mtbf_hours' => 371,
            'mttr_hours' => 1,
            'failure_rate' => 0.0026954178,
            'reliability' => 0.9973082112,
            'availability' => 0.9973118280,
            'spare_part_replacement_count' => 0,
            'vandalism_count' => 0,
            'calculation_profile' => ['downtime_mode' => 'hours'],
            'parity_status' => 'not_compared',
            'parity_differences' => null,
            'calculation_status' => 'calculated',
            'formula_version' => 'kai-rams-v1.0.0',
            'calculated_at' => now(),
        ];
    }
}
