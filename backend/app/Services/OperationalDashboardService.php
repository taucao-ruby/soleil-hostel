<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\IncidentType;
use App\Enums\RoomReadinessStatus;
use App\Enums\SettlementStatus;
use App\Enums\StayStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\ServiceRecoveryCase;
use App\Models\Stay;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class OperationalDashboardService
{
    public const ROOM_FLOOR_FIELD = null;

    public const ROOM_ZONE_FIELD = null;

    public const ROOM_TYPE_FIELD = 'room_type_code';

    public const RECOVERY_INCIDENT_TYPE_FIELD = 'incident_type';

    public const RECOVERY_REFUND_FIELD = 'refund_amount';

    public const RECOVERY_VOUCHER_FIELD = 'voucher_amount';

    public const RECOVERY_RELOCATION_COST_FIELD = 'cost_delta_absorbed';

    public const RECOVERY_RESOLUTION_TIMESTAMP_FIELD = 'resolved_at';

    public const RECOVERY_ESCALATION_TIMESTAMP_FIELD = 'escalated_at';

    public const BOOKING_DEPOSIT_FIELD = 'deposit_amount';

    /**
     * @return array{
     *     location_id: int,
     *     date: string,
     *     expected_arrivals_today: array{count: int, stays: list<array<string, mixed>>},
     *     guests_currently_in_house: array{count: int, stays: list<array<string, mixed>>},
     *     due_outs_today: array{count: int, stays: list<array<string, mixed>>},
     *     late_checkouts: array{count: int, stays: list<array<string, mixed>>},
     *     arrivals_blocked_by_room_not_ready: array{count: int, stays: list<array<string, mixed>>},
     *     confirmed_bookings_without_stay_row: array{count: int, bookings: list<array<string, mixed>>}
     * }
     */
    public function arrivalDepartureBoard(int $locationId, string $date): array
    {
        $boardDate = Carbon::parse($date);
        $dayStart = $boardDate->copy()->startOfDay();
        $dayEnd = $boardDate->copy()->endOfDay();

        $baseStayQuery = $this->baseStayBoardQuery($locationId);

        $expectedArrivals = $this->mapStayRows(
            (clone $baseStayQuery)
                ->where('stays.stay_status', StayStatus::EXPECTED->value)
                ->whereBetween('stays.scheduled_check_in_at', [$dayStart, $dayEnd])
                ->orderBy('stays.scheduled_check_in_at')
                ->get()
        );

        $inHouseStays = $this->mapStayRows(
            (clone $baseStayQuery)
                ->whereIn('stays.stay_status', array_map(
                    static fn (StayStatus $status) => $status->value,
                    StayStatus::inHouseStatuses()
                ))
                ->where(function ($query) use ($dayStart, $dayEnd) {
                    $query->whereNull('stays.scheduled_check_in_at')
                        ->orWhere('stays.scheduled_check_in_at', '<=', $dayEnd);
                })
                ->where(function ($query) use ($dayStart) {
                    $query->whereNull('stays.scheduled_check_out_at')
                        ->orWhere('stays.scheduled_check_out_at', '>', $dayStart);
                })
                ->orderBy('stays.scheduled_check_out_at')
                ->get()
        );

        $dueOuts = $this->mapStayRows(
            (clone $baseStayQuery)
                ->whereIn('stays.stay_status', array_map(
                    static fn (StayStatus $status) => $status->value,
                    StayStatus::inHouseStatuses()
                ))
                ->whereBetween('stays.scheduled_check_out_at', [$dayStart, $dayEnd])
                ->orderBy('stays.scheduled_check_out_at')
                ->get()
        );

        $lateCheckouts = $this->mapStayRows(
            (clone $baseStayQuery)
                ->whereIn('stays.stay_status', array_map(
                    static fn (StayStatus $status) => $status->value,
                    StayStatus::inHouseStatuses()
                ))
                ->where('stays.scheduled_check_out_at', '<', now())
                ->orderBy('stays.scheduled_check_out_at')
                ->get()
        );

        $blockedArrivals = array_values(array_filter(
            $expectedArrivals,
            static fn (array $stay): bool => $stay['room_id'] !== null
                && $stay['readiness_status'] !== RoomReadinessStatus::READY->value
        ));

        $bookingsWithoutStay = Booking::query()
            ->where('location_id', $locationId)
            ->where('status', BookingStatus::CONFIRMED)
            ->whereDate('check_in', $boardDate->toDateString())
            ->whereDoesntHave('stay')
            ->orderBy('check_in')
            ->get()
            ->map(static fn (Booking $booking): array => [
                'booking_id' => $booking->id,
                'room_id' => $booking->room_id,
                'guest_name' => $booking->guest_name,
                'guest_email' => $booking->guest_email,
                'check_in' => $booking->check_in?->toDateString(),
                'check_out' => $booking->check_out?->toDateString(),
            ])
            ->all();

        return [
            'location_id' => $locationId,
            'date' => $boardDate->toDateString(),
            'expected_arrivals_today' => [
                'count' => count($expectedArrivals),
                'stays' => $expectedArrivals,
            ],
            'guests_currently_in_house' => [
                'count' => count($inHouseStays),
                'stays' => $inHouseStays,
            ],
            'due_outs_today' => [
                'count' => count($dueOuts),
                'stays' => $dueOuts,
            ],
            'late_checkouts' => [
                'count' => count($lateCheckouts),
                'stays' => $lateCheckouts,
            ],
            'arrivals_blocked_by_room_not_ready' => [
                'count' => count($blockedArrivals),
                'stays' => $blockedArrivals,
            ],
            'confirmed_bookings_without_stay_row' => [
                'count' => count($bookingsWithoutStay),
                'bookings' => $bookingsWithoutStay,
            ],
        ];
    }

    /**
     * @return array{
     *     location_id: int,
     *     threshold_minutes: int,
     *     states: array<string, array{count: int, rooms: list<array<string, mixed>>}>,
     *     stale_housekeeping_rooms: list<array<string, mixed>>
     * }
     */
    public function roomReadinessBoard(int $locationId, int $staleThresholdMinutes = 90): array
    {
        $rooms = Room::query()
            ->where('location_id', $locationId)
            ->orderBy('room_number')
            ->orderBy('name')
            ->get();

        $states = [];

        foreach (RoomReadinessStatus::cases() as $status) {
            $groupedRooms = $rooms
                ->filter(static fn (Room $room): bool => $room->readiness_status === $status)
                ->map(fn (Room $room): array => $this->mapRoomRow($room))
                ->values()
                ->all();

            $states[$status->value] = [
                'count' => count($groupedRooms),
                'rooms' => $groupedRooms,
            ];
        }

        $staleRooms = $rooms
            ->filter(function (Room $room) use ($staleThresholdMinutes): bool {
                if (! in_array($room->readiness_status, [
                    RoomReadinessStatus::DIRTY,
                    RoomReadinessStatus::CLEANING,
                ], true)) {
                    return false;
                }

                return $room->readiness_changed_at !== null
                    && $room->readiness_changed_at->diffInMinutes(now()) > $staleThresholdMinutes;
            })
            ->map(function (Room $room): array {
                $row = $this->mapRoomRow($room);
                $row['minutes_in_status'] = $room->readiness_changed_at?->diffInMinutes(now()) ?? null;

                return $row;
            })
            ->values()
            ->all();

        return [
            'location_id' => $locationId,
            'threshold_minutes' => $staleThresholdMinutes,
            'states' => $states,
            'stale_housekeeping_rooms' => $staleRooms,
        ];
    }

    /**
     * @return array{
     *     location_id: int,
     *     date: string,
     *     open_recovery_cases: array{count: int, cases: list<array<string, mixed>>},
     *     late_checkout_blockers: array{count: int, stays: list<array<string, mixed>>},
     *     rooms_needing_reassignment: array{count: int, stays: list<array<string, mixed>>},
     *     internal_relocation_candidates: array{count: int, cases: list<array<string, mixed>>}|null,
     *     external_relocation_escalations: array{count: int, cases: list<array<string, mixed>>}|null,
     *     compensation_pending_settlement: array{count: int, cases: list<array<string, mixed>>}|null
     * }
     */
    public function exceptionBoard(int $locationId, string $date): array
    {
        $boardDate = Carbon::parse($date);
        $dayEnd = $boardDate->copy()->endOfDay();
        $arrivalBoard = $this->arrivalDepartureBoard($locationId, $boardDate->toDateString());

        $openCases = ServiceRecoveryCase::query()
            ->join('bookings', 'service_recovery_cases.booking_id', '=', 'bookings.id')
            ->where('bookings.location_id', $locationId)
            ->where('service_recovery_cases.opened_at', '<=', $dayEnd)
            ->whereNull('service_recovery_cases.resolved_at')
            ->orderBy('service_recovery_cases.opened_at')
            ->select('service_recovery_cases.*')
            ->get()
            ->map(fn (ServiceRecoveryCase $case): array => $this->mapRecoveryCaseRow($case))
            ->all();

        $arrivalsByRoom = [];
        foreach ($arrivalBoard['expected_arrivals_today']['stays'] as $arrival) {
            if ($arrival['room_id'] === null) {
                continue;
            }

            $arrivalsByRoom[$arrival['room_id']][] = $arrival['stay_id'];
        }

        $lateCheckoutBlockers = [];
        foreach ($arrivalBoard['late_checkouts']['stays'] as $lateCheckout) {
            $roomId = $lateCheckout['room_id'];

            if ($roomId === null || ! isset($arrivalsByRoom[$roomId])) {
                continue;
            }

            $lateCheckout['arriving_stay_ids'] = $arrivalsByRoom[$roomId];
            $lateCheckoutBlockers[] = $lateCheckout;
        }

        $roomsNeedingReassignment = array_values(array_filter(
            $arrivalBoard['arrivals_blocked_by_room_not_ready']['stays'],
            static fn (array $stay): bool => in_array(
                $stay['assignment_type'],
                [null, 'original'],
                true
            )
        ));

        $internalRelocationCandidates = array_values(array_filter(
            $openCases,
            static fn (array $case): bool => $case['incident_type'] === IncidentType::INTERNAL_RELOCATION->value
        ));

        $externalRelocationEscalations = array_values(array_filter(
            $openCases,
            static fn (array $case): bool => $case['incident_type'] === IncidentType::EXTERNAL_RELOCATION->value
                && $case['escalated_at'] !== null
        ));

        $compensationPendingSettlement = array_values(array_filter(
            $openCases,
            static fn (array $case): bool => (($case['refund_amount'] ?? 0) > 0) || (($case['voucher_amount'] ?? 0) > 0)
        ));

        return [
            'location_id' => $locationId,
            'date' => $boardDate->toDateString(),
            'open_recovery_cases' => [
                'count' => count($openCases),
                'cases' => $openCases,
            ],
            'late_checkout_blockers' => [
                'count' => count($lateCheckoutBlockers),
                'stays' => $lateCheckoutBlockers,
            ],
            'rooms_needing_reassignment' => [
                'count' => count($roomsNeedingReassignment),
                'stays' => $roomsNeedingReassignment,
            ],
            'internal_relocation_candidates' => [
                'count' => count($internalRelocationCandidates),
                'cases' => $internalRelocationCandidates,
            ],
            'external_relocation_escalations' => [
                'count' => count($externalRelocationEscalations),
                'cases' => $externalRelocationEscalations,
            ],
            'compensation_pending_settlement' => [
                'count' => count($compensationPendingSettlement),
                'cases' => $compensationPendingSettlement,
            ],
        ];
    }

    /**
     * @return array{
     *     location_id: int,
     *     date_from: string,
     *     date_to: string,
     *     confirmed_bookings_without_stay_count: int,
     *     unresolved_recovery_cases_count: int,
     *     unresolved_compensation_refund_amount: int|null,
     *     unresolved_voucher_amount: int|null,
     *     relocation_absorbed_cost_amount: int|null,
     *     deposit_or_advance_amount_total: int,
     *     bookings_with_deposit_count: int,
     *     bookings_without_deposit_count: int,
     *     settlement_status_breakdown: array<string, int>,
     *     total_settled_amount: int,
     *     total_outstanding_exposure: int,
     *     recognized_revenue: null,
     *     net_pnl_per_case: null
     * }
     */
    public function operationalExposureBoard(int $locationId, string $dateFrom, string $dateTo): array
    {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->endOfDay();

        $confirmedBookingsWithoutStayCount = Booking::query()
            ->where('location_id', $locationId)
            ->where('status', BookingStatus::CONFIRMED)
            ->whereBetween('check_in', [$from->toDateString(), $to->toDateString()])
            ->whereDoesntHave('stay')
            ->count();

        $recoveryBase = ServiceRecoveryCase::query()
            ->join('bookings', 'service_recovery_cases.booking_id', '=', 'bookings.id')
            ->where('bookings.location_id', $locationId)
            ->whereBetween('service_recovery_cases.opened_at', [$from, $to]);

        $unresolvedRecoveryCasesCount = (clone $recoveryBase)
            ->whereNull('service_recovery_cases.resolved_at')
            ->count();

        $unresolvedCompensationRefundAmount = (clone $recoveryBase)
            ->whereNull('service_recovery_cases.resolved_at')
            ->sum('service_recovery_cases.refund_amount');

        $unresolvedVoucherAmount = (clone $recoveryBase)
            ->whereNull('service_recovery_cases.resolved_at')
            ->sum('service_recovery_cases.voucher_amount');

        $relocationAbsorbedCostAmount = (clone $recoveryBase)
            ->whereIn('service_recovery_cases.incident_type', [
                IncidentType::INTERNAL_RELOCATION->value,
                IncidentType::EXTERNAL_RELOCATION->value,
            ])
            ->sum('service_recovery_cases.cost_delta_absorbed');

        // Deposit metrics
        $bookingBase = Booking::query()
            ->where('location_id', $locationId)
            ->whereBetween('check_in', [$from->toDateString(), $to->toDateString()]);

        $depositTotal = (int) (clone $bookingBase)->sum('deposit_amount');
        $withDepositCount = (clone $bookingBase)->where('deposit_amount', '>', 0)->count();
        $withoutDepositCount = (clone $bookingBase)->where('deposit_amount', 0)->count();

        // Settlement metrics
        $settlementBreakdown = (clone $recoveryBase)
            ->select('service_recovery_cases.settlement_status', DB::raw('COUNT(service_recovery_cases.id) as count'))
            ->groupBy('service_recovery_cases.settlement_status')
            ->pluck('count', 'settlement_status')
            ->map(static fn (mixed $count): int => (int) $count)
            ->toArray();

        $totalSettledAmount = (int) (clone $recoveryBase)
            ->whereIn('service_recovery_cases.settlement_status', [
                SettlementStatus::SETTLED->value,
                SettlementStatus::WAIVED->value,
            ])
            ->sum('service_recovery_cases.settled_amount');

        // Outstanding exposure: sum of outstanding amounts for unsettled/partially_settled cases
        $outstandingCases = (clone $recoveryBase)
            ->whereIn('service_recovery_cases.settlement_status', [
                SettlementStatus::UNSETTLED->value,
                SettlementStatus::PARTIALLY_SETTLED->value,
            ])
            ->select([
                'service_recovery_cases.refund_amount',
                'service_recovery_cases.voucher_amount',
                'service_recovery_cases.settled_amount',
            ])
            ->get();

        $totalOutstandingExposure = $outstandingCases->sum(function ($case) {
            $total = (int) ($case->refund_amount ?? 0) + (int) ($case->voucher_amount ?? 0);

            return max(0, $total - (int) ($case->settled_amount ?? 0));
        });

        return [
            'location_id' => $locationId,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'confirmed_bookings_without_stay_count' => $confirmedBookingsWithoutStayCount,
            'unresolved_recovery_cases_count' => $unresolvedRecoveryCasesCount,
            'unresolved_compensation_refund_amount' => (int) $unresolvedCompensationRefundAmount,
            'unresolved_voucher_amount' => (int) $unresolvedVoucherAmount,
            'relocation_absorbed_cost_amount' => (int) $relocationAbsorbedCostAmount,
            'deposit_or_advance_amount_total' => $depositTotal,
            'bookings_with_deposit_count' => $withDepositCount,
            'bookings_without_deposit_count' => $withoutDepositCount,
            'settlement_status_breakdown' => $settlementBreakdown,
            'total_settled_amount' => $totalSettledAmount,
            'total_outstanding_exposure' => (int) $totalOutstandingExposure,
            // Deferred: requires accounting/GL integration
            'recognized_revenue' => null,
            'net_pnl_per_case' => null,
        ];
    }

    private function activeAssignmentSubquery(): Builder
    {
        return DB::table('room_assignments')
            ->select([
                'room_assignments.id',
                'room_assignments.stay_id',
                'room_assignments.room_id',
                'room_assignments.assignment_type',
                'room_assignments.assignment_status',
            ])
            ->whereNull('room_assignments.assigned_until');
    }

    private function baseStayBoardQuery(int $locationId): \Illuminate\Database\Eloquent\Builder
    {
        return Stay::query()
            ->join('bookings', 'stays.booking_id', '=', 'bookings.id')
            ->leftJoinSub(
                $this->activeAssignmentSubquery(),
                'active_room_assignment',
                'active_room_assignment.stay_id',
                '=',
                'stays.id'
            )
            ->leftJoin('rooms', 'rooms.id', '=', 'active_room_assignment.room_id')
            ->where('bookings.location_id', $locationId)
            ->select([
                'stays.id as stay_id',
                'stays.booking_id',
                'stays.stay_status',
                'stays.scheduled_check_in_at',
                'stays.scheduled_check_out_at',
                'bookings.guest_name',
                'bookings.guest_email',
                'bookings.check_in as booking_check_in',
                'bookings.check_out as booking_check_out',
                'active_room_assignment.id as room_assignment_id',
                'active_room_assignment.assignment_type',
                'active_room_assignment.assignment_status',
                'rooms.id as room_id',
                'rooms.name as room_name',
                'rooms.room_number',
                'rooms.readiness_status',
            ]);
    }

    /**
     * @param  iterable<object>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapStayRows(iterable $rows): array
    {
        $mapped = [];

        foreach ($rows as $row) {
            $stayStatus = $row->stay_status instanceof StayStatus
                ? $row->stay_status->value
                : (string) $row->stay_status;

            $mapped[] = [
                'stay_id' => (int) $row->stay_id,
                'booking_id' => (int) $row->booking_id,
                'stay_status' => $stayStatus,
                'guest_name' => $row->guest_name,
                'guest_email' => $row->guest_email,
                'scheduled_check_in_at' => $row->scheduled_check_in_at ? Carbon::parse($row->scheduled_check_in_at)->toIso8601String() : null,
                'scheduled_check_out_at' => $row->scheduled_check_out_at ? Carbon::parse($row->scheduled_check_out_at)->toIso8601String() : null,
                'booking_check_in' => $row->booking_check_in ? Carbon::parse((string) $row->booking_check_in)->toDateString() : null,
                'booking_check_out' => $row->booking_check_out ? Carbon::parse((string) $row->booking_check_out)->toDateString() : null,
                'room_assignment_id' => $row->room_assignment_id ? (int) $row->room_assignment_id : null,
                'assignment_type' => $row->assignment_type,
                'assignment_status' => $row->assignment_status,
                'room_id' => $row->room_id ? (int) $row->room_id : null,
                'room_name' => $row->room_name,
                'room_number' => $row->room_number,
                'readiness_status' => $row->readiness_status,
            ];
        }

        return $mapped;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRoomRow(Room $room): array
    {
        return [
            'room_id' => $room->id,
            'room_name' => $room->name,
            'room_number' => $room->room_number,
            'readiness_status' => $room->readiness_status->value,
            'readiness_changed_at' => $room->readiness_changed_at?->toIso8601String(),
            'floor' => null,
            'zone' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRecoveryCaseRow(ServiceRecoveryCase $case): array
    {
        return [
            'case_id' => $case->id,
            'booking_id' => $case->booking_id,
            'stay_id' => $case->stay_id,
            'incident_type' => $case->incident_type->value,
            'case_status' => $case->case_status->value,
            'opened_at' => $case->opened_at?->toIso8601String(),
            'escalated_at' => $case->escalated_at?->toIso8601String(),
            'resolved_at' => $case->resolved_at?->toIso8601String(),
            'refund_amount' => $case->refund_amount,
            'voucher_amount' => $case->voucher_amount,
            'cost_delta_absorbed' => $case->cost_delta_absorbed,
        ];
    }
}
