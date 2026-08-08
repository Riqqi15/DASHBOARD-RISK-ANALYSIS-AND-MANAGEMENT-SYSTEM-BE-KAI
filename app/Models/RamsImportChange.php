<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'rams_import_batch_id', 'table_name', 'row_id', 'operation',
    'before_values', 'after_values', 'after_hash',
])]
final class RamsImportChange extends Model
{
    public function batch(): BelongsTo
    {
        return $this->belongsTo(RamsImportBatch::class, 'rams_import_batch_id');
    }

    protected function casts(): array
    {
        return [
            'row_id' => 'integer',
            'before_values' => 'array',
            'after_values' => 'array',
        ];
    }
}
