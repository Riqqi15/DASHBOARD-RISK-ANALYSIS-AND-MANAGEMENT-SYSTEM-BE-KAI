<?php

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;

class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active
            && ($user->isPusat() || ($user->isUnit() && $user->unit_kerja_id !== null));
    }

    public function view(User $user, StockMovement $movement): bool
    {
        return $this->belongsToVisibleUnit($user, $movement->unit_kerja_id);
    }

    public function correct(User $user, StockMovement $movement): bool
    {
        return $this->belongsToVisibleUnit($user, $movement->unit_kerja_id);
    }

    public function update(User $user, StockMovement $movement): bool
    {
        return false;
    }

    public function delete(User $user, StockMovement $movement): bool
    {
        return false;
    }

    private function belongsToVisibleUnit(User $user, int $unitKerjaId): bool
    {
        return $user->is_active
            && ($user->isPusat() || ($user->isUnit() && $user->unit_kerja_id === $unitKerjaId));
    }
}
