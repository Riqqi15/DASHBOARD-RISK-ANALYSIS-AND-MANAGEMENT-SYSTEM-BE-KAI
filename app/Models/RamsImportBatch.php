<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[
    Fillable([
        'unit_kerja_id',
        'uploaded_by_user_id',
        'fingerprint',
        'import_version',
        'workbook_name',
        'file_size',
        'storage_disk',
        'stored_path',
        'status',
        'progress_stage',
        'progress_percent',
        'dry_run',
        'summary',
        'error_message',
        'queued_at',
        'started_at',
        'finished_at',
        'rolled_back_by_user_id',
        'rolled_back_at',
        'rollback_error',
    ]),
]
final class RamsImportBatch extends Model
{
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(RamsImportIssue::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(RamsImportChange::class);
    }

    public function rolledBackBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rolled_back_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'progress_percent' => 'integer',
            'dry_run' => 'boolean',
            'summary' => 'array',
            'queued_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'rolled_back_at' => 'immutable_datetime',
        ];
    }
}
