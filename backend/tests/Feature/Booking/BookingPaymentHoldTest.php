<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Jobs\ExpireStaleBookings;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\StripeService;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class BookingPaymentHoldTest extends TestCase
{
    use RefreshDatabase;

    public function test_third_pending_booking_for_same_user_returns_pending_limit_error(): void
    {
        config()->set('bookings.max_pending_per_user', 2);

        $user = User::factory()->create();

        Booking::factory()
            ->for($user)
            ->for(Room::factory()->available()->ready())
            ->pending()
            ->create();

        Booking::factory()
            ->for($user)
            ->for(Room::factory()->available()->ready())
            ->pending()
            ->create();

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('createPaymentIntent');
        });

        $room = Room::factory()->available()->ready()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/bookings', [
                'room_id' => $room->id,
                'check_in' => Carbon::now()->addDays(30)->format('Y-m-d'),
                'check_out' => Carbon::now()->addDays(32)->format('Y-m-d'),
                'guest_name' => 'Limit Guest',
                'guest_email' => 'limit@example.com',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.code', 'PENDING_LIMIT_EXCEEDED');
    }

    public function test_booking_creation_rolls_back_when_payment_intent_creation_fails(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->available()->ready()->create(['price' => 125000]);

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andThrow(new Exception('Stripe unavailable'));
        });

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/bookings', [
                'room_id' => $room->id,
                'check_in' => Carbon::now()->addDays(10)->format('Y-m-d'),
                'check_out' => Carbon::now()->addDays(12)->format('Y-m-d'),
                'guest_name' => 'Rollback Guest',
                'guest_email' => 'rollback@example.com',
            ]);

        $response->assertStatus(500);

        $this->assertDatabaseMissing('bookings', [
            'guest_email' => 'rollback@example.com',
            'status' => BookingStatus::PENDING->value,
        ]);
    }

    public function test_created_pending_booking_stores_payment_intent_without_exposing_it(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->available()->ready()->create(['price' => 150000]);

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->with(Mockery::on(function (Booking $booking): bool {
                    return $booking->status === BookingStatus::PENDING
                        && $booking->amount === 300000;
                }))
                ->andReturn('pi_hold_test_123');
        });

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/bookings', [
                'room_id' => $room->id,
                'check_in' => Carbon::now()->addDays(20)->format('Y-m-d'),
                'check_out' => Carbon::now()->addDays(22)->format('Y-m-d'),
                'guest_name' => 'Payment Guest',
                'guest_email' => 'payment@example.com',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertArrayNotHasKey('payment_intent_id', $data);

        $this->assertDatabaseHas('bookings', [
            'guest_email' => 'payment@example.com',
            'payment_intent_id' => 'pi_hold_test_123',
            'amount' => 300000,
        ]);
    }

    public function test_expire_stale_bookings_cancels_payment_intent_before_expiring_booking(): void
    {
        config()->set('booking.pending_ttl_minutes', 30);

        $booking = Booking::factory()
            ->for(User::factory())
            ->for(Room::factory()->available()->ready())
            ->pending()
            ->create([
                'payment_intent_id' => 'pi_expire_test_123',
                'amount' => 300000,
                'check_in' => Carbon::now()->addDays(5)->startOfDay(),
                'check_out' => Carbon::now()->addDays(7)->startOfDay(),
            ]);

        $past = Carbon::now()->subMinutes(45);
        Booking::query()->whereKey($booking->id)->update([
            'created_at' => $past,
            'updated_at' => $past,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('cancelPaymentIntent')
                ->once()
                ->with('pi_expire_test_123');
        });

        (new ExpireStaleBookings)->handle();

        $booking->refresh();

        $this->assertSame(BookingStatus::CANCELLED, $booking->status);
        $this->assertSame(ExpireStaleBookings::EXPIRED_REASON, $booking->cancellation_reason);
    }
}
