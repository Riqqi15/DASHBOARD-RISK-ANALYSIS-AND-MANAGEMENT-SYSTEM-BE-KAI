<?php

namespace App\Policies;

use App\Models\SparePart;
use App\Models\User;

class SparePartPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, SparePart $sparePart): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->isPusat();
    }

    public function update(User $user, SparePart $sparePart): bool
    {
        return $user->is_active && $user->isPusat();
    }

    public function delete(User $user, SparePart $sparePart): bool
    {
        return $user->is_active && $user->isPusat();
    }
}
