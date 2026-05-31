<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Internal refund status projection (SH-05 / F-73).
 *
 * Closed 3-state contract for `bookings.refund_status` and the published
 * OpenAPI `Booking.refund_status` enum. Stripe emits a wider set of refund
 * statuses (pending, requires_action, succeeded, failed, canceled); those raw
 * values MUST be normalized through {@see self::tryFromStripe()} before they
 * are persisted, so the column and the API contract can never drift to a
 * provider-specific state.
 *
 * Persisted as the string ->value: the `refund_status` column is intentionally
 * a plain string projection, not an enum cast (see Booking::$casts).
 */
enum RefundStatus: string
{
    case PENDING = 'pending';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';

    /**
     * The closed set of valid persisted/contract values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Map a raw Stripe refund status into the internal closed set.
     *
     * Mapping (Stripe -> internal):
     *   pending          -> pending
     *   requires_action  -> pending    (still in flight, awaiting action)
     *   succeeded        -> succeeded
     *   failed           -> failed
     *   canceled         -> failed     (refund did not complete)
     *
     * Returns null for any unrecognized/missing status. Callers MUST fail
     * closed on null: never persist an unmapped raw status into
     * `bookings.refund_status`.
     */
    public static function tryFromStripe(?string $stripeStatus): ?self
    {
        return match ($stripeStatus) {
            'pending', 'requires_action' => self::PENDING,
            'succeeded' => self::SUCCEEDED,
            'failed', 'canceled' => self::FAILED,
            default => null,
        };
    }
}
