<?php

declare(strict_types=1);

namespace App\AiHarness\Enums;

enum ToolClassification: string
{
    case READ_ONLY = 'read_only';
    case APPROVAL_REQUIRED = 'approval_required';
    case BLOCKED = 'blocked';
}
