<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\StayStatus;
use App\Models\Stay;
use RuntimeException;

final class StayTransitionException extends RuntimeException
{
    public function __construct(
        private readonly Stay $stay,
        private readonly StayStatus $from,
        private readonly StayStatus $to,
    ) {
        parent::__construct(sprintf(
            "Stay #%d cannot transition from '%s' to '%s'.",
            $stay->id,
            $from->value,
            $to->value,
        ));
    }

    public function getStay(): Stay
    {
        return $this->stay;
    }

    public function getFrom(): StayStatus
    {
        return $this->from;
    }

    public function getTo(): StayStatus
    {
        return $this->to;
    }
}
