<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Canonical room type classification for operational comparison.
 *
 * Used in rooms.room_type_code.
 * Values must match the chk_rooms_room_type_code CHECK constraint.
 */
enum RoomTypeCode: string
{
    case DORMITORY = 'dormitory';
    case PRIVATE_SINGLE = 'private_single';
    case PRIVATE_DOUBLE = 'private_double';
    case PRIVATE_TWIN = 'private_twin';
    case PRIVATE_SUITE = 'private_suite';

    /**
     * Default tier for this room type.
     * 1 = budget/dormitory, 2 = standard private, 3 = superior/suite.
     */
    public function defaultTier(): int
    {
        return match ($this) {
            self::DORMITORY => 1,
            self::PRIVATE_SINGLE, self::PRIVATE_DOUBLE, self::PRIVATE_TWIN => 2,
            self::PRIVATE_SUITE => 3,
        };
    }
}
