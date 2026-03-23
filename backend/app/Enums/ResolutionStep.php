<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ranked blocked-arrival escalation order.
 */
enum ResolutionStep: string
{
    case EQUIVALENT_SAME_LOCATION = 'equivalent_same_location';
    case UPGRADE_SAME_LOCATION = 'upgrade_same_location';
    case EQUIVALENT_CROSS_LOCATION = 'equivalent_cross_location';
    case UPGRADE_CROSS_LOCATION = 'upgrade_cross_location';
    case EXTERNAL_ESCALATION = 'external_escalation';

    public function assignmentType(): AssignmentType
    {
        return match ($this) {
            self::EQUIVALENT_SAME_LOCATION,
            self::EQUIVALENT_CROSS_LOCATION => AssignmentType::EQUIVALENT_SWAP,
            self::UPGRADE_SAME_LOCATION,
            self::UPGRADE_CROSS_LOCATION => AssignmentType::COMPLIMENTARY_UPGRADE,
            self::EXTERNAL_ESCALATION => AssignmentType::OVERFLOW_RELOCATION,
        };
    }

    public function requiresOperatorApproval(): bool
    {
        return in_array($this, [
            self::EQUIVALENT_CROSS_LOCATION,
            self::UPGRADE_CROSS_LOCATION,
            self::EXTERNAL_ESCALATION,
        ], true);
    }
}
