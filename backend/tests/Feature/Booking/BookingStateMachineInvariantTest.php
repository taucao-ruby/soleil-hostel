<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\PaymentPolicy;
use App\Enums\PaymentStatus;
use App\Events\BookingStatusChanged;
use App\Exceptions\BookingTransitionException;
use App\Http\Controllers\Payment\StripeWebhookController;
use App\Jobs\ExpireStaleBookings;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Room;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class BookingStateMachineInvariantTest extends TestCase
{
    public function test_booking_creation_rejects_out_of_service_room_without_persisting_booking(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->create(['is_active' => true]);
        $room = Room::factory()
            ->forLocation($location)
            ->available()
            ->outOfService()
            ->create();

        $beforeCount = Booking::count();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/bookings', [
            'room_id' => $room->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guest_name' => 'Invariant Guest',
            'guest_email' => 'guest@example.test',
            'number_of_guests' => 1,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Room is not available for booking');

        $this->assertSame($beforeCount, Booking::count());
    }

    public function test_stale_confirm_cannot_resurrect_booking_cancelled_by_expiry(): void
    {
        config(['booking.pending_ttl_minutes' => 30]);

        $booking = Booking::factory()->create([
            'status' => BookingStatus::PENDING,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);
        $stalePendingInstance = $booking->fresh();

        (new ExpireStaleBookings)->handle();

        try {
            app(BookingService::class)->confirmBooking($stalePendingInstance);
        } catch (BookingTransitionException) {
            // Expected: transitionTo re-reads the row under lock and sees CANCELLED.
        }

        $booking->refresh();

        $this->assertContains($booking->status, [
            BookingStatus::CONFIRMED,
            BookingStatus::CANCELLED,
        ]);
        $this->assertSame(BookingStatus::CANCELLED, $booking->status);
        $this->assertSame(ExpireStaleBookings::EXPIRED_REASON, $booking->cancellation_reason);
    }

    public function test_payment_intent_succeeded_replay_is_idempotent(): void
    {
        Event::fake([BookingStatusChanged::class]);

        $booking = Booking::factory()->create([
            'status' => BookingStatus::PENDING,
            'payment_intent_id' => 'pi_replay_idempotent',
            'payment_policy' => PaymentPolicy::PREPAID,
            'payment_status' => PaymentStatus::REQUIRES_PAYMENT_METHOD,
            'payment_currency' => 'vnd',
            'amount' => 50000,
        ]);
        $payload = $this->paymentIntentSucceededPayload(
            eventId: 'evt_replay_idempotent',
            paymentIntentId: 'pi_replay_idempotent'
        );

        $first = $this->callStripeHandler('handlePaymentIntentSucceeded', $payload);
        $second = $this->callStripeHandler('handlePaymentIntentSucceeded', $payload);

        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame(200, $second->getStatusCode());
        $this->assertSame(BookingStatus::CONFIRMED, $booking->fresh()->status);
        $this->assertSame(1, StripeWebhookEvent::where('stripe_event_id', 'evt_replay_idempotent')->count());
        Event::assertDispatchedTimes(BookingStatusChanged::class, 1);
    }

    public function test_payment_intent_succeeded_failure_marks_event_failed_and_returns_retryable_status(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::PENDING,
            'payment_intent_id' => 'pi_forced_failure',
            'payment_policy' => PaymentPolicy::PREPAID,
            'payment_status' => PaymentStatus::REQUIRES_PAYMENT_METHOD,
            'payment_currency' => 'vnd',
            'amount' => 50000,
        ]);
        $payload = $this->paymentIntentSucceededPayload(
            eventId: 'evt_forced_failure',
            paymentIntentId: 'pi_forced_failure'
        );

        $this->app->instance(BookingService::class, new class extends BookingService
        {
            public function __construct() {}

            public function markPaidAndConfirm(
                Booking $booking,
                int $amountReceived,
                int $amountCapturable = 0,
            ): Booking {
                throw new \RuntimeException('forced confirmation failure');
            }
        });

        $response = $this->callStripeHandler('handlePaymentIntentSucceeded', $payload);

        $this->assertGreaterThanOrEqual(500, $response->getStatusCode());
        $this->assertLessThan(600, $response->getStatusCode());
        $this->assertSame(BookingStatus::PENDING, $booking->fresh()->status);
        $this->assertSame('failed', StripeWebhookEvent::where('stripe_event_id', 'evt_forced_failure')->value('status'));
    }

    public function test_named_write_paths_do_not_directly_update_booking_status(): void
    {
        $files = [
            app_path('Services/BookingService.php'),
            app_path('Services/CancellationService.php'),
            app_path('Http/Controllers/Payment/StripeWebhookController.php'),
            app_path('Jobs/ExpireStaleBookings.php'),
            app_path('Jobs/ReconcileRefundsJob.php'),
        ];

        $directStatusUpdatePattern = '/->update\s*\(\s*\[(?:(?!\]\s*\)).)*[\'"]status[\'"]\s*=>/s';

        foreach ($files as $file) {
            $this->assertDoesNotMatchRegularExpression(
                $directStatusUpdatePattern,
                file_get_contents($file),
                "{$file} directly updates status instead of using Booking::transitionTo()."
            );
        }
    }

    private function callStripeHandler(string $method, array $payload): JsonResponse
    {
        $controller = new StripeWebhookController;
        $reflection = new \ReflectionMethod($controller, $method);

        return $reflection->invoke($controller, $payload);
    }

    private function paymentIntentSucceededPayload(string $eventId, string $paymentIntentId): array
    {
        $booking = Booking::where('payment_intent_id', $paymentIntentId)->first();

        return [
            'id' => $eventId,
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => $paymentIntentId,
                    'status' => 'succeeded',
                    'amount' => 50000,
                    'currency' => 'vnd',
                    'amount_capturable' => 0,
                    'amount_received' => 50000,
                    'metadata' => [
                        'booking_id' => (string) $booking?->id,
                        'user_id' => (string) $booking?->user_id,
                    ],
                ],
            ],
        ];
    }
}
