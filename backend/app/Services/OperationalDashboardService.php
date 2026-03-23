<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CaseStatus;
use App\Enums\DepositStatus;
use App\Enums\IncidentType;
use App\Enums\RoomReadinessStatus;
use App\Enums\SettlementStatus;
use App\Enums\StayStatus;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Room;
use App\Models\ServiceRecoveryCase;
use App\Models\Stay;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OperationalDashboardService
{
    public function __construct(
        private readonly ArrivalResolutionService $arrivalResolutionService
    ) {}

    public function expectedArrivals(Location $location, Carbon $date): Collection
    {
        return Stay::query()
            ->with(['booking.room', 'currentRoomAssignment.room'])
            ->where('stay_status', StayStatus::EXPECTED->value)
            ->whereDate('scheduled_check_in_at', $date->toDateString())
            ->whereHas('booking', fn ($query) => $query->where('location_id', $location->id))
            ->orderBy('scheduled_check_in_at')
            ->get();
    }

    public function inHouseGuests(Location $location): Collection
    {
        return Stay::query()
            ->with(['booking.room', 'currentRoomAssignment.room'])
            ->inHouse()
            ->whereHas('booking', fn ($query) => $query->where('location_id', $location->id))
            ->orderBy('scheduled_check_out_at')
            ->get();
    }

    public function dueOuts(Location $location, Carbon $date): Collection
    {
        return Stay::query()
            ->with(['booking.room', 'currentRoomAssignment.room'])
            ->where('stay_status', StayStatus::IN_HOUSE->value)
            ->whereDate('scheduled_check_out_at', $date->toDateString())
            ->whereHas('booking', fn ($query) => $query->where('location_id', $location->id))
            ->orderBy('scheduled_check_out_at')
            ->get();
    }

    public function lateCheckouts(Location $location): Collection
    {
        return Stay::query()
            ->with(['booking.room', 'currentRoomAssignment.room'])
            ->lateCheckout()
            ->whereHas('booking', fn ($query) => $query->where('location_id', $location->id))
            ->orderBy('scheduled_check_out_at')
            ->get();
    }

    public function blockedArrivals(Location $location, Carbon $date): Collection
    {
        return $this->expectedArrivals($location, $date)
            ->filter(function (Stay $stay): bool {
                if ($this->hasOpenBlockingCase($stay)) {
                    return true;
                }

                return $this->arrivalResolutionService->blockerFor($stay) !== null;
            })
            ->values();
    }

    public function readyRooms(Location $location): Collection
    {
        return Room::query()
            ->atLocation($location)
            ->where('readiness_status', RoomReadinessStatus::READY->value)
            ->orderBy('room_number')
            ->get();
    }

    public function notReadyRooms(Location $location): Collection
    {
        return Room::query()
            ->atLocation($location)
            ->whereIn('readiness_status', [
                RoomReadinessStatus::DIRTY->value,
                RoomReadinessStatus::CLEANING->value,
                RoomReadinessStatus::INSPECTED->value,
            ])
            ->orderBy('room_number')
            ->get();
    }

    public function outOfServiceRooms(Location $location): Collection
    {
        return Room::query()
            ->atLocation($location)
            ->where('readiness_status', RoomReadinessStatus::OUT_OF_SERVICE->value)
            ->orderBy('room_number')
            ->get();
    }

    public function openRecoveryCases(Location $location): Collection
    {
        return ServiceRecoveryCase::query()
            ->with(['booking.room', 'stay'])
            ->open()
            ->whereHas('booking', fn ($query) => $query->where('location_id', $location->id))
            ->orderBy('opened_at')
            ->get();
    }

    public function manualReviewRequired(Location $location, Carbon $date): int
    {
        return $this->blockedArrivals($location, $date)
            ->filter(function (Stay $stay): bool {
                $blocker = $this->arrivalResolutionService->blockerFor($stay);

                if ($blocker === null) {
                    return false;
                }

                return $this->arrivalResolutionService->resolve($stay)->step->value
                    === \App\Enums\ResolutionStep::EXTERNAL_ESCALATION->value;
            })
            ->count();
    }

    public function depositCollected(Location $location, Carbon $from, Carbon $to): int
    {
        return (int) Booking::query()
            ->where('location_id', $location->id)
            ->where('deposit_status', DepositStatus::COLLECTED->value)
            ->whereBetween('check_in', [$from->toDateString(), $to->toDateString()])
            ->sum('deposit_amount');
    }

    public function openCompensationExposure(Location $location): int
    {
        return $this->sumExposureForSettlementStatuses($location, [
            SettlementStatus::UNSETTLED->value,
            SettlementStatus::PARTIALLY_SETTLED->value,
        ]);
    }

    public function settledCompensation(Location $location, Carbon $from, Carbon $to): int
    {
        return (int) DB::table('service_recovery_cases as src')
            ->join('bookings as bookings', 'bookings.id', '=', 'src.booking_id')
            ->where('bookings.location_id', $location->id)
            ->where('src.settlement_status', SettlementStatus::SETTLED->value)
            ->whereBetween('src.settled_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->sum('src.settled_amount');
    }

    public function unsettledExposure(Location $location): int
    {
        return $this->sumExposureForSettlementStatuses($location, [
            SettlementStatus::UNSETTLED->value,
        ]);
    }

    public function voucherExposure(Location $location): int
    {
        return (int) DB::table('service_recovery_cases as src')
            ->join('bookings as bookings', 'bookings.id', '=', 'src.booking_id')
            ->where('bookings.location_id', $location->id)
            ->whereNotIn('src.settlement_status', [
                SettlementStatus::SETTLED->value,
                SettlementStatus::WRITTEN_OFF->value,
            ])
            ->sum('src.voucher_amount');
    }

    public function relocationDeltaAbsorbed(Location $location, Carbon $from, Carbon $to): int
    {
        return (int) DB::table('service_recovery_cases as src')
            ->join('bookings as bookings', 'bookings.id', '=', 'src.booking_id')
            ->where('bookings.location_id', $location->id)
            ->whereIn('src.incident_type', [
                IncidentType::INTERNAL_RELOCATION->value,
                IncidentType::EXTERNAL_RELOCATION->value,
            ])
            ->whereBetween('src.opened_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->sum('src.cost_delta_absorbed');
    }

    private function hasOpenBlockingCase(Stay $stay): bool
    {
        return ServiceRecoveryCase::query()
            ->where('booking_id', $stay->booking_id)
            ->whereNotIn('case_status', [
                CaseStatus::RESOLVED->value,
                CaseStatus::CLOSED->value,
            ])
            ->exists();
    }

    /**
     * @param  array<int, string>  $settlementStatuses
     */
    private function sumExposureForSettlementStatuses(Location $location, array $settlementStatuses): int
    {
        $total = DB::table('service_recovery_cases as src')
            ->join('bookings as bookings', 'bookings.id', '=', 'src.booking_id')
            ->where('bookings.location_id', $location->id)
            ->whereIn('src.settlement_status', $settlementStatuses)
            ->selectRaw('
                COALESCE(SUM(
                    COALESCE(src.refund_amount, 0)
                    + COALESCE(src.voucher_amount, 0)
                    + COALESCE(src.cost_delta_absorbed, 0)
                    - COALESCE(src.settled_amount, 0)
                ), 0) AS total
            ')
            ->value('total');

        return (int) $total;
    }
}
