<?php

namespace App\Policies;

use App\Models\AssetGroup;
use App\Models\User;

class AssetGroupPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isPusat() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, AssetGroup $assetGroup): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AssetGroup $assetGroup): bool
    {
        return false;
    }

    public function delete(User $user, AssetGroup $assetGroup): bool
    {
        return false;
    }

    public function status(User $user, AssetGroup $assetGroup): bool
    {
        return false;
    }
}
