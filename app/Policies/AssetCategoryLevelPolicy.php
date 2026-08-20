<?php

namespace App\Policies;

use App\Models\AssetCategoryLevel;
use App\Models\User;

class AssetCategoryLevelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPusat() || $user->isUnit();
    }

    public function create(User $user): bool
    {
        return $user->isPusat();
    }

    public function update(User $user, AssetCategoryLevel $level): bool
    {
        return $user->isPusat();
    }

    public function delete(User $user, AssetCategoryLevel $level): bool
    {
        return $user->isPusat();
    }
}
