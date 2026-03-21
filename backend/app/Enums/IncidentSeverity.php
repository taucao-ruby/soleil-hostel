<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Service recovery incident severity.
 *
 * Used in service_recovery_cases.severity.
 * Values must match the chk_src_severity CHECK constraint.
 */
enum IncidentSeverity: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';
}
