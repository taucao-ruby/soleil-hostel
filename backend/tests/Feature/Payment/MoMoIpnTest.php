<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Enums\BookingStatus;
use App\Enums\PaymentPolicy;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\MoMoPayment;
use App\Models\Room;
use App\Models\User;
use App\Services\MoMoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * MoMo IPN idempotency + fail-closed coverage — mirrors StripeWebhookIdempotencyTest.
 *
 * Hits the REAL public route v1.payments.momo.ipn (the controller verifies the
 * HMAC signature itself, unlike Cashier). The route carries no auth, so an
 * un-authenticated post reaching the controller also proves T7's middleware stack.
 *
 * Linearization point: momo_webhook_events UNIQUE(order_id, trans_id). A replayed
 * IPN collides on INSERT and acks 204 before any second confirm runs.
 */
final class MoMoIpnTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.momo.secret_key' => 'test-momo-secret']);
        $this->user = User::factory()->create();
        $this->room = Room::factory()->available()->ready()->create();
    }

    public function test_valid_ipn_confirms_a_pending_prepaid_booking(): void
    {
        $booking = $this->pendingPrepaidBooking();
        $payload = $this->signedIpn($booking);

        $this->postJson(route('v1.payments.momo.ipn'), $payload)->assertNoContent();

        $booking->refresh();
        $this->assertSame(BookingStatus::CONFIRMED, $booking->status);
        $this->assertSame(PaymentStatus::PAID, $booking->payment_status);
        $this->assertDatabaseHas('momo_webhook_events', [
            'order_id' => $payload['orderId'],
            'status' => 'processed',
        ]);
        $this->assertDatabaseHas('momo_payments', [
            'order_id' => $payload['orderId'],
            'status' => 'paid',
        ]);
    }

    public function test_replayed_ipn_acks_204_and_confirms_exactly_once(): void
    {
        $booking = $this->pendingPrepaidBooking();
        $payload = $this->signedIpn($booking);

        $this->postJson(route('v1.payments.momo.ipn'), $payload)->assertNoContent();
        $this->postJson(route('v1.payments.momo.ipn'), $payload)->assertNoContent();

        $this->assertDatabaseCount('momo_webhook_events', 1);
        $booking->refresh();
        $this->assertSame(BookingStatus::CONFIRMED, $booking->status);
    }

    public function test_bad_signature_is_rejected_400_with_no_side_effect(): void
    {
        $booking = $this->pendingPrepaidBooking();
        $payload = $this->signedIpn($booking);
        $payload['signature'] = 'deadbeef';

        $this->postJson(route('v1.payments.momo.ipn'), $payload)->assertStatus(400);

        $booking->refresh();
        $this->assertSame(BookingStatus::PENDING, $booking->status);
        $this->assertDatabaseCount('momo_webhook_events', 0);
    }

    public function test_amount_mismatch_is_recorded_failed_and_does_not_confirm(): void
    {
        $booking = $this->pendingPrepaidBooking(50000);
        // Validly signed but with the wrong amount: the signature passes, the
        // handler's amount guard rejects. Proves defense-in-depth, not a signature
        // side effect.
        $payload = $this->signedIpn($booking, ['amount' => '999999']);

        $this->postJson(route('v1.payments.momo.ipn'), $payload)->assertNoContent();

        $booking->refresh();
        $this->assertSame(BookingStatus::PENDING, $booking->status);
        $this->assertDatabaseHas('momo_webhook_events', [
            'order_id' => $payload['orderId'],
            'status' => 'failed',
        ]);
    }

    public function test_ipn_for_unknown_order_is_handled_without_crash(): void
    {
        $booking = $this->pendingPrepaidBooking();
        $payload = $this->signedIpn($booking, ['orderId' => 'soleil-99999999-x'], persistPayment: false);

        $this->postJson(route('v1.payments.momo.ipn'), $payload)->assertNoContent();

        $this->assertDatabaseHas('momo_webhook_events', [
            'order_id' => 'soleil-99999999-x',
            'status' => 'processed',
        ]);
    }

    public function test_blank_secret_fails_closed_with_500(): void
    {
        $booking = $this->pendingPrepaidBooking();
        $payload = $this->signedIpn($booking);

        config(['services.momo.secret_key' => '']);

        $this->postJson(route('v1.payments.momo.ipn'), $payload)->assertStatus(500);

        $booking->refresh();
        $this->assertSame(BookingStatus::PENDING, $booking->status);
        $this->assertDatabaseCount('momo_webhook_events', 0);
    }

    public function test_ipn_route_is_rate_limited(): void
    {
        // Finding 1 (hardening): the public IPN route MUST declare an explicit
        // throttle — the `api` group carries no default one, so an unauthenticated
        // flood would otherwise be bounded only by the (cheap) signature rejection.
        $route = app('router')->getRoutes()->getByName('v1.payments.momo.ipn');

        $this->assertNotNull($route, 'MoMo IPN route is not registered.');
        $this->assertContains('throttle:120,1', $route->middleware());
    }

    public function test_create_persists_an_authoritative_order_record(): void
    {
        // Blank secret ⇒ MoMoService fake mode: deterministic order, no network call.
        config(['services.momo.secret_key' => null]);

        $booking = $this->pendingPrepaidBooking(50000);

        $this->actingAs($this->user, 'sanctum')
            ->postJson(route('v1.bookings.momo.create', $booking))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('momo_payments', [
            'booking_id' => $booking->id,
            'expected_amount' => 50000,
            'currency' => 'vnd',
            'status' => 'pending',
        ]);
    }

    private function pendingPrepaidBooking(int $amount = 50000): Booking
    {
        return Booking::factory()->for($this->user)->for($this->room)->create([
            'status' => BookingStatus::PENDING,
            'payment_policy' => PaymentPolicy::PREPAID,
            'payment_status' => PaymentStatus::REQUIRES_PAYMENT_METHOD,
            'payment_currency' => 'vnd',
            'amount' => $amount,
        ]);
    }

    /**
     * Build a MoMo IPN payload for a booking and sign it with the configured secret,
     * so verifyIpnSignature passes unless an override deliberately breaks it. Also
     * mints the authoritative momo_payments record the handler resolves through (what
     * create() does in production); pass persistPayment: false to simulate an IPN for
     * an order we never started.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function signedIpn(Booking $booking, array $overrides = [], bool $persistPayment = true): array
    {
        $orderId = (string) ($overrides['orderId'] ?? 'soleil-'.$booking->id.'-'.uniqid());

        if ($persistPayment) {
            MoMoPayment::create([
                'booking_id' => $booking->id,
                'order_id' => $orderId,
                'request_id' => (string) Str::uuid(),
                'expected_amount' => (int) $booking->amount,
                'currency' => 'vnd',
                'status' => 'pending',
            ]);
        }

        $payload = array_merge([
            'partnerCode' => 'MOMO',
            'orderId' => $orderId,
            'requestId' => (string) Str::uuid(),
            'amount' => (string) $booking->amount,
            'orderInfo' => 'Soleil #'.$booking->id,
            'orderType' => 'momo_wallet',
            'transId' => (string) random_int(10000000, 99999999),
            'resultCode' => 0,
            'message' => 'Successful.',
            'payType' => 'qr',
            'responseTime' => (string) now()->valueOf(),
            'extraData' => '',
        ], $overrides);

        $payload['orderId'] = $orderId;
        $payload['signature'] = app(MoMoService::class)->signIpn($payload);

        return $payload;
    }
}
