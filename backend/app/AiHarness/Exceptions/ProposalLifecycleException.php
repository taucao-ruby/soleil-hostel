<?php

declare(strict_types=1);

namespace App\AiHarness\Exceptions;

use DomainException;

/**
 * Base type for proposal lifecycle violations surfaced by the
 * confirmation controller as 422 with a stable error code.
 *
 * Concrete subclasses carry an `errorCode` consumed by the controller
 * to populate the `errors.code` field on the JSON response, so the
 * frontend (and audit log) can distinguish between expiry, drift,
 * and state-machine violations without parsing free-form messages.
 */
abstract class ProposalLifecycleException extends DomainException
{
    /**
     * Stable, machine-readable code surfaced on the 422 response.
     */
    abstract public function errorCode(): string;
}
