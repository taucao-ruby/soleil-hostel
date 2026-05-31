<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\RefundStatus;
use PHPUnit\Framework\TestCase;

/**
 * SH-05 / F-73: RefundStatus is the closed internal projection. tryFromStripe()
 * is the single normalization point that keeps raw Stripe statuses out of the
 * persisted contract.
 */
class RefundStatusTest extends TestCase
{
    public function test_values_is_the_closed_three_state_set(): void
    {
        $this->assertSame(['pending', 'succeeded', 'failed'], RefundStatus::values());
    }

    /**
     * @dataProvider stripeStatusProvider
     */
    public function test_try_from_stripe_normalizes_known_statuses(string $stripe, RefundStatus $expected): void
    {
        $this->assertSame($expected, RefundStatus::tryFromStripe($stripe));
    }

    /**
     * @return array<string, array{0: string, 1: RefundStatus}>
     */
    public static function stripeStatusProvider(): array
    {
        return [
            'pending stays pending' => ['pending', RefundStatus::PENDING],
            'requires_action -> pending' => ['requires_action', RefundStatus::PENDING],
            'succeeded stays succeeded' => ['succeeded', RefundStatus::SUCCEEDED],
            'failed stays failed' => ['failed', RefundStatus::FAILED],
            'canceled -> failed' => ['canceled', RefundStatus::FAILED],
        ];
    }

    public function test_try_from_stripe_returns_null_for_unknown_status(): void
    {
        $this->assertNull(RefundStatus::tryFromStripe('unknown'));
        $this->assertNull(RefundStatus::tryFromStripe('weird_new_state'));
        $this->assertNull(RefundStatus::tryFromStripe(null));
    }

    public function test_every_normalized_status_is_within_the_closed_set(): void
    {
        foreach (['pending', 'requires_action', 'succeeded', 'failed', 'canceled'] as $stripe) {
            $normalized = RefundStatus::tryFromStripe($stripe);

            $this->assertNotNull($normalized, "Stripe status '{$stripe}' must normalize.");
            $this->assertContains($normalized->value, RefundStatus::values());
        }
    }
}
