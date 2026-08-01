<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'unit_kerja_id', 'spare_part_id', 'source_key', 'workbook_hash', 'workbook_name', 'source_row',
    'max_yearly_failure', 'average_yearly_failure', 'max_lead_time_months',
    'average_lead_time_months', 'safety_stock', 'lead_time_demand', 'reorder_point',
    'severity', 'calculation_status', 'formula_version', 'calculated_at',
])]
final class UnitSparePartPolicy extends Model
{
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    protected function casts(): array
    {
        return [
            'max_yearly_failure' => 'decimal:2',
            'average_yearly_failure' => 'decimal:2',
            'max_lead_time_months' => 'decimal:2',
            'average_lead_time_months' => 'decimal:2',
            'safety_stock' => 'integer',
            'lead_time_demand' => 'integer',
            'reorder_point' => 'integer',
            'calculated_at' => 'immutable_datetime',
        ];
    }
}
