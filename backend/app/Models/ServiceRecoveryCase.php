<?php

namespace App\Models;

use App\Enums\CaseStatus;
use App\Enums\CompensationType;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentType;
use App\Enums\SettlementStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ServiceRecoveryCase Model — incident and compensation audit trail.
 *
 * Tracks service failures (room unavailability, late checkout conflicts,
 * overbooking events) and the compensatory actions taken.
 *
 * stay_id is nullable because incidents may be recorded before a stay is
 * created, or a case may be linked directly to a booking.
 *
 * Monetary columns (refund_amount, voucher_amount, cost_delta_absorbed)
 * are stored in cents (BIGINT), consistent with bookings.amount.
 *
 * settlement_status is operational financial tracking only.
 * It is not authoritative accounting / general-ledger state.
 */
class ServiceRecoveryCase extends Model
{
    use HasFactory;

    protected $table = 'service_recovery_cases';

    protected $fillable = [
        'booking_id',
        'stay_id',
        'incident_type',
        'severity',
        'case_status',
        'action_taken',
        'external_hotel_name',
        'external_booking_reference',
        'compensation_type',
        'refund_amount',
        'voucher_amount',
        'cost_delta_absorbed',
        'settlement_status',
        'settled_amount',
        'settled_at',
        'settlement_notes',
        'handled_by',
        'opened_at',
        'resolved_at',
        'notes',
    ];

    protected $casts = [
        'incident_type' => IncidentType::class,
        'severity' => IncidentSeverity::class,
        'case_status' => CaseStatus::class,
        'compensation_type' => CompensationType::class,
        'refund_amount' => 'integer',
        'voucher_amount' => 'integer',
        'cost_delta_absorbed' => 'integer',
        'settlement_status' => SettlementStatus::class,
        'settled_amount' => 'integer',
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
        'settled_at' => 'datetime',
    ];

    // ===== RELATIONSHIPS =====

    /**
     * The booking this case relates to.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * The stay this case relates to (nullable).
     */
    public function stay(): BelongsTo
    {
        return $this->belongsTo(Stay::class);
    }

    /**
     * Staff member handling this case.
     */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    // ===== SCOPES =====

    /**
     * Scope: cases that are still open (not resolved or closed).
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('case_status', [
            CaseStatus::RESOLVED->value,
            CaseStatus::CLOSED->value,
        ]);
    }

    /**
     * Scope: filter by severity level.
     */
    public function scopeBySeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope: only external relocation incidents.
     */
    public function scopeExternalRelocation(Builder $query): Builder
    {
        return $query->where('incident_type', IncidentType::EXTERNAL_RELOCATION->value);
    }

    /**
     * Scope: cases that still carry unsettled exposure.
     */
    public function scopeUnsettled(Builder $query): Builder
    {
        return $query->whereIn('settlement_status', array_map(
            static fn (SettlementStatus $status): string => $status->value,
            SettlementStatus::openStatuses()
        ));
    }

    /**
     * Scope: cases fully settled with the guest.
     */
    public function scopeSettled(Builder $query): Builder
    {
        return $query->where('settlement_status', SettlementStatus::SETTLED->value);
    }

    /**
     * Net open exposure for this case in cents.
     */
    public function totalExposure(): int
    {
        return (int) (
            ($this->refund_amount ?? 0)
            + ($this->voucher_amount ?? 0)
            + ($this->cost_delta_absorbed ?? 0)
            - ($this->settled_amount ?? 0)
        );
    }
}
