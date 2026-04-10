<?php

declare(strict_types=1);

namespace App\AiHarness\Enums;

enum RiskTier: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    /**
     * Risk tiers at HIGH or CRITICAL must never allow model-initiated mutations.
     */
    public function isBlockedForModel(): bool
    {
        return in_array($this, [self::HIGH, self::CRITICAL], true);
    }
}
