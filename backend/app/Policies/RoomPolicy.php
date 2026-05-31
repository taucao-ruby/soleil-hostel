<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    /**
     * Determine whether the user can view any room.
     */
    public function viewAny(User $user): bool
    {
        return true; // Everyone can list rooms
    }

    /**
     * Determine whether the user can view the room.
     */
    public function view(User $user, Room $room): bool
    {
        return true; // Everyone can view room details
    }

    /**
     * Determine whether the user can create rooms.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the room.
     */
    public function update(User $user, Room $room): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the room.
     */
    public function delete(User $user, Room $room): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update a room's operational readiness.
     *
     * Front-desk operators (moderator) and admins perform readiness transitions
     * (check-in / housekeeping). Distinct from update(), which is admin-only room
     * CRUD. SH-10 / F-63.
     */
    public function updateReadiness(User $user, Room $room): bool
    {
        return $user->isModerator(); // moderator OR admin (admin satisfies isAtLeast)
    }
}
