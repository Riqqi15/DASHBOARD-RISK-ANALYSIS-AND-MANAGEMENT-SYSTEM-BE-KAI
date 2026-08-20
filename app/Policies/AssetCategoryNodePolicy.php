<?php

namespace App\Policies;

use App\Models\AssetCategoryNode;
use App\Models\User;

class AssetCategoryNodePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPusat() || $user->isUnit();
    }

    public function create(User $user): bool
    {
        return $user->isPusat();
    }

    public function update(User $user, AssetCategoryNode $node): bool
    {
        return $user->isPusat();
    }

    public function delete(User $user, AssetCategoryNode $node): bool
    {
        return $user->isPusat();
    }
}
