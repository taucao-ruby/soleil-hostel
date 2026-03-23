<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Operational settlement tracking for service recovery cases.
 *
 * This is not authoritative accounting or GL state.
 */
enum SettlementStatus: string
{
    case UNSETTLED = 'unsettled';
    case PARTIALLY_SETTLED = 'partially_settled';
    case SETTLED = 'settled';
    case WRITTEN_OFF = 'written_off';

    /**
     * @return array<self>
     */
    public static function openStatuses(): array
    {
        return [self::UNSETTLED, self::PARTIALLY_SETTLED];
    }
}
