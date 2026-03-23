<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Booking deposit lifecycle.
 *
 * deposit_amount is an unearned-revenue/liability signal at collection time.
 * It is operational tracking, not ledger-based revenue recognition.
 */
enum DepositStatus: string
{
    case NONE = 'none';
    case COLLECTED = 'collected';
    case APPLIED = 'applied';
    case REFUNDED = 'refunded';
}
