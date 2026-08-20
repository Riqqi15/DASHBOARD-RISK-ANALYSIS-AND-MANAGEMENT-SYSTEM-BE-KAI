<?php

namespace App\Models;

use App\Enums\StockDirection;
use App\Enums\StockMovementType;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'unit_kerja_id',
    'spare_part_id',
    'actor_id',
    'type',
    'direction',
    'quantity',
    'stock_before',
    'stock_after',
    'movement_date',
    'reference_number',
    'notes',
    'reverses_movement_id',
    'idempotency_key',
])]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    public function forceDelete(): never
    {
        throw new LogicException('Ledger mutasi stok bersifat immutable.');
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function reversesMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_movement_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'reverses_movement_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when(
            $user->isUnit(),
            fn (Builder $visible): Builder => $visible->where(
                $visible->getModel()->qualifyColumn('unit_kerja_id'),
                $user->unit_kerja_id,
            ),
        );
    }

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'direction' => StockDirection::class,
            'quantity' => 'integer',
            'stock_before' => 'integer',
            'stock_after' => 'integer',
            'movement_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Ledger mutasi stok bersifat immutable.');
        });
        static::deleting(function (): never {
            throw new LogicException('Ledger mutasi stok bersifat immutable.');
        });
    }
}
