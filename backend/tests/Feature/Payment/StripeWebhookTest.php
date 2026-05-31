<?php

namespace Tests\Feature\Payment;

use App\Enums\BookingStatus;
use App\Enums\PaymentPolicy;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * HTTP-level signature contract for the Stripe webhook endpoint.
 *
 * Verification is owned by StripeWebhookController::handleWebhook (NOT Cashier's
 * optional middleware). The contract is fail-closed: a missing secret refuses
 * the request, and every malformed/forged request returns a controlled 400 —
 * never an unhandled 500 from invalid external input.
 *
 * Handler business logic is covered separately in StripeWebhookHandlerTest /
 * StripeWebhookIdempotencyTest (which invoke the handlers directly).
 */
class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookUrl = '/api/webhooks/stripe';

    private string $webhookSecret = 'whsec_test_secret_for_testing';

    protected function setUp(): void
    {
        parent::setUp();

        // Enable signature verification by configuring a webhook secret.
        config(['cashier.webhook.secret' => $this->webhookSecret]);
    }

    public function test_webhook_rejects_request_without_signature(): void
    {
        $response = $this->postJson($this->webhookUrl, [
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_fake_123']],
        ]);

        // No Stripe-Signature header -> controlled 400 (not 403, not 500).
        $response->assertStatus(400);
        $this->assertSame('Missing Stripe-Signature header.', $response->json('message'));
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $response = $this->postJson($this->webhookUrl, [
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_fake_123']],
        ], [
            'Stripe-Signature' => 't=1234567890,v1=invalid_signature_value',
        ]);

        $response->assertStatus(400);
        $this->assertSame('Invalid Stripe webhook signature.', $response->json('message'));
    }

    public function test_webhook_rejects_empty_payload(): void
    {
        $response = $this->postJson($this->webhookUrl, [], [
            'Stripe-Signature' => 't=1234567890,v1=invalid_signature_value',
        ]);

        $response->assertStatus(400);
    }

    public function test_webhook_fails_closed_when_secret_not_configured(): void
    {
        // An empty/unset secret must NOT silently disable verification. Cashier's
        // VerifyWebhookSignature middleware is only registered when the secret is
        // truthy, so relying on it would accept every unsigned webhook when the
        // secret is missing. handleWebhook() refuses instead: a server-side
        // misconfiguration is surfaced as 500 and the request is never processed
        // unverified.
        config(['cashier.webhook.secret' => '']);

        $response = $this->postJson($this->webhookUrl, [
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_fake_123']],
        ], [
            'Stripe-Signature' => 't=1234567890,v1=whatever',
        ]);

        $response->assertStatus(500);
        $this->assertSame('Webhook signature verification is not configured.', $response->json('message'));
    }

    public function test_webhook_accepts_valid_signature_and_confirms_booking(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::PENDING,
            'payment_intent_id' => 'pi_sig_happy_path',
            'payment_policy' => PaymentPolicy::PREPAID,
            'payment_status' => PaymentStatus::REQUIRES_PAYMENT_METHOD,
            'payment_currency' => 'vnd',
            'amount' => 50000,
        ]);

        $rawBody = json_encode([
            'id' => 'evt_sig_happy_path',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => 'pi_sig_happy_path',
                'status' => 'succeeded',
                'amount' => 50000,
                'currency' => 'vnd',
                'amount_capturable' => 0,
                'amount_received' => 50000,
                'metadata' => [
                    'booking_id' => (string) $booking->id,
                    'user_id' => (string) $booking->user_id,
                ],
            ]],
        ]);

        $response = $this->postSignedWebhook($rawBody);

        // 2xx ack so Stripe stops retrying, and the booking flips to confirmed.
        $response->assertSuccessful();

        $booking->refresh();
        $this->assertSame(BookingStatus::CONFIRMED, $booking->status);
    }

    public function test_webhook_route_exists_and_rejects_get(): void
    {
        $response = $this->getJson($this->webhookUrl);
        $response->assertStatus(405);
    }

    /**
     * POST a body signed with a valid Stripe v1 signature over the EXACT raw
     * bytes sent. Mirrors Stripe's scheme so verification exercises the real
     * code path rather than a bypass:
     *   signed_payload = "{timestamp}.{rawBody}"
     *   header         = "t={timestamp},v1={hmac_sha256(secret, signed_payload)}"
     */
    private function postSignedWebhook(string $rawBody): TestResponse
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$rawBody}", $this->webhookSecret);

        return $this->call(
            'POST',
            $this->webhookUrl,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            $rawBody,
        );
    }
}
