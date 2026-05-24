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
use Illuminate\Support\Facades\Log;
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

    public function test_exhausted_event_is_auto_failed_with_preserved_error_and_no_stripe_contact(): void
    {
        config(['booking.reconciliation.webhook_max_attempts' => 3]);

        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::PENDING,
                'payment_intent_id' => 'pi_exhausted',
                'amount' => 50000,
            ]);

        // A row that has already been re-claimed up to the threshold, carrying
        // the last transient error from a prior deferral.
        $event = $this->makeStuckEvent(
            stripeEventId: 'evt_exhausted',
            paymentIntentId: 'pi_exhausted',
            createdMinutesAgo: 60,
            reconcileAttempts: 3,
            error: 'upstream tcp reset (stripe.com)',
        );

        // Any Stripe retrieve would fail the test loudly: an exhausted row must
        // be failed WITHOUT another Stripe round-trip.
        $this->fakeStripeClientReturning([]);

        $exit = Artisan::call('webhook:reconcile-stuck-events', ['--minutes' => 15, '--limit' => 50]);

        $this->assertSame(0, $exit);

        $booking->refresh();
        $event->refresh();

        $this->assertSame(
            BookingStatus::PENDING,
            $booking->status,
            'An exhausted webhook event must not mutate the booking',
        );
        $this->assertSame('failed', $event->status);
        $this->assertNotNull($event->failed_at);
        $this->assertSame(3, $event->reconcile_attempts, 'Exhaustion fail must not bump the attempt counter');
        $this->assertStringContainsString('exhausted after 3 attempts', (string) $event->error);
        $this->assertStringContainsString('upstream tcp reset', (string) $event->error);
    }

    public function test_event_one_below_max_attempts_is_still_reconciled(): void
    {
        config(['booking.reconciliation.webhook_max_attempts' => 3]);

        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::PENDING,
                'payment_intent_id' => 'pi_last_chance',
                'amount' => 50000,
            ]);

        $event = $this->makeStuckEvent(
            stripeEventId: 'evt_last_chance',
            paymentIntentId: 'pi_last_chance',
            createdMinutesAgo: 20,
            reconcileAttempts: 2,
        );

        $this->fakeStripeClientReturning([
            'pi_last_chance' => $this->fakePaymentIntent(
                id: 'pi_last_chance',
                status: 'succeeded',
                amount: 50000,
                currency: 'vnd',
            ),
        ]);

        Artisan::call('webhook:reconcile-stuck-events', ['--minutes' => 15, '--limit' => 50]);

        $booking->refresh();
        $event->refresh();

        $this->assertSame(BookingStatus::CONFIRMED, $booking->status);
        $this->assertSame('processed', $event->status);
        $this->assertSame(
            3,
            $event->reconcile_attempts,
            'A row one below the threshold gets one final claim (attempts -> max), not an early skip',
        );
    }

    public function test_event_that_keeps_deferring_is_failed_once_attempts_exhaust(): void
    {
        config(['booking.reconciliation.webhook_max_attempts' => 2]);

        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::PENDING,
                'payment_intent_id' => 'pi_persistent_transient',
                'amount' => 50000,
            ]);

        $event = $this->makeStuckEvent(
            stripeEventId: 'evt_persistent_transient',
            paymentIntentId: 'pi_persistent_transient',
            createdMinutesAgo: 20,
            reconcileAttempts: 1,
        );

        // Stripe is a black hole: every retrieve is a transient connection error.
        $this->app->instance(StripeClient::class, $this->makeThrowingStripeClient(
            ApiConnectionException::factory('upstream tcp reset (stripe.com)'),
        ));

        // Run 1: attempts 1 -> 2 (claimed, transient defer), still processing.
        Artisan::call('webhook:reconcile-stuck-events', ['--minutes' => 15, '--limit' => 50]);

        $event->refresh();
        $this->assertSame('processing', $event->status);
        $this->assertSame(2, $event->reconcile_attempts);

        // Run 2: attempts already at the threshold -> auto-failed, no re-claim.
        Artisan::call('webhook:reconcile-stuck-events', ['--minutes' => 15, '--limit' => 50]);

        $booking->refresh();
        $event->refresh();

        $this->assertSame(BookingStatus::PENDING, $booking->status);
        $this->assertSame('failed', $event->status);
        $this->assertNotNull($event->failed_at);
        $this->assertSame(2, $event->reconcile_attempts, 'No further claim once exhausted');
        $this->assertStringContainsString('exhausted after 2 attempts', (string) $event->error);
        $this->assertStringContainsString('upstream tcp reset', (string) $event->error);
    }

    public function test_backlog_telemetry_emits_zero_when_no_stuck_events_exist(): void
    {
        $this->configureBacklogTelemetryForNoStripe(thresholdBaseline: 1, thresholdMultiplier: 3);
        Log::spy();

        $exit = Artisan::call('webhook:reconcile-stuck-events', ['--minutes' => 15, '--limit' => 50]);

        $this->assertSame(0, $exit);
        $this->assertBacklogInfoMetric('stripe_webhook_reconciler.backlog_count', 0);
        $this->assertBacklogInfoMetric('stripe_webhook_reconciler.backlog_threshold', 3);
        $this->assertBacklogHighMetric(0);
        $this->assertNoBacklogHighWarning();
    }

    public function test_backlog_telemetry_stays_healthy_one_below_threshold(): void
    {
        $this->configureBacklogTelemetryForNoStripe(thresholdBaseline: 1, thresholdMultiplier: 3);
        $this->makeStuckEvents(count: 2, prefix: 'below_threshold');
        Log::spy();

        Artisan::call('webhook:reconcile-stuck-events', ['--minutes' => 15, '--limit' => 50]);

        $this->assertBacklogInfoMetric('stripe_webhook_reconciler.backlog_count', 2);
        $this->assertBacklogInfoMetric('stripe_webhook_reconciler.backlog_threshold', 3);
        $this->assertBacklogHighMetric(0);
        $this->assertNoBacklogHighWarning();
    }

    public function test_backlog_telemetry_warns_at_exact_threshold(): void
    {
        $this->configureBacklogTelemetryForNoStripe(thresholdBaseline: 1, thresholdMultiplier: 3);
        $this->makeStuckEvents(count: 3, prefix: 'exact_threshold');
        Log::spy();

        Artisan::call('webhook:reconcile-stuck-events', ['--minutes' => 15, '--limit' => 50]);

        $this->assertBacklogInfoMetric('stripe_webhook_reconciler.backlog_count', 3);
        $this->assertBacklogInfoMetric('stripe_webhook_reconciler.backlog_threshold', 3);
        $this->assertBacklogHighWarning(1);
    }

    public function test_backlog_telemetry_warns_above_threshold(): void
    {
        $this->configureBacklogTelemetryForNoStripe(thresholdBaseline: 1, thresholdMultiplier: 3);
        $this->makeStuckEvents(count: 4, prefix: 'above_threshold');
        Log::spy();

        Artisan::call('webhook:reconcile-stuck-events', ['--minutes' => 15, '--limit' => 50]);

        $this->assertBacklogInfoMetric('stripe_webhook_reconciler.backlog_count', 4);
        $this->assertBacklogInfoMetric('stripe_webhook_reconciler.backlog_threshold', 3);
        $this->assertBacklogHighWarning(1);
    }

    public function test_exhausted_rows_keep_row_level_log_without_high_backlog_when_below_threshold(): void
    {
        config(['booking.reconciliation.webhook_max_attempts' => 3]);
        $this->configureBacklogTelemetryForNoStripe(thresholdBaseline: 1, thresholdMultiplier: 3);

        $this->makeStuckEvent(
            stripeEventId: 'evt_exhausted_low_backlog',
            paymentIntentId: 'pi_exhausted_low_backlog',
            createdMinutesAgo: 60,
            reconcileAttempts: 3,
            error: 'upstream tcp reset (stripe.com)',
        );

        Log::spy();

        Artisan::call('webhook:reconcile-stuck-events', ['--minutes' => 15, '--limit' => 50]);

        $this->assertBacklogInfoMetric('stripe_webhook_reconciler.backlog_count', 1);
        $this->assertBacklogHighMetric(0);
        $this->assertNoBacklogHighWarning();
        Log::shouldHaveReceived('error')
            ->with('stripe_webhook_reconciler.reconciliation_exhausted', \Mockery::on(
                fn (array $context): bool => $context['stripe_event_id'] === 'evt_exhausted_low_backlog'
                    && $context['payment_intent_id'] === 'pi_exhausted_low_backlog'
                    && $context['reconcile_attempts'] === 3
                    && $context['max_attempts'] === 3,
            ))
            ->once();
    }

    public function test_healthy_run_after_high_backlog_emits_zero_to_clear_stale_gauge(): void
    {
        $this->configureBacklogTelemetryForNoStripe(thresholdBaseline: 1, thresholdMultiplier: 2);
        $this->makeStuckEvents(count: 2, prefix: 'clear_stale_high');
        Log::spy();

        Artisan::call('webhook:reconcile-stuck-events', ['--minutes' => 15, '--limit' => 50]);

        StripeWebhookEvent::query()->update([
            'status' => 'processed',
            'processed_at' => Carbon::now(),
        ]);

        Artisan::call('webhook:reconcile-stuck-events', ['--minutes' => 15, '--limit' => 50]);

        $this->assertBacklogHighWarning(1);
        $this->assertBacklogHighMetric(0);
    }

    // ===== Helpers =====

    private function configureBacklogTelemetryForNoStripe(int $thresholdBaseline, int $thresholdMultiplier): void
    {
        config([
            'booking.reconciliation.webhook_backlog_alert_baseline' => $thresholdBaseline,
            'booking.reconciliation.webhook_backlog_alert_multiplier' => $thresholdMultiplier,
            'cashier.secret' => null,
        ]);
    }

    private function makeStuckEvents(int $count, string $prefix): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $this->makeStuckEvent(
                stripeEventId: "evt_{$prefix}_{$i}",
                paymentIntentId: "pi_{$prefix}_{$i}",
                createdMinutesAgo: 20,
            );
        }
    }

    private function makeStuckEvent(
        string $stripeEventId,
        string $paymentIntentId,
        int $createdMinutesAgo,
        int $reconcileAttempts = 0,
        ?string $error = null,
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

        // Backdate created_at so it falls past the --minutes threshold and
        // seed the prior reconcile state (attempts/error) some tests rely on.
        DB::table('stripe_webhook_events')
            ->where('id', $event->id)
            ->update([
                'created_at' => Carbon::now()->subMinutes($createdMinutesAgo),
                'reconcile_attempts' => $reconcileAttempts,
                'error' => $error,
            ]);

        return $event->fresh();
    }

    private function assertBacklogInfoMetric(string $metric, int $value): void
    {
        Log::shouldHaveReceived('info')
            ->with($metric, \Mockery::on(
                fn (array $context): bool => ($context['metric'] ?? null) === $metric
                    && ($context['value'] ?? null) === $value,
            ))
            ->once();
    }

    private function assertBacklogHighMetric(int $value): void
    {
        Log::shouldHaveReceived('info')
            ->with('stripe_webhook_reconciler.backlog_high', \Mockery::on(
                fn (array $context): bool => ($context['metric'] ?? null) === 'stripe_webhook_reconciler.backlog_high'
                    && ($context['value'] ?? null) === $value,
            ))
            ->once();
    }

    private function assertBacklogHighWarning(int $value): void
    {
        Log::shouldHaveReceived('warning')
            ->with('stripe_webhook_reconciler.backlog_high', \Mockery::on(
                fn (array $context): bool => ($context['metric'] ?? null) === 'stripe_webhook_reconciler.backlog_high'
                    && ($context['value'] ?? null) === $value,
            ))
            ->once();
    }

    private function assertNoBacklogHighWarning(): void
    {
        Log::shouldNotHaveReceived('warning', [
            'stripe_webhook_reconciler.backlog_high',
            \Mockery::any(),
        ]);
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
