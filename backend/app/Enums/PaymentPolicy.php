<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentPolicy: string
{
    case PREPAID = 'prepaid';
    case AUTHORIZE_THEN_CAPTURE = 'authorize_then_capture';
    case PAY_AT_PROPERTY = 'pay_at_property';
    case NOT_REQUIRED = 'not_required';

    public function requiresStripePaymentIntent(): bool
    {
        return in_array($this, [
            self::PREPAID,
            self::AUTHORIZE_THEN_CAPTURE,
        ], true);
    }
}
