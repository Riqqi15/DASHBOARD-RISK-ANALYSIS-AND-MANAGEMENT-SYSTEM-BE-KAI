<?php

namespace App\Policies;

use App\Models\AssetSubsystem;
use App\Models\User;

class AssetSubsystemPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isPusat() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, AssetSubsystem $assetSubsystem): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AssetSubsystem $assetSubsystem): bool
    {
        return false;
    }

    public function delete(User $user, AssetSubsystem $assetSubsystem): bool
    {
        return false;
    }

    public function status(User $user, AssetSubsystem $assetSubsystem): bool
    {
        return false;
    }
}
