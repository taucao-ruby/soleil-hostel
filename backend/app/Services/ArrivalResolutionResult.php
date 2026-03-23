<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AssignmentType;
use App\Enums\BlockerType;
use App\Enums\ResolutionStep;
use App\Models\Room;

final class ArrivalResolutionResult
{
    public function __construct(
        public readonly BlockerType $blockerType,
        public readonly ResolutionStep $step,
        public readonly ?Room $recommendedRoom,
        public readonly AssignmentType $assignmentType,
        public readonly bool $requiresOperatorApproval,
        public readonly ?string $externalEscalationNote = null,
    ) {}
}
