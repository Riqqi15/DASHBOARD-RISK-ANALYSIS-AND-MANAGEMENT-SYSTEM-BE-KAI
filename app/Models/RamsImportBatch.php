<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'unit_kerja_id', 'fingerprint', 'import_version', 'workbook_name', 'file_size', 'status', 'dry_run',
    'summary', 'error_message', 'started_at', 'finished_at',
])]
final class RamsImportBatch extends Model
{
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(RamsImportIssue::class);
    }

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'dry_run' => 'boolean',
            'summary' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
