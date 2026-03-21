<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Room assignment type — why was this assignment made?
 *
 * Used in room_assignments.assignment_type.
 * Values must match the chk_room_assignments_assignment_type CHECK constraint.
 */
enum AssignmentType: string
{
    case ORIGINAL = 'original';
    case EQUIVALENT_SWAP = 'equivalent_swap';
    case COMPLIMENTARY_UPGRADE = 'complimentary_upgrade';
    case MAINTENANCE_MOVE = 'maintenance_move';
    case OVERFLOW_RELOCATION = 'overflow_relocation';
}
