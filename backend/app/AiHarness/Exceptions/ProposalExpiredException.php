<?php

declare(strict_types=1);

namespace App\AiHarness\Exceptions;

class ProposalExpiredException extends ProposalLifecycleException
{
    public function __construct(string $message = 'Đề xuất đã hết hạn.')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'proposal_expired';
    }
}
