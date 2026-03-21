<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Service recovery compensation type — what was offered to the guest?
 *
 * Used in service_recovery_cases.compensation_type.
 * Values must match the chk_src_compensation_type CHECK constraint.
 */
enum CompensationType: string
{
    case NONE = 'none';
    case REFUND_PARTIAL = 'refund_partial';
    case REFUND_FULL = 'refund_full';
    case VOUCHER = 'voucher';
    case COMPLIMENTARY_UPGRADE = 'complimentary_upgrade';
    case REFUND_PLUS_VOUCHER = 'refund_plus_voucher';

    /**
     * Check if this compensation type involves a monetary refund.
     */
    public function hasRefund(): bool
    {
        return in_array($this, [
            self::REFUND_PARTIAL,
            self::REFUND_FULL,
            self::REFUND_PLUS_VOUCHER,
        ], true);
    }

    /**
     * Check if this compensation type involves a voucher.
     */
    public function hasVoucher(): bool
    {
        return in_array($this, [
            self::VOUCHER,
            self::REFUND_PLUS_VOUCHER,
        ], true);
    }
}
