<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\StayStatus;
use App\Events\BookingCancelled;
use App\Models\Stay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class HandleBookingCancelledForStay
{
    public function handle(BookingCancelled $event): void
    {
        DB::transaction(function () use ($event): void {
            $stay = Stay::query()
                ->where('booking_id', $event->booking->id)
                ->whereNotIn('stay_status', array_map(
                    static fn (StayStatus $status): string => $status->value,
                    StayStatus::terminalStatuses(),
                ))
                ->lockForUpdate()
                ->first();

            if ($stay === null) {
                return;
            }

            $stay->transitionTo(
                StayStatus::CANCELLED,
                reason: 'booking_cancelled',
                actor: $event->actor,
            );

            Log::info('Stay cancelled due to booking cancellation', [
                'stay_id' => $stay->id,
                'booking_id' => $event->booking->id,
            ]);
        });
    }
}
