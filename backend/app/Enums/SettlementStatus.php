<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Service recovery case settlement lifecycle.
 *
 * Used in service_recovery_cases.settlement_status.
 * Values must match the chk_src_settlement_status CHECK constraint.
 *
 * Settlement is the financial discharge of a compensation obligation.
 * It is distinct from resolved_at (operational resolution of the incident).
 */
enum SettlementStatus: string
{
    case UNSETTLED = 'unsettled';
    case PARTIALLY_SETTLED = 'partially_settled';
    case SETTLED = 'settled';
    case WAIVED = 'waived';

    /**
     * Check if this status represents a completed settlement.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::SETTLED, self::WAIVED], true);
    }
}
