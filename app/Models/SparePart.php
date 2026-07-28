<?php

namespace App\Models;

use Database\Factories\SparePartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'asset_subsystem_id',
    'code',
    'source_key',
    'equipment',
    'detail_equipment',
    'max_yearly_failure',
    'average_yearly_failure',
    'max_lead_time_months',
    'average_lead_time_months',
    'safety_stock',
    'lead_time_demand',
    'reorder_point',
    'severity',
    'unit_of_measure',
    'is_active',
])]
class SparePart extends Model
{
    /** @use HasFactory<SparePartFactory> */
    use HasFactory, SoftDeletes;

    public function assetSubsystem(): BelongsTo
    {
        return $this->belongsTo(AssetSubsystem::class);
    }

    public function inventoryStocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
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
            'is_active' => 'boolean',
        ];
    }
}
