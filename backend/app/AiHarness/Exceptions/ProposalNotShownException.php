<?php

declare(strict_types=1);

namespace App\AiHarness\Exceptions;

class ProposalNotShownException extends ProposalLifecycleException
{
    public function __construct(string $message = 'Đề xuất phải được hiển thị trước khi xác nhận.')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'proposal_not_shown';
    }
}
