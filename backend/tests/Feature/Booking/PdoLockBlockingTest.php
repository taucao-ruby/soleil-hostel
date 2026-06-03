<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PdoLockBlockingTest — T-1: the loser proof.
 *
 * Pairs with T-0 (winner acquires the lock). Together they prove the pessimistic
 * SELECT ... FOR UPDATE used by CreateBookingService genuinely serialises writers:
 *
 *   1. Connection A (default pgsql) opens a transaction and locks a booking row.
 *   2. Connection B (an independent PDO session) tries to lock the same row and
 *      must BLOCK, then FAIL with PostgreSQL lock_timeout SQLSTATE 55P03.
 *
 * Why the lock target is a *committed* row, not a Booking::factory() row:
 * RefreshDatabase wraps each test in an uncommitted transaction on the default
 * connection. A factory row therefore exists only inside that transaction and is
 * invisible to any other PDO session (MVCC). Connection B's `WHERE id = ?` would
 * match zero rows and return immediately — never blocking, never timing out. The
 * lock can only be contended for a row both sessions can see, i.e. a committed
 * one. We seed it over the loser connection (autocommit) together with the
 * throwaway location + room it needs as FK parents (bookings.room_id and
 * rooms.location_id are both enforced), and delete them explicitly afterwards
 * (they live outside RefreshDatabase's rollback). The dedicated room also keeps
 * the seed clear of the no_overlapping_bookings exclusion constraint.
 */
class PdoLockBlockingTest extends TestCase
{
    use RefreshDatabase;

    /** Ephemeral second-connection name registered at runtime (no config/database.php edit, C-2). */
    private const LOSER = 'pgsql_loser';

    /** Marker that uniquely identifies this test's committed seed booking for cleanup. */
    private const SEED_MARKER = 'pdo-lock-blocking@soleil.test';

    /** Marker (rooms.name) for the committed FK-parent room this test seeds. */
    private const ROOM_MARKER = 'PDO Lock Blocking Seed Room';

    /** Marker (locations.name) for the committed FK-grandparent location. */
    private const LOCATION_MARKER = 'PDO Lock Blocking Seed Location';

    protected function setUp(): void
    {
        parent::setUp();

        // Register an ephemeral connection mirroring the primary pgsql config,
        // then purge it so its first use yields a fresh, genuinely independent
        // PDO/TCP session rather than a reused one (C-2).
        $primaryConfig = config('database.connections.'.config('database.default'));
        config(['database.connections.'.self::LOSER => $primaryConfig]);
        DB::purge(self::LOSER);

        // Remove any committed seed leaked by a previously crashed run so a fresh
        // insert can never collide with the exclusion constraint.
        $this->purgeSeedRows();
    }

    public function test_second_pdo_connection_blocks_on_held_lock_then_fails(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('lock_timeout / SQLSTATE 55P03 is PostgreSQL-only');
        }

        // -- Step 1: Arrange a COMMITTED lock target over the loser connection.
        // bookings.room_id (RESTRICT) and rooms.location_id (NOT NULL) are both
        // enforced, so the lock target needs committed FK parents. Seed a
        // throwaway location + room via Eloquent (which applies the array /
        // enum casts), then insert the booking as a raw row (the minimal column
        // set proven by BookingLocationTriggerTest). The dedicated room keeps the
        // seed clear of the no_overlapping_bookings exclusion constraint.
        $location = Location::factory()->make([
            'name' => self::LOCATION_MARKER,
            'slug' => 'pdo-lock-seed-'.uniqid(),
        ]);
        $location->setConnection(self::LOSER);
        $location->save();

        $room = Room::factory()->make([
            'location_id' => $location->id,
            'room_number' => null,
            'name' => self::ROOM_MARKER,
        ]);
        $room->setConnection(self::LOSER);
        $room->save();

        $checkIn = Carbon::now()->addYears(5)->startOfDay();
        $lockedId = DB::connection(self::LOSER)->table('bookings')->insertGetId([
            'room_id' => $room->id,
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkIn->clone()->addDays(2)->toDateString(),
            'guest_name' => 'PDO Lock Blocking',
            'guest_email' => self::SEED_MARKER,
            'status' => BookingStatus::PENDING->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $winnerLockHeld = false;
        $loserTxnOpen = false;

        try {
            // -- Step 2: Connection A (winner) holds SELECT ... FOR UPDATE.
            DB::connection('pgsql')->beginTransaction();
            $winnerLockHeld = true;

            $locked = Booking::query()->where('id', $lockedId)->withLock()->first();
            $this->assertNotNull($locked, 'Connection A must see and lock the committed seed row.');

            // -- Step 3: Connection B (loser) attempts the same lock and blocks.
            DB::connection(self::LOSER)->beginTransaction();
            $loserTxnOpen = true;
            DB::connection(self::LOSER)->statement("SET LOCAL lock_timeout = '2s'");

            $start = microtime(true);

            try {
                DB::connection(self::LOSER)->table('bookings')
                    ->where('id', $lockedId)
                    ->lockForUpdate()
                    ->first();

                // Reached only if the loser did NOT block — the locking contract is broken.
                $this->fail('Loser connection acquired the lock immediately; expected it to block then time out (55P03).');
            } catch (QueryException $e) {
                $elapsed = microtime(true) - $start;

                // -- Step 4: It blocked (~2s), then failed with lock timeout.
                $this->assertSame('55P03', $e->getCode(), 'Expected PostgreSQL lock_timeout SQLSTATE 55P03.');
                $this->assertStringContainsString('lock timeout', $e->getMessage());
                $this->assertGreaterThanOrEqual(1.8, $elapsed, 'Loser must have blocked ~2s, not returned immediately.');
            }
        } finally {
            // -- Step 5: Release the loser's txn, then the winner's lock, then the seed.
            if ($loserTxnOpen && DB::connection(self::LOSER)->transactionLevel() > 0) {
                DB::connection(self::LOSER)->rollBack();
            }
            // Roll back only the savepoint we opened — never the outer RefreshDatabase
            // transaction (level 1) — which releases the winner's row lock.
            if ($winnerLockHeld && DB::connection('pgsql')->transactionLevel() > 1) {
                DB::connection('pgsql')->rollBack();
            }

            $this->purgeSeedRows();
        }
    }

    protected function tearDown(): void
    {
        // Guarantee cleanup even if the test failed before its finally block ran.
        try {
            if (DB::connection(self::LOSER)->transactionLevel() > 0) {
                DB::connection(self::LOSER)->rollBack();
            }
        } catch (\Throwable) {
            // connection may be unconfigured / already torn down
        }

        $this->purgeSeedRows();
        DB::purge(self::LOSER);

        parent::tearDown();
    }

    /**
     * Delete this test's committed seed row(s) over the loser connection.
     *
     * Bounded by a transaction-scoped lock_timeout so it can never hang if some
     * session still held the row; a leak would simply be purged by the next run.
     */
    private function purgeSeedRows(): void
    {
        try {
            $loser = DB::connection(self::LOSER);
            $loser->beginTransaction();
            $loser->statement("SET LOCAL lock_timeout = '3s'");
            $loser->table('bookings')->where('guest_email', self::SEED_MARKER)->delete();
            $loser->table('rooms')->where('name', self::ROOM_MARKER)->delete();
            $loser->table('locations')->where('name', self::LOCATION_MARKER)->delete();
            $loser->commit();
        } catch (\Throwable) {
            try {
                if (DB::connection(self::LOSER)->transactionLevel() > 0) {
                    DB::connection(self::LOSER)->rollBack();
                }
            } catch (\Throwable) {
                // give up silently; setUp of the next run retries the purge
            }
        }
    }
}
