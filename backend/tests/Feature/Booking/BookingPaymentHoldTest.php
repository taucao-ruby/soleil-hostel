<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\PaymentPolicy;
use App\Enums\PaymentStatus;
use App\Jobs\ExpireStaleBookings;
use App\Models\Booking;
use App\Models\PaymentCancellationTask;
use App\Models\Room;
use App\Models\User;
use App\Services\Payment\PaymentIntentCancellationOutcome;
use App\Services\Payment\PaymentIntentStartResult;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_booking_creation_does_not_call_stripe_and_leaves_booking_pending_payment(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->available()->ready()->create(['price' => 125000]);

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('createPaymentIntent');
        });

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/bookings', [
                'room_id' => $room->id,
                'check_in' => Carbon::now()->addDays(10)->format('Y-m-d'),
                'check_out' => Carbon::now()->addDays(12)->format('Y-m-d'),
                'guest_name' => 'Rollback Guest',
                'guest_email' => 'rollback@example.com',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('bookings', [
            'guest_email' => 'rollback@example.com',
            'status' => BookingStatus::PENDING->value,
            'payment_policy' => PaymentPolicy::PREPAID->value,
            'payment_status' => PaymentStatus::REQUIRES_CONFIRMATION->value,
            'payment_intent_id' => null,
        ]);
    }

    public function test_owner_payment_intent_endpoint_returns_client_secret_and_stores_intent(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->available()->ready()->create(['price' => 150000]);

        $bookingResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/bookings', [
                'room_id' => $room->id,
                'check_in' => Carbon::now()->addDays(20)->format('Y-m-d'),
                'check_out' => Carbon::now()->addDays(22)->format('Y-m-d'),
                'guest_name' => 'Payment Guest',
                'guest_email' => 'payment@example.com',
            ]);

        $bookingResponse->assertCreated()
            ->assertJsonPath('success', true);

        $bookingId = $bookingResponse->json('data.id');
        $data = $bookingResponse->json('data');
        $this->assertArrayNotHasKey('payment_intent_id', $data);

        $baselineTxLevel = DB::transactionLevel();
        $this->mock(StripeService::class, function (MockInterface $mock) use ($baselineTxLevel): void {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->with(Mockery::on(function (Booking $booking) use ($baselineTxLevel): bool {
                    $this->assertSame($baselineTxLevel, DB::transactionLevel());
                    $this->assertDatabaseHas('bookings', [
                        'id' => $booking->id,
                        'status' => BookingStatus::PENDING->value,
                        'payment_intent_id' => null,
                    ]);

                    return $booking->status === BookingStatus::PENDING
                        && $booking->amount === 300000
                        && $booking->payment_policy === PaymentPolicy::PREPAID;
                }))
                ->andReturn(new PaymentIntentStartResult(
                    id: 'pi_hold_test_123',
                    clientSecret: 'pi_hold_test_123_secret_unit',
                    status: 'requires_payment_method',
                    amount: 300000,
                    currency: 'vnd',
                ));
        });

        $paymentResponse = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/bookings/{$bookingId}/payment-intent");

        $paymentResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.client_secret', 'pi_hold_test_123_secret_unit');

        $this->assertDatabaseHas('bookings', [
            'guest_email' => 'payment@example.com',
            'status' => BookingStatus::PENDING->value,
            'payment_intent_id' => 'pi_hold_test_123',
            'payment_policy' => PaymentPolicy::PREPAID->value,
            'payment_status' => PaymentStatus::REQUIRES_PAYMENT_METHOD->value,
            'amount' => 300000,
        ]);
    }

    public function test_other_user_cannot_receive_booking_client_secret(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $booking = Booking::factory()
            ->for($owner)
            ->for(Room::factory()->available()->ready())
            ->pending()
            ->create([
                'amount' => 300000,
                'payment_policy' => PaymentPolicy::PREPAID,
                'payment_status' => PaymentStatus::REQUIRES_CONFIRMATION,
            ]);

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('createPaymentIntent');
        });

        $this->actingAs($otherUser, 'sanctum')
            ->postJson("/api/v1/bookings/{$booking->id}/payment-intent")
            ->assertForbidden();
    }

    public function test_prepaid_verify_keeps_booking_pending_when_payment_is_unconfirmed(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()
            ->for($user)
            ->for(Room::factory()->available()->ready())
            ->pending()
            ->create([
                'amount' => 300000,
                'payment_intent_id' => 'pi_pending_verify',
                'payment_policy' => PaymentPolicy::PREPAID,
                'payment_status' => PaymentStatus::REQUIRES_PAYMENT_METHOD,
                'payment_currency' => 'vnd',
            ]);

        $this->partialMock(StripeService::class, function (MockInterface $mock) use ($booking): void {
            $mock->shouldReceive('retrievePaymentIntent')
                ->once()
                ->with('pi_pending_verify')
                ->andReturn($this->paymentIntentForBooking($booking, 'requires_payment_method'));
        });

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/bookings/{$booking->id}/payment/verify")
            ->assertStatus(202)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.status', BookingStatus::PENDING->value)
            ->assertJsonPath('data.payment_status', PaymentStatus::REQUIRES_PAYMENT_METHOD->value);

        $booking->refresh();
        $this->assertSame(BookingStatus::PENDING, $booking->status);
        $this->assertSame(PaymentStatus::REQUIRES_PAYMENT_METHOD, $booking->payment_status);
    }

    public function test_prepaid_verify_succeeded_payment_confirms_booking_and_marks_paid(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()
            ->for($user)
            ->for(Room::factory()->available()->ready())
            ->pending()
            ->create([
                'amount' => 300000,
                'payment_intent_id' => 'pi_succeeded_verify',
                'payment_policy' => PaymentPolicy::PREPAID,
                'payment_status' => PaymentStatus::PROCESSING,
                'payment_currency' => 'vnd',
            ]);

        $this->partialMock(StripeService::class, function (MockInterface $mock) use ($booking): void {
            $mock->shouldReceive('retrievePaymentIntent')
                ->once()
                ->with('pi_succeeded_verify')
                ->andReturn($this->paymentIntentForBooking($booking, 'succeeded'));
        });

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/bookings/{$booking->id}/payment/verify")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', BookingStatus::CONFIRMED->value)
            ->assertJsonPath('data.payment_status', PaymentStatus::PAID->value);

        $booking->refresh();
        $this->assertSame(BookingStatus::CONFIRMED, $booking->status);
        $this->assertSame(PaymentStatus::PAID, $booking->payment_status);
        $this->assertSame(300000, $booking->amount_received);
        $this->assertNotNull($booking->paid_at);
    }

    public function test_prepaid_verify_rejects_amount_mismatch_without_confirming(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()
            ->for($user)
            ->for(Room::factory()->available()->ready())
            ->pending()
            ->create([
                'amount' => 300000,
                'payment_intent_id' => 'pi_mismatch_verify',
                'payment_policy' => PaymentPolicy::PREPAID,
                'payment_status' => PaymentStatus::PROCESSING,
                'payment_currency' => 'vnd',
            ]);

        $this->partialMock(StripeService::class, function (MockInterface $mock) use ($booking): void {
            $mock->shouldReceive('retrievePaymentIntent')
                ->once()
                ->with('pi_mismatch_verify')
                ->andReturn($this->paymentIntentForBooking($booking, 'succeeded', amount: 299999));
        });

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/bookings/{$booking->id}/payment/verify")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $booking->refresh();
        $this->assertSame(BookingStatus::PENDING, $booking->status);
        $this->assertSame(PaymentStatus::PROCESSING, $booking->payment_status);
    }

    public function test_payment_intent_creation_passes_deterministic_idempotency_key_and_metadata(): void
    {
        config()->set('cashier.secret', 'sk_test_local');
        config()->set('cashier.currency', 'vnd');

        $user = User::factory()->create();
        $room = Room::factory()->available()->ready()->create(['price' => 150000]);
        $booking = Booking::factory()
            ->for($user)
            ->for($room)
            ->pending()
            ->create([
                'amount' => 300000,
                'payment_policy' => PaymentPolicy::PREPAID,
                'payment_status' => PaymentStatus::REQUIRES_CONFIRMATION,
                'payment_currency' => 'vnd',
                'check_in' => Carbon::now()->addDays(20)->startOfDay(),
                'check_out' => Carbon::now()->addDays(22)->startOfDay(),
            ]);

        $capture = (object) [
            'payload' => null,
            'options' => null,
        ];
        $fakeStripe = new class($capture) extends \Stripe\StripeClient
        {
            public object $paymentIntents;

            public function __construct(object $capture)
            {
                $this->paymentIntents = new class($capture)
                {
                    public function __construct(private object $capture) {}

                    /**
                     * @param  array<string, mixed>  $payload
                     * @param  array<string, mixed>  $options
                     */
                    public function create(array $payload, array $options): object
                    {
                        $this->capture->payload = $payload;
                        $this->capture->options = $options;

                        return (object) [
                            'id' => 'pi_captured_idempotent',
                            'client_secret' => 'pi_captured_idempotent_secret_unit',
                            'status' => 'requires_payment_method',
                            'amount' => $payload['amount'],
                            'currency' => $payload['currency'],
                            'metadata' => $payload['metadata'],
                            'amount_capturable' => 0,
                            'amount_received' => 0,
                        ];
                    }
                };
            }
        };

        $this->app->instance(\Stripe\StripeClient::class, $fakeStripe);

        $service = app(StripeService::class);
        $paymentIntent = $service->createPaymentIntent($booking);

        $this->assertSame('pi_captured_idempotent', $paymentIntent->id);
        $this->assertSame('pi_captured_idempotent_secret_unit', $paymentIntent->clientSecret);
        $this->assertSame(
            'booking:'.$booking->id.':payment_intent:create:v1',
            $capture->options['idempotency_key']
        );
        $this->assertSame('automatic', $capture->payload['capture_method']);
        $this->assertTrue($capture->payload['automatic_payment_methods']['enabled']);
        $this->assertSame((string) $booking->id, $capture->payload['metadata']['booking_id']);
        $this->assertSame((string) $booking->room_id, $capture->payload['metadata']['room_id']);
        $this->assertSame((string) $booking->location_id, $capture->payload['metadata']['location_id']);
        $this->assertSame((string) $booking->user_id, $capture->payload['metadata']['user_id']);
        $this->assertSame(PaymentPolicy::PREPAID->value, $capture->payload['metadata']['payment_policy']);
    }

    /**
     * StripeService boundary (PAY-03): a cancellable PaymentIntent is canceled
     * with the caller's stable idempotency key, and the booking-id ownership is
     * verified against PaymentIntent metadata first.
     */
    public function test_cancel_payment_intent_for_booking_passes_idempotency_key_for_cancellable_intent(): void
    {
        config()->set('cashier.secret', 'sk_test_local');

        $user = User::factory()->create();
        $room = Room::factory()->available()->ready()->create(['price' => 150000]);
        $booking = Booking::factory()
            ->for($user)
            ->for($room)
            ->cancelled()
            ->create(['payment_intent_id' => 'pi_cancellable', 'amount' => 300000]);

        $fakeStripe = $this->fakeStripeClientReturning('requires_capture', (string) $booking->id);
        $this->app->instance(\Stripe\StripeClient::class, $fakeStripe);

        $outcome = app(StripeService::class)->cancelPaymentIntentForBooking(
            $booking,
            'booking:'.$booking->id.':payment_intent_cancel:v1',
        );

        $this->assertSame(PaymentIntentCancellationOutcome::Canceled, $outcome);
        $this->assertSame(1, $fakeStripe->cancelCount);
        $this->assertSame('pi_cancellable', $fakeStripe->canceledId);
        $this->assertSame(
            'booking:'.$booking->id.':payment_intent_cancel:v1',
            $fakeStripe->cancelOpts['idempotency_key'] ?? null,
        );
    }

    public function test_cancel_payment_intent_for_booking_is_idempotent_when_already_canceled(): void
    {
        config()->set('cashier.secret', 'sk_test_local');

        $booking = Booking::factory()
            ->for(User::factory())
            ->for(Room::factory()->available()->ready())
            ->cancelled()
            ->create(['payment_intent_id' => 'pi_already', 'amount' => 300000]);

        $fakeStripe = $this->fakeStripeClientReturning('canceled', (string) $booking->id);
        $this->app->instance(\Stripe\StripeClient::class, $fakeStripe);

        $outcome = app(StripeService::class)->cancelPaymentIntentForBooking($booking, 'k');

        $this->assertSame(PaymentIntentCancellationOutcome::AlreadyCanceled, $outcome);
        $this->assertSame(0, $fakeStripe->cancelCount, 'Already-canceled intent must not be re-canceled');
    }

    public function test_cancel_payment_intent_for_booking_reports_not_cancellable_for_succeeded_intent(): void
    {
        config()->set('cashier.secret', 'sk_test_local');

        $booking = Booking::factory()
            ->for(User::factory())
            ->for(Room::factory()->available()->ready())
            ->cancelled()
            ->create(['payment_intent_id' => 'pi_succeeded', 'amount' => 300000]);

        $fakeStripe = $this->fakeStripeClientReturning('succeeded', (string) $booking->id);
        $this->app->instance(\Stripe\StripeClient::class, $fakeStripe);

        $outcome = app(StripeService::class)->cancelPaymentIntentForBooking($booking, 'k');

        $this->assertSame(PaymentIntentCancellationOutcome::NotCancellable, $outcome);
        $this->assertSame(0, $fakeStripe->cancelCount, 'A succeeded intent must never be canceled');
    }

    private function paymentIntentForBooking(
        Booking $booking,
        string $status,
        ?int $amount = null,
        ?string $currency = null,
    ): object {
        $amount ??= (int) $booking->amount;
        $currency ??= (string) $booking->payment_currency;

        return (object) [
            'id' => $booking->payment_intent_id,
            'status' => $status,
            'amount' => $amount,
            'currency' => $currency,
            'metadata' => (object) [
                'booking_id' => (string) $booking->id,
                'user_id' => (string) $booking->user_id,
            ],
            'amount_capturable' => $status === 'requires_capture' ? $amount : 0,
            'amount_received' => $status === 'succeeded' ? $amount : 0,
        ];
    }

    /**
     * Fake StripeClient whose paymentIntents->retrieve() returns a given status
     * + booking-id metadata, and whose cancel() records its id/options.
     */
    private function fakeStripeClientReturning(string $status, string $metadataBookingId): \Stripe\StripeClient
    {
        return new class($status, $metadataBookingId) extends \Stripe\StripeClient
        {
            public object $paymentIntents;

            public int $cancelCount = 0;

            public ?string $canceledId = null;

            public ?array $cancelOpts = null;

            public function __construct(string $status, string $metadataBookingId)
            {
                $outer = $this;
                $this->paymentIntents = new class($outer, $status, $metadataBookingId)
                {
                    public function __construct(
                        private object $outer,
                        private string $status,
                        private string $metadataBookingId,
                    ) {}

                    public function retrieve($id, $params = null, $opts = null): object
                    {
                        return (object) [
                            'id' => $id,
                            'status' => $this->status,
                            'metadata' => (object) ['booking_id' => $this->metadataBookingId],
                        ];
                    }

                    /**
                     * @param  array<string, mixed>|null  $params
                     * @param  array<string, mixed>|null  $opts
                     */
                    public function cancel($id, $params = null, $opts = null): object
                    {
                        $this->outer->cancelCount++;
                        $this->outer->canceledId = $id;
                        $this->outer->cancelOpts = $opts;

                        return (object) ['id' => $id, 'status' => 'canceled'];
                    }
                };
            }
        };
    }

    /**
     * PAY-03 transaction-boundary regression: ExpireStaleBookings must NOT make
     * any Stripe call while expiring a booking (that would run under the
     * booking row lock). It records a durable payment_cancellation_tasks row
     * instead; the Stripe cancel happens later in ProcessPaymentCancellationOutbox.
     */
    public function test_expire_stale_bookings_records_cancellation_task_without_calling_stripe(): void
    {
        config()->set('booking.pending_ttl_minutes', 30);

        $booking = Booking::factory()
            ->for(User::factory())
            ->for(Room::factory()->available()->ready())
            ->pending()
            ->create([
                'payment_intent_id' => 'pi_expire_test_123',
                'payment_policy' => PaymentPolicy::PREPAID,
                'payment_status' => PaymentStatus::REQUIRES_PAYMENT_METHOD,
                'amount' => 300000,
                'check_in' => Carbon::now()->addDays(5)->startOfDay(),
                'check_out' => Carbon::now()->addDays(7)->startOfDay(),
            ]);

        $past = Carbon::now()->subMinutes(45);
        Booking::query()->whereKey($booking->id)->update([
            'created_at' => $past,
            'updated_at' => $past,
        ]);

        // The expiry path must touch neither Stripe entrypoint.
        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('cancelPaymentIntentForBooking');
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        (new ExpireStaleBookings)->handle();

        $booking->refresh();

        $this->assertSame(BookingStatus::CANCELLED, $booking->status);
        $this->assertSame(ExpireStaleBookings::EXPIRED_REASON, $booking->cancellation_reason);

        // A durable cancellation task is enqueued for the offline drainer.
        $task = PaymentCancellationTask::query()
            ->where('booking_id', $booking->id)
            ->sole();
        $this->assertSame('pi_expire_test_123', $task->payment_intent_id);
        $this->assertSame(PaymentCancellationTask::ACTION_CANCEL, $task->action);
        $this->assertSame(PaymentCancellationTask::STATUS_PENDING, $task->status);
        $this->assertSame(0, $task->attempts);
    }

    public function test_expire_stale_bookings_without_payment_intent_creates_no_task(): void
    {
        config()->set('booking.pending_ttl_minutes', 30);

        $booking = Booking::factory()
            ->for(User::factory())
            ->for(Room::factory()->available()->ready())
            ->pending()
            ->create([
                'payment_intent_id' => null,
                'payment_policy' => PaymentPolicy::PREPAID,
                'payment_status' => PaymentStatus::REQUIRES_CONFIRMATION,
                'amount' => 300000,
                'check_in' => Carbon::now()->addDays(5)->startOfDay(),
                'check_out' => Carbon::now()->addDays(7)->startOfDay(),
            ]);

        $past = Carbon::now()->subMinutes(45);
        Booking::query()->whereKey($booking->id)->update([
            'created_at' => $past,
            'updated_at' => $past,
        ]);

        (new ExpireStaleBookings)->handle();

        $booking->refresh();

        $this->assertSame(BookingStatus::CANCELLED, $booking->status);
        $this->assertSame(0, PaymentCancellationTask::query()->where('booking_id', $booking->id)->count());
    }
}
