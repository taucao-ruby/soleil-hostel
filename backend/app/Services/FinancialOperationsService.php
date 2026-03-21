<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\IncidentType;
use App\Enums\SettlementStatus;
use App\Models\Booking;
use App\Models\ServiceRecoveryCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * FINANCIAL TREATMENT BOUNDARY - SOLEIL HOSTEL OPERATIONAL VISIBILITY
 *
 * This service provides OPERATIONAL VISIBILITY only.
 * It is NOT a general ledger. It is NOT authoritative accounting output.
 *
 * Deposit / advance collected:
 *   - Recorded at booking confirmation when a deposit payment field is present.
 *   - NOT treated as recognized revenue at collection time.
 *   - Revenue recognition model (accrual, night-by-night posting, deferred liability)
 *     is INTENTIONALLY DEFERRED - requires accounting integration beyond this layer.
 *
 * Compensation exposure:
 *   - Derived from service_recovery_cases compensation field(s) found in source.
 *   - Represents operational liability estimate, not audited accounting liability.
 *
 * Absorbed relocation cost:
 *   - Derived from the source-confirmed cost/delta field on relocation cases, if present.
 *   - Represents cost recorded by operator at time of relocation decision.
 *
 * Full revenue recognition, accrual accounting, deferred liability modeling,
 * and general ledger integration are EXPLICITLY OUT OF SCOPE for this layer.
 */
final class FinancialOperationsService
{
    public const DEPOSIT_AMOUNT_FIELD = 'deposit_amount';

    public const ADVANCE_AMOUNT_FIELD = null;

    public const SETTLEMENT_TIMESTAMP_FIELD = 'settled_at';

    /**
     * @return array{
     *     location_id: int,
     *     date_from: string,
     *     date_to: string,
     *     deposit_amount_total: int,
     *     bookings_with_deposit_count: int,
     *     bookings_without_advance_count: int,
     *     blocked_reason: string|null
     * }
     */
    public function depositSummary(int $locationId, string $dateFrom, string $dateTo): array
    {
        $from = Carbon::parse($dateFrom)->toDateString();
        $to = Carbon::parse($dateTo)->toDateString();

        $baseQuery = Booking::query()
            ->where('location_id', $locationId)
            ->whereBetween('check_in', [$from, $to]);

        $depositTotal = (int) (clone $baseQuery)->sum('deposit_amount');
        $withDepositCount = (clone $baseQuery)->where('deposit_amount', '>', 0)->count();
        $withoutDepositCount = (clone $baseQuery)->where('deposit_amount', 0)->count();

        return [
            'location_id' => $locationId,
            'date_from' => $from,
            'date_to' => $to,
            'deposit_amount_total' => $depositTotal,
            'bookings_with_deposit_count' => $withDepositCount,
            'bookings_without_advance_count' => $withoutDepositCount,
            'blocked_reason' => null,
        ];
    }

    /**
     * @return array{
     *     location_id: int,
     *     date_from: string,
     *     date_to: string,
     *     unresolved_refund_amount: int,
     *     resolved_refund_amount: int,
     *     cases_by_incident_type: array<array-key, int>,
     *     voucher_amount_total: int,
     *     relocation_absorbed_cost_total: int
     * }
     */
    public function compensationExposure(int $locationId, string $dateFrom, string $dateTo): array
    {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->endOfDay();

        $baseQuery = ServiceRecoveryCase::query()
            ->join('bookings', 'service_recovery_cases.booking_id', '=', 'bookings.id')
            ->where('bookings.location_id', $locationId)
            ->whereBetween('service_recovery_cases.opened_at', [$from, $to]);

        /** @var array<string, int> $casesByType */
        $casesByType = (clone $baseQuery)
            ->select('service_recovery_cases.incident_type', DB::raw('COUNT(service_recovery_cases.id) as total'))
            ->groupBy('service_recovery_cases.incident_type')
            ->pluck('total', 'incident_type')
            ->map(static fn (mixed $count): int => (int) $count)
            ->toArray();

        return [
            'location_id' => $locationId,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'unresolved_refund_amount' => (int) ((clone $baseQuery)
                ->whereNull('service_recovery_cases.resolved_at')
                ->sum('service_recovery_cases.refund_amount')),
            'resolved_refund_amount' => (int) ((clone $baseQuery)
                ->whereNotNull('service_recovery_cases.resolved_at')
                ->sum('service_recovery_cases.refund_amount')),
            'cases_by_incident_type' => $casesByType,
            'voucher_amount_total' => (int) ((clone $baseQuery)
                ->sum('service_recovery_cases.voucher_amount')),
            'relocation_absorbed_cost_total' => (int) ((clone $baseQuery)
                ->whereIn('service_recovery_cases.incident_type', [
                    IncidentType::INTERNAL_RELOCATION->value,
                    IncidentType::EXTERNAL_RELOCATION->value,
                ])
                ->sum('service_recovery_cases.cost_delta_absorbed')),
        ];
    }

    /**
     * @return array{
     *     location_id: int,
     *     date_from: string,
     *     date_to: string,
     *     relocation_cases_count: int,
     *     absorbed_cost_total: int,
     *     unresolved_cases_count: int,
     *     resolved_cases_count: int,
     *     manual_financial_settlement_case_ids: array<array-key, int>,
     *     settlement_tracking_blocked: bool
     * }
     */
    public function relocationCostSummary(int $locationId, string $dateFrom, string $dateTo): array
    {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->endOfDay();

        $baseQuery = ServiceRecoveryCase::query()
            ->join('bookings', 'service_recovery_cases.booking_id', '=', 'bookings.id')
            ->where('bookings.location_id', $locationId)
            ->whereBetween('service_recovery_cases.opened_at', [$from, $to])
            ->whereIn('service_recovery_cases.incident_type', [
                IncidentType::INTERNAL_RELOCATION->value,
                IncidentType::EXTERNAL_RELOCATION->value,
            ]);

        $manualSettlementCaseIds = (clone $baseQuery)
            ->where('service_recovery_cases.settlement_status', SettlementStatus::UNSETTLED->value)
            ->pluck('service_recovery_cases.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return [
            'location_id' => $locationId,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'relocation_cases_count' => (clone $baseQuery)->count(),
            'absorbed_cost_total' => (int) ((clone $baseQuery)->sum('service_recovery_cases.cost_delta_absorbed')),
            'unresolved_cases_count' => (clone $baseQuery)->whereNull('service_recovery_cases.resolved_at')->count(),
            'resolved_cases_count' => (clone $baseQuery)->whereNotNull('service_recovery_cases.resolved_at')->count(),
            'manual_financial_settlement_case_ids' => $manualSettlementCaseIds,
            'settlement_tracking_blocked' => false,
        ];
    }
}
