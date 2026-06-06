<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * BookingOverlapMatrixTest — T-3.
 *
 * Runs the overlap / double-book / half-open / cancel-frees-room matrix against
 * BOTH the legacy `/api/bookings` and the versioned `/api/v1/bookings` endpoints
 * from a SINGLE parametrised data provider. This proves the two routes share one
 * engine (Booking::overlappingBookings scope + CreateBookingService) with
 * identical behaviour — only the endpoint URL varies, so there is no test or
 * production logic duplication.
 *
 * Contract asserted is THIS repo's real semantics (not the generic spec):
 *   - half-open interval [check_in, check_out): adjacent stays do NOT conflict (201)
 *   - application-layer overlap (pre-insert FOR UPDATE check) → 422
 *   - a true parallel race that slips to the PG exclusion constraint → 409;
 *     the sequential simulation here always resolves at the application layer,
 *     so losers are 422 (the 409 path is proven in ConcurrentBookingTest).
 */
#[Group('booking')]
class BookingOverlapMatrixTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        // Both endpoints sit behind ['check_token_valid', 'verified'].
        $this->user = User::factory()->create(['email_verified_at' => now()]);

        $this->room = Room::factory()->available()->ready()->create([
            'price' => 100.00,
            'max_guests' => 4,
        ]);

        // This suite exercises overlap logic, not rate limiting; both endpoints
        // carry `throttle:booking`, so drop it for deterministic multi-POST cases.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function bookingEndpoints(): array
    {
        return [
            'legacy /api/bookings' => ['/api/bookings', 'legacy'],
            'v1 /api/v1/bookings' => ['/api/v1/bookings', 'v1'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(string $checkIn, string $checkOut, array $overrides = []): array
    {
        return array_merge([
            'room_id' => $this->room->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guest_name' => 'Matrix Guest',
            'guest_email' => 'matrix@example.com',
        ], $overrides);
    }

    private function book(string $endpoint, array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user, 'sanctum')->postJson($endpoint, $payload);
    }

    #[DataProvider('bookingEndpoints')]
    public function test_no_overlap_creates_booking(string $endpoint, string $version): void
    {
        $response = $this->book($endpoint, $this->payload(
            Carbon::now()->addDays(5)->toDateString(),
            Carbon::now()->addDays(8)->toDateString(),
        ));

        $response->assertStatus(201)->assertJson([
            'success' => true,
            'data' => ['status' => BookingStatus::PENDING->value],
        ]);

        $this->assertDatabaseHas('bookings', [
            'room_id' => $this->room->id,
            'guest_email' => 'matrix@example.com',
        ]);
    }

    #[DataProvider('bookingEndpoints')]
    public function test_exact_same_dates_blocked(string $endpoint, string $version): void
    {
        $checkIn = Carbon::now()->addDays(5)->toDateString();
        $checkOut = Carbon::now()->addDays(8)->toDateString();

        $this->book($endpoint, $this->payload($checkIn, $checkOut))->assertStatus(201);

        // Identical interval, same room → application-layer overlap → 422.
        $this->book($endpoint, $this->payload($checkIn, $checkOut, ['guest_email' => 'second@example.com']))
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertSame(1, Booking::where('room_id', $this->room->id)->count(), "duplicate persisted ({$version})");
    }

    #[DataProvider('bookingEndpoints')]
    public function test_partial_overlap_blocked(string $endpoint, string $version): void
    {
        // Existing days 5–10; new days 8–12 → intervals intersect → 422.
        $this->book($endpoint, $this->payload(
            Carbon::now()->addDays(5)->toDateString(),
            Carbon::now()->addDays(10)->toDateString(),
        ))->assertStatus(201);

        $this->book($endpoint, $this->payload(
            Carbon::now()->addDays(8)->toDateString(),
            Carbon::now()->addDays(12)->toDateString(),
            ['guest_email' => 'second@example.com'],
        ))->assertStatus(422)->assertJson(['success' => false]);

        $this->assertSame(1, Booking::where('room_id', $this->room->id)->count(), "overlap persisted ({$version})");
    }

    #[DataProvider('bookingEndpoints')]
    public function test_adjacent_half_open_allowed(string $endpoint, string $version): void
    {
        $boundary = Carbon::now()->addDays(8)->toDateString();

        // Existing days 5–8.
        $this->book($endpoint, $this->payload(
            Carbon::now()->addDays(5)->toDateString(),
            $boundary,
        ))->assertStatus(201);

        // New starts exactly when the previous checks out → half-open → no conflict → 201.
        $this->book($endpoint, $this->payload(
            $boundary,
            Carbon::now()->addDays(10)->toDateString(),
            ['guest_email' => 'second@example.com'],
        ))->assertStatus(201)->assertJson(['success' => true]);

        $this->assertSame(2, Booking::where('room_id', $this->room->id)->count(), "adjacent stays missing ({$version})");
    }

    #[DataProvider('bookingEndpoints')]
    public function test_cancel_frees_room(string $endpoint, string $version): void
    {
        $checkIn = Carbon::now()->addDays(5)->toDateString();
        $checkOut = Carbon::now()->addDays(8)->toDateString();

        $first = $this->book($endpoint, $this->payload($checkIn, $checkOut));
        $first->assertStatus(201);
        $bookingId = $first->json('data.id');

        // Same dates blocked while the first booking is active.
        $this->book($endpoint, $this->payload($checkIn, $checkOut, ['guest_email' => 'second@example.com']))
            ->assertStatus(422);

        // Cancel via the matching versioned route → booking leaves ACTIVE_STATUSES.
        $this->actingAs($this->user, 'sanctum')
            ->postJson("{$endpoint}/{$bookingId}/cancel")
            ->assertStatus(200);

        // Interval is free again → 201.
        $this->book($endpoint, $this->payload($checkIn, $checkOut, ['guest_email' => 'third@example.com']))
            ->assertStatus(201);
    }

    #[DataProvider('bookingEndpoints')]
    public function test_concurrent_same_room_one_winner(string $endpoint, string $version): void
    {
        $checkIn = Carbon::now()->addDays(15)->toDateString();
        $checkOut = Carbon::now()->addDays(18)->toDateString();

        $success = 0;
        $conflict = 0;

        // Sequential simulation: the pessimistic FOR UPDATE check resolves every
        // loser at the application layer (422). A genuinely parallel race that
        // reaches the PG exclusion constraint (409) is proven in ConcurrentBookingTest.
        for ($i = 0; $i < 5; $i++) {
            $status = $this->book($endpoint, $this->payload($checkIn, $checkOut, [
                'guest_email' => "racer{$i}@example.com",
            ]))->status();

            if ($status === 201) {
                $success++;
            } elseif (in_array($status, [409, 422], true)) {
                $conflict++;
            }
        }

        $this->assertSame(1, $success, "exactly one winner expected ({$version})");
        $this->assertSame(4, $conflict, "all other attempts must conflict ({$version})");
        $this->assertSame(1, Booking::where('room_id', $this->room->id)->count(), "more than one booking persisted ({$version})");
    }
}
