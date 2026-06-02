<?php

namespace Tests\Feature\Migration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migration safety coverage for the F-47 / F-46 backlog items.
 *
 * PostgreSQL only: the assertions inspect real index/constraint catalog state,
 * and the production database is PostgreSQL. The suite runs on pgsql
 * (see phpunit.xml); on any other driver these tests skip cleanly so they never
 * become fragile on a SQLite-only machine.
 *
 * DDL issued here (DROP/CREATE INDEX) runs inside the RefreshDatabase
 * transaction. PostgreSQL has transactional DDL, so every change is rolled back
 * at teardown and no other test sees a mutated schema.
 */
class ReconcileLegacyIndexMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const RECONCILE_MIGRATION = 'migrations/2026_02_11_000000_reconcile_legacy_index_ordering.php';

    /**
     * Indexes this migration actually creates on the canonical chain (the two
     * that optimize_booking_indexes had dropped).
     */
    private const RECONCILE_OWNED = [
        ['bookings', 'bookings_room_id_check_in_check_out_index'],
        ['bookings', 'bookings_status_check_out_index'],
    ];

    /**
     * Indexes whose names up() guards but which pre-exist from earlier
     * migrations (create_bookings_table, add_user_id_to_bookings,
     * add_nplusone_fix_indexes). down() must NOT touch these.
     */
    private const PRE_EXISTING = [
        ['bookings', 'bookings_room_id_index'],
        ['bookings', 'bookings_user_id_index'],
        ['bookings', 'bookings_status_index'],
        ['bookings', 'bookings_user_id_check_in_index'],
        ['rooms', 'rooms_status_index'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Index reconciliation assertions require the PostgreSQL driver.');
        }
    }

    /**
     * F-47: down() is no longer a no-op and reverses *exactly* the indexes up()
     * creates on the standard chain — without clobbering the pre-existing
     * indexes owned by earlier migrations. up() then restores them, and down()
     * is idempotent thanks to the hasIndex guards.
     */
    public function test_reconcile_down_reverses_only_its_own_indexes_and_round_trips(): void
    {
        $migration = require database_path(self::RECONCILE_MIGRATION);

        // After migrate:fresh every index in play exists.
        foreach ([...self::RECONCILE_OWNED, ...self::PRE_EXISTING] as [$table, $index]) {
            $this->assertTrue(
                Schema::hasIndex($table, $index),
                "Expected {$index} to exist after migrate:fresh"
            );
        }

        // down() drops ONLY the two indexes this migration creates.
        $migration->down();

        foreach (self::RECONCILE_OWNED as [$table, $index]) {
            $this->assertFalse(
                Schema::hasIndex($table, $index),
                "down() must drop reconcile-owned index {$index}"
            );
        }
        foreach (self::PRE_EXISTING as [$table, $index]) {
            $this->assertTrue(
                Schema::hasIndex($table, $index),
                "down() must NOT drop pre-existing index {$index} (owned by an earlier migration)"
            );
        }

        // up() restores the reconcile-owned indexes (idempotent for the rest).
        $migration->up();

        foreach (self::RECONCILE_OWNED as [$table, $index]) {
            $this->assertTrue(
                Schema::hasIndex($table, $index),
                "up() must recreate reconcile-owned index {$index}"
            );
        }

        // down() guarded by hasIndex → calling it twice must not raise.
        $migration->down();
        $migration->down();

        foreach (self::RECONCILE_OWNED as [$table, $index]) {
            $this->assertFalse(
                Schema::hasIndex($table, $index),
                "down() must remain idempotent for {$index}"
            );
        }
    }

    /**
     * F-46: the reviews.booking_id FK is added on PostgreSQL. This proves the
     * driver guard (DB::getDriverName() !== 'pgsql') evaluates to false on pgsql
     * and the constraint is created — i.e. the guard was not inverted.
     */
    public function test_reviews_booking_id_fk_present_on_postgres(): void
    {
        $fk = DB::selectOne(
            "SELECT 1 AS ok FROM pg_constraint WHERE conname = 'fk_reviews_booking_id' AND contype = 'f'"
        );

        $this->assertNotNull(
            $fk,
            'F-46: reviews.booking_id FK (fk_reviews_booking_id) must be present after migrating on PostgreSQL'
        );
    }
}
