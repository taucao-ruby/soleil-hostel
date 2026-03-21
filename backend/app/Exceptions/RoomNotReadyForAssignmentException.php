<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Room;
use DomainException;

final class RoomNotReadyForAssignmentException extends DomainException
{
    public static function forRoom(Room $room): self
    {
        $status = $room->readiness_status?->value ?? '';

        return new self(
            "Room #{$room->id} is not ready for assignment. Current readiness status: {$status}."
        );
    }
}
