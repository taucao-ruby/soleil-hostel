<?php

declare(strict_types=1);

namespace App\AiHarness\Enums;

enum TaskType: string
{
    case FAQ_LOOKUP = 'faq_lookup';
    case ROOM_DISCOVERY = 'room_discovery';
    case BOOKING_STATUS = 'booking_status';
    case ADMIN_DRAFT = 'admin_draft';
}
