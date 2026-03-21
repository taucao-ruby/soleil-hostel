<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Stay status — operational occupancy lifecycle.
 *
 * Distinct from BookingStatus (commercial reservation state).
 * A booking can be confirmed while the guest has any of these stay statuses.
 *
 * State machine (simplified):
 * expected → in_house → checked_out
 * expected → no_show
 * in_house → late_checkout → checked_out
 * in_house → relocated_internal | relocated_external (→ closed via new assignment)
 */
enum StayStatus: string
{
    case EXPECTED = 'expected';
    case IN_HOUSE = 'in_house';
    case LATE_CHECKOUT = 'late_checkout';
    case CHECKED_OUT = 'checked_out';
    case NO_SHOW = 'no_show';
    case RELOCATED_INTERNAL = 'relocated_internal';
    case RELOCATED_EXTERNAL = 'relocated_external';

    /**
     * Statuses that indicate the guest is physically present.
     *
     * @return array<self>
     */
    public static function inHouseStatuses(): array
    {
        return [self::IN_HOUSE, self::LATE_CHECKOUT];
    }

    /**
     * Check if the guest is currently in-house (occupying a room).
     */
    public function isInHouse(): bool
    {
        return in_array($this, self::inHouseStatuses(), true);
    }

    /**
     * Check if this is a terminal state (no further operational transitions).
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::CHECKED_OUT,
            self::NO_SHOW,
            self::RELOCATED_EXTERNAL,
        ], true);
    }
}
