<?php

declare(strict_types=1);

namespace App\AiHarness\Exceptions;

class ProposalPriceChangedException extends ProposalLifecycleException
{
    public function __construct(
        public readonly int $quotedPriceCents,
        public readonly int $currentPriceCents,
        string $message = 'Giá phòng đã thay đổi kể từ khi đề xuất được tạo.',
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'proposal_price_changed';
    }
}
