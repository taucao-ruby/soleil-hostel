<?php

declare(strict_types=1);

namespace App\AiHarness\Enums;

enum ResponseClass: string
{
    case ANSWER = 'answer';
    case ABSTAIN = 'abstain';
    case REFUSAL = 'refusal';
    case ERROR = 'error';
    case FALLBACK = 'fallback';
}
