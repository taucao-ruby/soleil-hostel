<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Events\BookingUpdated;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * BookingUpdateTest — integration tests for PUT /api/v1/bookings/{booking}.
 *
 * Covers:
 *   ✅ Owner can update dates and guest info
 *   ✅ Partial update (guest info only, dates unchanged)
 *   ✅ Date shift accepted when no overlap
 *   ✅ Conflict on update rejected (same room, overlapping dates)
 *   ✅ check_out <= check_in rejected (422 validation)
 *   ✅ Past check_in rejected (422 validation)
 *   ✅ Non-owner gets 403
 *   ✅ Unauthenticated gets 401
 *   ✅ Unverified user gets 403
 *   ✅ Admin can update any booking
 *   ✅ BookingUpdated event dispatched on success
 *   ✅ Response shape matches BookingResource contract
 */
class BookingUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $admin;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->admin = User::factory()->admin()->create();
        $this->room = Room::factory()->create();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Returns a valid update payload for a booking. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'check_in' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'guest_name' => 'Nguyễn Văn B',
            'guest_email' => 'nguyen.van.b@example.com',
        ], $overrides);
    }

    /** Creates a booking owned by $this->user for $this->room with non-overlapping future dates. */
    private function ownerBooking(array $overrides = []): Booking
    {
        return Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->pending()
            ->create(array_merge([
                'check_in' => Carbon::now()->addDays(5)->startOfDay(),
                'check_out' => Carbon::now()->addDays(7)->startOfDay(),
            ], $overrides));
    }

    // ─── Success ──────────────────────────────────────────────────────────────

    public function test_owner_can_update_booking_dates_and_guest_info(): void
    {
        Event::fake([BookingUpdated::class]);

        $booking = $this->ownerBooking();

        $newCheckIn = Carbon::now()->addDays(10)->format('Y-m-d');
        $newCheckOut = Carbon::now()->addDays(12)->format('Y-m-d');

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/bookings/{$booking->id}", [
                'check_in' => $newCheckIn,
                'check_out' => $newCheckOut,
                'guest_name' => 'Trần Thị C',
                'guest_email' => 'tran.thi.c@example.com',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.check_in', $newCheckIn)
            ->assertJsonPath('data.check_out', $newCheckOut)
            ->assertJsonPath('data.guest_name', 'Trần Thị C')
            ->assertJsonPath('data.guest_email', 'tran.thi.c@example.com');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'check_in' => $newCheckIn,
            'check_out' => $newCheckOut,
            'guest_name' => 'Trần Thị C',
            'guest_email' => 'tran.thi.c@example.com',
        ]);

        Event::assertDispatched(BookingUpdated::class);
    }

    public function test_owner_can_update_guest_info_keeping_same_dates(): void
    {
        $booking = $this->ownerBooking([
            'check_in' => Carbon::now()->addDays(8)->startOfDay(),
            'check_out' => Carbon::now()->addDays(10)->startOfDay(),
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/bookings/{$booking->id}", [
                'check_in' => $booking->check_in->format('Y-m-d'),
                'check_out' => $booking->check_out->format('Y-m-d'),
                'guest_name' => 'Lê Văn D',
                'guest_email' => 'le.van.d@example.com',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.guest_name', 'Lê Văn D')
            ->assertJsonPath('data.guest_email', 'le.van.d@example.com');
    }

    public function test_owner_can_shift_dates_forward_on_same_room_without_conflict(): void
    {
        // Existing booking: days 5-7
        $booking = $this->ownerBooking([
            'check_in' => Carbon::now()->addDays(5)->startOfDay(),
            'check_out' => Carbon::now()->addDays(7)->startOfDay(),
        ]);

        // Shift to days 20-22 — no overlap
        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/bookings/{$booking->id}", $this->payload([
                'check_in' => Carbon::now()->addDays(20)->format('Y-m-d'),
                'check_out' => Carbon::now()->addDays(22)->format('Y-m-d'),
            ]));

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_update_any_users_booking(): void
    {
        $booking = $this->ownerBooking();

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/bookings/{$booking->id}", $this->payload([
                'check_in' => Carbon::now()->addDays(15)->format('Y-m-d'),
                'check_out' => Carbon::now()->addDays(17)->format('Y-m-d'),
            ]));

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    // ─── Conflict prevention ──────────────────────────────────────────────────

    public function test_update_rejected_when_new_dates_overlap_another_booking(): void
    {
        // Booking A (owned by user, days 5-7) — this is what we try to update
        $bookingA = $this->ownerBooking([
            'check_in' => Carbon::now()->addDays(5)->startOfDay(),
            'check_out' => Carbon::now()->addDays(7)->startOfDay(),
        ]);

        // Booking B (same room, different user, days 12-14) — the obstacle
        $otherUser = User::factory()->create();
        Booking::factory()
            ->for($otherUser)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => Carbon::now()->addDays(12)->startOfDay(),
                'check_out' => Carbon::now()->addDays(14)->startOfDay(),
            ]);

        // Try to move booking A into days 12-14 (overlaps booking B)
        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/bookings/{$bookingA->id}", $this->payload([
                'check_in' => Carbon::now()->addDays(12)->format('Y-m-d'),
                'check_out' => Carbon::now()->addDays(14)->format('Y-m-d'),
            ]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    // ─── Validation errors ────────────────────────────────────────────────────

    public function test_update_rejected_when_check_out_is_same_as_check_in(): void
    {
        $booking = $this->ownerBooking();
        $date = Carbon::now()->addDays(10)->format('Y-m-d');

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/bookings/{$booking->id}", $this->payload([
                'check_in' => $date,
                'check_out' => $date, // same day — check_out must be AFTER check_in
            ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['check_out']);
    }

    public function test_update_rejected_when_check_out_is_before_check_in(): void
    {
        $booking = $this->ownerBooking();

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/bookings/{$booking->id}", $this->payload([
                'check_in' => Carbon::now()->addDays(10)->format('Y-m-d'),
                'check_out' => Carbon::now()->addDays(8)->format('Y-m-d'),
            ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['check_out']);
    }

    public function test_update_rejected_when_check_in_is_in_the_past(): void
    {
        $booking = $this->ownerBooking();

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/bookings/{$booking->id}", $this->payload([
                'check_in' => Carbon::yesterday()->format('Y-m-d'),
                'check_out' => Carbon::now()->addDays(1)->format('Y-m-d'),
            ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['check_in']);
    }

    public function test_update_rejected_when_guest_name_is_missing(): void
    {
        $booking = $this->ownerBooking();
        $data = $this->payload();
        unset($data['guest_name']);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/bookings/{$booking->id}", $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['guest_name']);
    }

    public function test_update_rejected_when_guest_email_is_invalid(): void
    {
        $booking = $this->ownerBooking();

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/bookings/{$booking->id}", $this->payload([
                'guest_email' => 'not-an-email',
            ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['guest_email']);
    }

    // ─── Authorization ────────────────────────────────────────────────────────

    public function test_non_owner_cannot_update_booking(): void
    {
        $booking = $this->ownerBooking();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->putJson("/api/v1/bookings/{$booking->id}", $this->payload());

        $response->assertForbidden();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $booking = $this->ownerBooking();

        $response = $this->putJson("/api/v1/bookings/{$booking->id}", $this->payload());

        $response->assertUnauthorized();
    }

    public function test_unverified_user_cannot_update_booking(): void
    {
        $unverified = User::factory()->unverified()->create();
        $booking = Booking::factory()
            ->for($unverified)
            ->for($this->room)
            ->pending()
            ->create([
                'check_in' => Carbon::now()->addDays(5)->startOfDay(),
                'check_out' => Carbon::now()->addDays(7)->startOfDay(),
            ]);

        $response = $this->actingAs($unverified)
            ->putJson("/api/v1/bookings/{$booking->id}", $this->payload());

        // verified middleware returns 403 for unverified users
        $response->assertForbidden();
    }

    // ─── Response shape ───────────────────────────────────────────────────────

    public function test_update_response_contains_all_booking_resource_fields(): void
    {
        $booking = $this->ownerBooking();

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/bookings/{$booking->id}", $this->payload([
                'check_in' => Carbon::now()->addDays(15)->format('Y-m-d'),
                'check_out' => Carbon::now()->addDays(17)->format('Y-m-d'),
            ]));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'room_id',
                    'user_id',
                    'check_in',
                    'check_out',
                    'guest_name',
                    'guest_email',
                    'status',
                    'status_label',
                    'nights',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_update_response_dates_are_formatted_as_y_m_d(): void
    {
        $booking = $this->ownerBooking();

        $checkIn = Carbon::now()->addDays(15)->format('Y-m-d');
        $checkOut = Carbon::now()->addDays(17)->format('Y-m-d');

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/bookings/{$booking->id}", $this->payload([
                'check_in' => $checkIn,
                'check_out' => $checkOut,
            ]));

        $data = $response->json('data');

        // Must be Y-m-d, not ISO timestamp
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $data['check_in']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $data['check_out']);
        $this->assertEquals($checkIn, $data['check_in']);
        $this->assertEquals($checkOut, $data['check_out']);
    }

    // ─── Edge cases ───────────────────────────────────────────────────────────

    public function test_update_does_not_change_booking_status(): void
    {
        $booking = $this->ownerBooking();
        $originalStatus = $booking->status->value;

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/bookings/{$booking->id}", $this->payload([
                'check_in' => Carbon::now()->addDays(15)->format('Y-m-d'),
                'check_out' => Carbon::now()->addDays(17)->format('Y-m-d'),
            ]));

        $response->assertOk()
            ->assertJsonPath('data.status', $originalStatus);
    }

    public function test_guest_name_xss_is_sanitized_on_update(): void
    {
        $booking = $this->ownerBooking();

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/bookings/{$booking->id}", $this->payload([
                'guest_name' => '<script>alert("xss")</script>Nguyễn',
            ]));

        // Request should succeed (purifier strips tags, doesn't reject)
        $response->assertOk();

        // Persisted value must not contain <script>
        $this->assertStringNotContainsString(
            '<script>',
            $response->json('data.guest_name')
        );
    }
}
