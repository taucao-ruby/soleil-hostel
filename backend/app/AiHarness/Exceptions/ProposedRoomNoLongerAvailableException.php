<?php

declare(strict_types=1);

namespace App\AiHarness\Exceptions;

class ProposedRoomNoLongerAvailableException extends ProposalLifecycleException
{
    public function __construct(string $message = 'Phòng đề xuất không còn khả dụng.')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'proposed_room_no_longer_available';
    }
}
