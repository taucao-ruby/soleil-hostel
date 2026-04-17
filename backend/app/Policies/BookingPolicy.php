<?php

namespace App\Policies;

use App\Enums\BookingStatus;
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
     *
     * Two-stage gate:
     *   1. Ownership / admin check (who).
     *   2. Lifecycle gate (state machine): only PENDING and CONFIRMED bookings
     *      may be edited. CANCELLED, REFUND_PENDING, and REFUND_FAILED are
     *      post-cancellation states whose dates/room/guest contact must be
     *      immutable; allowing edits would corrupt refund accounting and
     *      violate the BookingStatus state machine.
     *
     * Admins are not exempt from the lifecycle gate. Admin edits to a
     * cancelled booking would be silent state-machine violations; admins
     * must explicitly cancel/restore via dedicated endpoints instead.
     */
    public function update(User $user, Booking $booking): bool
    {
        if (! $user->isAdmin() && $user->id !== $booking->user_id) {
            return false;
        }

        return $booking->status->isActive();
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
     *
     * NOTE: This policy method is NOT invoked by AdminBookingController,
     * which uses Gate::authorize('view-all-bookings') instead (allows moderator+).
     * This method is retained for completeness but is currently latent.
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

    /**
     * Determine whether the user can confirm a pending booking.
     * Only admins can confirm bookings.
     */
    public function confirm(User $user, Booking $booking): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can cancel a booking.
     *
     * Rules:
     * 1. User must own the booking OR be an admin
     * 2. Booking must be in a cancellable state (pending, confirmed, or refund_failed)
     * 3. Already cancelled bookings return true for idempotency
     * 4. Cannot cancel after check-in has started (unless admin or config allows)
     */
    public function cancel(User $user, Booking $booking): bool
    {
        // Check ownership
        $isOwner = $user->id === $booking->user_id;
        $isAdmin = $user->isAdmin();

        if (! $isOwner && ! $isAdmin) {
            return false;
        }

        // Allow re-cancellation for idempotency (returns existing state)
        if ($booking->status === BookingStatus::CANCELLED) {
            return true;
        }

        // Check if booking is in cancellable state
        if (! $booking->status->isCancellable()) {
            return false;
        }

        // Regular users cannot cancel after check-in has started
        if (! $isAdmin && $booking->isStarted()) {
            return config('booking.cancellation.allow_after_checkin', false);
        }

        return true;
    }

    /**
     * Determine whether the user can force cancel a booking.
     * Force cancel bypasses refund and sets booking to cancelled immediately.
     * Only admins can force cancel.
     */
    public function forceCancel(User $user, Booking $booking): bool
    {
        return $user->isAdmin() && ! $booking->status->isTerminal();
    }
}
