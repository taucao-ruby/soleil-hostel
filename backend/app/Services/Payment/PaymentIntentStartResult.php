<?php

declare(strict_types=1);

namespace App\Services\Payment;

final readonly class PaymentIntentStartResult
{
    public function __construct(
        public string $id,
        public ?string $clientSecret,
        public string $status,
        public int $amount,
        public string $currency,
        public int $amountCapturable = 0,
        public int $amountReceived = 0,
    ) {}
}
