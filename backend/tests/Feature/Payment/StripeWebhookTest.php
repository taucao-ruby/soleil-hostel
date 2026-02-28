<?php

namespace Tests\Feature\Payment;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookUrl = '/api/webhooks/stripe';

    protected function setUp(): void
    {
        parent::setUp();

        // Enable signature verification by setting a webhook secret
        config(['cashier.webhook.secret' => 'whsec_test_secret_for_testing']);
    }

    public function test_webhook_rejects_request_without_signature(): void
    {
        $response = $this->postJson($this->webhookUrl, [
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_fake_123']],
        ]);

        // Without valid Stripe-Signature header, Cashier rejects the request
        $response->assertForbidden();
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $response = $this->postJson($this->webhookUrl, [
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_fake_123']],
        ], [
            'Stripe-Signature' => 't=1234567890,v1=invalid_signature_value',
        ]);

        $response->assertForbidden();
    }

    public function test_webhook_rejects_empty_payload(): void
    {
        $response = $this->postJson($this->webhookUrl, [], [
            'Stripe-Signature' => 't=1234567890,v1=invalid_signature_value',
        ]);

        $response->assertForbidden();
    }

    public function test_webhook_route_exists_and_rejects_get(): void
    {
        $response = $this->getJson($this->webhookUrl);
        $response->assertStatus(405);
    }
}
