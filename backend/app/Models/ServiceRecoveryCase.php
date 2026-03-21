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
        'handled_by',
        'opened_at',
        'escalated_at',
        'resolved_at',
        'notes',
        // Settlement lifecycle
        'settlement_status',
        'settled_at',
        'settled_amount',
        'settlement_notes',
    ];

    protected $casts = [
        'incident_type' => IncidentType::class,
        'severity' => IncidentSeverity::class,
        'case_status' => CaseStatus::class,
        'compensation_type' => CompensationType::class,
        'refund_amount' => 'integer',
        'voucher_amount' => 'integer',
        'cost_delta_absorbed' => 'integer',
        'opened_at' => 'datetime',
        'escalated_at' => 'datetime',
        'resolved_at' => 'datetime',
        'settlement_status' => SettlementStatus::class,
        'settled_at' => 'datetime',
        'settled_amount' => 'integer',
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
    public function scopeBySeverity(Builder $query, IncidentSeverity $severity): Builder
    {
        return $query->where('severity', $severity->value);
    }

    /**
     * Scope: only external relocation incidents.
     */
    public function scopeExternalRelocation(Builder $query): Builder
    {
        return $query->where('incident_type', IncidentType::EXTERNAL_RELOCATION->value);
    }

    // ===== SETTLEMENT PREDICATES =====

    /**
     * Check if this case has not been financially settled.
     */
    public function isUnsettled(): bool
    {
        return $this->settlement_status === SettlementStatus::UNSETTLED;
    }

    /**
     * Check if this case has been financially settled (settled or waived).
     */
    public function isSettled(): bool
    {
        return in_array($this->settlement_status, [
            SettlementStatus::SETTLED,
            SettlementStatus::WAIVED,
        ], true);
    }

    /**
     * Calculate outstanding compensation amount (total owed minus settled).
     * All amounts in cents.
     */
    public function outstandingAmount(): int
    {
        $totalCompensation = (int) ($this->refund_amount ?? 0) + (int) ($this->voucher_amount ?? 0);

        return max(0, $totalCompensation - (int) ($this->settled_amount ?? 0));
    }
}
