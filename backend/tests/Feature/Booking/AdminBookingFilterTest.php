<?php

namespace Tests\Feature\Booking;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AdminBookingFilterTest — Validates that the admin booking index applies
 * filter parameters (check_in, check_out, status, location_id, search).
 *
 * Wave 2 — Lane 2A: TodayOperations backend filter support.
 *
 * Default Wave 0 semantics used (unsigned fields, recommended defaults):
 *   T2: check_in_start/end — inclusive DATE comparison (>= start, <= end)
 *   T3: check_out_start/end — inclusive DATE comparison (>= start, <= end)
 *   T4: status=confirmed only
 */
class AdminBookingFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $moderator;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->moderator = User::factory()->create(['role' => UserRole::MODERATOR]);
        $this->room = Room::factory()->create();
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function booking(array $overrides = []): Booking
    {
        return Booking::factory()->create(array_merge([
            'room_id'    => $this->room->id,
            'user_id'    => $this->admin->id,
            'check_in'   => Carbon::today()->addDays(5)->toDateString(),
            'check_out'  => Carbon::today()->addDays(7)->toDateString(),
            'status'     => 'confirmed',
        ], $overrides));
    }

    private function asAdmin(): self
    {
        return $this->actingAs($this->admin, 'sanctum');
    }

    private function asModerator(): self
    {
        return $this->actingAs($this->moderator, 'sanctum');
    }

    // ─── 1. No filters → returns all bookings ──────────────────────────────

    public function test_index_returns_all_bookings_when_no_filters(): void
    {
        $this->booking(['check_in' => '2026-01-01', 'check_out' => '2026-01-03']);
        $this->booking(['check_in' => '2026-02-01', 'check_out' => '2026-02-03']);

        $response = $this->asAdmin()->getJson('/api/v1/admin/bookings');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, count($response->json('data.bookings')));
    }

    // ─── 2. check_in_start filter ──────────────────────────────────────────

    public function test_check_in_start_filters_out_earlier_bookings(): void
    {
        // Booking before the filter date — should be excluded
        $this->booking(['check_in' => '2026-01-01', 'check_out' => '2026-01-03']);
        // Booking on or after the filter date — should be included
        $included = $this->booking(['check_in' => '2026-04-01', 'check_out' => '2026-04-03']);

        $response = $this->asAdmin()->getJson('/api/v1/admin/bookings?check_in_start=2026-04-01');

        $response->assertStatus(200);
        $ids = collect($response->json('data.bookings'))->pluck('id');
        $this->assertContains($included->id, $ids->toArray());
        // Old booking must be absent
        foreach ($ids as $id) {
            $booking = Booking::find($id);
            $this->assertGreaterThanOrEqual('2026-04-01', $booking->check_in);
        }
    }

    // ─── 3. check_in_end filter ────────────────────────────────────────────

    public function test_check_in_end_filters_out_later_bookings(): void
    {
        $included = $this->booking(['check_in' => '2026-03-01', 'check_out' => '2026-03-03']);
        // Booking after the filter end date — should be excluded
        $this->booking(['check_in' => '2026-06-01', 'check_out' => '2026-06-03']);

        $response = $this->asAdmin()->getJson('/api/v1/admin/bookings?check_in_end=2026-03-31');

        $response->assertStatus(200);
        $ids = collect($response->json('data.bookings'))->pluck('id');
        $this->assertContains($included->id, $ids->toArray());
        foreach ($ids as $id) {
            $booking = Booking::find($id);
            $this->assertLessThanOrEqual('2026-03-31', $booking->check_in);
        }
    }

    // ─── 4. TodayOperations arrivals: check_in_start = check_in_end = today ─

    public function test_same_day_check_in_range_returns_only_today_arrivals(): void
    {
        $today = Carbon::today()->toDateString();
        $tomorrow = Carbon::tomorrow()->toDateString();

        $todayArrival = $this->booking([
            'check_in'  => $today,
            'check_out' => $tomorrow,
            'status'    => 'confirmed',
        ]);
        $otherArrival = $this->booking([
            'check_in'  => Carbon::today()->addDays(3)->toDateString(),
            'check_out' => Carbon::today()->addDays(5)->toDateString(),
            'status'    => 'confirmed',
        ]);

        $response = $this->asAdmin()->getJson(
            "/api/v1/admin/bookings?check_in_start={$today}&check_in_end={$today}&status=confirmed"
        );

        $response->assertStatus(200);
        $ids = collect($response->json('data.bookings'))->pluck('id')->toArray();
        $this->assertContains($todayArrival->id, $ids);
        $this->assertNotContains($otherArrival->id, $ids);
    }

    // ─── 5. TodayOperations departures: check_out range ───────────────────

    public function test_same_day_check_out_range_returns_only_today_departures(): void
    {
        $today = Carbon::today()->toDateString();

        $todayDeparture = $this->booking([
            'check_in'  => Carbon::yesterday()->toDateString(),
            'check_out' => $today,
            'status'    => 'confirmed',
        ]);
        $otherDeparture = $this->booking([
            'check_in'  => Carbon::today()->addDays(2)->toDateString(),
            'check_out' => Carbon::today()->addDays(4)->toDateString(),
            'status'    => 'confirmed',
        ]);

        $response = $this->asAdmin()->getJson(
            "/api/v1/admin/bookings?check_out_start={$today}&check_out_end={$today}&status=confirmed"
        );

        $response->assertStatus(200);
        $ids = collect($response->json('data.bookings'))->pluck('id')->toArray();
        $this->assertContains($todayDeparture->id, $ids);
        $this->assertNotContains($otherDeparture->id, $ids);
    }

    // ─── 6. Status filter: confirmed only ─────────────────────────────────

    public function test_status_filter_returns_only_matching_status(): void
    {
        $confirmed = $this->booking(['status' => 'confirmed']);
        $pending   = $this->booking(['status' => 'pending',
            'check_in'  => Carbon::today()->addDays(10)->toDateString(),
            'check_out' => Carbon::today()->addDays(12)->toDateString(),
        ]);

        $response = $this->asAdmin()->getJson('/api/v1/admin/bookings?status=confirmed');

        $response->assertStatus(200);
        $ids = collect($response->json('data.bookings'))->pluck('id')->toArray();
        $this->assertContains($confirmed->id, $ids);
        $this->assertNotContains($pending->id, $ids);
    }

    public function test_status_filter_pending_excludes_confirmed(): void
    {
        $confirmed = $this->booking(['status' => 'confirmed']);
        $pending   = $this->booking(['status' => 'pending',
            'check_in'  => Carbon::today()->addDays(10)->toDateString(),
            'check_out' => Carbon::today()->addDays(12)->toDateString(),
        ]);

        $response = $this->asAdmin()->getJson('/api/v1/admin/bookings?status=pending');

        $response->assertStatus(200);
        $ids = collect($response->json('data.bookings'))->pluck('id')->toArray();
        $this->assertContains($pending->id, $ids);
        $this->assertNotContains($confirmed->id, $ids);
    }

    // ─── 7. location_id filter ────────────────────────────────────────────

    public function test_location_id_filter_returns_only_matching_location(): void
    {
        $locationA = Location::factory()->create();
        $locationB = Location::factory()->create();

        // Rooms must belong to the intended location so that the Booking Observer
        // (which auto-sets location_id from room.location_id) assigns the right value.
        $roomA = Room::factory()->create(['location_id' => $locationA->id]);
        $roomB = Room::factory()->create(['location_id' => $locationB->id]);

        $bookingA = Booking::factory()->create([
            'room_id'   => $roomA->id,
            'user_id'   => $this->admin->id,
            'check_in'  => Carbon::today()->addDays(5)->toDateString(),
            'check_out' => Carbon::today()->addDays(7)->toDateString(),
            'status'    => 'confirmed',
        ]);
        $bookingB = Booking::factory()->create([
            'room_id'   => $roomB->id,
            'user_id'   => $this->admin->id,
            'check_in'  => Carbon::today()->addDays(10)->toDateString(),
            'check_out' => Carbon::today()->addDays(12)->toDateString(),
            'status'    => 'confirmed',
        ]);

        // After creation, observer assigns location_id from room
        $this->assertEquals($locationA->id, $bookingA->fresh()->location_id);
        $this->assertEquals($locationB->id, $bookingB->fresh()->location_id);

        $response = $this->asAdmin()->getJson("/api/v1/admin/bookings?location_id={$locationA->id}");

        $response->assertStatus(200);
        $ids = collect($response->json('data.bookings'))->pluck('id')->toArray();
        $this->assertContains($bookingA->id, $ids);
        $this->assertNotContains($bookingB->id, $ids);
    }

    // ─── 8. Response shape: contains bookings array and meta pagination ────

    public function test_response_shape_contains_bookings_and_meta(): void
    {
        $this->booking();

        $response = $this->asAdmin()->getJson('/api/v1/admin/bookings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'bookings',
                    'meta' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                    ],
                ],
            ]);
    }

    // ─── 9. Soft-deleted bookings included by default (admin view) ─────────

    public function test_trashed_bookings_are_included_in_admin_index(): void
    {
        $active  = $this->booking();
        $trashed = $this->booking([
            'check_in'  => Carbon::today()->addDays(10)->toDateString(),
            'check_out' => Carbon::today()->addDays(12)->toDateString(),
        ]);
        $trashed->delete();

        $response = $this->asAdmin()->getJson('/api/v1/admin/bookings');

        $response->assertStatus(200);
        $ids = collect($response->json('data.bookings'))->pluck('id')->toArray();
        $this->assertContains($active->id, $ids);
        $this->assertContains($trashed->id, $ids);
    }

    // ─── 10. Moderator can call filtered index ─────────────────────────────

    public function test_moderator_can_use_check_in_filter(): void
    {
        $today = Carbon::today()->toDateString();
        $this->booking(['check_in' => $today, 'check_out' => Carbon::tomorrow()->toDateString(), 'status' => 'confirmed']);

        $response = $this->asModerator()->getJson(
            "/api/v1/admin/bookings?check_in_start={$today}&check_in_end={$today}&status=confirmed"
        );

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.bookings'));
    }

    // ─── 11. No status filter → soft-deleted bookings still included ───────

    public function test_status_filter_does_not_hide_soft_deleted_rows(): void
    {
        $trashed = $this->booking(['status' => 'confirmed']);
        $trashed->delete();

        // Without status filter, trashed must appear
        $response = $this->asAdmin()->getJson('/api/v1/admin/bookings');
        $ids = collect($response->json('data.bookings'))->pluck('id')->toArray();
        $this->assertContains($trashed->id, $ids);
    }
}
