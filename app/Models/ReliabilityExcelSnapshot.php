<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asset_id',
    'workbook_hash',
    'workbook_name',
    'sheet_name',
    'source_row',
    'baseline_date',
    'calculation_date',
    'summary_values',
    'summary_formulas',
    'summary_errors',
    'formula_profile',
    'imported_at',
])]
final class ReliabilityExcelSnapshot extends Model
{
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    protected function casts(): array
    {
        return [
            'source_row' => 'integer',
            'baseline_date' => 'immutable_date',
            'calculation_date' => 'immutable_date',
            'summary_values' => 'array',
            'summary_formulas' => 'array',
            'summary_errors' => 'array',
            'formula_profile' => 'array',
            'imported_at' => 'immutable_datetime',
        ];
    }
}
