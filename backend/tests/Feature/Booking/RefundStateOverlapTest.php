<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BL-2 regression coverage — refund settlement states must not block room
 * availability.
 *
 * Three invariants are locked here:
 *
 * 1. REFUND_PENDING and REFUND_FAILED are NOT in ACTIVE_STATUSES, so the
 *    scope-level overlap check (Booking::overlappingBookings) and the
 *    PostgreSQL partial EXCLUDE USING gist constraint
 *    `no_overlapping_bookings` both ignore them when admitting a new
 *    PENDING / CONFIRMED booking for the same room and dates.
 *
 * 2. BookingStatus::canTransitionTo forbids REFUND_PENDING -> CONFIRMED and
 *    REFUND_FAILED -> CONFIRMED, so the application state machine cannot
 *    resurrect occupancy entitlement from a refund settlement state.
 *
 * 3. If something bypasses the application state machine and flips a refund
 *    row back to CONFIRMED via a raw DB write, the PostgreSQL exclusion
 *    constraint rejects the row with SQLSTATE 23P01 whenever it would
 *    overlap an existing active booking. This is the database-level backstop.
 *
 * Reference: BL-2 in docs/FINDINGS_BACKLOG.md.
 */
#[\PHPUnit\Framework\Attributes\Group('booking')]
class RefundStateOverlapTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->room = Room::factory()->available()->ready()->create();
    }

    // ===== TEST A: REFUND_PENDING does not block a new active booking =====

    public function test_refund_pending_existing_booking_does_not_block_overlapping_active_booking(): void
    {
        $existing = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::REFUND_PENDING,
                'check_in' => Carbon::parse('2026-07-01'),
                'check_out' => Carbon::parse('2026-07-05'),
            ]);

        // Scope-level: refund_pending row must NOT appear in the overlap query
        // that admit-new-booking flows rely on.
        $overlapMatches = Booking::overlappingBookings(
            $this->room->id,
            Carbon::parse('2026-07-02'),
            Carbon::parse('2026-07-04'),
        )->count();

        $this->assertSame(
            0,
            $overlapMatches,
            'A REFUND_PENDING booking must not match scopeOverlappingBookings',
        );

        // DB-level: creating the overlapping active booking must succeed.
        // Under PostgreSQL this also proves the partial exclusion constraint
        // (predicate: status IN ('pending','confirmed') AND deleted_at IS NULL)
        // does not pick up the refund_pending row.
        $new = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::PENDING,
                'check_in' => Carbon::parse('2026-07-02'),
                'check_out' => Carbon::parse('2026-07-04'),
            ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $new->id,
            'room_id' => $this->room->id,
            'status' => BookingStatus::PENDING->value,
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $existing->id,
            'status' => BookingStatus::REFUND_PENDING->value,
        ]);
    }

    // ===== TEST B: REFUND_FAILED does not block a new active booking =====

    public function test_refund_failed_existing_booking_does_not_block_overlapping_active_booking(): void
    {
        $existing = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::REFUND_FAILED,
                'check_in' => Carbon::parse('2026-07-01'),
                'check_out' => Carbon::parse('2026-07-05'),
                'refund_status' => 'failed',
                'refund_error' => 'Card declined',
            ]);

        $overlapMatches = Booking::overlappingBookings(
            $this->room->id,
            Carbon::parse('2026-07-02'),
            Carbon::parse('2026-07-04'),
        )->count();

        $this->assertSame(
            0,
            $overlapMatches,
            'A REFUND_FAILED booking must not match scopeOverlappingBookings',
        );

        $new = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::CONFIRMED,
                'check_in' => Carbon::parse('2026-07-02'),
                'check_out' => Carbon::parse('2026-07-04'),
            ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $new->id,
            'room_id' => $this->room->id,
            'status' => BookingStatus::CONFIRMED->value,
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $existing->id,
            'status' => BookingStatus::REFUND_FAILED->value,
        ]);
    }

    // ===== TEST C: state machine forbids refund states returning to CONFIRMED =====

    public function test_state_machine_forbids_refund_pending_to_confirmed(): void
    {
        $this->assertFalse(
            BookingStatus::REFUND_PENDING->canTransitionTo(BookingStatus::CONFIRMED),
            'REFUND_PENDING must not transition back to CONFIRMED',
        );
    }

    public function test_state_machine_forbids_refund_failed_to_confirmed(): void
    {
        $this->assertFalse(
            BookingStatus::REFUND_FAILED->canTransitionTo(BookingStatus::CONFIRMED),
            'REFUND_FAILED must not transition back to CONFIRMED',
        );
    }

    public function test_state_machine_also_forbids_refund_states_back_to_pending(): void
    {
        // PENDING is the other ACTIVE_STATUS — closing the loophole on both
        // re-entry points into occupancy.
        $this->assertFalse(
            BookingStatus::REFUND_PENDING->canTransitionTo(BookingStatus::PENDING),
            'REFUND_PENDING must not transition back to PENDING',
        );
        $this->assertFalse(
            BookingStatus::REFUND_FAILED->canTransitionTo(BookingStatus::PENDING),
            'REFUND_FAILED must not transition back to PENDING',
        );
    }

    // ===== TEST D: PostgreSQL rejects out-of-band refund -> CONFIRMED reversal =====

    /**
     * @dataProvider refundSettlementStateProvider
     */
    public function test_postgres_rejects_raw_update_resurrecting_refund_row_into_overlapping_confirmed(
        BookingStatus $refundState,
    ): void {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Exclusion constraint backstop is PostgreSQL-only');
        }

        // Old booking sits in a refund settlement state and therefore does NOT
        // match the partial-exclude predicate.
        $oldId = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => $refundState,
                'check_in' => Carbon::parse('2026-07-01'),
                'check_out' => Carbon::parse('2026-07-05'),
            ])->id;

        // A new active booking for an overlapping range succeeds — proves the
        // refund row was not blocking availability in the first place.
        Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::CONFIRMED,
                'check_in' => Carbon::parse('2026-07-02'),
                'check_out' => Carbon::parse('2026-07-04'),
            ]);

        // Bypass BookingStatus::canTransitionTo and force the old refund row
        // back to CONFIRMED via a raw write. Both rows now match the partial
        // exclude predicate and overlap, so PG must raise SQLSTATE 23P01.
        //
        // The raw UPDATE is wrapped in DB::transaction(...) so it runs inside a
        // SAVEPOINT relative to the RefreshDatabase outer transaction. When the
        // exclusion constraint fires, only the savepoint rolls back; subsequent
        // assertions can still query the row without hitting SQLSTATE 25P02
        // ("current transaction is aborted").
        $caught = null;
        try {
            DB::transaction(function () use ($oldId): void {
                DB::table('bookings')
                    ->where('id', $oldId)
                    ->update([
                        'status' => BookingStatus::CONFIRMED->value,
                        'updated_at' => now(),
                    ]);
            });
        } catch (QueryException $e) {
            $caught = $e;
        }

        $this->assertNotNull(
            $caught,
            'Raw refund-state -> CONFIRMED reversal must throw on overlap',
        );
        $this->assertSame(
            '23P01',
            $caught->getCode(),
            'Expected SQLSTATE 23P01 from no_overlapping_bookings exclusion constraint',
        );
        $this->assertStringContainsStringIgnoringCase(
            'no_overlapping_bookings',
            $caught->getMessage(),
            'PG error message must reference the exclusion constraint by name',
        );

        // And the refund row must remain in its settlement state — the failed
        // UPDATE has been rolled back by the exclusion violation.
        $this->assertDatabaseHas('bookings', [
            'id' => $oldId,
            'status' => $refundState->value,
        ]);
    }

    public static function refundSettlementStateProvider(): array
    {
        return [
            'refund_pending' => [BookingStatus::REFUND_PENDING],
            'refund_failed' => [BookingStatus::REFUND_FAILED],
        ];
    }
}
