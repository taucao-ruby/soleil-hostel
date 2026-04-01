<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AdminBookingConfirmTest — integration tests for POST /api/v1/bookings/{booking}/confirm.
 *
 * Route: POST /api/v1/bookings/{booking}/confirm
 * Middleware: check_token_valid, verified, role:admin, throttle:10,1
 *
 * Covers:
 *   ✅ Admin can confirm a pending booking → 200, status = confirmed
 *   ✅ Moderator cannot confirm → 403
 *   ✅ Regular user cannot confirm → 403
 *   ✅ Unauthenticated → 401
 *   ✅ Unverified admin → 403
 *   ✅ Already-confirmed booking → 422
 *   ✅ Cancelled booking → 422
 *   ✅ refund_pending booking → 422
 *   ✅ Response shape on success
 *   ✅ Database updated to confirmed status
 */
class AdminBookingConfirmTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $moderator;

    private User $user;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin     = User::factory()->admin()->create();
        $this->moderator = User::factory()->moderator()->create();
        $this->user      = User::factory()->create();
        $this->room      = Room::factory()->create();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function pendingBooking(array $overrides = []): Booking
    {
        return Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->pending()
            ->create(array_merge([
                'check_in'  => Carbon::now()->addDays(5)->startOfDay(),
                'check_out' => Carbon::now()->addDays(7)->startOfDay(),
            ], $overrides));
    }

    private function confirmUrl(Booking $booking): string
    {
        return "/api/v1/bookings/{$booking->id}/confirm";
    }

    // ─── Success ──────────────────────────────────────────────────────────────

    public function test_admin_can_confirm_pending_booking(): void
    {
        $booking = $this->pendingBooking();

        $response = $this->actingAs($this->admin)
            ->postJson($this->confirmUrl($booking));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', BookingStatus::CONFIRMED->value);

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => BookingStatus::CONFIRMED->value,
        ]);
    }

    public function test_confirm_response_contains_all_booking_resource_fields(): void
    {
        $booking = $this->pendingBooking();

        $response = $this->actingAs($this->admin)
            ->postJson($this->confirmUrl($booking));

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

    public function test_confirm_response_includes_room_relationship(): void
    {
        $booking = $this->pendingBooking();

        $response = $this->actingAs($this->admin)
            ->postJson($this->confirmUrl($booking));

        $response->assertOk()
            ->assertJsonPath('data.room.id', $this->room->id);
    }

    // ─── Wrong status ─────────────────────────────────────────────────────────

    public function test_confirming_already_confirmed_booking_returns_422(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in'  => Carbon::now()->addDays(5)->startOfDay(),
                'check_out' => Carbon::now()->addDays(7)->startOfDay(),
            ]);

        $response = $this->actingAs($this->admin)
            ->postJson($this->confirmUrl($booking));

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_confirming_cancelled_booking_returns_422(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->cancelled()
            ->create([
                'check_in'  => Carbon::now()->addDays(5)->startOfDay(),
                'check_out' => Carbon::now()->addDays(7)->startOfDay(),
            ]);

        $response = $this->actingAs($this->admin)
            ->postJson($this->confirmUrl($booking));

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_confirming_refund_pending_booking_returns_422(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->refundPending()
            ->create([
                'check_in'  => Carbon::now()->addDays(5)->startOfDay(),
                'check_out' => Carbon::now()->addDays(7)->startOfDay(),
            ]);

        $response = $this->actingAs($this->admin)
            ->postJson($this->confirmUrl($booking));

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    // ─── Authorization ────────────────────────────────────────────────────────

    public function test_moderator_cannot_confirm_booking(): void
    {
        $booking = $this->pendingBooking();

        $response = $this->actingAs($this->moderator)
            ->postJson($this->confirmUrl($booking));

        $response->assertForbidden();

        // Status must not have changed
        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => BookingStatus::PENDING->value,
        ]);
    }

    public function test_regular_user_cannot_confirm_booking(): void
    {
        $booking = $this->pendingBooking();

        $response = $this->actingAs($this->user)
            ->postJson($this->confirmUrl($booking));

        $response->assertForbidden();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $booking = $this->pendingBooking();

        $response = $this->postJson($this->confirmUrl($booking));

        $response->assertUnauthorized();
    }

    public function test_unverified_admin_cannot_confirm_booking(): void
    {
        $unverifiedAdmin = User::factory()->admin()->unverified()->create();
        $booking         = $this->pendingBooking();

        $response = $this->actingAs($unverifiedAdmin)
            ->postJson($this->confirmUrl($booking));

        // verified middleware blocks unverified users before role check
        $response->assertForbidden();
    }

    // ─── Booking not found ────────────────────────────────────────────────────

    public function test_confirming_nonexistent_booking_returns_404(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/bookings/99999/confirm');

        $response->assertNotFound();
    }

    // ─── Status preservation ──────────────────────────────────────────────────

    public function test_confirm_sets_status_to_confirmed_in_database(): void
    {
        $booking = $this->pendingBooking();

        $this->assertEquals(BookingStatus::PENDING, $booking->fresh()->status);

        $this->actingAs($this->admin)
            ->postJson($this->confirmUrl($booking))
            ->assertOk();

        $this->assertEquals(BookingStatus::CONFIRMED, $booking->fresh()->status);
    }
}
