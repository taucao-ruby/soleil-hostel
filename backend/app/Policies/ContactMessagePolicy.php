<?php

namespace App\Policies;

use App\Models\User;

class ContactMessagePolicy
{
    /**
     * Contact messages contain guest communications and are admin-only per
     * PERMISSION_MATRIX Table F / rows A16-A17.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function markRead(User $user): bool
    {
        return $user->isAdmin();
    }
}
