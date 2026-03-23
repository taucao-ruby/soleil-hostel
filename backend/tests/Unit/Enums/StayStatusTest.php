<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\StayStatus;
use PHPUnit\Framework\TestCase;

class StayStatusTest extends TestCase
{
    public function test_in_house_statuses_include_only_live_occupancy_states(): void
    {
        $this->assertSame(
            [StayStatus::IN_HOUSE, StayStatus::LATE_CHECKOUT],
            StayStatus::inHouseStatuses()
        );
    }

    public function test_terminal_statuses_match_completed_or_relocated_states(): void
    {
        $this->assertSame(
            [
                StayStatus::CHECKED_OUT,
                StayStatus::NO_SHOW,
                StayStatus::RELOCATED_INTERNAL,
                StayStatus::RELOCATED_EXTERNAL,
            ],
            StayStatus::terminalStatuses()
        );
    }

    public function test_can_transition_to_allows_only_pm_approved_stay_state_machine(): void
    {
        $this->assertTrue(StayStatus::EXPECTED->canTransitionTo(StayStatus::IN_HOUSE));
        $this->assertTrue(StayStatus::EXPECTED->canTransitionTo(StayStatus::NO_SHOW));
        $this->assertTrue(StayStatus::IN_HOUSE->canTransitionTo(StayStatus::LATE_CHECKOUT));
        $this->assertTrue(StayStatus::IN_HOUSE->canTransitionTo(StayStatus::CHECKED_OUT));
        $this->assertTrue(StayStatus::IN_HOUSE->canTransitionTo(StayStatus::RELOCATED_INTERNAL));
        $this->assertTrue(StayStatus::LATE_CHECKOUT->canTransitionTo(StayStatus::CHECKED_OUT));
        $this->assertTrue(StayStatus::LATE_CHECKOUT->canTransitionTo(StayStatus::RELOCATED_EXTERNAL));
    }

    public function test_can_transition_to_rejects_illegal_stay_transitions(): void
    {
        $this->assertFalse(StayStatus::EXPECTED->canTransitionTo(StayStatus::CHECKED_OUT));
        $this->assertFalse(StayStatus::EXPECTED->canTransitionTo(StayStatus::LATE_CHECKOUT));
        $this->assertFalse(StayStatus::IN_HOUSE->canTransitionTo(StayStatus::NO_SHOW));
        $this->assertFalse(StayStatus::LATE_CHECKOUT->canTransitionTo(StayStatus::IN_HOUSE));
        $this->assertFalse(StayStatus::CHECKED_OUT->canTransitionTo(StayStatus::IN_HOUSE));
        $this->assertFalse(StayStatus::RELOCATED_INTERNAL->canTransitionTo(StayStatus::CHECKED_OUT));
    }
}
