<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case NOT_REQUIRED = 'not_required';
    case OFFLINE_DUE = 'offline_due';
    case REQUIRES_CONFIRMATION = 'requires_confirmation';
    case REQUIRES_PAYMENT_METHOD = 'requires_payment_method';
    case REQUIRES_ACTION = 'requires_action';
    case PROCESSING = 'processing';
    case AUTHORIZED = 'authorized';
    case PAID = 'paid';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case CAPTURE_FAILED = 'capture_failed';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';

    public static function fromStripePaymentIntentStatus(string $status): self
    {
        return match ($status) {
            'requires_payment_method' => self::REQUIRES_PAYMENT_METHOD,
            'requires_confirmation' => self::REQUIRES_CONFIRMATION,
            'requires_action' => self::REQUIRES_ACTION,
            'processing' => self::PROCESSING,
            'requires_capture' => self::AUTHORIZED,
            'succeeded' => self::PAID,
            'canceled' => self::CANCELLED,
            default => self::FAILED,
        };
    }
}
