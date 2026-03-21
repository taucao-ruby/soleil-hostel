<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\RoomReadinessStatus;
use App\Models\Room;

class RoomObserver
{
    public function creating(Room $room): void
    {
        if ($room->readiness_status) {
            return;
        }

        $isMaintenanceRoom = $room->status === 'maintenance';

        $room->readiness_status = $isMaintenanceRoom
            ? RoomReadinessStatus::OUT_OF_SERVICE
            : RoomReadinessStatus::READY;
        $room->readiness_changed_at = now();
        $room->readiness_changed_by = null;
        $room->out_of_service_reason = $isMaintenanceRoom
            ? 'Initialized from legacy room.status=maintenance on room creation.'
            : null;
    }
}
