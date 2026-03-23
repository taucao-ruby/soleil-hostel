<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Physical room readiness state.
 *
 * Distinct from:
 * - bookings.status (commercial reservation state)
 * - stays.stay_status (guest operational lifecycle)
 */
enum RoomReadinessStatus: string
{
    case READY = 'ready';
    case OCCUPIED = 'occupied';
    case DIRTY = 'dirty';
    case CLEANING = 'cleaning';
    case INSPECTED = 'inspected';
    case OUT_OF_SERVICE = 'out_of_service';

    /**
     * States that block immediate arrival into the room.
     *
     * @return array<self>
     */
    public static function arrivalBlockedStatuses(): array
    {
        return [
            self::DIRTY,
            self::CLEANING,
            self::INSPECTED,
            self::OUT_OF_SERVICE,
        ];
    }

    public function isReadyForArrival(): bool
    {
        return $this === self::READY;
    }
}
