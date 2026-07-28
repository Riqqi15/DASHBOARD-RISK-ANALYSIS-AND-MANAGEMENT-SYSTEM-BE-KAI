<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPusat() || ($user->isUnit() && $user->unit_kerja_id !== null);
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->isPusat() || $asset->unit_kerja_id === $user->unit_kerja_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Asset $asset): bool
    {
        return $this->view($user, $asset);
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $this->view($user, $asset);
    }
}
