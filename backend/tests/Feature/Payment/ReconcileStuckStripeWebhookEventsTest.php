<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\ApiConnectionException;
use Stripe\StripeClient;
use Tests\TestCase;

/**
 * Integration tests for the webhook:reconcile-stuck-events reaper.
 *
 * Simulates the silent-failure path: the live webhook controller INSERTs a
 * stripe_webhook_events row with status='processing' and the worker dies
 * before markProcessed/markFailed. Each test sets up that exact state
 * (status=processing, created_at backdated past --minutes), invokes the
 * artisan command with a fake StripeClient, and asserts the recovery
 * contract holds end-to-end.
 */
final class ReconcileStuckStripeWebhookEventsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->room = Room::factory()->available()->ready()->create();

        // Ensure the reaper resolves our injected StripeClient even though
        // cashier.secret is empty in the testing environment, and pin the
        // currency so amount/currency assertions are deterministic across
        // local .env values.
        config([
            'cashier.secret' => 'sk_test_dummy_for_reconciler',
            'cashier.currency' => 'vnd',
        ]);
    }

    public function test_recovers_stale_processing_event_by_confirming_pending_booking(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::PENDING,
                'payment_intent_id' => 'pi_stuck_succeeds',
                'amount' => 50000,
            ]);

        $event = $this->makeStuckEvent(
            stripeEventId: 'evt_stuck_succeeds',
            paymentIntentId: 'pi_stuck_succeeds',
            createdMinutesAgo: 16,
        );

        $this->fakeStripeClientReturning([
            'pi_stuck_succeeds' => $this->fakePaymentIntent(
                id: 'pi_stuck_succeeds',
                status: 'succeeded',
                amount: 50000,
                currency: 'vnd',
            ),
        ]);

        $exit = Artisan::call('webhook:reconcile-stuck-events', ['--minutes' => 15, '--limit' => 50]);

        $this->assertSame(0, $exit);

        $booking->refresh();
        $this->assertSame(BookingStatus::CONFIRMED, $booking->status);

        $event->refresh();
        $this->assertSame('processed', $event->status);
        $this->assertNotNull($event->processed_at);
        $this->assertNull($event->failed_at);
        $this->assertNull($event->error);
        $this->assertNotNull($event->reconcile_started_at);
        $this->assertNotNull($event->reconcile_finished_at);
        $this->assertSame(1, $event->reconcile_attempts);
    }

    public function test_ignores_fresh_processing_row_within_age_threshold(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::PENDING,
                'payment_intent_id' => 'pi_fresh',
                'amount' => 50000,
            ]);

        $event = $this->makeStuckEvent(
            stripeEventId: 'evt_fresh',
            paymentIntentId: 'pi_fresh',
            createdMinutesAgo: 3,
        );

        // The fake would assert(false) if invoked — fresh rows must not be
        // touched, including no Stripe HTTP call.
        $this->fakeStripeClientReturning([]);

        Artisan::call('webhook:reconcile-stuck-events', ['--minutes' => 15, '--limit' => 50]);

        $booking->refresh();
        $event->refresh();

        $this->assertSame(BookingStatus::PENDING, $booking->status);
        $this->assertSame('processing', $event->status);
        $this->assertNull($event->reconcile_started_at);
        $this->assertSame(0, $event->reconcile_attempts);
    }

    public function test_idempotent_for_already_confirmed_booking_marks_event_processed_without_duplicate_side_effects(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::CONFIRMED,
                'payment_intent_id' => 'pi_already_confirmed',
                'amount' => 50000,
            ]);

        $event = $this->makeStuckEvent(
            stripeEventId: 'evt_already_confirmed',
            paymentIntentId: 'pi_already_confirmed',
            createdMinutesAgo: 20,
        );

        $this->fakeStripeClientReturning([
            'pi_already_confirmed' => $this->fakePaymentIntent(
                id: 'pi_already_confirmed',
                status: 'succeeded',
                amount: 50000,
                currency: 'vnd',
            ),
        ]);

        // BookingService::confirmBooking must NOT be called a second time.
        $this->mock(BookingService::class, function ($mock): void {
            $mock->shouldNotReceive('confirmBooking');
        });

        Artisan::call('webhook:reconcile-stuck-events', ['--minutes' => 15, '--limit' => 50]);

        $booking->refresh();
        $event->refresh();

        $this->assertSame(BookingStatus::CONFIRMED, $booking->status);
        $this->assertSame('processed', $event->status);
        $this->assertNull($event->error);
        $this->assertNull($event->failed_at);
    }

    public function test_marks_event_failed_when_remote_payment_intent_is_not_succeeded(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::PENDING,
                'payment_intent_id' => 'pi_not_succeeded',
                'amount' => 50000,
            ]);

        $event = $this->makeStuckEvent(
            stripeEventId: 'evt_not_succeeded',
            paymentIntentId: 'pi_not_succeeded',
            createdMinutesAgo: 20,
        );

        $this->fakeStripeClientReturning([
            'pi_not_succeeded' => $this->fakePaymentIntent(
                id: 'pi_not_succeeded',
                status: 'requires_payment_method',
                amount: 50000,
                currency: 'vnd',
            ),
        ]);

        Artisan::call('webhook:reconcile-stuck-events', ['--minutes' => 15, '--limit' => 50]);

        $booking->refresh();
        $event->refresh();

        $this->assertSame(
            BookingStatus::PENDING,
            $booking->status,
            'Booking must NOT be confirmed when Stripe reports the PaymentIntent did not succeed',
        );
        $this->assertSame('failed', $event->status);
        $this->assertNotNull($event->failed_at);
        $this->assertNotNull($event->error);
        $this->assertStringContainsString('requires_payment_method', (string) $event->error);
    }

    public function test_defers_processing_on_transient_stripe_api_error(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::PENDING,
                'payment_intent_id' => 'pi_transient',
                'amount' => 50000,
            ]);

        $event = $this->makeStuckEvent(
            stripeEventId: 'evt_transient',
            paymentIntentId: 'pi_transient',
            createdMinutesAgo: 20,
        );

        $this->app->instance(StripeClient::class, $this->makeThrowingStripeClient(
            ApiConnectionException::factory('upstream tcp reset (stripe.com)'),
        ));

        Artisan::call('webhook:reconcile-stuck-events', ['--minutes' => 15, '--limit' => 50]);

        $booking->refresh();
        $event->refresh();

        $this->assertSame(BookingStatus::PENDING, $booking->status);
        $this->assertSame(
            'processing',
            $event->status,
            'Transient Stripe error must leave the event in processing so the next run retries',
        );
        $this->assertNotNull($event->error);
        $this->assertNotNull($event->reconcile_started_at);
        $this->assertNotNull($event->reconcile_finished_at);
        $this->assertSame(1, $event->reconcile_attempts);
    }

    public function test_marks_failed_when_remote_amount_does_not_match_local_booking(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::PENDING,
                'payment_intent_id' => 'pi_amount_mismatch',
                'amount' => 50000,
            ]);

        $event = $this->makeStuckEvent(
            stripeEventId: 'evt_amount_mismatch',
            paymentIntentId: 'pi_amount_mismatch',
            createdMinutesAgo: 20,
        );

        $this->fakeStripeClientReturning([
            'pi_amount_mismatch' => $this->fakePaymentIntent(
                id: 'pi_amount_mismatch',
                status: 'succeeded',
                amount: 99000, // mismatch
                currency: 'vnd',
            ),
        ]);

        Artisan::call('webhook:reconcile-stuck-events', ['--minutes' => 15, '--limit' => 50]);

        $booking->refresh();
        $event->refresh();

        $this->assertSame(BookingStatus::PENDING, $booking->status);
        $this->assertSame('failed', $event->status);
        $this->assertStringContainsString('amount mismatch', (string) $event->error);
    }

    public function test_unsupported_event_type_is_skipped_by_scope_and_does_not_crash_run(): void
    {
        // Construct a stale processing row of a type the reaper does not
        // support. The scopeStaleProcessing predicate filters it out, so the
        // reaper completes without touching the row and without throwing.
        $event = StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_charge_disputed',
            'type' => 'charge.dispute.created',
            'status' => 'processing',
            'payload' => ['id' => 'evt_charge_disputed', 'data' => ['object' => ['id' => 'ch_xx']]],
        ]);

        DB::table('stripe_webhook_events')
            ->where('id', $event->id)
            ->update(['created_at' => Carbon::now()->subMinutes(30)]);

        $this->fakeStripeClientReturning([]);

        $exit = Artisan::call('webhook:reconcile-stuck-events', ['--minutes' => 15, '--limit' => 50]);

        $this->assertSame(0, $exit);

        $event->refresh();
        $this->assertSame(
            'processing',
            $event->status,
            'Unsupported event types must not be touched by the reaper (no dynamic dispatch)',
        );
        $this->assertSame(0, $event->reconcile_attempts);
    }

    public function test_marks_failed_when_booking_is_in_invalid_state_for_confirmation(): void
    {
        // Operationally suspicious: payment succeeded but local booking is
        // CANCELLED. The handler refuses to force the state transition; the
        // reaper marks the event failed with explicit context so an operator
        // can investigate.
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::CANCELLED,
                'payment_intent_id' => 'pi_invalid_state',
                'amount' => 50000,
                'cancelled_at' => Carbon::now()->subHour(),
            ]);

        $event = $this->makeStuckEvent(
            stripeEventId: 'evt_invalid_state',
            paymentIntentId: 'pi_invalid_state',
            createdMinutesAgo: 20,
        );

        $this->fakeStripeClientReturning([
            'pi_invalid_state' => $this->fakePaymentIntent(
                id: 'pi_invalid_state',
                status: 'succeeded',
                amount: 50000,
                currency: 'vnd',
            ),
        ]);

        Artisan::call('webhook:reconcile-stuck-events', ['--minutes' => 15, '--limit' => 50]);

        $booking->refresh();
        $event->refresh();

        $this->assertSame(BookingStatus::CANCELLED, $booking->status);
        $this->assertSame('failed', $event->status);
        $this->assertStringContainsString('manual review required', (string) $event->error);
    }

    // ===== Helpers =====

    private function makeStuckEvent(
        string $stripeEventId,
        string $paymentIntentId,
        int $createdMinutesAgo,
    ): StripeWebhookEvent {
        $event = StripeWebhookEvent::create([
            'stripe_event_id' => $stripeEventId,
            'type' => 'payment_intent.succeeded',
            'status' => 'processing',
            'payload' => [
                'id' => $stripeEventId,
                'type' => 'payment_intent.succeeded',
                'data' => ['object' => ['id' => $paymentIntentId]],
            ],
        ]);

        // Backdate created_at so it falls past the --minutes threshold.
        DB::table('stripe_webhook_events')
            ->where('id', $event->id)
            ->update(['created_at' => Carbon::now()->subMinutes($createdMinutesAgo)]);

        return $event->fresh();
    }

    /**
     * Install a fake StripeClient that returns the given map of
     * PaymentIntent stubs keyed by id. Any retrieval for an id not in the
     * map fails the test loudly.
     *
     * @param  array<string, object>  $paymentIntentsById
     */
    private function fakeStripeClientReturning(array $paymentIntentsById): void
    {
        $fake = new class($paymentIntentsById) extends StripeClient
        {
            public object $paymentIntents;

            /** @param array<string, object> $intents */
            public function __construct(array $intents)
            {
                $this->paymentIntents = new class($intents)
                {
                    /** @param array<string, object> $intents */
                    public function __construct(private array $intents) {}

                    public function retrieve($id, $params = null, $opts = null): object
                    {
                        if (! is_string($id) || ! array_key_exists($id, $this->intents)) {
                            throw new \RuntimeException("test fake: unexpected paymentIntents->retrieve({$id})");
                        }

                        return $this->intents[$id];
                    }
                };
            }
        };

        $this->app->instance(StripeClient::class, $fake);
    }

    private function makeThrowingStripeClient(\Throwable $throwable): StripeClient
    {
        return new class($throwable) extends StripeClient
        {
            public object $paymentIntents;

            public function __construct(\Throwable $throwable)
            {
                $this->paymentIntents = new class($throwable)
                {
                    public function __construct(private \Throwable $throwable) {}

                    public function retrieve($id, $params = null, $opts = null): object
                    {
                        throw $this->throwable;
                    }
                };
            }
        };
    }

    private function fakePaymentIntent(
        string $id,
        string $status,
        int $amount,
        string $currency,
    ): object {
        return (object) [
            'id' => $id,
            'status' => $status,
            'amount' => $amount,
            'currency' => $currency,
        ];
    }
}
