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
     * Statuses that end the current stay lifecycle.
     *
     * @return array<self>
     */
    public static function terminalStatuses(): array
    {
        return [
            self::CHECKED_OUT,
            self::NO_SHOW,
            self::RELOCATED_INTERNAL,
            self::RELOCATED_EXTERNAL,
        ];
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
        return in_array($this, self::terminalStatuses(), true);
    }

    /**
     * Validate whether an operational stay transition is allowed.
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::EXPECTED => in_array($target, [
                self::IN_HOUSE,
                self::NO_SHOW,
            ], true),
            self::IN_HOUSE => in_array($target, [
                self::LATE_CHECKOUT,
                self::CHECKED_OUT,
                self::RELOCATED_INTERNAL,
                self::RELOCATED_EXTERNAL,
            ], true),
            self::LATE_CHECKOUT => in_array($target, [
                self::CHECKED_OUT,
                self::RELOCATED_INTERNAL,
                self::RELOCATED_EXTERNAL,
            ], true),
            self::CHECKED_OUT,
            self::NO_SHOW,
            self::RELOCATED_INTERNAL,
            self::RELOCATED_EXTERNAL => false,
        };
    }
}
