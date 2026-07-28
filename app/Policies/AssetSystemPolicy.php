<?php

namespace App\Policies;

use App\Models\AssetSystem;
use App\Models\User;

class AssetSystemPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isPusat() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, AssetSystem $assetSystem): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AssetSystem $assetSystem): bool
    {
        return false;
    }

    public function delete(User $user, AssetSystem $assetSystem): bool
    {
        return false;
    }

    public function status(User $user, AssetSystem $assetSystem): bool
    {
        return false;
    }
}
