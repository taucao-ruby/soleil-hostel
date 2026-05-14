<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AdminBookingIndexValidationTest — F-7 hardening.
 *
 * GET /api/v1/admin/bookings previously read query filters straight from
 * $request->query() with no allowlist or bounds. AdminBookingIndexRequest now
 * validates them. These tests assert the validation contract (status allowlist,
 * Y-m-d dates, date-range ordering, bounded search, capped per_page) while
 * proving the existing RBAC source (route role:moderator + Gate
 * view-all-bookings) and response shape are unchanged.
 */
class AdminBookingIndexValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $moderator;

    private User $user;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->moderator = User::factory()->create(['role' => UserRole::MODERATOR]);
        $this->user = User::factory()->user()->create();
        $this->room = Room::factory()->create();
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function booking(array $overrides = []): Booking
    {
        return Booking::factory()->create(array_merge([
            'room_id' => $this->room->id,
            'user_id' => $this->admin->id,
            'check_in' => Carbon::today()->addDays(5)->toDateString(),
            'check_out' => Carbon::today()->addDays(7)->toDateString(),
            'status' => 'confirmed',
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

    private function asUser(): self
    {
        return $this->actingAs($this->user, 'sanctum');
    }

    // ─── 1. status allowlist ────────────────────────────────────────────────

    public function test_invalid_status_is_rejected(): void
    {
        $this->asAdmin()
            ->getJson('/api/v1/admin/bookings?status=hacked')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_all_valid_statuses_are_accepted(): void
    {
        foreach (BookingStatus::cases() as $status) {
            $this->asAdmin()
                ->getJson('/api/v1/admin/bookings?status='.$status->value)
                ->assertStatus(200);
        }
    }

    // ─── 2. date format ─────────────────────────────────────────────────────

    public function test_invalid_date_format_is_rejected(): void
    {
        $this->asAdmin()
            ->getJson('/api/v1/admin/bookings?check_in_start=not-a-date')
            ->assertStatus(422)
            ->assertJsonValidationErrors('check_in_start');
    }

    public function test_non_ymd_date_format_is_rejected(): void
    {
        // A real date in a non-Y-m-d format is still rejected — filters are date-only.
        $this->asAdmin()
            ->getJson('/api/v1/admin/bookings?check_out_end=2026/05/14')
            ->assertStatus(422)
            ->assertJsonValidationErrors('check_out_end');
    }

    // ─── 3. date-range ordering ─────────────────────────────────────────────

    public function test_check_in_end_before_check_in_start_is_rejected(): void
    {
        $this->asAdmin()
            ->getJson('/api/v1/admin/bookings?check_in_start=2026-05-14&check_in_end=2026-05-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('check_in_end');
    }

    public function test_check_out_end_before_check_out_start_is_rejected(): void
    {
        $this->asAdmin()
            ->getJson('/api/v1/admin/bookings?check_out_start=2026-05-14&check_out_end=2026-05-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('check_out_end');
    }

    public function test_equal_check_in_range_is_accepted(): void
    {
        // TodayOperations sends check_in_start == check_in_end for same-day arrivals.
        $today = Carbon::today()->toDateString();

        $this->asAdmin()
            ->getJson("/api/v1/admin/bookings?check_in_start={$today}&check_in_end={$today}")
            ->assertStatus(200);
    }

    public function test_single_open_ended_date_bound_is_accepted(): void
    {
        // Only one end of a range supplied — the cross-field rule must not misfire.
        $this->asAdmin()
            ->getJson('/api/v1/admin/bookings?check_in_end=2026-05-31')
            ->assertStatus(200);

        $this->asAdmin()
            ->getJson('/api/v1/admin/bookings?check_out_start=2026-05-01')
            ->assertStatus(200);
    }

    // ─── 4. search bound ────────────────────────────────────────────────────

    public function test_search_exceeding_max_length_is_rejected(): void
    {
        $this->asAdmin()
            ->getJson('/api/v1/admin/bookings?search='.str_repeat('a', 256))
            ->assertStatus(422)
            ->assertJsonValidationErrors('search');
    }

    // ─── 5. location_id ─────────────────────────────────────────────────────

    public function test_non_integer_location_id_is_rejected(): void
    {
        $this->asAdmin()
            ->getJson('/api/v1/admin/bookings?location_id=not-an-int')
            ->assertStatus(422)
            ->assertJsonValidationErrors('location_id');
    }

    public function test_nonexistent_location_id_is_rejected(): void
    {
        $this->asAdmin()
            ->getJson('/api/v1/admin/bookings?location_id=999999')
            ->assertStatus(422)
            ->assertJsonValidationErrors('location_id');
    }

    // ─── 6. per_page bounds ─────────────────────────────────────────────────

    public function test_per_page_zero_is_rejected(): void
    {
        $this->asAdmin()
            ->getJson('/api/v1/admin/bookings?per_page=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_per_page_above_max_is_rejected(): void
    {
        $this->asAdmin()
            ->getJson('/api/v1/admin/bookings?per_page=101')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_per_page_non_integer_is_rejected(): void
    {
        $this->asAdmin()
            ->getJson('/api/v1/admin/bookings?per_page=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_per_page_at_max_is_accepted(): void
    {
        $this->asAdmin()
            ->getJson('/api/v1/admin/bookings?per_page=100')
            ->assertStatus(200);
    }

    public function test_per_page_is_applied_to_pagination(): void
    {
        // Distinct, non-overlapping date ranges so the exclusion constraint holds.
        $this->booking(['check_in' => '2026-01-01', 'check_out' => '2026-01-03']);
        $this->booking(['check_in' => '2026-02-01', 'check_out' => '2026-02-03']);
        $this->booking(['check_in' => '2026-03-01', 'check_out' => '2026-03-03']);

        $response = $this->asAdmin()->getJson('/api/v1/admin/bookings?per_page=1');

        $response->assertStatus(200)
            ->assertJsonPath('data.meta.per_page', 1);
        $this->assertCount(1, $response->json('data.bookings'));
    }

    public function test_default_per_page_is_preserved_at_50(): void
    {
        $response = $this->asAdmin()->getJson('/api/v1/admin/bookings');

        $response->assertStatus(200)
            ->assertJsonPath('data.meta.per_page', 50);
    }

    // ─── 7. RBAC preservation ───────────────────────────────────────────────

    public function test_authorized_admin_can_access_index(): void
    {
        $this->asAdmin()
            ->getJson('/api/v1/admin/bookings')
            ->assertStatus(200);
    }

    public function test_moderator_access_is_unchanged(): void
    {
        $this->asModerator()
            ->getJson('/api/v1/admin/bookings?status=confirmed&per_page=100')
            ->assertStatus(200);
    }

    public function test_moderator_invalid_filter_is_rejected(): void
    {
        // The authorized lower role still goes through validation.
        $this->asModerator()
            ->getJson('/api/v1/admin/bookings?status=hacked')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_user_role_is_forbidden(): void
    {
        $this->asUser()
            ->getJson('/api/v1/admin/bookings')
            ->assertStatus(403);
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson('/api/v1/admin/bookings')
            ->assertStatus(401);
    }

    public function test_invalid_filter_does_not_leak_to_unauthorized_user(): void
    {
        // RBAC runs before validation — a user role with a bad filter still
        // gets 403, never a 422 that would confirm the endpoint shape.
        $this->asUser()
            ->getJson('/api/v1/admin/bookings?status=hacked')
            ->assertStatus(403);
    }

    // ─── 8. valid-filter regression guard ───────────────────────────────────

    public function test_valid_filters_are_accepted_and_response_shape_is_preserved(): void
    {
        $this->booking();

        $this->asAdmin()
            ->getJson('/api/v1/admin/bookings?check_in_start=2026-05-01&check_in_end=2026-05-31&status=confirmed&search=guest&per_page=25')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'bookings',
                    'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                ],
            ]);
    }
}
