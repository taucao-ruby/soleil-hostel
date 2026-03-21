<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Room readiness status - physical room state for front-desk operations.
 *
 * Distinct from rooms.status:
 * - rooms.status = coarse commercial/catalog availability
 * - rooms.readiness_status = physical check-in readiness right now
 */
enum RoomReadinessStatus: string
{
    case READY = 'ready';
    case OCCUPIED = 'occupied';
    case DIRTY = 'dirty';
    case CLEANING = 'cleaning';
    case INSPECTED = 'inspected';
    case OUT_OF_SERVICE = 'out_of_service';

    public function blocksArrival(): bool
    {
        return $this !== self::READY;
    }
}
