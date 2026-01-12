<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * ReviewPolicy - Authorization for Review CRUD operations.
 * 
 * Business Rules:
 * - Create: Authenticated user must own a confirmed booking with checkout passed, no existing review.
 * - Update: Owner only.
 * - Delete: Owner OR Admin.
 * - Admin bypass: Can delete any review but CANNOT create fake reviews.
 * 
 * @see docs/backend/REVIEW_POLICY_AUTHORIZATION.md for full design rationale.
 */
class ReviewPolicy
{
    /**
     * Admin bypass for delete only. Admins CANNOT create fake reviews.
     * 
     * Return values:
     * - true: Allow (bypass further checks)
     * - false: Deny (explicit rejection)
     * - null: Defer to specific policy method
     */
    public function before(User $user, string $ability): ?bool
    {
        if (!$user->isAdmin()) {
            return null; // Non-admin: defer to specific method
        }

        // Admin can delete any review
        if ($ability === 'delete') {
            return true;
        }

        // Admin cannot create reviews (prevent fake reviews)
        if ($ability === 'create') {
            return false; // Explicit denial, not deferral
        }

        // Admin update: defer to ownership check (admins shouldn't edit others' reviews)
        return null;
    }

    /**
     * Determine if user can create a review for the given booking.
     * 
     * Requirements:
     * 1. User must own the booking
     * 2. Booking status must be CONFIRMED
     * 3. Checkout date must have passed
     * 4. No existing review for this booking (via pre-loaded relation)
     * 
     * @param User $user Authenticated user
     * @param Booking $booking Must have 'review' relation pre-loaded
     */
    public function create(User $user, Booking $booking): Response
    {
        // Ownership check: user must own the booking
        if ($booking->user_id !== $user->id) {
            return Response::deny('You do not own this booking.');
        }

        // Status check: booking must be confirmed (not pending, cancelled, etc.)
        if ($booking->status !== BookingStatus::CONFIRMED) {
            return Response::deny('Booking must be confirmed to leave a review.');
        }

        // Temporal check: checkout must have passed
        if (!$booking->check_out->isPast()) {
            return Response::deny('Cannot review before checkout date.');
        }

        // Uniqueness check: no existing review for this booking
        // Uses pre-loaded relation to avoid DB query in policy
        if ($booking->relationLoaded('review') && $booking->review !== null) {
            return Response::deny('Review already exists for this booking.');
        }

        return Response::allow();
    }

    /**
     * Determine if user can update the review.
     * 
     * Only the review author can update their review.
     * Admins defer here from before() - they cannot edit others' reviews.
     */
    public function update(User $user, Review $review): Response
    {
        if ($review->user_id !== $user->id) {
            return Response::deny('You do not own this review.');
        }

        return Response::allow();
    }

    /**
     * Determine if user can delete the review.
     * 
     * Owner can delete their own review.
     * Admins bypass this via before() hook.
     */
    public function delete(User $user, Review $review): Response
    {
        if ($review->user_id !== $user->id) {
            return Response::deny('You do not own this review.');
        }

        return Response::allow();
    }

    /**
     * Determine if user can view the review.
     * 
     * Reviews are public - anyone can view.
     */
    public function view(?User $user, Review $review): bool
    {
        return true;
    }

    /**
     * Determine if user can view any reviews.
     * 
     * Review index is public.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }
}
