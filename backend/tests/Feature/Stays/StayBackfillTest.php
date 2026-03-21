<?php

namespace Tests\Feature\Stays;

use App\Enums\BookingStatus;
use App\Enums\StayStatus;
use App\Models\Booking;
use App\Models\Stay;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stay backfill and lazy-creation idempotency tests.
 *
 * Covers:
 * - 2A: Lazy stay creation (BookingService::confirmBooking) is idempotent
 * - 2B: stays:backfill-operational is idempotent (re-run creates zero rows)
 * - 2C: stays:backfill-operational respects scope (4 sub-cases)
 * - 2D: stays:backfill-operational --dry-run persists nothing
 */
class StayBackfillTest extends TestCase
{
    use RefreshDatabase;

    // ===== 2A — LAZY HOOK IDEMPOTENCY =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_confirming_booking_twice_does_not_create_duplicate_stay(): void
    {
        $bookingService = app(BookingService::class);

        $booking = Booking::factory()->create(['status' => BookingStatus::PENDING]);

        // First confirmation — creates the Stay
        $bookingService->confirmBooking($booking);

        $this->assertDatabaseCount('stays', 1);
        $this->assertDatabaseHas('stays', ['booking_id' => $booking->id]);

        // The booking lifecycle prevents calling confirmBooking() again (status is now confirmed).
        // Test the underlying Stay::firstOrCreate mechanism directly — this is what the hook calls.
        $stayIdBefore = Stay::where('booking_id', $booking->id)->value('id');

        Stay::firstOrCreate(
            ['booking_id' => $booking->id],
            [
                'stay_status'            => StayStatus::EXPECTED,
                'scheduled_check_in_at'  => $booking->check_in->copy()->setTime(14, 0, 0),
                'scheduled_check_out_at' => $booking->check_out->copy()->setTime(12, 0, 0),
                'actual_check_in_at'     => null,
                'actual_check_out_at'    => null,
            ]
        );

        // Still exactly one stay — no duplicate created, no exception thrown
        $this->assertDatabaseCount('stays', 1);
        $this->assertEquals($stayIdBefore, Stay::where('booking_id', $booking->id)->value('id'));
    }

    // ===== 2B — BACKFILL COMMAND IDEMPOTENCY =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_backfill_command_is_idempotent(): void
    {
        Booking::factory()->confirmed()->create([
            'check_in'  => Carbon::today()->addDay()->toDateString(),
            'check_out' => Carbon::today()->addDays(3)->toDateString(),
        ]);

        $this->assertDatabaseCount('stays', 0);

        // First run — should create 1 stay
        $this->artisan('stays:backfill-operational')->assertSuccessful();

        $this->assertDatabaseCount('stays', 1);

        // Second run — should create 0 additional stays
        $this->artisan('stays:backfill-operational')->assertSuccessful();

        $this->assertDatabaseCount('stays', 1);
    }

    // ===== 2C — BACKFILL COMMAND SCOPE CORRECTNESS =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_backfill_command_creates_stay_for_confirmed_future_booking(): void
    {
        Booking::factory()->confirmed()->create([
            'check_in'  => Carbon::today()->addDay()->toDateString(),
            'check_out' => Carbon::today()->addDays(3)->toDateString(),
        ]);

        $this->artisan('stays:backfill-operational')->assertSuccessful();

        $this->assertDatabaseCount('stays', 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_backfill_command_skips_confirmed_booking_with_past_checkout(): void
    {
        Booking::factory()->confirmed()->create([
            'check_in'  => Carbon::today()->subDays(5)->toDateString(),
            'check_out' => Carbon::today()->subDay()->toDateString(),
        ]);

        $this->artisan('stays:backfill-operational')->assertSuccessful();

        $this->assertDatabaseCount('stays', 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_backfill_command_skips_cancelled_booking(): void
    {
        Booking::factory()->cancelled()->create([
            'check_in'  => Carbon::today()->addDay()->toDateString(),
            'check_out' => Carbon::today()->addDays(3)->toDateString(),
        ]);

        $this->artisan('stays:backfill-operational')->assertSuccessful();

        $this->assertDatabaseCount('stays', 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_backfill_command_skips_confirmed_booking_that_already_has_stay(): void
    {
        $booking = Booking::factory()->confirmed()->create([
            'check_in'  => Carbon::today()->addDay()->toDateString(),
            'check_out' => Carbon::today()->addDays(3)->toDateString(),
        ]);

        Stay::factory()->forBooking($booking)->expected()->create();

        $this->assertDatabaseCount('stays', 1);

        $this->artisan('stays:backfill-operational')->assertSuccessful();

        // Still exactly 1 — no second stay created
        $this->assertDatabaseCount('stays', 1);
    }

    // ===== 2D — DRY-RUN PRODUCES NO ROWS =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_backfill_command_dry_run_does_not_persist(): void
    {
        Booking::factory()->confirmed()->create([
            'check_in'  => Carbon::today()->addDay()->toDateString(),
            'check_out' => Carbon::today()->addDays(3)->toDateString(),
        ]);

        $this->artisan('stays:backfill-operational', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseCount('stays', 0);
    }
}
