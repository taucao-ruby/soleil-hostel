<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RoomAssignment Model — actual room allocation per stay window.
 *
 * Each row records one room assignment interval:
 * - assigned_from: when the guest started using this room
 * - assigned_until: when the assignment ended (NULL = currently active)
 *
 * The partial unique index udx_room_assignments_one_active_per_stay
 * (PostgreSQL only) enforces at most one active assignment per stay.
 * When closing an assignment, set assigned_until before creating a new one.
 */
class RoomAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'stay_id',
        'room_id',
        'assignment_type',
        'assignment_status',
        'assigned_from',
        'assigned_until',
        'assigned_by',
        'reason_code',
        'notes',
    ];

    protected $casts = [
        'assignment_type' => AssignmentType::class,
        'assignment_status' => AssignmentStatus::class,
        'assigned_from' => 'datetime',
        'assigned_until' => 'datetime',
    ];

    // ===== RELATIONSHIPS =====

    /**
     * The stay this assignment belongs to.
     */
    public function stay(): BelongsTo
    {
        return $this->belongsTo(Stay::class);
    }

    /**
     * The booking this assignment is for.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * The room assigned.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Staff member who made the assignment.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // ===== SCOPES =====

    /**
     * Scope: only currently active assignments (assigned_until IS NULL).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('assigned_until');
    }

    /**
     * Scope: filter assignments for a specific stay.
     */
    public function scopeForStay(Builder $query, int $stayId): Builder
    {
        return $query->where('stay_id', $stayId);
    }
}
