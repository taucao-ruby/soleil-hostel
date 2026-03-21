<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Service recovery case status — case lifecycle.
 *
 * Used in service_recovery_cases.case_status.
 * Values must match the chk_src_case_status CHECK constraint.
 */
enum CaseStatus: string
{
    case OPEN = 'open';
    case INVESTIGATING = 'investigating';
    case ACTION_IN_PROGRESS = 'action_in_progress';
    case COMPENSATED = 'compensated';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';

    /**
     * Statuses that are considered closed/inactive for queue management.
     *
     * @return array<self>
     */
    public static function closedStatuses(): array
    {
        return [self::RESOLVED, self::CLOSED];
    }

    /**
     * Check if this case is still open (needs attention).
     */
    public function isOpen(): bool
    {
        return ! in_array($this, self::closedStatuses(), true);
    }
}
