<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /**
     * Determine whether the user can view the booking.
     * Admins and moderators can view any booking.
     */
    public function view(User $user, Booking $booking): bool
    {
        return $user->isAtLeast(UserRole::MODERATOR) || $user->id === $booking->user_id;
    }

    /**
     * Determine whether the user can update the booking.
     * Admins can update any booking.
     */
    public function update(User $user, Booking $booking): bool
    {
        return $user->isAdmin() || $user->id === $booking->user_id;
    }

    /**
     * Determine whether the user can delete the booking.
     * Admins can delete any booking.
     */
    public function delete(User $user, Booking $booking): bool
    {
        return $user->isAdmin() || $user->id === $booking->user_id;
    }

    /**
     * Determine whether the user can create bookings.
     */
    public function create(User $user): bool
    {
        return true; // All authenticated users can create bookings
    }

    /**
     * Determine whether the user can view any bookings (admin index).
     */
    public function viewAny(User $user): bool
    {
        return $user->isAtLeast(UserRole::MODERATOR);
    }

    /**
     * Determine whether the user can view trashed (soft deleted) bookings.
     * Only admins can view trashed bookings for audit purposes.
     */
    public function viewTrashed(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore a soft deleted booking.
     * Only admins can restore bookings.
     */
    public function restore(User $user, Booking $booking): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete a booking.
     * Only admins can force delete (for GDPR "right to be forgotten").
     */
    public function forceDelete(User $user, Booking $booking): bool
    {
        return $user->isAdmin();
    }
}
