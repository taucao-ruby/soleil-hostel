<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\PaymentPolicy;
use App\Models\Booking;
use App\Services\MoMoService;
use Tests\TestCase;

/**
 * MoMo gateway-adapter unit coverage. Unlike StripeServiceTest (pure PHPUnit),
 * this extends Tests\TestCase: signCreatePayment/signIpn read
 * config('services.momo.secret_key') and createPayment reads app()->environment(),
 * both of which require a booted container.
 */
final class MoMoServiceTest extends TestCase
{
    private const SANDBOX_SECRET = 'K951B6PE1waDMi640xX08PD3vg6EkVlz';

    /**
     * Security lock: the create-payment signature is the canonical §2-ordered raw
     * string HMAC'd with the secret. Asserts against an INDEPENDENT hash_hmac over
     * the literal ordered string — if the method ksorts/reorders fields or drops
     * one, the HMAC diverges and this fails.
     */
    public function test_sign_create_payment_matches_canonical_ordered_hmac(): void
    {
        config(['services.momo.secret_key' => self::SANDBOX_SECRET]);

        $fields = [
            'accessKey' => 'F8BBA842ECF85',
            'amount' => '10000',
            'extraData' => '',
            'ipnUrl' => 'https://x/ipn',
            'orderId' => 'soleil-1-abc',
            'orderInfo' => 'Test',
            'partnerCode' => 'MOMO',
            'redirectUrl' => 'https://x/r',
            'requestId' => 'req-1',
            'requestType' => 'captureWallet',
        ];

        $expectedRaw = 'accessKey=F8BBA842ECF85&amount=10000&extraData=&ipnUrl=https://x/ipn&orderId=soleil-1-abc'
            .'&orderInfo=Test&partnerCode=MOMO&redirectUrl=https://x/r&requestId=req-1&requestType=captureWallet';
        $expected = hash_hmac('sha256', $expectedRaw, self::SANDBOX_SECRET);

        $this->assertSame($expected, app(MoMoService::class)->signCreatePayment($fields));
    }

    /**
     * Same security lock for the IPN signature — the 13-field §2 canonical order.
     */
    public function test_sign_ipn_matches_canonical_ordered_hmac(): void
    {
        // signIpn() sources accessKey from config (single source of truth), so the
        // config value — not the $fields entry — drives the signed accessKey segment.
        config([
            'services.momo.secret_key' => self::SANDBOX_SECRET,
            'services.momo.access_key' => 'F8BBA842ECF85',
        ]);

        $fields = [
            'accessKey' => 'F8BBA842ECF85',
            'amount' => '10000',
            'extraData' => '',
            'message' => 'Successful.',
            'orderId' => 'soleil-1-abc',
            'orderInfo' => 'Test',
            'orderType' => 'momo_wallet',
            'partnerCode' => 'MOMO',
            'payType' => 'qr',
            'requestId' => 'req-1',
            'responseTime' => '1700000000000',
            'resultCode' => 0,
            'transId' => '2588653829',
        ];

        $expectedRaw = 'accessKey=F8BBA842ECF85&amount=10000&extraData=&message=Successful.&orderId=soleil-1-abc'
            .'&orderInfo=Test&orderType=momo_wallet&partnerCode=MOMO&payType=qr&requestId=req-1'
            .'&responseTime=1700000000000&resultCode=0&transId=2588653829';
        $expected = hash_hmac('sha256', $expectedRaw, self::SANDBOX_SECRET);

        $this->assertSame($expected, app(MoMoService::class)->signIpn($fields));
    }

    /**
     * Regression guard for the IPN sign↔verify contract — the asymmetry that returned
     * 400 for every valid IPN whenever config access_key was non-empty (e.g. CI's
     * .env.testing). A payload signed by signIpn — which omits accessKey, exactly as
     * MoMo's inbound IPN body does — MUST verify, and tampering any signed field MUST
     * flip it to false. Pins the contract one assertion away, not three HTTP layers.
     */
    public function test_verify_ipn_accepts_a_payload_signed_by_sign_ipn(): void
    {
        config([
            'services.momo.secret_key' => 'test-momo-secret',
            'services.momo.access_key' => 'F8BBA842ECF85',
        ]);

        $svc = app(MoMoService::class);

        // The inbound IPN fields MoMo actually sends — NO accessKey.
        $payload = [
            'partnerCode' => 'MOMO',
            'orderId' => 'soleil-1-abc',
            'requestId' => 'req-1',
            'amount' => '50000',
            'orderInfo' => 'Soleil #1',
            'orderType' => 'momo_wallet',
            'transId' => '2588653829',
            'resultCode' => 0,
            'message' => 'Successful.',
            'payType' => 'qr',
            'responseTime' => '1700000000000',
            'extraData' => '',
        ];
        $payload['signature'] = $svc->signIpn($payload);

        $this->assertTrue($svc->verifyIpnSignature($payload));

        // Negative: tampering any signed field must flip verification to false.
        $tampered = $payload;
        $tampered['amount'] = '999999';
        $this->assertFalse($svc->verifyIpnSignature($tampered));
    }

    public function test_order_id_round_trips_and_rejects_malformed(): void
    {
        $service = app(MoMoService::class);

        $booking = new Booking;
        $booking->id = 77;
        $booking->exists = true;

        $orderId = $service->orderId($booking);

        $this->assertStringStartsWith('soleil-77-', $orderId);
        $this->assertSame(77, $service->bookingIdFromOrderId($orderId));
        $this->assertNull($service->bookingIdFromOrderId('garbage'));
        $this->assertNull($service->bookingIdFromOrderId('soleil--abc'));
        $this->assertNull($service->bookingIdFromOrderId('soleil-0-abc'));
    }

    public function test_fake_mode_create_payment_is_deterministic_without_network(): void
    {
        // Blank secret in the testing environment => fake mode (no network).
        config(['services.momo.secret_key' => null]);

        $service = app(MoMoService::class);

        $booking = new Booking;
        $booking->forceFill([
            'id' => 7,
            'amount' => 150000,
            'payment_currency' => 'vnd',
            'payment_policy' => PaymentPolicy::PREPAID,
        ]);
        $booking->exists = true;

        $first = $service->createPayment($booking);
        $second = $service->createPayment($booking);

        $this->assertSame($first->orderId, $second->orderId);
        $this->assertSame($first->payUrl, $second->payUrl);
        $this->assertSame(150000, $first->amount);
        $this->assertSame('vnd', $first->currency);
        $this->assertNotEmpty($first->payUrl);
        $this->assertStringStartsWith('soleil-7-', $first->orderId);
    }
}
