<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\RoomReadinessStatus;
use App\Enums\StayStatus;
use App\Models\Stay;
use App\Services\RoomReadinessService;

class StayObserver
{
    public function __construct(
        private readonly RoomReadinessService $roomReadinessService
    ) {}

    public function updated(Stay $stay): void
    {
        if (! $stay->wasChanged('stay_status')) {
            return;
        }

        $room = $this->roomReadinessService->roomForStay($stay);

        if (! $room) {
            return;
        }

        $status = $stay->stay_status;

        if ($status === StayStatus::IN_HOUSE) {
            $this->roomReadinessService->transition(
                room: $room,
                toStatus: RoomReadinessStatus::OCCUPIED,
                changedBy: $stay->checked_in_by,
                reason: 'Automatic transition triggered by stay check-in.',
                stayId: $stay->id
            );
        }

        if ($status === StayStatus::CHECKED_OUT) {
            $this->roomReadinessService->transition(
                room: $room,
                toStatus: RoomReadinessStatus::DIRTY,
                changedBy: $stay->checked_out_by,
                reason: 'Automatic transition triggered by stay check-out.',
                stayId: $stay->id
            );
        }
    }
}
