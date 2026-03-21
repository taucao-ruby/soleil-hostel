<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AssignmentStatus;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Services\RoomReadinessService;

class RoomAssignmentObserver
{
    public function __construct(
        private readonly RoomReadinessService $roomReadinessService
    ) {}

    public function creating(RoomAssignment $assignment): void
    {
        $assignmentStatus = $assignment->assignment_status instanceof AssignmentStatus
            ? $assignment->assignment_status->value
            : (string) ($assignment->assignment_status ?? AssignmentStatus::ACTIVE->value);

        if ($assignment->assigned_until !== null || $assignmentStatus !== AssignmentStatus::ACTIVE->value) {
            return;
        }

        /** @var Room $room */
        $room = $assignment->room instanceof Room
            ? $assignment->room
            : Room::query()->findOrFail($assignment->room_id);

        $this->roomReadinessService->assertRoomReadyForAssignment($room);
    }
}
