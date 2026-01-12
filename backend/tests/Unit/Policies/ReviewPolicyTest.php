<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use App\Policies\ReviewPolicy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for ReviewPolicy.
 * 
 * These tests verify policy logic in isolation.
 * Uses fake model instances with pre-set properties and relations.
 * Extends Laravel TestCase to enable Eloquent relation methods.
 * 
 * @see docs/backend/REVIEW_POLICY_AUTHORIZATION.md
 */
class ReviewPolicyTest extends TestCase
{
    private ReviewPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ReviewPolicy();
    }

    // ===== CREATE TESTS =====

    #[Test]
    public function create_allowed_for_owner_with_confirmed_completed_booking(): void
    {
        $user = $this->makeUser(1, UserRole::USER);
        $booking = $this->makeBooking(
            userId: 1,
            status: BookingStatus::CONFIRMED,
            checkOut: Carbon::yesterday()
        );
        $booking->setRelation('review', null);

        $response = $this->policy->create($user, $booking);

        $this->assertTrue($response->allowed());
    }

    #[Test]
    public function create_denied_for_non_owner(): void
    {
        $user = $this->makeUser(1, UserRole::USER);
        $booking = $this->makeBooking(
            userId: 2, // Different user
            status: BookingStatus::CONFIRMED,
            checkOut: Carbon::yesterday()
        );
        $booking->setRelation('review', null);

        $response = $this->policy->create($user, $booking);

        $this->assertFalse($response->allowed());
        $this->assertStringContainsString('do not own this booking', $response->message());
    }

    #[Test]
    public function create_denied_for_pending_booking(): void
    {
        $user = $this->makeUser(1, UserRole::USER);
        $booking = $this->makeBooking(
            userId: 1,
            status: BookingStatus::PENDING,
            checkOut: Carbon::yesterday()
        );
        $booking->setRelation('review', null);

        $response = $this->policy->create($user, $booking);

        $this->assertFalse($response->allowed());
        $this->assertStringContainsString('must be confirmed', $response->message());
    }

    #[Test]
    public function create_denied_for_cancelled_booking(): void
    {
        $user = $this->makeUser(1, UserRole::USER);
        $booking = $this->makeBooking(
            userId: 1,
            status: BookingStatus::CANCELLED,
            checkOut: Carbon::yesterday()
        );
        $booking->setRelation('review', null);

        $response = $this->policy->create($user, $booking);

        $this->assertFalse($response->allowed());
        $this->assertStringContainsString('must be confirmed', $response->message());
    }

    #[Test]
    public function create_denied_before_checkout(): void
    {
        $user = $this->makeUser(1, UserRole::USER);
        $booking = $this->makeBooking(
            userId: 1,
            status: BookingStatus::CONFIRMED,
            checkOut: Carbon::tomorrow() // Future checkout
        );
        $booking->setRelation('review', null);

        $response = $this->policy->create($user, $booking);

        $this->assertFalse($response->allowed());
        $this->assertStringContainsString('before checkout', $response->message());
    }

    #[Test]
    public function create_denied_when_review_already_exists(): void
    {
        $user = $this->makeUser(1, UserRole::USER);
        $booking = $this->makeBooking(
            userId: 1,
            status: BookingStatus::CONFIRMED,
            checkOut: Carbon::yesterday()
        );
        // Simulate existing review via pre-loaded relation
        $existingReview = new Review();
        $existingReview->id = 99;
        $booking->setRelation('review', $existingReview);

        $response = $this->policy->create($user, $booking);

        $this->assertFalse($response->allowed());
        $this->assertStringContainsString('already exists', $response->message());
    }

    // ===== BEFORE HOOK TESTS (ADMIN BYPASS) =====

    #[Test]
    public function before_returns_false_for_admin_create_attempt(): void
    {
        $admin = $this->makeUser(1, UserRole::ADMIN);

        $result = $this->policy->before($admin, 'create');

        $this->assertFalse($result);
    }

    #[Test]
    public function before_returns_true_for_admin_delete(): void
    {
        $admin = $this->makeUser(1, UserRole::ADMIN);

        $result = $this->policy->before($admin, 'delete');

        $this->assertTrue($result);
    }

    #[Test]
    public function before_returns_null_for_admin_update(): void
    {
        $admin = $this->makeUser(1, UserRole::ADMIN);

        $result = $this->policy->before($admin, 'update');

        $this->assertNull($result);
    }

    #[Test]
    public function before_returns_null_for_regular_user(): void
    {
        $user = $this->makeUser(1, UserRole::USER);

        $resultCreate = $this->policy->before($user, 'create');
        $resultDelete = $this->policy->before($user, 'delete');
        $resultUpdate = $this->policy->before($user, 'update');

        $this->assertNull($resultCreate);
        $this->assertNull($resultDelete);
        $this->assertNull($resultUpdate);
    }

    #[Test]
    public function before_returns_null_for_moderator(): void
    {
        $moderator = $this->makeUser(1, UserRole::MODERATOR);

        $result = $this->policy->before($moderator, 'delete');

        $this->assertNull($result); // Moderators don't get admin bypass
    }

    // ===== UPDATE TESTS =====

    #[Test]
    public function update_allowed_for_owner(): void
    {
        $user = $this->makeUser(1, UserRole::USER);
        $review = $this->makeReview(userId: 1);

        $response = $this->policy->update($user, $review);

        $this->assertTrue($response->allowed());
    }

    #[Test]
    public function update_denied_for_non_owner(): void
    {
        $user = $this->makeUser(1, UserRole::USER);
        $review = $this->makeReview(userId: 2);

        $response = $this->policy->update($user, $review);

        $this->assertFalse($response->allowed());
        $this->assertStringContainsString('do not own this review', $response->message());
    }

    // ===== DELETE TESTS =====

    #[Test]
    public function delete_allowed_for_owner(): void
    {
        $user = $this->makeUser(1, UserRole::USER);
        $review = $this->makeReview(userId: 1);

        $response = $this->policy->delete($user, $review);

        $this->assertTrue($response->allowed());
    }

    #[Test]
    public function delete_denied_for_non_owner_non_admin(): void
    {
        $user = $this->makeUser(1, UserRole::USER);
        $review = $this->makeReview(userId: 2);

        $response = $this->policy->delete($user, $review);

        $this->assertFalse($response->allowed());
        $this->assertStringContainsString('do not own this review', $response->message());
    }

    // ===== VIEW TESTS =====

    #[Test]
    public function view_allowed_for_anyone(): void
    {
        $review = $this->makeReview(userId: 1);

        // Authenticated user
        $user = $this->makeUser(2, UserRole::USER);
        $this->assertTrue($this->policy->view($user, $review));

        // Guest (null user)
        $this->assertTrue($this->policy->view(null, $review));
    }

    #[Test]
    public function viewAny_allowed_for_anyone(): void
    {
        $user = $this->makeUser(1, UserRole::USER);
        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->viewAny(null));
    }

    // ===== HELPER METHODS =====

    /**
     * Create a fake User instance without database.
     */
    private function makeUser(int $id, UserRole $role): User
    {
        $user = new User();
        $user->id = $id;
        $user->role = $role;
        return $user;
    }

    /**
     * Create a fake Booking instance without database.
     */
    private function makeBooking(int $userId, BookingStatus $status, Carbon $checkOut): Booking
    {
        $booking = new Booking();
        $booking->user_id = $userId;
        $booking->status = $status;
        $booking->check_out = $checkOut;
        return $booking;
    }

    /**
     * Create a fake Review instance without database.
     */
    private function makeReview(int $userId): Review
    {
        $review = new Review();
        $review->id = 1;
        $review->user_id = $userId;
        return $review;
    }
}
