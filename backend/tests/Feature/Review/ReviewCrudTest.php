<?php

namespace Tests\Feature\Review;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ReviewCrudTest — POST /api/v1/reviews, PUT/PATCH, DELETE
 *
 * Covers:
 * - create × success / forbidden (non-owner, admin, unconfirmed, duplicate) / validation
 * - update × success / forbidden
 * - delete × success (owner) / success (admin) / forbidden
 * - rating constraint (1–5)
 */
class ReviewCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $admin;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => UserRole::USER,
            'email_verified_at' => now(),
        ]);

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'email_verified_at' => now(),
        ]);

        $this->room = Room::factory()->create();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a review-eligible booking: confirmed, checkout yesterday, owned by $user.
     */
    private function confirmedPastBooking(?User $user = null): Booking
    {
        return Booking::factory()->create([
            'user_id' => ($user ?? $this->user)->id,
            'room_id' => $this->room->id,
            'status' => BookingStatus::CONFIRMED,
            'check_in' => now()->subDays(3)->toDateString(),
            'check_out' => now()->subDay()->toDateString(),
        ]);
    }

    private function validPayload(Booking $booking): array
    {
        return [
            'booking_id' => $booking->id,
            'title' => 'Great hostel',
            'content' => 'Comfortable rooms and friendly staff. Highly recommended.',
            'rating' => 5,
        ];
    }

    // -------------------------------------------------------------------------
    // CREATE — happy path
    // -------------------------------------------------------------------------

    /**
     * Test 1: Owner of a confirmed+past booking can create a review.
     */
    public function test_owner_with_valid_booking_can_create_review(): void
    {
        $booking = $this->confirmedPastBooking();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reviews', $this->validPayload($booking));

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'booking_id' => $booking->id,
                    'room_id' => $this->room->id,
                    'user_id' => $this->user->id,
                    'rating' => 5,
                    'approved' => false,
                ],
            ]);

        $this->assertDatabaseHas('reviews', [
            'booking_id' => $booking->id,
            'user_id' => $this->user->id,
            'rating' => 5,
        ]);
    }

    // -------------------------------------------------------------------------
    // CREATE — authorization denials
    // -------------------------------------------------------------------------

    /**
     * Test 2: Admin cannot create a review (ReviewPolicy::before() denies 'create' for admins).
     */
    public function test_admin_cannot_create_review(): void
    {
        $booking = $this->confirmedPastBooking($this->user);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/reviews', $this->validPayload($booking));

        $response->assertStatus(403);
        $this->assertDatabaseMissing('reviews', ['booking_id' => $booking->id]);
    }

    /**
     * Test 3: Non-owner cannot create a review for another user's booking.
     */
    public function test_non_owner_cannot_create_review_for_others_booking(): void
    {
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $booking = $this->confirmedPastBooking($this->user);

        $response = $this->actingAs($otherUser, 'sanctum')
            ->postJson('/api/v1/reviews', $this->validPayload($booking));

        $response->assertStatus(403);
        $this->assertDatabaseMissing('reviews', ['booking_id' => $booking->id]);
    }

    /**
     * Test 4: Cannot create a review for an unconfirmed (PENDING) booking.
     */
    public function test_create_fails_for_unconfirmed_booking(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => BookingStatus::PENDING,
            'check_in' => now()->subDays(3)->toDateString(),
            'check_out' => now()->subDay()->toDateString(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reviews', $this->validPayload($booking));

        $response->assertStatus(403);
    }

    /**
     * Test 5: Cannot submit a second review for the same booking (duplicate).
     */
    public function test_duplicate_review_for_same_booking_is_denied(): void
    {
        $booking = $this->confirmedPastBooking();

        // First review
        Review::factory()->forBooking($booking)->create();

        // Attempt second review
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reviews', $this->validPayload($booking));

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // CREATE — validation
    // -------------------------------------------------------------------------

    /**
     * Test 6: Rating above 5 is rejected with 422.
     */
    public function test_create_fails_when_rating_exceeds_max(): void
    {
        $booking = $this->confirmedPastBooking();

        $payload = $this->validPayload($booking);
        $payload['rating'] = 6;

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reviews', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    /**
     * Test 7: Rating of 0 is rejected with 422.
     */
    public function test_create_fails_when_rating_is_zero(): void
    {
        $booking = $this->confirmedPastBooking();

        $payload = $this->validPayload($booking);
        $payload['rating'] = 0;

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reviews', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    /**
     * Test 8: Missing required fields returns 422.
     */
    public function test_create_fails_when_required_fields_missing(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reviews', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['booking_id', 'title', 'content', 'rating']);
    }

    /**
     * Test 9: Unauthenticated request returns 401.
     */
    public function test_create_requires_authentication(): void
    {
        $booking = $this->confirmedPastBooking();

        $response = $this->postJson('/api/v1/reviews', $this->validPayload($booking));

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // UPDATE
    // -------------------------------------------------------------------------

    /**
     * Test 10: Owner can update their own review.
     */
    public function test_owner_can_update_own_review(): void
    {
        $booking = $this->confirmedPastBooking();
        $review = Review::factory()->forBooking($booking)->create(['rating' => 3]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/reviews/{$review->id}", [
                'rating' => 4,
                'title' => 'Updated title',
                'content' => 'Updated content with enough text for validation here.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['rating' => 4, 'title' => 'Updated title'],
            ]);

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'rating' => 4]);
    }

    /**
     * Test 11: Non-owner cannot update another user's review.
     */
    public function test_non_owner_cannot_update_others_review(): void
    {
        $booking = $this->confirmedPastBooking();
        $review = Review::factory()->forBooking($booking)->create();

        $otherUser = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($otherUser, 'sanctum')
            ->putJson("/api/v1/reviews/{$review->id}", ['rating' => 1]);

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // DELETE
    // -------------------------------------------------------------------------

    /**
     * Test 12: Owner can delete their own review.
     */
    public function test_owner_can_delete_own_review(): void
    {
        $booking = $this->confirmedPastBooking();
        $review = Review::factory()->forBooking($booking)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/reviews/{$review->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    /**
     * Test 13: Admin can delete any review (ReviewPolicy::before() bypasses ownership).
     */
    public function test_admin_can_delete_any_review(): void
    {
        $booking = $this->confirmedPastBooking();
        $review = Review::factory()->forBooking($booking)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/reviews/{$review->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    /**
     * Test 14: Non-owner cannot delete another user's review.
     */
    public function test_non_owner_cannot_delete_others_review(): void
    {
        $booking = $this->confirmedPastBooking();
        $review = Review::factory()->forBooking($booking)->create();

        $otherUser = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($otherUser, 'sanctum')
            ->deleteJson("/api/v1/reviews/{$review->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }
}
