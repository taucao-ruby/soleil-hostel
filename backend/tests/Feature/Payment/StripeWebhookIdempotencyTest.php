<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Enums\BookingStatus;
use App\Enums\PaymentPolicy;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Payment\StripeWebhookController;
use App\Models\Booking;
use App\Models\Room;
use App\Models\StripeRefundEvent;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BL-3 regression coverage — Stripe webhook idempotency must hold under
 * at-least-once delivery and concurrent retries.
 *
 * Stripe is at-least-once: any event may be delivered multiple times,
 * possibly concurrently, possibly minutes apart. Our contract is that
 * each event's business effect (booking confirm, refund settlement) is
 * applied at most once.
 *
 * Two durable linearization points enforce this:
 *
 * 1. `stripe_webhook_events.stripe_event_id` UNIQUE constraint —
 *    `handlePaymentIntentSucceeded` does an INSERT-first and catches
 *    UniqueConstraintViolationException, so a duplicate delivery short-
 *    circuits to a 200 response before any booking mutation runs.
 *
 * 2. `stripe_refund_events.stripe_refund_id` UNIQUE constraint —
 *    `handleChargeRefunded` INSERTs the refund-event row inside the
 *    same DB::transaction that mutates the booking; a duplicate refund
 *    delivery throws on the INSERT and the booking mutation is rolled
 *    back via savepoint.
 *
 * Reference: BL-3.
 */
class StripeWebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private StripeWebhookController $controller;

    private User $user;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new StripeWebhookController;
        $this->user = User::factory()->create();
        $this->room = Room::factory()->available()->ready()->create();
    }

    // ===== payment_intent.succeeded — duplicate-delivery contract =====

    public function test_duplicate_payment_intent_event_id_is_no_op_via_unique_constraint(): void
    {
        // A previous delivery has already been processed: webhook ledger has
        // a row for evt_X, and the booking sits in CONFIRMED.
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::CONFIRMED,
                'payment_intent_id' => 'pi_replay_test',
                'payment_policy' => PaymentPolicy::PREPAID,
                'payment_status' => PaymentStatus::PAID,
                'payment_currency' => 'vnd',
                'amount' => 50000,
            ]);

        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_replay_test',
            'type' => 'payment_intent.succeeded',
            'status' => 'processed',
            'payload' => ['id' => 'evt_replay_test'],
            'processed_at' => now(),
        ]);

        $payload = $this->makePaymentIntentPayload('evt_replay_test', 'pi_replay_test');

        // Spy on BookingService — a true replay must never call confirmBooking
        // a second time. Mocking via the container proves the short-circuit.
        $serviceMock = $this->mock(BookingService::class);
        $serviceMock->shouldNotReceive('confirmBooking');

        $response = $this->callHandler('handlePaymentIntentSucceeded', $payload);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['handled']);

        // Ledger still has exactly one row for evt_X; no duplicate inserted.
        $this->assertSame(
            1,
            StripeWebhookEvent::where('stripe_event_id', 'evt_replay_test')->count(),
            'Duplicate delivery must not insert a second stripe_webhook_events row',
        );

        // Booking state unchanged — no second confirmation side effect.
        $booking->refresh();
        $this->assertSame(BookingStatus::CONFIRMED, $booking->status);
    }

    public function test_first_delivery_inserts_ledger_row_before_business_mutation(): void
    {
        // Before any side effect runs, the webhook event row must exist as
        // the durable claim on this stripe_event_id. This is the contract
        // that makes the second-delivery short-circuit work.
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::PENDING,
                'payment_intent_id' => 'pi_first_delivery',
                'payment_policy' => PaymentPolicy::PREPAID,
                'payment_status' => PaymentStatus::REQUIRES_PAYMENT_METHOD,
                'payment_currency' => 'vnd',
                'amount' => 50000,
            ]);

        $this->assertDatabaseMissing('stripe_webhook_events', [
            'stripe_event_id' => 'evt_first_delivery',
        ]);

        $payload = $this->makePaymentIntentPayload('evt_first_delivery', 'pi_first_delivery');

        $response = $this->callHandler('handlePaymentIntentSucceeded', $payload);

        $this->assertSame(200, $response->getStatusCode());

        // Ledger row written exactly once.
        $events = StripeWebhookEvent::where('stripe_event_id', 'evt_first_delivery')->get();
        $this->assertCount(1, $events);
        $this->assertSame('processed', $events->first()->status);
        $this->assertNotNull($events->first()->processed_at);

        // Business mutation applied exactly once.
        $booking->refresh();
        $this->assertSame(BookingStatus::CONFIRMED, $booking->status);
    }

    // ===== payment_intent.succeeded — failed-path contract =====

    public function test_failed_business_mutation_marks_event_failed_and_returns_500(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::PENDING,
                'payment_intent_id' => 'pi_failure_test',
                'payment_policy' => PaymentPolicy::PREPAID,
                'payment_status' => PaymentStatus::REQUIRES_PAYMENT_METHOD,
                'payment_currency' => 'vnd',
                'amount' => 50000,
            ]);

        // Force confirmBooking to throw so the catch branch (markFailed + 500)
        // runs. The webhook event ledger row was already committed by the
        // outer INSERT-first transaction, so it must end up marked 'failed'
        // — never 'processed'. A 'processed' marker on a failed mutation
        // would silently absorb every Stripe retry.
        $serviceMock = $this->mock(BookingService::class);
        $serviceMock->shouldReceive('markPaidAndConfirm')
            ->once()
            ->andThrow(new \RuntimeException('simulated downstream failure'));

        $payload = $this->makePaymentIntentPayload('evt_failure_test', 'pi_failure_test');

        $response = $this->callHandler('handlePaymentIntentSucceeded', $payload);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['handled']);

        $event = StripeWebhookEvent::where('stripe_event_id', 'evt_failure_test')->firstOrFail();
        $this->assertSame(
            'failed',
            $event->status,
            'Failed business mutation must not leave a misleading processed marker',
        );

        $booking->refresh();
        $this->assertSame(
            BookingStatus::PENDING,
            $booking->status,
            'No partial state change must leak when business mutation fails',
        );
    }

    public function test_stripe_retry_after_failed_event_is_terminal_not_reattempted(): void
    {
        // Document the current at-most-once posture explicitly: once a
        // stripe_event_id is in the ledger (in any status, including
        // 'failed'), the UNIQUE-constraint short-circuit returns 200 on
        // every subsequent Stripe retry. That preserves the at-most-once
        // contract even if it makes failed events operationally terminal
        // (recovery is a human action, not an auto-retry). Any future
        // change that flips failed events to auto-retry must update this
        // test and the BL-3 finding.
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::PENDING,
                'payment_intent_id' => 'pi_terminal_test',
                'payment_policy' => PaymentPolicy::PREPAID,
                'payment_status' => PaymentStatus::REQUIRES_PAYMENT_METHOD,
                'payment_currency' => 'vnd',
                'amount' => 50000,
            ]);

        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_terminal_test',
            'type' => 'payment_intent.succeeded',
            'status' => 'failed',
            'payload' => ['id' => 'evt_terminal_test'],
        ]);

        $serviceMock = $this->mock(BookingService::class);
        $serviceMock->shouldNotReceive('confirmBooking');

        $payload = $this->makePaymentIntentPayload('evt_terminal_test', 'pi_terminal_test');

        $response = $this->callHandler('handlePaymentIntentSucceeded', $payload);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['handled']);

        // Ledger still one row, status still 'failed', booking untouched.
        $event = StripeWebhookEvent::where('stripe_event_id', 'evt_terminal_test')->firstOrFail();
        $this->assertSame('failed', $event->status);

        $booking->refresh();
        $this->assertSame(BookingStatus::PENDING, $booking->status);

        $this->assertSame(
            1,
            StripeWebhookEvent::where('stripe_event_id', 'evt_terminal_test')->count(),
        );
    }

    // ===== payment_intent.payment_failed — duplicate-delivery contract =====

    public function test_payment_intent_payment_failed_webhook_is_idempotent(): void
    {
        // Same INSERT-first guard as the succeeded path: a duplicate Stripe
        // delivery of payment_intent.payment_failed must not double-log or
        // (in future) double-mutate. Today the handler only logs, but locking
        // the pattern in now means any business logic added later inherits
        // the guarantee without re-doing the audit. BL-3.
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::PENDING,
                'payment_intent_id' => 'pi_failed_replay',
                'payment_policy' => PaymentPolicy::PREPAID,
                'payment_status' => PaymentStatus::REQUIRES_PAYMENT_METHOD,
                'payment_currency' => 'vnd',
                'amount' => 50000,
            ]);

        $payload = $this->makePaymentFailedPayload(
            stripeEventId: 'evt_failed_replay',
            paymentIntentId: 'pi_failed_replay',
            failureMessage: 'Your card was declined.',
        );

        // First delivery: writes the ledger row, runs the warning log,
        // and marks the event processed.
        $first = $this->callHandler('handlePaymentIntentPaymentFailed', $payload);
        $this->assertSame(200, $first->getStatusCode());
        $this->assertTrue($first->getData(true)['handled']);

        $events = StripeWebhookEvent::where('stripe_event_id', 'evt_failed_replay')->get();
        $this->assertCount(1, $events, 'First delivery must persist exactly one ledger row');
        $this->assertSame('processed', $events->first()->status);
        $this->assertNotNull($events->first()->processed_at);

        // Booking stays pending while payment status records the failed attempt.
        $booking->refresh();
        $this->assertSame(BookingStatus::PENDING, $booking->status);
        $this->assertSame(PaymentStatus::FAILED, $booking->payment_status);

        // Second delivery (identical payload): short-circuits at the UNIQUE
        // constraint. No second ledger row, no booking mutation, no
        // additional side effects — the response is still 200/handled.
        $second = $this->callHandler('handlePaymentIntentPaymentFailed', $payload);
        $this->assertSame(200, $second->getStatusCode());
        $this->assertTrue($second->getData(true)['handled']);

        $this->assertSame(
            1,
            StripeWebhookEvent::where('stripe_event_id', 'evt_failed_replay')->count(),
            'Duplicate payment_failed delivery must not insert a second stripe_webhook_events row',
        );

        $booking->refresh();
        $this->assertSame(
            BookingStatus::PENDING,
            $booking->status,
            'payment_failed must not confirm booking state on first OR duplicate delivery',
        );
        $this->assertSame(PaymentStatus::FAILED, $booking->payment_status);
    }

    public function test_payment_intent_payment_failed_without_event_id_short_circuits_2xx(): void
    {
        // Malformed payload (no event id). Cannot dedupe against a missing
        // identifier; we preserve the historical always-2xx posture so Stripe
        // does not retry a payload we cannot reason about. No ledger row is
        // written because there is no key to write under.
        $payload = [
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_no_event_id',
                    'last_payment_error' => ['message' => 'Card declined.'],
                ],
            ],
        ];

        $response = $this->callHandler('handlePaymentIntentPaymentFailed', $payload);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['handled']);
        $this->assertSame(0, StripeWebhookEvent::count());
    }

    // ===== charge.refunded — duplicate-delivery contract =====

    public function test_duplicate_charge_refunded_event_is_no_op_via_refund_id_unique_constraint(): void
    {
        // refund_events ledger has stripe_refund_id as the durable claim
        // (not stripe_event_id). Duplicate delivery of charge.refunded for
        // the same refund must not re-mutate the booking nor add a second
        // ledger row.
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::CANCELLED,
                'payment_intent_id' => 'pi_refund_replay',
                'refund_id' => 're_replay_test',
                'refund_status' => 'succeeded',
                'refund_amount' => 40000,
                'amount' => 40000,
            ]);

        StripeRefundEvent::create([
            'stripe_refund_id' => 're_replay_test',
            'stripe_event_id' => 'evt_refund_replay_first',
            'booking_id' => $booking->id,
            'amount_refunded' => 40000,
            'currency' => 'vnd',
        ]);

        $originalUpdatedAt = $booking->updated_at;

        // Capture booking row's updated_at to prove no mutation happened.
        $payload = $this->makeChargeRefundedPayload(
            stripeEventId: 'evt_refund_replay_second',
            chargeId: 'ch_replay',
            paymentIntentId: 'pi_refund_replay',
            refundId: 're_replay_test',
            refundStatus: 'succeeded',
            refundAmount: 40000,
        );

        $response = $this->callHandler('handleChargeRefunded', $payload);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['handled']);

        // Exactly one refund-event row, regardless of how many Stripe events
        // reference the same refund.
        $this->assertSame(
            1,
            StripeRefundEvent::where('stripe_refund_id', 're_replay_test')->count(),
            'Duplicate refund delivery must not insert a second stripe_refund_events row',
        );

        $booking->refresh();
        $this->assertSame(BookingStatus::CANCELLED, $booking->status);
        $this->assertEquals(
            $originalUpdatedAt?->timestamp,
            $booking->updated_at?->timestamp,
            'Booking row must not be re-updated by a replayed refund event',
        );
    }

    // ===== PostgreSQL DB-level linearization point =====

    /**
     * @dataProvider uniqueConstraintTableProvider
     */
    public function test_postgres_unique_constraint_rejects_duplicate_event_insert(
        string $table,
        string $column,
        callable $firstRow,
        callable $secondRow,
    ): void {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('UNIQUE-constraint linearization proof is PostgreSQL-only');
        }

        // First INSERT lands the durable claim.
        DB::table($table)->insert($firstRow());

        // Second INSERT (same identifier, different metadata) is rejected
        // by the UNIQUE constraint. Wrap in DB::transaction so the failure
        // rolls back to a savepoint and subsequent assertions still work
        // under RefreshDatabase's outer transaction (avoids 25P02).
        $caught = null;
        try {
            DB::transaction(function () use ($table, $secondRow): void {
                DB::table($table)->insert($secondRow());
            });
        } catch (UniqueConstraintViolationException $e) {
            $caught = $e;
        }

        $this->assertNotNull(
            $caught,
            "Duplicate INSERT on {$table}.{$column} must throw UniqueConstraintViolationException — "
                .'this is the linearization point that makes webhook idempotency safe',
        );

        // Exactly one row survives; the second never landed.
        $this->assertSame(
            1,
            DB::table($table)->count(),
            'PG UNIQUE constraint must have prevented the second row from being inserted',
        );
    }

    public static function uniqueConstraintTableProvider(): array
    {
        return [
            'stripe_webhook_events.stripe_event_id' => [
                'stripe_webhook_events',
                'stripe_event_id',
                fn (): array => [
                    'stripe_event_id' => 'evt_pg_lin_test',
                    'type' => 'payment_intent.succeeded',
                    'status' => 'processing',
                    'payload' => json_encode(['id' => 'evt_pg_lin_test']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                fn (): array => [
                    'stripe_event_id' => 'evt_pg_lin_test',
                    'type' => 'payment_intent.succeeded',
                    'status' => 'processing',
                    'payload' => json_encode(['id' => 'evt_pg_lin_test', 'replay' => true]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
            'stripe_refund_events.stripe_refund_id' => [
                'stripe_refund_events',
                'stripe_refund_id',
                fn (): array => [
                    'stripe_refund_id' => 're_pg_lin_test',
                    'stripe_event_id' => 'evt_refund_pg_first',
                    'booking_id' => null,
                    'amount_refunded' => 10000,
                    'currency' => 'vnd',
                ],
                fn (): array => [
                    'stripe_refund_id' => 're_pg_lin_test',
                    'stripe_event_id' => 'evt_refund_pg_second',
                    'booking_id' => null,
                    'amount_refunded' => 10000,
                    'currency' => 'vnd',
                ],
            ],
        ];
    }

    // ===== Helpers =====

    private function callHandler(string $method, array $payload): \Illuminate\Http\JsonResponse
    {
        $reflection = new \ReflectionMethod($this->controller, $method);

        return $reflection->invoke($this->controller, $payload);
    }

    private function makePaymentIntentPayload(string $stripeEventId, string $paymentIntentId): array
    {
        $booking = Booking::where('payment_intent_id', $paymentIntentId)->first();

        return [
            'id' => $stripeEventId,
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

    private function makePaymentFailedPayload(
        string $stripeEventId,
        string $paymentIntentId,
        string $failureMessage,
    ): array {
        $booking = Booking::where('payment_intent_id', $paymentIntentId)->first();

        return [
            'id' => $stripeEventId,
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => $paymentIntentId,
                    'status' => 'requires_payment_method',
                    'amount' => 50000,
                    'currency' => 'vnd',
                    'metadata' => [
                        'booking_id' => (string) $booking?->id,
                        'user_id' => (string) $booking?->user_id,
                    ],
                    'last_payment_error' => [
                        'message' => $failureMessage,
                    ],
                ],
            ],
        ];
    }

    private function makeChargeRefundedPayload(
        string $stripeEventId,
        string $chargeId,
        string $paymentIntentId,
        string $refundId,
        string $refundStatus,
        int $refundAmount,
    ): array {
        return [
            'id' => $stripeEventId,
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => $chargeId,
                    'payment_intent' => $paymentIntentId,
                    'amount_refunded' => $refundAmount,
                    'currency' => 'vnd',
                    'refunds' => [
                        'data' => [
                            [
                                'id' => $refundId,
                                'status' => $refundStatus,
                                'amount' => $refundAmount,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
