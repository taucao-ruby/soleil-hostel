<?php

namespace Tests\Feature\Database;

use App\Models\Room;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CHECK Constraint Tests — verifies DB-level data integrity constraints.
 *
 * PostgreSQL only. Covers:
 * - chk_rooms_max_guests (migration 2026_03_17_000002)
 * - chk_rooms_price (migration 2026_02_22_000001, already present)
 * - chk_bookings_dates (migration 2026_02_22_000001, already present)
 * - chk_reviews_rating (migration 2026_02_22_000001, already present)
 * - no_overlapping_bookings predicate guard (migration 2026_02_12_000001) — F-80/C-1:
 *   asserts the live constraint PREDICATE, not mere existence, because the
 *   migration chain holds two historical versions (2025_12_18 without the
 *   deleted_at filter; the 2026_02_12 fix's down() restores the lax one)
 * - btree_gist extension presence (required by the exclusion constraint)
 * - chk_bookings_status (2026_03_17_000003) / chk_bookings_deposit_status (2026_05_02_000001)
 * - raw-DB overlap rejection with SQLSTATE 23P01 (exclusion_violation)
 */
#[\PHPUnit\Framework\Attributes\Group('booking')]
class CheckConstraintTest extends TestCase
{
    use RefreshDatabase;

    private function isPgsql(): bool
    {
        return \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql';
    }

    /**
     * Fetch the live, catalog-normalized definition of a constraint.
     *
     * pg_get_constraintdef() output is PostgreSQL's canonical rendering
     * (casts spelled out, IN-lists rewritten as = ANY(ARRAY[...])), NOT the
     * raw SQL from the migration — assertions below therefore target the
     * normalized text as verified against the live catalog.
     */
    private function constraintDef(string $table, string $constraint): ?string
    {
        $row = DB::selectOne(
            'SELECT pg_get_constraintdef(oid) AS def
             FROM pg_constraint
             WHERE conrelid = ?::regclass AND conname = ?',
            [$table, $constraint]
        );

        return $row?->def;
    }

    // ===== chk_rooms_max_guests =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_room_max_guests_zero_rejected(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('CHECK constraints require PostgreSQL');
        }

        $this->expectException(QueryException::class);

        Room::factory()->create(['max_guests' => 0]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_room_max_guests_negative_rejected(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('CHECK constraints require PostgreSQL');
        }

        $this->expectException(QueryException::class);

        Room::factory()->create(['max_guests' => -1]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_room_max_guests_positive_accepted(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('CHECK constraints require PostgreSQL');
        }

        $room = Room::factory()->create(['max_guests' => 1]);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'max_guests' => 1,
        ]);
    }

    // ===== no_overlapping_bookings predicate guard (F-80 / correction C-1) =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_overlap_exclusion_constraint_predicate_is_exact(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('Exclusion constraint requires PostgreSQL');
        }

        $def = $this->constraintDef('bookings', 'no_overlapping_bookings');

        $this->assertNotNull($def, 'bookings.no_overlapping_bookings exclusion constraint is missing');

        // GiST exclusion over (room_id =, half-open daterange &&).
        // Migration text: daterange(check_in, check_out, '[)') — catalog renders the bound spec with an explicit ::text cast.
        $this->assertStringContainsString('EXCLUDE USING gist', $def);
        $this->assertStringContainsString('room_id WITH =', $def);
        $this->assertStringContainsString("daterange(check_in, check_out, '[)'::text) WITH &&", $def);

        // Status filter — migration text: status IN ('pending', 'confirmed').
        // Asserting the catalog-normalized clause pins BOTH the active statuses and the absence of any other status.
        $this->assertStringContainsString(
            "(status)::text = ANY ((ARRAY['pending'::character varying, 'confirmed'::character varying])::text[])",
            $def
        );

        // Soft-delete filter — the 2026_02_12 fix; its absence means the lax 2025_12_18 predicate is live.
        $this->assertStringContainsString('deleted_at IS NULL', $def);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_btree_gist_extension_is_installed(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('btree_gist is a PostgreSQL extension');
        }

        $row = DB::selectOne("SELECT 1 AS present FROM pg_extension WHERE extname = 'btree_gist'");

        $this->assertNotNull($row, 'btree_gist extension is not installed — the overlap exclusion constraint cannot exist without it');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_bookings_check_constraints_have_correct_definitions(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('CHECK constraints require PostgreSQL');
        }

        // chk_bookings_status — the five BookingStatus enum values, nothing else.
        $statusDef = $this->constraintDef('bookings', 'chk_bookings_status');
        $this->assertNotNull($statusDef, 'chk_bookings_status is missing');
        $this->assertStringContainsString(
            "(status)::text = ANY ((ARRAY['pending'::character varying, 'confirmed'::character varying, "
            ."'refund_pending'::character varying, 'cancelled'::character varying, "
            ."'refund_failed'::character varying])::text[])",
            $statusDef
        );

        // chk_bookings_dates — half-open interval invariant: check_out strictly after check_in.
        $datesDef = $this->constraintDef('bookings', 'chk_bookings_dates');
        $this->assertNotNull($datesDef, 'chk_bookings_dates is missing');
        $this->assertStringContainsString('check_out > check_in', $datesDef);

        // chk_bookings_deposit_status — includes the CONC-005 terminal states (2026_05_02_000001 extension).
        $depositDef = $this->constraintDef('bookings', 'chk_bookings_deposit_status');
        $this->assertNotNull($depositDef, 'chk_bookings_deposit_status is missing');
        $this->assertStringContainsString(
            "(deposit_status)::text = ANY ((ARRAY['none'::character varying, 'collected'::character varying, "
            ."'applied'::character varying, 'refunded'::character varying, 'partial_refund'::character varying, "
            ."'forfeited'::character varying])::text[])",
            $depositDef
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_db_rejects_overlapping_bookings_independently_of_app_with_sqlstate_23p01(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('Exclusion constraint requires PostgreSQL');
        }

        $room = Room::factory()->create();

        $checkIn = now()->addDays(10)->toDateString();
        $checkOut = now()->addDays(13)->toDateString();

        // Bypass Eloquent entirely: no model events, no app-layer overlap validation.
        DB::insert(
            'INSERT INTO bookings (room_id, check_in, check_out, guest_name, guest_email, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [$room->id, $checkIn, $checkOut, 'First Guest', 'first@example.com', 'confirmed']
        );

        try {
            // Nested DB::transaction => SAVEPOINT under RefreshDatabase's wrapping
            // transaction; the 23P01 rolls back to the savepoint instead of
            // aborting the outer transaction (avoids 25P02 on the assertions below).
            DB::transaction(function () use ($room): void {
                DB::insert(
                    'INSERT INTO bookings (room_id, check_in, check_out, guest_name, guest_email, status, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())',
                    [
                        $room->id,
                        now()->addDays(11)->toDateString(),
                        now()->addDays(12)->toDateString(),
                        'Second Guest',
                        'second@example.com',
                        'pending',
                    ]
                );
            });

            $this->fail('Overlapping raw insert was accepted — the exclusion constraint did not fire');
        } catch (QueryException $e) {
            $sqlState = $e->errorInfo[0] ?? (string) $e->getCode();

            $this->assertSame(
                '23P01',
                $sqlState,
                'Expected SQLSTATE 23P01 (exclusion_violation), got '.$sqlState.': '.$e->getMessage()
            );
        }

        $this->assertSame(
            1,
            DB::table('bookings')->where('room_id', $room->id)->count(),
            'Exactly one booking must survive for the contested room/date range'
        );
    }

    // ===== money CHECK constraints (F-81 / P0-2, migration 2026_06_11_000001) =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_money_check_constraints_have_correct_definitions(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('CHECK constraints require PostgreSQL');
        }

        // Catalog-normalized definitions, verified against the live catalog.
        // NULL-tolerant where the column is nullable (NULL = "not applicable").
        $expected = [
            ['bookings', 'chk_bookings_amount_nonneg', 'CHECK (((amount IS NULL) OR (amount >= 0)))'],
            ['bookings', 'chk_bookings_refund_amount_nonneg', 'CHECK (((refund_amount IS NULL) OR (refund_amount >= 0)))'],
            ['bookings', 'chk_bookings_deposit_amount_nonneg', 'CHECK (((deposit_amount IS NULL) OR (deposit_amount >= 0)))'],
            ['bookings', 'chk_bookings_amount_capturable_nonneg', 'CHECK ((amount_capturable >= 0))'],
            ['bookings', 'chk_bookings_amount_received_nonneg', 'CHECK ((amount_received >= 0))'],
            ['bookings', 'chk_bookings_refund_le_amount', 'CHECK (((refund_amount IS NULL) OR (amount IS NULL) OR (refund_amount <= amount)))'],
            ['bookings', 'chk_bookings_deposit_le_amount', 'CHECK (((deposit_amount IS NULL) OR (amount IS NULL) OR (deposit_amount <= amount)))'],
            ['deposit_events', 'chk_deposit_events_refund_amount_nonneg', 'CHECK (((refund_amount IS NULL) OR (refund_amount >= 0)))'],
            ['service_recovery_cases', 'chk_src_refund_amount_nonneg', 'CHECK (((refund_amount IS NULL) OR (refund_amount >= 0)))'],
            ['service_recovery_cases', 'chk_src_voucher_amount_nonneg', 'CHECK (((voucher_amount IS NULL) OR (voucher_amount >= 0)))'],
            ['service_recovery_cases', 'chk_src_cost_delta_absorbed_nonneg', 'CHECK (((cost_delta_absorbed IS NULL) OR (cost_delta_absorbed >= 0)))'],
            ['stripe_refund_events', 'chk_stripe_refund_events_amount_refunded_nonneg', 'CHECK ((amount_refunded >= 0))'],
            // F-81 successor (migration 2026_06_24_000001): momo_payments arrived after the
            // F-81 pass and re-opened the class; expected_amount is NOT NULL so no NULL tolerance.
            ['momo_payments', 'chk_momo_payments_expected_amount_nonneg', 'CHECK ((expected_amount >= 0))'],
        ];

        foreach ($expected as [$table, $constraint, $definition]) {
            $this->assertSame(
                $definition,
                $this->constraintDef($table, $constraint),
                "{$table}.{$constraint} is missing or has the wrong definition"
            );
        }

        // [OUT OF SCOPE — UNPROVEN, needs PaymentStatus review, see F-81]:
        // intentionally NO cross-column capture-state constraints
        // (amount_received vs amount_capturable vs PaymentStatus phases).
        $this->assertNull(
            $this->constraintDef('bookings', 'chk_bookings_received_le_capturable'),
            'Capture-state cross-column constraint must not exist until the PaymentStatus review lands'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_negative_booking_amount_rejected_at_db_layer(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('CHECK constraints require PostgreSQL');
        }

        $room = Room::factory()->create();

        $this->expectException(QueryException::class);

        DB::insert(
            'INSERT INTO bookings (room_id, check_in, check_out, guest_name, guest_email, status, amount, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [$room->id, now()->addDays(20)->toDateString(), now()->addDays(22)->toDateString(), 'Guest', 'g@example.com', 'pending', -1]
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_refund_exceeding_amount_rejected_at_db_layer(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('CHECK constraints require PostgreSQL');
        }

        $room = Room::factory()->create();

        $this->expectException(QueryException::class);

        DB::insert(
            'INSERT INTO bookings (room_id, check_in, check_out, guest_name, guest_email, status, amount, refund_amount, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [$room->id, now()->addDays(30)->toDateString(), now()->addDays(32)->toDateString(), 'Guest', 'g@example.com', 'pending', 1000, 1001]
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_negative_momo_payment_expected_amount_rejected_at_db_layer(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('CHECK constraints require PostgreSQL');
        }

        // momo_payments is loosely coupled (no FK on booking_id), so a raw insert
        // with an arbitrary booking_id exercises only the money CHECK (F-81 successor).
        $this->expectException(QueryException::class);

        DB::insert(
            'INSERT INTO momo_payments (booking_id, order_id, expected_amount, currency, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())',
            [999999, 'order-neg-amount-'.uniqid(), -1, 'vnd', 'pending']
        );
    }
}
