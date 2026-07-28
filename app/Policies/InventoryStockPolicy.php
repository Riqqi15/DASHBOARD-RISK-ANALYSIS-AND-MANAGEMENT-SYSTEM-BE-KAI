<?php

namespace App\Policies;

use App\Models\InventoryStock;
use App\Models\User;

class InventoryStockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active
            && ($user->isPusat() || ($user->isUnit() && $user->unit_kerja_id !== null));
    }

    public function view(User $user, InventoryStock $stock): bool
    {
        return $this->belongsToVisibleUnit($user, $stock->unit_kerja_id);
    }

    public function createMovement(User $user, InventoryStock $stock): bool
    {
        return $this->belongsToVisibleUnit($user, $stock->unit_kerja_id);
    }

    private function belongsToVisibleUnit(User $user, int $unitKerjaId): bool
    {
        return $user->is_active
            && ($user->isPusat() || ($user->isUnit() && $user->unit_kerja_id === $unitKerjaId));
    }
}
