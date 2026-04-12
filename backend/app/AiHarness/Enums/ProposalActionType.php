<?php

declare(strict_types=1);

namespace App\AiHarness\Enums;

/**
 * Allowed action types for BookingActionProposal.
 *
 * These are proposal verbs — the model SUGGESTS, never EXECUTES.
 * There is no 'execute_booking' or 'execute_cancellation' — those do not exist.
 */
enum ProposalActionType: string
{
    case SUGGEST_BOOKING = 'suggest_booking';
    case SUGGEST_CANCELLATION = 'suggest_cancellation';
}
