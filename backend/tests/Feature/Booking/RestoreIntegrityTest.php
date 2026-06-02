<?php

namespace Tests\Feature\Booking;

use App\Enums\UserRole;
use App\Events\BookingRestored;
use App\Exceptions\BookingRestoreConflictException;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\BookingService;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * RestoreIntegrityTest — Wave 1 restore-path correctness tests.
 *
 * Covers:
 * 1. Sequential overlap returns 422, not 500
 * 2. Concurrent 23P01 backstop returns 409, not 500
 * 3. Restore fires BookingRestored event
 * 4. Restore invalidates room availability cache
 * 5. Bulk restore supports mixed outcomes (partial success)
 * 6. Bulk conflict does not roll back already-restored items
 * 7. Bulk response shape contains success_count + failure_count
 * 8. BookingRestoreConflictException is thrown by service (not swallowed)
 */
class RestoreIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->user = User::factory()->user()->create();
        $this->room = Room::factory()->create();
    }

    // =========================================================================
    // Helper
    // =========================================================================

    private function trashedBooking(array $overrides = []): Booking
    {
        $booking = Booking::factory()
            ->forUser($this->user)
            ->forRoom($this->room)
            ->confirmed()
            ->create(array_merge([
                'check_in' => Carbon::now()->addDays(5)->startOfDay(),
                'check_out' => Carbon::now()->addDays(8)->startOfDay(),
            ], $overrides));

        $booking->softDeleteWithAudit($this->admin->id);

        return $booking->fresh();
    }

    /**
     * Creates a QueryException whose getCode() returns '23P01' (SQLSTATE 23P01).
     *
     * PDOException::$code is protected and Exception::getCode() is final, so
     * we can't subclass or override it normally. ReflectionProperty lets us
     * set the protected property directly, matching what PHP's PDO extension
     * does internally when a PostgreSQL exclusion constraint fires.
     */
    private function make23P01QueryException(): QueryException
    {
        $pdoException = new \PDOException('ERROR: conflicting key value violates exclusion constraint');
        $pdoException->errorInfo = ['23P01', null, null];

        $ref = new \ReflectionProperty(\Exception::class, 'code');
        $ref->setAccessible(true);
        $ref->setValue($pdoException, '23P01');

        return new QueryException(
            'pgsql',
            'INSERT INTO bookings ...',
            [],
            $pdoException
        );
    }

    // =========================================================================
    // PR-1A: Restore integrity
    // =========================================================================

    /**
     * Test 1: Sequential overlap detected inside transaction → 422 (not 500)
     *
     * A new active booking is created for the same dates before restoring the
     * trashed one. The lock-aware check detects the conflict and returns 422.
     */
    public function test_restore_with_sequential_conflict_returns_422(): void
    {
        $trashed = $this->trashedBooking();

        // Create a conflicting confirmed booking for the same room + dates
        Booking::factory()
            ->forUser($this->user)
            ->forRoom($this->room)
            ->confirmed()
            ->create([
                'check_in' => $trashed->check_in,
                'check_out' => $trashed->check_out,
            ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/bookings/{$trashed->id}/restore");

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('booking.restore_conflict'));

        // Booking must remain trashed
        $this->assertSoftDeleted('bookings', ['id' => $trashed->id]);
    }

    /**
     * Test 2: DB exclusion-constraint (SQLSTATE 23P01) backstop → 409 (not 500)
     *
     * Simulates the concurrent-restore scenario where the PostgreSQL exclusion
     * constraint fires. We mock BookingService::restore() to throw the typed
     * QueryException to verify the controller maps it to 409 Conflict.
     */
    public function test_restore_db_exclusion_constraint_returns_409(): void
    {
        $trashed = $this->trashedBooking();

        // Simulate the DB exclusion constraint exception (SQLSTATE 23P01)
        $queryException = $this->make23P01QueryException();

        /** @var \Mockery\MockInterface&BookingService $mockService */
        $mockService = $this->mock(BookingService::class);
        $mockService->shouldReceive('getTrashedBookingById')
            ->once()
            ->with($trashed->id)
            ->andReturn($trashed);
        $mockService->shouldReceive('restore')
            ->once()
            ->andThrow($queryException);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/bookings/{$trashed->id}/restore");

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('booking.restore_concurrent_conflict'));
    }

    /**
     * Test 3: Non-23P01 QueryException re-throws (not swallowed as conflict)
     *
     * A generic DB error must not be masked as a conflict response.
     */
    public function test_restore_non_conflict_query_exception_rethrows(): void
    {
        $trashed = $this->trashedBooking();

        $pdoException = new \PDOException('Disk full');
        $pdoException->errorInfo = ['HY000', null, null];

        $queryException = new QueryException('pgsql', 'INSERT ...', [], $pdoException);

        /** @var \Mockery\MockInterface&BookingService $mockService */
        $mockService = $this->mock(BookingService::class);
        $mockService->shouldReceive('getTrashedBookingById')
            ->once()
            ->andReturn($trashed);
        $mockService->shouldReceive('restore')
            ->once()
            ->andThrow($queryException);

        $this->expectException(QueryException::class);

        $this->withoutExceptionHandling()
            ->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/bookings/{$trashed->id}/restore");
    }

    /**
     * Test 4: BookingService::restore() throws BookingRestoreConflictException
     *         when lock-aware overlap check detects a conflict.
     *
     * This is a service-layer test verifying the domain exception is raised
     * (not swallowed) when the repository reports an overlap inside the
     * transaction. Uses the real service with a conflicting active booking in DB.
     */
    public function test_service_restore_throws_conflict_exception_when_overlap_exists(): void
    {
        $trashed = $this->trashedBooking();

        // Create an active booking that overlaps
        Booking::factory()
            ->forUser($this->user)
            ->forRoom($this->room)
            ->confirmed()
            ->create([
                'check_in' => $trashed->check_in,
                'check_out' => $trashed->check_out,
            ]);

        $this->expectException(BookingRestoreConflictException::class);

        app(BookingService::class)->restore($trashed);
    }

    /**
     * Test 5: Clean restore (no conflict) succeeds and booking is active.
     */
    public function test_restore_clean_succeeds(): void
    {
        $trashed = $this->trashedBooking();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/bookings/{$trashed->id}/restore");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('booking.restored'));

        // Booking is active again
        $booking = Booking::find($trashed->id);
        $this->assertNotNull($booking);
        $this->assertNull($booking->deleted_at);
        $this->assertNull($booking->deleted_by);
    }

    // =========================================================================
    // PR-1B: Cache correctness
    // =========================================================================

    /**
     * Test 6: Successful restore fires the BookingRestored event.
     *
     * The event drives the queued listener's availability cache invalidation
     * in addition to the synchronous call in BookingService::restore().
     */
    public function test_restore_fires_booking_restored_event(): void
    {
        Event::fake([BookingRestored::class]);

        $trashed = $this->trashedBooking();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/bookings/{$trashed->id}/restore")
            ->assertStatus(200);

        Event::assertDispatched(BookingRestored::class, function ($event) use ($trashed) {
            return $event->booking->id === $trashed->id;
        });
    }

    /**
     * Test 7: Successful restore invalidates room availability cache.
     *
     * Cache is seeded through RoomAvailabilityService::getRoomAvailability()
     * so it is stored with the correct tags. After restore,
     * BookingService::restore() must clear it via invalidateAvailability().
     */
    public function test_restore_invalidates_room_availability_cache(): void
    {
        $trashed = $this->trashedBooking();

        /** @var RoomAvailabilityService $svc */
        $svc = app(RoomAvailabilityService::class);

        // Prime the cache through the service (stores with proper tags)
        $svc->getRoomAvailability($this->room->id);

        // Verify the tagged cache key is now present
        $cacheKey = "room-availability:room:{$this->room->id}";
        $roomTag = "room-availability-{$this->room->id}";
        $cachedBefore = Cache::tags(['room-availability', $roomTag])->get($cacheKey);
        $this->assertNotNull($cachedBefore, 'Pre-condition: cache should be seeded via service');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/bookings/{$trashed->id}/restore")
            ->assertStatus(200);

        // invalidateAvailability() flushes Cache::tags([$roomTag]) and Cache::tags(['room-availability'])
        $cachedAfter = Cache::tags(['room-availability', $roomTag])->get($cacheKey);
        $this->assertNull($cachedAfter, 'Room availability tagged cache should be cleared after restore');
    }

    /**
     * Test 8: Conflict on restore does NOT fire BookingRestored event.
     */
    public function test_restore_conflict_does_not_fire_event(): void
    {
        Event::fake([BookingRestored::class]);

        $trashed = $this->trashedBooking();

        Booking::factory()
            ->forUser($this->user)
            ->forRoom($this->room)
            ->confirmed()
            ->create([
                'check_in' => $trashed->check_in,
                'check_out' => $trashed->check_out,
            ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/bookings/{$trashed->id}/restore")
            ->assertStatus(422);

        Event::assertNotDispatched(BookingRestored::class);
    }

    // =========================================================================
    // PR-1C: Bulk restore consistency
    // =========================================================================

    /**
     * Test 9: Bulk restore with all-success returns correct response shape.
     */
    public function test_bulk_restore_all_success_returns_correct_shape(): void
    {
        $b1 = $this->trashedBooking(['check_in' => Carbon::now()->addDays(5)->startOfDay(), 'check_out' => Carbon::now()->addDays(8)->startOfDay()]);
        $b2 = $this->trashedBooking(['check_in' => Carbon::now()->addDays(15)->startOfDay(), 'check_out' => Carbon::now()->addDays(18)->startOfDay()]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/bookings/restore-bulk', [
                'ids' => [$b1->id, $b2->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.success_count', 2)
            ->assertJsonPath('data.failure_count', 0)
            ->assertJsonPath('data.restored_count', 2)  // backward compat
            ->assertJsonPath('data.failed', []);

        $this->assertNotNull(Booking::find($b1->id));
        $this->assertNotNull(Booking::find($b2->id));
    }

    /**
     * Test 10: Bulk restore with mixed outcomes — conflict does not roll back
     *          the successfully restored booking.
     *
     * b1 — clean (will succeed)
     * b2 — has an overlapping active booking (will fail with 'Date conflict')
     */
    public function test_bulk_restore_mixed_outcome_partial_success(): void
    {
        $b1 = $this->trashedBooking(['check_in' => Carbon::now()->addDays(5)->startOfDay(), 'check_out' => Carbon::now()->addDays(8)->startOfDay()]);
        $b2 = $this->trashedBooking(['check_in' => Carbon::now()->addDays(20)->startOfDay(), 'check_out' => Carbon::now()->addDays(23)->startOfDay()]);

        // Create a blocker for b2
        Booking::factory()
            ->forUser($this->user)
            ->forRoom($this->room)
            ->confirmed()
            ->create([
                'check_in' => $b2->check_in,
                'check_out' => $b2->check_out,
            ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/bookings/restore-bulk', [
                'ids' => [$b1->id, $b2->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.success_count', 1)
            ->assertJsonPath('data.failure_count', 1)
            ->assertJsonPath('data.restored_count', 1);  // backward compat

        // b1 must be restored
        $this->assertNotNull(Booking::find($b1->id));

        // b2 must still be trashed
        $this->assertSoftDeleted('bookings', ['id' => $b2->id]);

        // failed array must explain the failure
        $failed = $response->json('data.failed');
        $this->assertCount(1, $failed);
        $this->assertEquals($b2->id, $failed[0]['id']);
        $this->assertEquals(__('booking.bulk_date_conflict'), $failed[0]['reason']);
    }

    /**
     * Test 11: Bulk restore with a non-trashed ID — only that item fails.
     *
     * An active (non-deleted) booking exists in the DB and passes the
     * `exists:bookings,id` validation, but getTrashedBookingById() returns null
     * because onlyTrashed() excludes it. The controller maps it to a per-item
     * failure without affecting the successfully restored booking.
     */
    public function test_bulk_restore_not_found_id_is_per_item_failure(): void
    {
        $b1 = $this->trashedBooking(['check_in' => Carbon::now()->addDays(5)->startOfDay(), 'check_out' => Carbon::now()->addDays(8)->startOfDay()]);

        // Active (non-trashed) booking — passes exists validation but is not trashed
        $activeBooking = Booking::factory()
            ->forUser($this->user)
            ->forRoom($this->room)
            ->confirmed()
            ->create([
                'check_in' => Carbon::now()->addDays(20)->startOfDay(),
                'check_out' => Carbon::now()->addDays(23)->startOfDay(),
            ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/bookings/restore-bulk', [
                'ids' => [$b1->id, $activeBooking->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.success_count', 1)
            ->assertJsonPath('data.failure_count', 1);

        $failed = $response->json('data.failed');
        $this->assertCount(1, $failed);
        $this->assertEquals($activeBooking->id, $failed[0]['id']);
        $this->assertEquals(__('booking.bulk_not_found'), $failed[0]['reason']);

        // b1 still restored
        $this->assertNotNull(Booking::find($b1->id));
    }

    /**
     * Test 12: Bulk restore with all conflicting — all fail, none restored.
     */
    public function test_bulk_restore_all_conflict_returns_zero_success(): void
    {
        $b1 = $this->trashedBooking(['check_in' => Carbon::now()->addDays(5)->startOfDay(), 'check_out' => Carbon::now()->addDays(8)->startOfDay()]);
        $b2 = $this->trashedBooking(['check_in' => Carbon::now()->addDays(15)->startOfDay(), 'check_out' => Carbon::now()->addDays(18)->startOfDay()]);

        // Create blockers for both
        Booking::factory()->forUser($this->user)->forRoom($this->room)->confirmed()->create([
            'check_in' => $b1->check_in, 'check_out' => $b1->check_out,
        ]);
        Booking::factory()->forUser($this->user)->forRoom($this->room)->confirmed()->create([
            'check_in' => $b2->check_in, 'check_out' => $b2->check_out,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/bookings/restore-bulk', [
                'ids' => [$b1->id, $b2->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.success_count', 0)
            ->assertJsonPath('data.failure_count', 2)
            ->assertJsonPath('data.restored_count', 0);

        $this->assertSoftDeleted('bookings', ['id' => $b1->id]);
        $this->assertSoftDeleted('bookings', ['id' => $b2->id]);
    }

    /**
     * Test 13: Bulk restore DB 23P01 on one item maps to bulk_date_conflict,
     *          other items are unaffected.
     */
    public function test_bulk_restore_concurrent_db_conflict_per_item(): void
    {
        $b1 = $this->trashedBooking(['check_in' => Carbon::now()->addDays(5)->startOfDay(), 'check_out' => Carbon::now()->addDays(8)->startOfDay()]);
        $b2 = $this->trashedBooking(['check_in' => Carbon::now()->addDays(20)->startOfDay(), 'check_out' => Carbon::now()->addDays(23)->startOfDay()]);

        // Simulate 23P01 only for b2
        $queryException = $this->make23P01QueryException();

        /** @var \Mockery\MockInterface&BookingService $mockService */
        $mockService = $this->mock(BookingService::class);
        // The trashed lookup is now batched through the (real) repository, so the
        // controller no longer calls BookingService::getTrashedBookingById here.
        // Only the per-row restore() is exercised on the mocked service; it is
        // matched by booking id because the repository returns freshly fetched
        // models rather than these in-memory fixtures.
        $mockService->shouldReceive('restore')
            ->with(\Mockery::on(fn ($b) => $b->id === $b1->id))
            ->andReturn(true);
        $mockService->shouldReceive('restore')
            ->with(\Mockery::on(fn ($b) => $b->id === $b2->id))
            ->andThrow($queryException);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/bookings/restore-bulk', [
                'ids' => [$b1->id, $b2->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.success_count', 1)
            ->assertJsonPath('data.failure_count', 1);

        $failed = $response->json('data.failed');
        $this->assertEquals($b2->id, $failed[0]['id']);
        $this->assertEquals(__('booking.bulk_date_conflict'), $failed[0]['reason']);
    }

    /**
     * Test 14: Bulk restore fires BookingRestored event for each successful restore.
     */
    public function test_bulk_restore_fires_event_per_successful_restore(): void
    {
        Event::fake([BookingRestored::class]);

        $b1 = $this->trashedBooking(['check_in' => Carbon::now()->addDays(5)->startOfDay(), 'check_out' => Carbon::now()->addDays(8)->startOfDay()]);
        $b2 = $this->trashedBooking(['check_in' => Carbon::now()->addDays(15)->startOfDay(), 'check_out' => Carbon::now()->addDays(18)->startOfDay()]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/bookings/restore-bulk', [
                'ids' => [$b1->id, $b2->id],
            ])
            ->assertStatus(200);

        Event::assertDispatched(BookingRestored::class, 2);
    }

    /**
     * F-45 regression: bulk restore must batch the trashed-booking lookup into a
     * single query instead of one find() per id (N+1).
     *
     * Scope of the assertion: only the *lookup* phase is O(1). The restore work
     * itself stays O(N) by design — each booking still gets its own lock, overlap
     * check, audit write, and BookingRestored event — so this test deliberately
     * does NOT assert a constant total query count. It asserts (a) at least one
     * batched onlyTrashed() whereIn lookup runs, and (b) zero single-id trashed
     * find() lookups (the old N+1 shape).
     */
    public function test_bulk_restore_batches_trashed_lookup_no_n_plus_one(): void
    {
        $b1 = $this->trashedBooking(['check_in' => Carbon::now()->addDays(5)->startOfDay(), 'check_out' => Carbon::now()->addDays(8)->startOfDay()]);
        $b2 = $this->trashedBooking(['check_in' => Carbon::now()->addDays(15)->startOfDay(), 'check_out' => Carbon::now()->addDays(18)->startOfDay()]);
        $b3 = $this->trashedBooking(['check_in' => Carbon::now()->addDays(25)->startOfDay(), 'check_out' => Carbon::now()->addDays(28)->startOfDay()]);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/bookings/restore-bulk', [
                'ids' => [$b1->id, $b2->id, $b3->id],
            ]);

        $queries = collect(DB::getQueryLog())->pluck('query')->map(fn ($q) => strtolower($q));
        DB::disableQueryLog();

        $response->assertStatus(200)
            ->assertJsonPath('data.success_count', 3)
            ->assertJsonPath('data.failure_count', 0);

        // A "trashed lookup" is a SELECT against bookings filtered by the
        // onlyTrashed() scope ("deleted_at ... is not null"). The restore path
        // locks rows with withTrashed() (no deleted_at predicate), so it is not
        // matched here. SQL is matched loosely to survive SQLite/PG grammar.
        $isTrashedBookingsSelect = fn (string $q): bool => str_contains($q, 'from "bookings"')
            && str_contains($q, 'deleted_at')
            && str_contains($q, 'is not null');

        // Batched preload: a single whereIn over the requested ids.
        $batched = $queries->filter(fn ($q) => $isTrashedBookingsSelect($q) && str_contains($q, ' in ('));

        // Old N+1 shape: one single-id trashed find() per requested id.
        $perId = $queries->filter(fn ($q) => $isTrashedBookingsSelect($q)
            && ! str_contains($q, ' in (')
            && str_contains($q, '"id" = ?'));

        $this->assertGreaterThanOrEqual(
            1,
            $batched->count(),
            'restoreBulk must batch the trashed lookup into a single whereIn query'
        );
        $this->assertCount(
            0,
            $perId,
            'restoreBulk must not look up trashed bookings one id at a time (N+1 regression)'
        );
    }

    /**
     * Test 15: Moderator cannot access restore (admin-only gate).
     *          Included here to confirm the RBAC boundary is not broken by Wave 1.
     */
    public function test_moderator_cannot_restore_booking(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::MODERATOR]);
        $trashed = $this->trashedBooking();

        $this->actingAs($moderator, 'sanctum')
            ->postJson("/api/v1/admin/bookings/{$trashed->id}/restore")
            ->assertStatus(403);
    }

    /**
     * Test 16: Moderator cannot bulk restore (admin-only gate).
     */
    public function test_moderator_cannot_bulk_restore(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::MODERATOR]);
        $trashed = $this->trashedBooking();

        $this->actingAs($moderator, 'sanctum')
            ->postJson('/api/v1/admin/bookings/restore-bulk', ['ids' => [$trashed->id]])
            ->assertStatus(403);
    }

    // =========================================================================
    // BL-1: PostgreSQL EXCLUDE backstop for empty-overlap-set restore race
    // =========================================================================

    /**
     * BL-1 contract: when the app-layer lock-aware overlap check returns
     * empty (because no active overlapping row exists at lock time), the
     * PostgreSQL EXCLUDE constraint (no_overlapping_bookings) is the sole
     * authority that prevents the restore UPDATE from creating an overlap.
     *
     * This test exercises a REAL exclusion-constraint violation against the
     * real database — no mocked QueryException. It simulates the empty-set
     * race by stubbing BookingRepositoryInterface::hasOverlappingBookingsWithLock
     * so the service believes there is no overlap to lock, then proves that
     * the subsequent `UPDATE bookings SET deleted_at = NULL ...` issued by
     * Eloquent's SoftDeletes::restore() trips the partial-index exclusion
     * constraint and raises SQLSTATE 23P01.
     *
     * Why this is the right shape of test for BL-1:
     *   - Existing test_restore_db_exclusion_constraint_returns_409 uses a
     *     reflection-built QueryException — it proves the controller maps a
     *     23P01 to 409, but does NOT prove the DB actually fires 23P01 on
     *     the restore UPDATE.
     *   - Existing test_postgres_exclusion_constraint_emits_sqlstate_23p01
     *     proves 23P01 fires on a raw INSERT, but not on the restore UPDATE
     *     path that flips deleted_at NOT NULL → NULL.
     *   - This test closes both gaps for the restore flow: the empty-set
     *     race is the precise BL-1 scenario, and the assertion is on real
     *     PG behavior, not a fixture.
     *
     * Skipped on non-pgsql drivers — the EXCLUDE USING gist constraint and
     * the partial-index predicate are PostgreSQL-specific.
     */
    public function test_restore_pg_exclude_fires_23p01_when_app_lock_sees_empty_set(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('BL-1 EXCLUDE backstop requires PostgreSQL driver.');
        }

        // Trashed booking — created and soft-deleted FIRST so the factory's
        // active INSERT does not collide with any blocker. After soft delete
        // the row sits outside the partial-index predicate
        // (status IN ('pending','confirmed') AND deleted_at IS NULL).
        $trashed = $this->trashedBooking([
            'check_in' => Carbon::parse('2026-06-02')->startOfDay(),
            'check_out' => Carbon::parse('2026-06-05')->startOfDay(),
        ]);

        // Conflicting active booking that the partial-index predicate includes.
        // Inserts cleanly because the trashed candidate is outside the predicate.
        // This is the row the EXCLUDE constraint will compare the restore UPDATE
        // against once the test forces the restore to proceed past the empty
        // app-layer lock check.
        $blocker = Booking::factory()
            ->forUser($this->user)
            ->forRoom($this->room)
            ->confirmed()
            ->create([
                'check_in' => Carbon::parse('2026-06-01')->startOfDay(),
                'check_out' => Carbon::parse('2026-06-04')->startOfDay(),
            ]);

        // Simulate the empty-overlap-set race window: the app-layer
        // hasOverlappingBookingsWithLock returns false even though PG sees
        // the overlap. This is exactly what happens when two restores both
        // observe zero conflicts before either commits.
        $repo = \Mockery::mock(BookingRepositoryInterface::class);
        $repo->shouldReceive('hasOverlappingBookingsWithLock')
            ->once()
            ->andReturn(false);
        $this->app->instance(BookingRepositoryInterface::class, $repo);

        $service = $this->app->make(BookingService::class);

        $caught = null;

        try {
            $service->restore($trashed);
        } catch (QueryException $e) {
            $caught = $e;
        }

        $this->assertNotNull(
            $caught,
            'PG EXCLUDE constraint must fire on restore UPDATE when app-layer lock missed the conflict'
        );
        $this->assertSame(
            '23P01',
            $caught->getCode(),
            'Expected SQLSTATE 23P01 from no_overlapping_bookings on restore UPDATE'
        );
        $this->assertSame(
            '23P01',
            $caught->errorInfo[0] ?? null,
            'errorInfo[0] must also carry SQLSTATE 23P01 (relied on by isPgExclusionViolation helper)'
        );
        $this->assertStringContainsStringIgnoringCase(
            'no_overlapping_bookings',
            $caught->getMessage(),
            'PG error message must reference the exclusion constraint name'
        );

        // Final DB state: blocker remains active, trashed booking remains trashed.
        // The restore UPDATE was rolled back by PG and the service transaction.
        $this->assertNull(
            Booking::find($blocker->id)?->deleted_at,
            'Blocker booking must remain active and committed'
        );
        $this->assertSoftDeleted('bookings', ['id' => $trashed->id]);

        // Invariant: no two active overlapping bookings for this room.
        $activeOverlap = Booking::query()
            ->where('room_id', $this->room->id)
            ->whereIn('status', Booking::ACTIVE_STATUSES)
            ->where('check_in', '<', Carbon::parse('2026-06-05')->startOfDay())
            ->where('check_out', '>', Carbon::parse('2026-06-01')->startOfDay())
            ->count();
        $this->assertSame(1, $activeOverlap, 'DB must contain exactly one active booking in the contested range');
    }

    /**
     * BL-1 end-to-end: when PG EXCLUDE fires on the restore UPDATE, the
     * AdminBookingController must surface a 409 Conflict (not 500) and must
     * not leak SQLSTATE / constraint internals to the client.
     *
     * Pairs with the service-layer test above: the service-layer test proves
     * PG raises 23P01; this test proves the controller's isPgExclusionViolation
     * helper recognizes the real PG exception (not just our reflection fixture).
     */
    public function test_restore_returns_409_on_real_pg_exclusion_violation(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('BL-1 EXCLUDE backstop requires PostgreSQL driver.');
        }

        // Create trashed candidate first so the factory's active INSERT does
        // not collide with the blocker (see test above for rationale).
        $trashed = $this->trashedBooking([
            'check_in' => Carbon::parse('2026-07-02')->startOfDay(),
            'check_out' => Carbon::parse('2026-07-05')->startOfDay(),
        ]);

        $blocker = Booking::factory()
            ->forUser($this->user)
            ->forRoom($this->room)
            ->confirmed()
            ->create([
                'check_in' => Carbon::parse('2026-07-01')->startOfDay(),
                'check_out' => Carbon::parse('2026-07-04')->startOfDay(),
            ]);

        // Force the empty-overlap-set window at the service layer.
        $repo = \Mockery::mock(BookingRepositoryInterface::class);
        $repo->shouldReceive('hasOverlappingBookingsWithLock')
            ->once()
            ->andReturn(false);
        $this->app->instance(BookingRepositoryInterface::class, $repo);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/bookings/{$trashed->id}/restore");

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('booking.restore_concurrent_conflict'));

        // Must not leak SQLSTATE or constraint internals
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('23P01', $body, 'SQLSTATE 23P01 must not leak to client');
        $this->assertStringNotContainsString('SQLSTATE', $body, 'SQLSTATE prefix must not leak to client');
        $this->assertStringNotContainsString('no_overlapping_bookings', $body, 'Constraint name must not leak to client');

        // Trashed booking must remain trashed and blocker remains active
        $this->assertSoftDeleted('bookings', ['id' => $trashed->id]);
        $this->assertNull(Booking::find($blocker->id)?->deleted_at);
    }
}
