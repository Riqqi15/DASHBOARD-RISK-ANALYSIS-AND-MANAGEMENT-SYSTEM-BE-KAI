<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'rams_import_batch_id', 'sheet_name', 'source_row', 'source_column',
    'severity', 'message', 'context',
])]
final class RamsImportIssue extends Model
{
    public function batch(): BelongsTo
    {
        return $this->belongsTo(RamsImportBatch::class, 'rams_import_batch_id');
    }

    protected function casts(): array
    {
        return ['context' => 'array', 'source_row' => 'integer'];
    }
}
