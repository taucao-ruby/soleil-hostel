<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentPolicy;
use App\Models\Booking;
use App\Services\Payment\MoMoPaymentStartResult;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * MoMo (sandbox) gateway adapter — the additive, parallel payment path
 * (MOMO_SANDBOX_EXECUTION_PLAN T3). It builds + signs a create-payment request,
 * sends it, and verifies inbound IPN signatures. It is a pure adapter: it performs
 * NO booking state mutation (that is the IPN handler via BookingService::markPaidAndConfirm)
 * and opens NO DB transaction. It does network I/O only and must never be called
 * inside a booking/room lock. The MoMo analogue of StripeService for the create path.
 */
class MoMoService
{
    /**
     * MoMo AIO v2 create-payment signing order — fixed canonical sequence the
     * raw string is built from (S1). Hardcoded so signing order can never be
     * derived from attacker-controlled array key order; values are read out of
     * the field map by these keys only.
     */
    private const CREATE_SIGNATURE_FIELDS = [
        'accessKey',
        'amount',
        'extraData',
        'ipnUrl',
        'orderId',
        'orderInfo',
        'partnerCode',
        'redirectUrl',
        'requestId',
        'requestType',
    ];

    /**
     * MoMo AIO v2 IPN signing order — the fixed canonical sequence for the
     * server→server notification signature (S1). [UNPROVEN] pinned verbatim from
     * MOMO_SANDBOX_EXECUTION_PLAN §2; T8 / a live sandbox IPN must confirm it.
     */
    private const IPN_SIGNATURE_FIELDS = [
        'accessKey',
        'amount',
        'extraData',
        'message',
        'orderId',
        'orderInfo',
        'orderType',
        'partnerCode',
        'payType',
        'requestId',
        'responseTime',
        'resultCode',
        'transId',
    ];

    public function __construct(
        private readonly Factory $http
    ) {}

    /**
     * Start a MoMo create-payment request for a payable booking and return the
     * redirect/QR/deeplink channels. Network I/O only — never call inside a lock.
     */
    public function createPayment(Booking $booking): MoMoPaymentStartResult
    {
        $amount = $this->expectedAmount($booking);
        $currency = $this->expectedCurrency($booking);

        /** @var PaymentPolicy $policy */
        $policy = $booking->payment_policy;

        if ($amount <= 0) {
            throw new RuntimeException('Booking amount must be greater than zero.');
        }

        if (! $policy->requiresStripePaymentIntent()) {
            throw new RuntimeException('Booking payment policy does not require a MoMo payment.');
        }

        $stableKey = $this->createPaymentStableKey($booking);

        if ($this->shouldUseTestingFake()) {
            $orderId = 'soleil-'.$booking->id.'-'.substr(hash('sha256', $stableKey), 0, 12);
            $requestId = substr(hash('sha256', $stableKey.':request'), 0, 32);

            return new MoMoPaymentStartResult(
                orderId: $orderId,
                requestId: $requestId,
                payUrl: 'https://test-payment.momo.vn/pay/'.$orderId,
                deeplink: 'momo://app?action=payment&orderId='.$orderId,
                qrCodeUrl: 'https://test-payment.momo.vn/qr/'.$orderId,
                amount: $amount,
                currency: $currency,
            );
        }

        $orderId = $this->orderId($booking);
        $requestId = (string) Str::uuid();

        // The 10 canonical signed fields. orderInfo is signed AND sent verbatim,
        // so MoMo's server-side re-sign matches; never sign one value and send another.
        $fields = [
            'accessKey' => (string) config('services.momo.access_key'),
            'amount' => (string) $amount,
            'extraData' => '',
            'ipnUrl' => (string) config('services.momo.ipn_url'),
            'orderId' => $orderId,
            'orderInfo' => 'Thanh toán đặt phòng Soleil #'.$booking->id,
            'partnerCode' => (string) config('services.momo.partner_code'),
            'redirectUrl' => (string) config('services.momo.redirect_url'),
            'requestId' => $requestId,
            'requestType' => (string) config('services.momo.request_type'),
        ];

        $signature = $this->signCreatePayment($fields);

        $payload = $fields + [
            'partnerName' => 'Soleil Hostel',
            'storeId' => (string) config('services.momo.store_id'),
            'lang' => 'vi',
            'autoCapture' => true,
            'signature' => $signature,
        ];

        $endpoint = (string) config('services.momo.endpoint');

        $response = $this->http
            ->connectTimeout((int) config('services.momo.connect_timeout'))
            ->timeout((int) config('services.momo.read_timeout'))
            ->asJson()
            ->post($endpoint, $payload);

        $resultCode = $response->json('resultCode');
        $payUrl = $response->json('payUrl');

        if (! $response->successful() || $resultCode !== 0 || ! is_string($payUrl) || $payUrl === '') {
            // S5: surface only non-sensitive fields — never the signature or secret.
            throw new RuntimeException(sprintf(
                'MoMo create-payment failed for order %s (resultCode=%s): %s',
                $orderId,
                is_scalar($resultCode) ? (string) $resultCode : 'unknown',
                (string) $response->json('message', ''),
            ));
        }

        $deeplink = $response->json('deeplink');
        $qrCodeUrl = $response->json('qrCodeUrl');

        return new MoMoPaymentStartResult(
            orderId: $orderId,
            requestId: $requestId,
            payUrl: $payUrl,
            deeplink: is_string($deeplink) && $deeplink !== '' ? $deeplink : null,
            qrCodeUrl: is_string($qrCodeUrl) && $qrCodeUrl !== '' ? $qrCodeUrl : null,
            amount: $amount,
            currency: $currency,
        );
    }

    /**
     * HMAC-SHA256 (lowercase hex) of the canonical create-payment raw string (S1/S2).
     * accessKey is read from $fields so the method is vector-testable; the secret
     * is read from config and never enters $fields.
     *
     * @param  array<string, mixed>  $fields
     */
    public function signCreatePayment(array $fields): string
    {
        return $this->hmac($this->rawSignature($fields, self::CREATE_SIGNATURE_FIELDS));
    }

    /**
     * HMAC-SHA256 (lowercase hex) of the canonical IPN raw string (S1/S2).
     *
     * @param  array<string, mixed>  $fields
     */
    public function signIpn(array $fields): string
    {
        return $this->hmac($this->rawSignature($fields, self::IPN_SIGNATURE_FIELDS));
    }

    /**
     * Verify an inbound MoMo IPN signature. Fail-closed security boundary (S3/S4):
     * - blank/non-string secret_key ⇒ false (cannot verify ⇒ reject; never "passes"
     *   an unverifiable IPN, never throws the secret into a message).
     * - blank/non-string provided signature ⇒ false.
     * - constant-time hash_equals compare, never === / ==.
     *
     * MoMo does NOT transmit accessKey in the IPN body, yet it is part of the
     * signing string — so we inject our OWN accessKey from config rather than trust
     * the payload to supply it. This both matches MoMo's signature and prevents the
     * attacker-controlled payload from dictating accessKey.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyIpnSignature(array $payload): bool
    {
        $secret = config('services.momo.secret_key');

        if (! is_string($secret) || $secret === '') {
            return false;
        }

        $provided = $payload['signature'] ?? null;

        if (! is_string($provided) || $provided === '') {
            return false;
        }

        $fields = $payload;
        $fields['accessKey'] = (string) config('services.momo.access_key');

        return hash_equals($this->signIpn($fields), $provided);
    }

    /**
     * The booking↔order mapping that avoids a bookings migration. The random
     * nonce is for MoMo's per-create orderId uniqueness, not secrecy.
     */
    public function orderId(Booking $booking): string
    {
        if (! $booking->exists) {
            throw new RuntimeException('Booking must be persisted before creating a MoMo order id.');
        }

        return 'soleil-'.((int) $booking->getKey()).'-'.Str::random(16);
    }

    /**
     * Strict inverse of orderId() (S6). Trusted downstream, so reject anything
     * malformed (anchored pattern, positive id) rather than coercing — no loose
     * explode. Returns the booking id only when well-formed and > 0, else null.
     */
    public function bookingIdFromOrderId(string $orderId): ?int
    {
        if (preg_match('/^soleil-(\d{1,18})-[A-Za-z0-9]+$/', $orderId, $matches) !== 1) {
            return null;
        }

        $bookingId = (int) $matches[1];

        return $bookingId > 0 ? $bookingId : null;
    }

    /**
     * Build a canonical raw signing string from an EXPLICIT, hardcoded key order
     * (S1). Each value is coerced to string; a missing field signs as empty. Never
     * ksort()/derive order from input, never include a field outside the list.
     *
     * @param  array<string, mixed>  $fields
     * @param  list<string>  $orderedKeys
     */
    private function rawSignature(array $fields, array $orderedKeys): string
    {
        $segments = [];

        foreach ($orderedKeys as $key) {
            $segments[] = $key.'='.(string) ($fields[$key] ?? '');
        }

        return implode('&', $segments);
    }

    private function hmac(string $raw): string
    {
        return hash_hmac('sha256', $raw, (string) config('services.momo.secret_key'));
    }

    /**
     * Stable, attempt-independent key for fake-mode determinism — mirrors
     * StripeService::paymentIntentIdempotencyKey. Requires a persisted booking.
     */
    private function createPaymentStableKey(Booking $booking): string
    {
        if (! $booking->exists) {
            throw new RuntimeException('Booking must be persisted before creating a MoMo payment.');
        }

        return sprintf('booking:%d:momo:create:v1', (int) $booking->getKey());
    }

    /**
     * Mirror StripeService: in the test environment with no MoMo secret configured,
     * return a deterministic result instead of hitting the network.
     */
    private function shouldUseTestingFake(): bool
    {
        return app()->environment('testing') && blank(config('services.momo.secret_key'));
    }

    private function expectedAmount(Booking $booking): int
    {
        return (int) $booking->amount;
    }

    private function expectedCurrency(Booking $booking): string
    {
        $currency = (string) $booking->payment_currency;

        if ($currency !== '') {
            return strtolower($currency);
        }

        return strtolower((string) config('cashier.currency', 'vnd'));
    }
}
