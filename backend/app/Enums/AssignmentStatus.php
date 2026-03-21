<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Room assignment status — lifecycle of a single assignment row.
 *
 * Used in room_assignments.assignment_status.
 * Values must match the chk_room_assignments_assignment_status CHECK constraint.
 *
 * active    = guest is currently in this room (assigned_until IS NULL)
 * closed    = assignment ended normally (guest checked out or moved)
 * cancelled = assignment was voided before it was used
 */
enum AssignmentStatus: string
{
    case ACTIVE = 'active';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';
}
