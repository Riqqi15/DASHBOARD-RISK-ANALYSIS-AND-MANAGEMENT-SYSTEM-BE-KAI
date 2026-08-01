<?php

namespace App\Models;

use App\Enums\UnitType;
use Database\Factories\UnitKerjaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'type', 'is_active'])]
class UnitKerja extends Model
{
    /** @use HasFactory<UnitKerjaFactory> */
    use HasFactory, SoftDeletes;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function openings(): HasMany
    {
        return $this->unitSubsystemOpenings();
    }

    public function unitSubsystemOpenings(): HasMany
    {
        return $this->hasMany(UnitSubsystemOpening::class);
    }

    public function stocks(): HasMany
    {
        return $this->inventoryStocks();
    }

    public function inventoryStocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function sparePartPolicies(): HasMany
    {
        return $this->hasMany(UnitSparePartPolicy::class);
    }

    public function movements(): HasMany
    {
        return $this->stockMovements();
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    protected function casts(): array
    {
        return [
            'type' => UnitType::class,
            'is_active' => 'boolean',
        ];
    }
}
