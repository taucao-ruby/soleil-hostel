<?php

namespace Tests\Feature\Stays;

use App\Enums\StayStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\Stay;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Stay invariant tests — schema constraints and model scopes.
 *
 * Covers:
 * - One stay per booking (UNIQUE booking_id constraint)
 * - scopeInHouse returns in_house and late_checkout only
 * - scopeExpectedToday by scheduled_check_in_at date
 * - Stay creation does not affect booking overlap (orthogonal domains)
 */
class StayInvariantTest extends TestCase
{
    use RefreshDatabase;

    // ===== ONE STAY PER BOOKING =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_one_stay_per_booking_unique_constraint(): void
    {
        $booking = Booking::factory()->confirmed()->create();
        Stay::factory()->forBooking($booking)->create();

        $this->expectException(QueryException::class);

        // Second stay for same booking must be rejected
        Stay::factory()->forBooking($booking)->create();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_different_bookings_can_each_have_a_stay(): void
    {
        $booking1 = Booking::factory()->confirmed()->create();
        $booking2 = Booking::factory()->confirmed()->create();

        $stay1 = Stay::factory()->forBooking($booking1)->create();
        $stay2 = Stay::factory()->forBooking($booking2)->create();

        $this->assertDatabaseHas('stays', ['id' => $stay1->id]);
        $this->assertDatabaseHas('stays', ['id' => $stay2->id]);
    }

    // ===== scopeInHouse =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_scope_in_house_returns_in_house_and_late_checkout(): void
    {
        Stay::factory()->inHouse()->create();
        Stay::factory()->lateCheckout()->create();
        Stay::factory()->expected()->create();
        Stay::factory()->checkedOut()->create();
        Stay::factory()->noShow()->create();

        $inHouseStays = Stay::inHouse()->get();

        $this->assertCount(2, $inHouseStays);

        foreach ($inHouseStays as $stay) {
            $this->assertTrue(
                in_array($stay->stay_status, [StayStatus::IN_HOUSE, StayStatus::LATE_CHECKOUT], true),
                "Expected in_house or late_checkout, got {$stay->stay_status->value}"
            );
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_scope_in_house_excludes_expected_and_checked_out(): void
    {
        Stay::factory()->expected()->create();
        Stay::factory()->checkedOut()->create();
        Stay::factory()->noShow()->create();

        $this->assertCount(0, Stay::inHouse()->get());
    }

    // ===== scopeExpectedToday =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_scope_expected_today_returns_arrivals_scheduled_for_today(): void
    {
        // Expected today
        Stay::factory()->expected()->create([
            'scheduled_check_in_at' => Carbon::today()->setHour(14),
        ]);

        // Expected tomorrow — must NOT appear
        Stay::factory()->expected()->create([
            'scheduled_check_in_at' => Carbon::tomorrow()->setHour(14),
        ]);

        // In-house with today check-in — must NOT appear (wrong status)
        Stay::factory()->inHouse()->create([
            'scheduled_check_in_at' => Carbon::today()->setHour(14),
        ]);

        $expectedToday = Stay::expectedToday()->get();

        $this->assertCount(1, $expectedToday);
        $this->assertEquals(StayStatus::EXPECTED, $expectedToday->first()->stay_status);
    }

    // ===== STAY TRANSITION GUARDS =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_transition_to_allows_valid_stay_lifecycle_change(): void
    {
        $stay = Stay::factory()->expected()->create();

        $stay->transitionTo(StayStatus::IN_HOUSE);

        $this->assertEquals(StayStatus::IN_HOUSE, $stay->fresh()->stay_status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_transition_to_rejects_illegal_stay_lifecycle_change(): void
    {
        $stay = Stay::factory()->expected()->create();

        $this->expectException(\RuntimeException::class);

        $stay->transitionTo(StayStatus::CHECKED_OUT);
    }

    // ===== BOOKING OVERLAP ISOLATION =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_stay_creation_does_not_affect_booking_overlap_logic(): void
    {
        // Create two confirmed bookings for different rooms (no overlap concern)
        $booking1 = Booking::factory()->confirmed()->create();
        $booking2 = Booking::factory()->confirmed()->create();

        // Creating stays must not affect the bookings table overlap constraint
        Stay::factory()->forBooking($booking1)->inHouse()->create();
        Stay::factory()->forBooking($booking2)->inHouse()->create();

        // Verify bookings are untouched
        $this->assertDatabaseHas('bookings', ['id' => $booking1->id]);
        $this->assertDatabaseHas('bookings', ['id' => $booking2->id]);

        // Verify stays were created independently
        $this->assertDatabaseHas('stays', ['booking_id' => $booking1->id]);
        $this->assertDatabaseHas('stays', ['booking_id' => $booking2->id]);
    }

    // ===== BOOKING OVERLAP EXCLUSION REGRESSION GUARD =====

    /**
     * Regression guard: the PostgreSQL EXCLUDE USING gist no-overlap constraint on bookings
     * must still fire after the stays/room_assignments/service_recovery_cases migrations.
     *
     * This test explicitly verifies the constraint is intact by attempting a direct
     * DB-layer overlapping insert, bypassing application-layer validation.
     *
     * PostgreSQL only — the EXCLUDE USING gist constraint does not exist on SQLite.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_booking_overlap_exclusion_remains_intact_after_stay_migration(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Booking overlap EXCLUDE USING gist constraint requires PostgreSQL');
        }

        $room = Room::factory()->create();
        $user = \App\Models\User::factory()->create();

        // First confirmed booking: Apr 1–5
        Booking::factory()->confirmed()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'check_in' => '2026-04-01',
            'check_out' => '2026-04-05',
        ]);

        $this->expectException(QueryException::class);

        // Second confirmed booking overlapping (Apr 3–7) must be rejected by the exclusion constraint
        Booking::factory()->confirmed()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'check_in' => '2026-04-03',
            'check_out' => '2026-04-07',
        ]);
    }

    // ===== scopeDueOutToday =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_scope_due_out_today_returns_in_house_with_checkout_today(): void
    {
        // Due out today
        Stay::factory()->inHouse()->create([
            'scheduled_check_out_at' => Carbon::today()->setHour(11),
        ]);

        // Due out tomorrow — must NOT appear
        Stay::factory()->inHouse()->create([
            'scheduled_check_out_at' => Carbon::tomorrow()->setHour(11),
        ]);

        // Late checkout with today checkout — must NOT appear (different status filter)
        Stay::factory()->lateCheckout()->create([
            'scheduled_check_out_at' => Carbon::today()->setHour(11),
        ]);

        $dueOut = Stay::dueOutToday()->get();

        $this->assertCount(1, $dueOut);
        $this->assertEquals(StayStatus::IN_HOUSE, $dueOut->first()->stay_status);
    }
}
