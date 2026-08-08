<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asset_id', 'source_key', 'workbook_hash', 'workbook_name', 'sheet_name', 'source_row',
    'function_criterion', 'production_impact', 'lead_time_months', 'price_category',
    'current_stock', 'total_assets', 'average_yearly_usage', 'sla_percentage',
    'failure_safety_stock', 'item_classification', 'repairable', 'installed_at',
    'lifetime_years', 'vandalism_count', 'likelihood', 'consequence', 'criticality',
    'lead_time_category', 'inventory_policy', 'needed_stock', 'proposal_quantity',
    'proposal_reasonableness', 'safety_stock_usage', 'safety_stock_mca',
    'safety_stock_failure', 'final_safety_stock', 'age_years', 'age_condition',
    'lifetime_status', 'risk_rating', 'risk_level', 'calculation_status',
    'excel_values', 'excel_formulas', 'parity_status', 'parity_differences',
    'formula_version', 'calculated_at',
])]
final class PredictiveAssetSnapshot extends Model
{
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    protected function casts(): array
    {
        return [
            'function_criterion' => 'integer',
            'production_impact' => 'integer',
            'lead_time_months' => 'decimal:2',
            'current_stock' => 'integer',
            'total_assets' => 'integer',
            'average_yearly_usage' => 'decimal:4',
            'sla_percentage' => 'decimal:4',
            'failure_safety_stock' => 'decimal:4',
            'repairable' => 'boolean',
            'installed_at' => 'date',
            'lifetime_years' => 'decimal:2',
            'vandalism_count' => 'integer',
            'likelihood' => 'integer',
            'consequence' => 'integer',
            'needed_stock' => 'integer',
            'proposal_quantity' => 'integer',
            'safety_stock_usage' => 'decimal:4',
            'safety_stock_mca' => 'decimal:4',
            'safety_stock_failure' => 'decimal:4',
            'final_safety_stock' => 'integer',
            'age_years' => 'decimal:4',
            'risk_rating' => 'integer',
            'excel_values' => 'array',
            'excel_formulas' => 'array',
            'parity_differences' => 'array',
            'calculated_at' => 'immutable_datetime',
        ];
    }
}
