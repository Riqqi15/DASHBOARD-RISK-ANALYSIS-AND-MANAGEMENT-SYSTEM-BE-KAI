<?php

namespace App\Models;

use Database\Factories\FailureLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asset_id',
    'spare_part_id',
    'created_by',
    'source_key',
    'idempotency_key',
    'location',
    'resort',
    'qc',
    'failure_event',
    'cause',
    'action_taken',
    'started_at',
    'resolved_at',
    'downtime_minutes',
    'spare_part_replaced',
    'spare_part_marker',
    'spare_part_quantity',
    'vandalism',
    'vandalism_marker',
    'workbook_hash',
    'workbook_name',
    'sheet_name',
    'source_row',
])]
class FailureLog extends Model
{
    /** @use HasFactory<FailureLogFactory> */
    use HasFactory;

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when(
            $user->isUnit(),
            fn (Builder $visible): Builder => $visible->whereHas(
                'asset',
                fn (Builder $assets): Builder => $assets->where('unit_kerja_id', $user->unit_kerja_id),
            ),
        );
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
            'downtime_minutes' => 'integer',
            'spare_part_replaced' => 'boolean',
            'spare_part_quantity' => 'integer',
            'vandalism' => 'boolean',
            'source_row' => 'integer',
        ];
    }
}
