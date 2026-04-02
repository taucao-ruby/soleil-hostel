<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BookingReviewTest — integration tests for the review lifecycle.
 *
 * Routes:
 *   POST   /api/v1/reviews            — create
 *   PUT    /api/v1/reviews/{review}   — update
 *   DELETE /api/v1/reviews/{review}   — delete
 *
 * Policy (ReviewPolicy):
 *   create : owner + confirmed + checkout past + no existing review
 *            admin CANNOT create (prevents fake reviews)
 *   update : owner only
 *   delete : owner OR admin
 *
 * Covers:
 *   ✅ Owner creates review for past confirmed booking
 *   ✅ Response shape on create (201, all fields)
 *   ✅ review.approved defaults to false on create
 *   ✅ Cannot create for pending booking (status check)
 *   ✅ Cannot create for future checkout (temporal check)
 *   ✅ Cannot create when review already exists (duplicate)
 *   ✅ Non-owner cannot create review for someone else's booking
 *   ✅ Admin cannot create review (fake-review prevention)
 *   ✅ Unauthenticated create returns 401
 *   ✅ Validation: missing booking_id, title, content, rating
 *   ✅ Validation: rating out of range (0, 6)
 *   ✅ Owner can update title/content/rating
 *   ✅ Non-owner cannot update (403)
 *   ✅ Owner can delete own review
 *   ✅ Admin can delete any review (policy before() bypass)
 *   ✅ Non-owner non-admin cannot delete (403)
 *   ✅ Review persisted with correct booking_id, room_id, user_id
 */
class BookingReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $admin;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user  = User::factory()->create();
        $this->admin = User::factory()->admin()->create();
        $this->room  = Room::factory()->create();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * A booking that satisfies all review creation requirements:
     * confirmed status, checkout in the past.
     */
    private function reviewableBooking(?User $owner = null, array $overrides = []): Booking
    {
        return Booking::factory()
            ->for($owner ?? $this->user)
            ->for($this->room)
            ->confirmed()
            ->create(array_merge([
                'check_in'  => Carbon::now()->subDays(5)->startOfDay(),
                'check_out' => Carbon::now()->subDays(2)->startOfDay(),
            ], $overrides));
    }

    /** A valid store payload for a given booking. */
    private function reviewPayload(Booking $booking, array $overrides = []): array
    {
        return array_merge([
            'booking_id' => $booking->id,
            'title'      => 'Trải nghiệm tuyệt vời',
            'content'    => 'Phòng sạch sẽ, nhân viên thân thiện và chu đáo.',
            'rating'     => 5,
        ], $overrides);
    }

    // ─── POST /api/v1/reviews ─────────────────────────────────────────────────

    public function test_owner_can_create_review_for_past_confirmed_booking(): void
    {
        $booking = $this->reviewableBooking();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/reviews', $this->reviewPayload($booking));

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.booking_id', $booking->id)
            ->assertJsonPath('data.room_id', $this->room->id)
            ->assertJsonPath('data.user_id', $this->user->id)
            ->assertJsonPath('data.rating', 5);

        $this->assertDatabaseHas('reviews', [
            'booking_id' => $booking->id,
            'user_id'    => $this->user->id,
            'rating'     => 5,
        ]);
    }

    public function test_review_approved_defaults_to_false(): void
    {
        $booking = $this->reviewableBooking();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/reviews', $this->reviewPayload($booking));

        $response->assertStatus(201)
            ->assertJsonPath('data.approved', false);
    }

    public function test_create_response_contains_required_fields(): void
    {
        $booking = $this->reviewableBooking();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/reviews', $this->reviewPayload($booking));

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'content',
                    'rating',
                    'booking_id',
                    'room_id',
                    'user_id',
                    'approved',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_review_stored_with_correct_guest_name_and_email(): void
    {
        $booking = $this->reviewableBooking();

        $this->actingAs($this->user)
            ->postJson('/api/v1/reviews', $this->reviewPayload($booking))
            ->assertStatus(201);

        $this->assertDatabaseHas('reviews', [
            'booking_id'  => $booking->id,
            'guest_name'  => $this->user->name,
            'guest_email' => $this->user->email,
        ]);
    }

    // ─── Policy: status checks ────────────────────────────────────────────────

    public function test_cannot_create_review_for_pending_booking(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->pending()
            ->create([
                'check_in'  => Carbon::now()->subDays(5)->startOfDay(),
                'check_out' => Carbon::now()->subDays(2)->startOfDay(),
            ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/reviews', $this->reviewPayload($booking));

        $response->assertForbidden();
    }

    public function test_cannot_create_review_for_cancelled_booking(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->cancelled()
            ->create([
                'check_in'  => Carbon::now()->subDays(5)->startOfDay(),
                'check_out' => Carbon::now()->subDays(2)->startOfDay(),
            ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/reviews', $this->reviewPayload($booking));

        $response->assertForbidden();
    }

    // ─── Policy: temporal check ───────────────────────────────────────────────

    public function test_cannot_create_review_when_checkout_is_in_the_future(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in'  => Carbon::now()->addDays(5)->startOfDay(),
                'check_out' => Carbon::now()->addDays(7)->startOfDay(), // future
            ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/reviews', $this->reviewPayload($booking));

        $response->assertForbidden();
    }

    // ─── Policy: duplicate check ──────────────────────────────────────────────

    public function test_cannot_create_second_review_for_same_booking(): void
    {
        $booking = $this->reviewableBooking();

        // Create the first review
        Review::factory()
            ->forBooking($booking)
            ->create(['user_id' => $this->user->id]);

        // Attempt to create a second review for the same booking
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/reviews', $this->reviewPayload($booking));

        $response->assertForbidden();
    }

    // ─── Policy: ownership ────────────────────────────────────────────────────

    public function test_non_owner_cannot_create_review_for_others_booking(): void
    {
        $otherUser = User::factory()->create();
        $booking   = $this->reviewableBooking($otherUser); // owned by otherUser

        $response = $this->actingAs($this->user) // different user tries
            ->postJson('/api/v1/reviews', $this->reviewPayload($booking));

        $response->assertForbidden();
    }

    public function test_admin_cannot_create_review(): void
    {
        // Admin owns the booking but policy before() explicitly denies admin creates
        $booking = $this->reviewableBooking($this->admin);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/reviews', $this->reviewPayload($booking));

        $response->assertForbidden();
    }

    // ─── Auth guard ───────────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_create_review(): void
    {
        $booking = $this->reviewableBooking();

        $response = $this->postJson('/api/v1/reviews', $this->reviewPayload($booking));

        $response->assertUnauthorized();
    }

    // ─── Validation ───────────────────────────────────────────────────────────

    public function test_create_requires_booking_id(): void
    {
        $payload = $this->reviewPayload($this->reviewableBooking());
        unset($payload['booking_id']);

        $this->actingAs($this->user)
            ->postJson('/api/v1/reviews', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['booking_id']);
    }

    public function test_create_requires_title(): void
    {
        $booking = $this->reviewableBooking();
        $payload = $this->reviewPayload($booking);
        unset($payload['title']);

        $this->actingAs($this->user)
            ->postJson('/api/v1/reviews', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_create_requires_content(): void
    {
        $booking = $this->reviewableBooking();
        $payload = $this->reviewPayload($booking);
        unset($payload['content']);

        $this->actingAs($this->user)
            ->postJson('/api/v1/reviews', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_create_requires_rating(): void
    {
        $booking = $this->reviewableBooking();
        $payload = $this->reviewPayload($booking);
        unset($payload['rating']);

        $this->actingAs($this->user)
            ->postJson('/api/v1/reviews', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_create_rejects_rating_below_1(): void
    {
        $booking = $this->reviewableBooking();

        $this->actingAs($this->user)
            ->postJson('/api/v1/reviews', $this->reviewPayload($booking, ['rating' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_create_rejects_rating_above_5(): void
    {
        $booking = $this->reviewableBooking();

        $this->actingAs($this->user)
            ->postJson('/api/v1/reviews', $this->reviewPayload($booking, ['rating' => 6]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_create_rejects_nonexistent_booking_id(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/reviews', [
                'booking_id' => 99999,
                'title'      => 'Test',
                'content'    => 'Test content',
                'rating'     => 5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['booking_id']);
    }

    // ─── PUT /api/v1/reviews/{review} ─────────────────────────────────────────

    public function test_owner_can_update_review(): void
    {
        $booking = $this->reviewableBooking();
        $review  = Review::factory()
            ->forBooking($booking)
            ->create(['user_id' => $this->user->id, 'rating' => 3]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/reviews/{$review->id}", [
                'title'   => 'Tiêu đề mới',
                'content' => 'Nội dung đánh giá đã cập nhật.',
                'rating'  => 4,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.title', 'Tiêu đề mới');

        $this->assertDatabaseHas('reviews', [
            'id'     => $review->id,
            'rating' => 4,
        ]);
    }

    public function test_non_owner_cannot_update_review(): void
    {
        $otherUser = User::factory()->create();
        $booking   = $this->reviewableBooking($otherUser);
        $review    = Review::factory()
            ->forBooking($booking)
            ->create(['user_id' => $otherUser->id]);

        $this->actingAs($this->user) // different user
            ->putJson("/api/v1/reviews/{$review->id}", [
                'title'   => 'Hack title',
                'content' => 'Hack content',
                'rating'  => 1,
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_update_others_review(): void
    {
        $booking = $this->reviewableBooking();
        $review  = Review::factory()
            ->forBooking($booking)
            ->create(['user_id' => $this->user->id]);

        // Admin is not the owner — policy before() returns null for update,
        // deferring to the ownership check which denies
        $this->actingAs($this->admin)
            ->putJson("/api/v1/reviews/{$review->id}", [
                'title'   => 'Admin edit',
                'content' => 'Admin content',
                'rating'  => 2,
            ])
            ->assertForbidden();
    }

    // ─── DELETE /api/v1/reviews/{review} ──────────────────────────────────────

    public function test_owner_can_delete_own_review(): void
    {
        $booking = $this->reviewableBooking();
        $review  = Review::factory()
            ->forBooking($booking)
            ->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/reviews/{$review->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_admin_can_delete_any_review(): void
    {
        $booking = $this->reviewableBooking();
        $review  = Review::factory()
            ->forBooking($booking)
            ->create(['user_id' => $this->user->id]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/reviews/{$review->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_non_owner_non_admin_cannot_delete_review(): void
    {
        $otherUser = User::factory()->create();
        $booking   = $this->reviewableBooking($otherUser);
        $review    = Review::factory()
            ->forBooking($booking)
            ->create(['user_id' => $otherUser->id]);

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/reviews/{$review->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }

    public function test_unauthenticated_cannot_delete_review(): void
    {
        $booking = $this->reviewableBooking();
        $review  = Review::factory()
            ->forBooking($booking)
            ->create(['user_id' => $this->user->id]);

        $this->deleteJson("/api/v1/reviews/{$review->id}")
            ->assertUnauthorized();
    }
}
