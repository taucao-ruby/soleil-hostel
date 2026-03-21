<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Service recovery incident type — what triggered the case?
 *
 * Used in service_recovery_cases.incident_type.
 * Values must match the chk_src_incident_type CHECK constraint.
 */
enum IncidentType: string
{
    case LATE_CHECKOUT_BLOCKING_ARRIVAL = 'late_checkout_blocking_arrival';
    case ROOM_UNAVAILABLE_MAINTENANCE = 'room_unavailable_maintenance';
    case OVERBOOKING_NO_ROOM = 'overbooking_no_room';
    case EQUIVALENT_SWAP = 'equivalent_swap';
    case COMPLIMENTARY_UPGRADE = 'complimentary_upgrade';
    case INTERNAL_RELOCATION = 'internal_relocation';
    case EXTERNAL_RELOCATION = 'external_relocation';
}
