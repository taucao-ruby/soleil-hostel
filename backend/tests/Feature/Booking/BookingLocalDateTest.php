<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\StripeService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

final class BookingLocalDateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_store_accepts_hostel_local_today_during_utc_previous_day_window(): void
    {
        $this->freezeHostelTime('2026-05-26 00:30:00');

        $user = User::factory()->create();
        $room = Room::factory()->available()->ready()->create(['price' => 150000]);

        // PAY-02 decoupled PaymentIntent creation from booking store: the
        // booking is created PENDING with no PaymentIntent, and the client
        // starts payment afterwards via POST /bookings/{booking}/payment-intent.
        // This test exercises hostel-local-date acceptance only.
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/bookings', [
                'room_id' => $room->id,
                'check_in' => '2026-05-26',
                'check_out' => '2026-05-27',
                'guest_name' => 'Local Today',
                'guest_email' => 'local-today@example.com',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('bookings', [
            'room_id' => $room->id,
            'guest_email' => 'local-today@example.com',
            'check_in' => '2026-05-26',
            'check_out' => '2026-05-27',
            'payment_intent_id' => null,
        ]);
    }

    public function test_store_rejects_hostel_local_yesterday_during_utc_previous_day_window(): void
    {
        $this->freezeHostelTime('2026-05-26 03:00:00');

        $user = User::factory()->create();
        $room = Room::factory()->available()->ready()->create(['price' => 150000]);

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('createPaymentIntent');
        });

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/bookings', [
                'room_id' => $room->id,
                'check_in' => '2026-05-25',
                'check_out' => '2026-05-26',
                'guest_name' => 'Local Yesterday',
                'guest_email' => 'local-yesterday@example.com',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['check_in']);
    }

    public function test_started_state_uses_hostel_local_civil_date(): void
    {
        $this->freezeHostelTime('2026-05-26 00:30:00');

        $started = Booking::factory()->make([
            'check_in' => '2026-05-26',
            'check_out' => '2026-05-27',
        ]);
        $future = Booking::factory()->make([
            'check_in' => '2026-05-27',
            'check_out' => '2026-05-28',
        ]);

        $this->assertTrue($started->isStarted());
        $this->assertFalse($future->isStarted());
    }

    public function test_cancellation_window_uses_hostel_local_check_in_midnight(): void
    {
        $this->freezeHostelTime('2026-05-26 00:30:00');
        config()->set('booking.cancellation.full_refund_hours', 48);
        config()->set('booking.cancellation.partial_refund_hours', 24);
        config()->set('booking.cancellation.partial_refund_pct', 50);
        config()->set('booking.cancellation.allow_fee', false);

        $booking = Booking::factory()->confirmed()->make([
            'check_in' => '2026-05-28',
            'check_out' => '2026-05-29',
            'amount' => 10000,
        ]);

        $policy = $booking->cancellationPolicy();

        $this->assertSame(47, $policy->hoursUntilCheckIn);
        $this->assertSame(50, $policy->refundPercent);
        $this->assertSame(5000, $booking->calculateRefundAmount());
        $this->assertSame(50, $booking->getRefundPercentage());
    }

    public function test_admin_check_in_filter_preserves_selected_hostel_local_date(): void
    {
        $this->freezeHostelTime('2026-05-26 00:30:00');

        $admin = User::factory()->admin()->create();
        $room = Room::factory()->create();

        $selectedDate = Booking::factory()->confirmed()->create([
            'room_id' => $room->id,
            'user_id' => $admin->id,
            'check_in' => '2026-05-26',
            'check_out' => '2026-05-27',
        ]);
        $previousDate = Booking::factory()->confirmed()->create([
            'room_id' => $room->id,
            'user_id' => $admin->id,
            'check_in' => '2026-05-25',
            'check_out' => '2026-05-26',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson(
            '/api/v1/admin/bookings?check_in_start=2026-05-26&check_in_end=2026-05-26&status=confirmed'
        );

        $response->assertOk();

        $ids = collect($response->json('data.bookings'))->pluck('id')->toArray();
        $this->assertContains($selectedDate->id, $ids);
        $this->assertNotContains($previousDate->id, $ids);
    }

    private function freezeHostelTime(string $localDateTime): void
    {
        config()->set('app.timezone', 'Asia/Ho_Chi_Minh');
        config()->set('booking.business_timezone', 'Asia/Ho_Chi_Minh');

        $now = CarbonImmutable::parse($localDateTime, 'Asia/Ho_Chi_Minh');

        Carbon::setTestNow($now->toMutable());
        CarbonImmutable::setTestNow($now);
    }
}
