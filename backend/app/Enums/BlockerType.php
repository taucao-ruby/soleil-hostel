<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why an expected arrival cannot use its originally booked room.
 */
enum BlockerType: string
{
    case LATE_CHECKOUT = 'late_checkout';
    case ROOM_NOT_READY = 'room_not_ready';
    case OUT_OF_SERVICE = 'out_of_service';
    case NO_INTERNAL_ROOM = 'no_internal_room';
}
