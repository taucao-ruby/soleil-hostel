<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Result of starting a MoMo create-payment request — the redirect/QR/deeplink
 * channels plus the amount the guest is expected to pay. MoMo analogue of
 * PaymentIntentStartResult.
 */
final readonly class MoMoPaymentStartResult
{
    public function __construct(
        public string $orderId,
        public string $requestId,
        public string $payUrl,
        public ?string $deeplink,
        public ?string $qrCodeUrl,
        public int $amount,
        public string $currency,
    ) {}
}
