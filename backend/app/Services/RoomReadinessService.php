<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoomReadinessStatus;
use App\Exceptions\RoomNotReadyForAssignmentException;
use App\Models\Room;
use App\Models\RoomReadinessLog;
use App\Models\Stay;
use Illuminate\Support\Facades\DB;

final class RoomReadinessService
{
    public function transition(
        Room $room,
        RoomReadinessStatus $toStatus,
        ?int $changedBy = null,
        ?string $reason = null,
        ?int $stayId = null
    ): Room {
        return DB::transaction(function () use ($room, $toStatus, $changedBy, $reason, $stayId) {
            /** @var Room $lockedRoom */
            $lockedRoom = Room::query()->lockForUpdate()->findOrFail($room->id);
            $fromStatus = $this->currentStatus($lockedRoom);

            if ($fromStatus === $toStatus) {
                return $lockedRoom;
            }

            $lockedRoom->forceFill([
                'readiness_status' => $toStatus,
                'readiness_changed_at' => now(),
                'readiness_changed_by' => $changedBy,
                'out_of_service_reason' => $toStatus === RoomReadinessStatus::OUT_OF_SERVICE ? $reason : null,
            ])->save();

            RoomReadinessLog::create([
                'room_id' => $lockedRoom->id,
                'stay_id' => $stayId,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'changed_at' => now(),
                'changed_by' => $changedBy,
                'reason' => $reason,
            ]);

            return $lockedRoom->refresh();
        });
    }

    public function assertRoomReadyForAssignment(Room $room): void
    {
        $freshRoom = Room::query()->findOrFail($room->id);

        if ($this->currentStatus($freshRoom) !== RoomReadinessStatus::READY) {
            throw RoomNotReadyForAssignmentException::forRoom($freshRoom);
        }
    }

    public function roomForStay(Stay $stay): ?Room
    {
        $assignment = $stay->roomAssignments()
            ->with('room')
            ->orderByRaw('CASE WHEN assigned_until IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('assigned_from')
            ->first();

        return $assignment?->room;
    }

    private function currentStatus(Room $room): RoomReadinessStatus
    {
        if ($room->readiness_status instanceof RoomReadinessStatus) {
            return $room->readiness_status;
        }

        return RoomReadinessStatus::from((string) ($room->readiness_status ?? RoomReadinessStatus::READY->value));
    }
}
