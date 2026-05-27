<?php

declare(strict_types=1);

namespace App\Services\Payment;

use Stripe\Refund;

/**
 * Outcome of the reconciler's "does a Stripe refund already exist?" pre-check
 * (PAY-01). Distinguishes three cases the retry path must treat differently:
 *
 *  - none()        no existing usable refund -> safe to create one.
 *  - match($r)     exactly one usable refund found -> sync local state, do NOT
 *                  create a second one.
 *  - ambiguous()   multiple candidate refunds matched and none could be
 *                  uniquely identified as Soleil's -> do NOT create; flag for
 *                  manual reconciliation.
 */
final readonly class ExistingRefundMatch
{
    /**
     * @param  list<string>  $candidateRefundIds
     */
    private function __construct(
        public bool $ambiguous,
        public ?Refund $refund,
        public array $candidateRefundIds = [],
    ) {}

    public static function none(): self
    {
        return new self(false, null);
    }

    public static function match(Refund $refund): self
    {
        return new self(false, $refund);
    }

    /**
     * @param  list<string>  $candidateRefundIds
     */
    public static function ambiguous(array $candidateRefundIds): self
    {
        return new self(true, null, $candidateRefundIds);
    }
}
