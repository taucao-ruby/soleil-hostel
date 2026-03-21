<?php

namespace Tests\Feature\Operations;

use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Enums\BookingStatus;
use App\Enums\CaseStatus;
use App\Enums\CompensationType;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentType;
use App\Enums\RoomReadinessStatus;
use App\Enums\RoomTypeCode;
use App\Enums\SettlementStatus;
use App\Enums\StayStatus;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\ServiceRecoveryCase;
use App\Models\Stay;
use App\Models\User;
use App\Services\CheckInBlockageResolver;
use App\Services\FinancialOperationsService;
use App\Services\OperationalDashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalPassThreeTest extends TestCase
{
    use RefreshDatabase;

    private CheckInBlockageResolver $blockageResolver;

    private FinancialOperationsService $financialService;

    private OperationalDashboardService $dashboardService;

    protected function setUp(): void
    {
        parent::setUp();

        Location::query()->delete();

        $this->blockageResolver = app(CheckInBlockageResolver::class);
        $this->financialService = app(FinancialOperationsService::class);
        $this->dashboardService = app(OperationalDashboardService::class);
    }

    // ===== GROUP A: Room Classification =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_rooms_with_same_type_and_tier_are_equivalent(): void
    {
        $location = Location::factory()->create();
        $roomA = Room::factory()->create([
            'location_id' => $location->id,
            'room_type_code' => RoomTypeCode::PRIVATE_DOUBLE,
            'room_tier' => 2,
        ]);
        $roomB = Room::factory()->create([
            'location_id' => $location->id,
            'room_type_code' => RoomTypeCode::PRIVATE_DOUBLE,
            'room_tier' => 2,
        ]);

        $this->assertTrue($roomA->isEquivalentTo($roomB));
        $this->assertTrue($roomB->isEquivalentTo($roomA));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_room_with_higher_tier_is_upgrade_over_lower_tier(): void
    {
        $location = Location::factory()->create();
        $standard = Room::factory()->create([
            'location_id' => $location->id,
            'room_type_code' => RoomTypeCode::PRIVATE_SINGLE,
            'room_tier' => 2,
        ]);
        $suite = Room::factory()->create([
            'location_id' => $location->id,
            'room_type_code' => RoomTypeCode::PRIVATE_SUITE,
            'room_tier' => 3,
        ]);

        $this->assertTrue($suite->isUpgradeOver($standard));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_room_with_lower_tier_is_NOT_accepted_as_upgrade(): void
    {
        $location = Location::factory()->create();
        $dorm = Room::factory()->create([
            'location_id' => $location->id,
            'room_type_code' => RoomTypeCode::DORMITORY,
            'room_tier' => 1,
        ]);
        $standard = Room::factory()->create([
            'location_id' => $location->id,
            'room_type_code' => RoomTypeCode::PRIVATE_SINGLE,
            'room_tier' => 2,
        ]);

        $this->assertFalse($dorm->isUpgradeOver($standard));
        $this->assertFalse($standard->isUpgradeOver($standard));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_cross_location_equivalence_ignores_location_id(): void
    {
        $locationA = Location::factory()->create();
        $locationB = Location::factory()->create();
        $roomA = Room::factory()->create([
            'location_id' => $locationA->id,
            'room_type_code' => RoomTypeCode::PRIVATE_TWIN,
            'room_tier' => 2,
        ]);
        $roomB = Room::factory()->create([
            'location_id' => $locationB->id,
            'room_type_code' => RoomTypeCode::PRIVATE_TWIN,
            'room_tier' => 2,
        ]);

        $this->assertTrue($roomA->isCrossLocationEquivalentTo($roomB));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_room_factory_populates_type_and_tier(): void
    {
        $room = Room::factory()->create();

        $this->assertNotNull($room->room_type_code);
        $this->assertInstanceOf(RoomTypeCode::class, $room->room_type_code);
        $this->assertNotNull($room->room_tier);
        $this->assertContains($room->room_tier, [1, 2, 3]);
        $this->assertSame($room->room_type_code->defaultTier(), $room->room_tier);
    }

    // ===== GROUP B: Blockage Resolver — Extended Escalation =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_equivalent_swap_creates_room_assignment_and_recovery_case(): void
    {
        $location = Location::factory()->create();
        $originalRoom = $this->makeClassifiedRoom($location, RoomReadinessStatus::READY, RoomTypeCode::PRIVATE_DOUBLE, 2);
        $swapRoom = $this->makeClassifiedRoom($location, RoomReadinessStatus::READY, RoomTypeCode::PRIVATE_DOUBLE, 2);

        $stay = $this->makeStayWithAssignment($originalRoom, StayStatus::EXPECTED);
        $this->setRoomReadiness($originalRoom, RoomReadinessStatus::DIRTY);

        $result = $this->blockageResolver->resolve($stay->fresh('currentRoomAssignment.room'));

        $this->assertSame('equivalent_swap', $result['resolution']);
        $this->assertSame($swapRoom->id, $result['new_room_id']);

        // Verify RoomAssignment was created
        $newAssignment = RoomAssignment::where('stay_id', $stay->id)
            ->where('room_id', $swapRoom->id)
            ->first();
        $this->assertNotNull($newAssignment);
        $this->assertEquals(AssignmentType::EQUIVALENT_SWAP, $newAssignment->assignment_type);

        // Verify ServiceRecoveryCase was created
        $case = ServiceRecoveryCase::findOrFail($result['case_id']);
        $this->assertEquals(IncidentType::EQUIVALENT_SWAP, $case->incident_type);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_equivalent_swap_only_considers_ready_rooms(): void
    {
        $location = Location::factory()->create();
        $originalRoom = $this->makeClassifiedRoom($location, RoomReadinessStatus::READY, RoomTypeCode::PRIVATE_DOUBLE, 2);
        // Equivalent room exists but is dirty — should not be picked
        $this->makeClassifiedRoom($location, RoomReadinessStatus::DIRTY, RoomTypeCode::PRIVATE_DOUBLE, 2);

        $stay = $this->makeStayWithAssignment($originalRoom, StayStatus::EXPECTED);
        $this->setRoomReadiness($originalRoom, RoomReadinessStatus::OUT_OF_SERVICE);

        $result = $this->blockageResolver->resolve($stay->fresh('currentRoomAssignment.room'));

        // Should fall through to external_relocation_escalated since no ready room available
        $this->assertNotSame('equivalent_swap', $result['resolution']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_complimentary_upgrade_selects_minimum_higher_tier(): void
    {
        $location = Location::factory()->create();
        $originalRoom = $this->makeClassifiedRoom($location, RoomReadinessStatus::READY, RoomTypeCode::DORMITORY, 1);
        // Tier 2 and tier 3 available — should select tier 2 (minimum upgrade)
        $tier2Room = $this->makeClassifiedRoom($location, RoomReadinessStatus::READY, RoomTypeCode::PRIVATE_SINGLE, 2);
        $this->makeClassifiedRoom($location, RoomReadinessStatus::READY, RoomTypeCode::PRIVATE_SUITE, 3);

        $stay = $this->makeStayWithAssignment($originalRoom, StayStatus::EXPECTED);
        $this->setRoomReadiness($originalRoom, RoomReadinessStatus::OUT_OF_SERVICE);

        $result = $this->blockageResolver->resolve($stay->fresh('currentRoomAssignment.room'));

        $this->assertSame('complimentary_upgrade', $result['resolution']);
        $this->assertSame($tier2Room->id, $result['new_room_id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_upgrade_never_selects_lower_or_equal_tier(): void
    {
        $location = Location::factory()->create();
        $originalRoom = $this->makeClassifiedRoom($location, RoomReadinessStatus::READY, RoomTypeCode::PRIVATE_SINGLE, 2);
        // Only lower tier available
        $this->makeClassifiedRoom($location, RoomReadinessStatus::READY, RoomTypeCode::DORMITORY, 1);

        $stay = $this->makeStayWithAssignment($originalRoom, StayStatus::EXPECTED);
        $this->setRoomReadiness($originalRoom, RoomReadinessStatus::OUT_OF_SERVICE);

        $result = $this->blockageResolver->resolve($stay->fresh('currentRoomAssignment.room'));

        // Should NOT get complimentary_upgrade
        $this->assertNotSame('complimentary_upgrade', $result['resolution']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_internal_relocation_recommended_when_no_same_location_room(): void
    {
        $location = Location::factory()->create();
        $otherLocation = Location::factory()->create();
        $originalRoom = $this->makeClassifiedRoom($location, RoomReadinessStatus::READY, RoomTypeCode::PRIVATE_DOUBLE, 2);
        $destRoom = $this->makeClassifiedRoom($otherLocation, RoomReadinessStatus::READY, RoomTypeCode::PRIVATE_DOUBLE, 2);

        $stay = $this->makeStayWithAssignment($originalRoom, StayStatus::EXPECTED);
        $this->setRoomReadiness($originalRoom, RoomReadinessStatus::OUT_OF_SERVICE);

        $result = $this->blockageResolver->resolve($stay->fresh('currentRoomAssignment.room'));

        $this->assertSame('internal_relocation_recommended', $result['resolution']);
        $this->assertTrue($result['requires_manual_action']);
        $this->assertSame($otherLocation->id, $result['destination_location_id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_internal_relocation_does_not_create_room_assignment(): void
    {
        $location = Location::factory()->create();
        $otherLocation = Location::factory()->create();
        $originalRoom = $this->makeClassifiedRoom($location, RoomReadinessStatus::READY, RoomTypeCode::PRIVATE_DOUBLE, 2);
        $this->makeClassifiedRoom($otherLocation, RoomReadinessStatus::READY, RoomTypeCode::PRIVATE_DOUBLE, 2);

        $stay = $this->makeStayWithAssignment($originalRoom, StayStatus::EXPECTED);
        $this->setRoomReadiness($originalRoom, RoomReadinessStatus::OUT_OF_SERVICE);

        $assignmentCountBefore = RoomAssignment::where('stay_id', $stay->id)->count();

        $this->blockageResolver->resolve($stay->fresh('currentRoomAssignment.room'));

        // Only the original assignment should exist
        $this->assertSame($assignmentCountBefore, RoomAssignment::where('stay_id', $stay->id)->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_internal_relocation_prefers_equivalent_tier_over_upgrade(): void
    {
        $location = Location::factory()->create();
        $otherLocation = Location::factory()->create();
        $originalRoom = $this->makeClassifiedRoom($location, RoomReadinessStatus::READY, RoomTypeCode::PRIVATE_DOUBLE, 2);

        // Other location: tier 2 (equivalent) and tier 3 (upgrade)
        $equivRoom = $this->makeClassifiedRoom($otherLocation, RoomReadinessStatus::READY, RoomTypeCode::PRIVATE_DOUBLE, 2);
        $this->makeClassifiedRoom($otherLocation, RoomReadinessStatus::READY, RoomTypeCode::PRIVATE_SUITE, 3);

        $stay = $this->makeStayWithAssignment($originalRoom, StayStatus::EXPECTED);
        $this->setRoomReadiness($originalRoom, RoomReadinessStatus::OUT_OF_SERVICE);

        $result = $this->blockageResolver->resolve($stay->fresh('currentRoomAssignment.room'));

        $this->assertSame('internal_relocation_recommended', $result['resolution']);
        $this->assertSame($equivRoom->id, $result['destination_room_id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_external_escalation_fires_when_no_internal_candidate_exists(): void
    {
        $location = Location::factory()->create();
        $originalRoom = $this->makeClassifiedRoom($location, RoomReadinessStatus::READY, RoomTypeCode::PRIVATE_SUITE, 3);

        $stay = $this->makeStayWithAssignment($originalRoom, StayStatus::EXPECTED);
        $this->setRoomReadiness($originalRoom, RoomReadinessStatus::OUT_OF_SERVICE);

        $result = $this->blockageResolver->resolve($stay->fresh('currentRoomAssignment.room'));

        $this->assertSame('external_relocation_escalated', $result['resolution']);
        $this->assertTrue($result['requires_manual_action']);
        $this->assertNotNull($result['case_id']);

        $case = ServiceRecoveryCase::findOrFail($result['case_id']);
        $this->assertEquals(IncidentType::EXTERNAL_RELOCATION, $case->incident_type);
        $this->assertNotNull($case->escalated_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_attempted_steps_records_each_failed_escalation(): void
    {
        $location = Location::factory()->create();
        $originalRoom = $this->makeClassifiedRoom($location, RoomReadinessStatus::READY, RoomTypeCode::PRIVATE_SUITE, 3);

        $stay = $this->makeStayWithAssignment($originalRoom, StayStatus::EXPECTED);
        $this->setRoomReadiness($originalRoom, RoomReadinessStatus::OUT_OF_SERVICE);

        $result = $this->blockageResolver->resolve($stay->fresh('currentRoomAssignment.room'));

        $steps = collect($result['attempted_steps'])->pluck('step')->all();

        $this->assertContains('detect', $steps);
        $this->assertContains('equivalent_swap', $steps);
        $this->assertContains('complimentary_upgrade', $steps);
        $this->assertContains('internal_relocation', $steps);
        $this->assertContains('external_relocation_escalation', $steps);

        // Verify failed steps have 'no_candidates' outcome
        $failedSteps = collect($result['attempted_steps'])
            ->filter(fn ($s) => ($s['outcome'] ?? '') === 'no_candidates')
            ->pluck('step')
            ->all();

        $this->assertContains('equivalent_swap', $failedSteps);
        $this->assertContains('complimentary_upgrade', $failedSteps);
        $this->assertContains('internal_relocation', $failedSteps);
    }

    // ===== GROUP C: Financial Settlement / Deposit =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_new_recovery_case_defaults_to_unsettled(): void
    {
        $case = ServiceRecoveryCase::factory()->create();

        $this->assertEquals(SettlementStatus::UNSETTLED, $case->settlement_status);
        $this->assertTrue($case->isUnsettled());
        $this->assertFalse($case->isSettled());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_settlement_status_transition_to_settled(): void
    {
        $case = ServiceRecoveryCase::factory()->create([
            'refund_amount' => 5000,
            'settlement_status' => SettlementStatus::UNSETTLED,
        ]);

        $case->update([
            'settlement_status' => SettlementStatus::SETTLED,
            'settled_at' => now(),
            'settled_amount' => 5000,
        ]);

        $case->refresh();

        $this->assertEquals(SettlementStatus::SETTLED, $case->settlement_status);
        $this->assertTrue($case->isSettled());
        $this->assertFalse($case->isUnsettled());
        $this->assertNotNull($case->settled_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_outstanding_amount_is_zero_when_fully_settled(): void
    {
        $case = ServiceRecoveryCase::factory()->create([
            'refund_amount' => 5000,
            'voucher_amount' => 2000,
            'settlement_status' => SettlementStatus::SETTLED,
            'settled_amount' => 7000,
        ]);

        $this->assertSame(0, $case->outstandingAmount());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_outstanding_amount_is_correct_when_partially_settled(): void
    {
        $case = ServiceRecoveryCase::factory()->create([
            'refund_amount' => 5000,
            'voucher_amount' => 2000,
            'settlement_status' => SettlementStatus::PARTIALLY_SETTLED,
            'settled_amount' => 3000,
        ]);

        // Outstanding = (5000 + 2000) - 3000 = 4000
        $this->assertSame(4000, $case->outstandingAmount());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_waived_case_is_treated_as_settled(): void
    {
        $case = ServiceRecoveryCase::factory()->create([
            'refund_amount' => 5000,
            'settlement_status' => SettlementStatus::WAIVED,
            'settled_at' => now(),
            'settled_amount' => 0,
            'settlement_notes' => 'Guest declined compensation.',
        ]);

        $this->assertTrue($case->isSettled());
        $this->assertFalse($case->isUnsettled());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_deposit_summary_sums_deposit_amounts_by_date_range(): void
    {
        $location = Location::factory()->create();
        $room = Room::factory()->create(['location_id' => $location->id]);

        Booking::factory()->confirmed()->forRoom($room)->create([
            'check_in' => '2026-08-05',
            'check_out' => '2026-08-07',
            'deposit_amount' => 4000,
        ]);

        Booking::factory()->confirmed()->forRoom($room)->create([
            'check_in' => '2026-08-10',
            'check_out' => '2026-08-12',
            'deposit_amount' => 6000,
        ]);

        $result = $this->financialService->depositSummary($location->id, '2026-08-01', '2026-08-31');

        $this->assertSame(10000, $result['deposit_amount_total']);
        $this->assertSame(2, $result['bookings_with_deposit_count']);
        $this->assertNull($result['blocked_reason']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_deposit_summary_excludes_bookings_outside_date_range(): void
    {
        $location = Location::factory()->create();
        $room = Room::factory()->create(['location_id' => $location->id]);

        Booking::factory()->confirmed()->forRoom($room)->create([
            'check_in' => '2026-08-15',
            'check_out' => '2026-08-17',
            'deposit_amount' => 4000,
        ]);

        // Outside range
        Booking::factory()->confirmed()->forRoom($room)->create([
            'check_in' => '2026-09-15',
            'check_out' => '2026-09-17',
            'deposit_amount' => 9999,
        ]);

        $result = $this->financialService->depositSummary($location->id, '2026-08-01', '2026-08-31');

        $this->assertSame(4000, $result['deposit_amount_total']);
        $this->assertSame(1, $result['bookings_with_deposit_count']);
    }

    // ===== GROUP D: Operational Exposure Board =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_exposure_board_includes_deposit_total(): void
    {
        $location = Location::factory()->create();
        $room = Room::factory()->create(['location_id' => $location->id]);

        Booking::factory()->confirmed()->forRoom($room)->create([
            'check_in' => '2026-08-05',
            'check_out' => '2026-08-07',
            'deposit_amount' => 3000,
        ]);

        Booking::factory()->confirmed()->forRoom($room)->create([
            'check_in' => '2026-08-10',
            'check_out' => '2026-08-12',
            'deposit_amount' => 0,
        ]);

        $board = $this->dashboardService->operationalExposureBoard(
            $location->id,
            '2026-08-01',
            '2026-08-31'
        );

        $this->assertSame(3000, $board['deposit_or_advance_amount_total']);
        $this->assertSame(1, $board['bookings_with_deposit_count']);
        $this->assertSame(1, $board['bookings_without_deposit_count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_exposure_board_settlement_breakdown_sums_correctly(): void
    {
        $location = Location::factory()->create();
        $room = $this->makeClassifiedRoom($location, RoomReadinessStatus::READY, RoomTypeCode::PRIVATE_SINGLE, 2);
        $stay = $this->makeStayWithAssignment($room, StayStatus::EXPECTED, '2026-08-10', '2026-08-12');

        ServiceRecoveryCase::create([
            'booking_id' => $stay->booking_id,
            'stay_id' => $stay->id,
            'incident_type' => IncidentType::ROOM_UNAVAILABLE_MAINTENANCE,
            'case_status' => CaseStatus::OPEN,
            'compensation_type' => CompensationType::REFUND_PARTIAL,
            'refund_amount' => 5000,
            'opened_at' => Carbon::parse('2026-08-10 10:00:00'),
            'settlement_status' => SettlementStatus::UNSETTLED,
        ]);

        ServiceRecoveryCase::create([
            'booking_id' => $stay->booking_id,
            'stay_id' => $stay->id,
            'incident_type' => IncidentType::LATE_CHECKOUT_BLOCKING_ARRIVAL,
            'case_status' => CaseStatus::RESOLVED,
            'compensation_type' => CompensationType::REFUND_PARTIAL,
            'refund_amount' => 3000,
            'opened_at' => Carbon::parse('2026-08-10 12:00:00'),
            'resolved_at' => Carbon::parse('2026-08-10 15:00:00'),
            'settlement_status' => SettlementStatus::SETTLED,
            'settled_amount' => 3000,
            'settled_at' => Carbon::parse('2026-08-10 16:00:00'),
        ]);

        $board = $this->dashboardService->operationalExposureBoard(
            $location->id,
            '2026-08-01',
            '2026-08-31'
        );

        $this->assertArrayHasKey('settlement_status_breakdown', $board);
        $this->assertSame(1, $board['settlement_status_breakdown'][SettlementStatus::UNSETTLED->value] ?? 0);
        $this->assertSame(1, $board['settlement_status_breakdown'][SettlementStatus::SETTLED->value] ?? 0);
        $this->assertSame(3000, $board['total_settled_amount']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_exposure_board_total_outstanding_excludes_settled_cases(): void
    {
        $location = Location::factory()->create();
        $room = $this->makeClassifiedRoom($location, RoomReadinessStatus::READY, RoomTypeCode::PRIVATE_SINGLE, 2);
        $stay = $this->makeStayWithAssignment($room, StayStatus::EXPECTED, '2026-08-10', '2026-08-12');

        // Unsettled case: 5000 refund outstanding
        ServiceRecoveryCase::create([
            'booking_id' => $stay->booking_id,
            'stay_id' => $stay->id,
            'incident_type' => IncidentType::ROOM_UNAVAILABLE_MAINTENANCE,
            'case_status' => CaseStatus::OPEN,
            'compensation_type' => CompensationType::REFUND_PARTIAL,
            'refund_amount' => 5000,
            'opened_at' => Carbon::parse('2026-08-10 10:00:00'),
            'settlement_status' => SettlementStatus::UNSETTLED,
        ]);

        // Settled case: should NOT count in outstanding
        ServiceRecoveryCase::create([
            'booking_id' => $stay->booking_id,
            'stay_id' => $stay->id,
            'incident_type' => IncidentType::LATE_CHECKOUT_BLOCKING_ARRIVAL,
            'case_status' => CaseStatus::RESOLVED,
            'compensation_type' => CompensationType::REFUND_PARTIAL,
            'refund_amount' => 3000,
            'opened_at' => Carbon::parse('2026-08-10 12:00:00'),
            'resolved_at' => now(),
            'settlement_status' => SettlementStatus::SETTLED,
            'settled_amount' => 3000,
            'settled_at' => now(),
        ]);

        $board = $this->dashboardService->operationalExposureBoard(
            $location->id,
            '2026-08-01',
            '2026-08-31'
        );

        $this->assertSame(5000, $board['total_outstanding_exposure']);
    }

    // ===== HELPERS =====

    private function makeClassifiedRoom(
        Location $location,
        RoomReadinessStatus $readiness,
        RoomTypeCode $typeCode,
        int $tier
    ): Room {
        $room = Room::factory()->create([
            'location_id' => $location->id,
            'status' => $readiness === RoomReadinessStatus::OUT_OF_SERVICE ? 'maintenance' : 'available',
            'room_type_code' => $typeCode,
            'room_tier' => $tier,
        ]);

        return $this->setRoomReadiness($room, $readiness);
    }

    private function setRoomReadiness(Room $room, RoomReadinessStatus $readiness): Room
    {
        $room->forceFill([
            'readiness_status' => $readiness,
            'readiness_changed_at' => now()->subMinutes(120),
            'readiness_changed_by' => null,
            'out_of_service_reason' => $readiness === RoomReadinessStatus::OUT_OF_SERVICE
                ? 'Test maintenance block.'
                : null,
        ])->saveQuietly();

        return $room->refresh();
    }

    private function makeStayWithAssignment(
        Room $room,
        StayStatus $stayStatus,
        ?string $checkIn = null,
        ?string $checkOut = null
    ): Stay {
        $checkInDate = $checkIn ? Carbon::parse($checkIn) : Carbon::today();
        $checkOutDate = $checkOut ? Carbon::parse($checkOut) : Carbon::tomorrow();

        $booking = Booking::factory()
            ->confirmed()
            ->forRoom($room)
            ->create([
                'check_in' => $checkInDate->toDateString(),
                'check_out' => $checkOutDate->toDateString(),
            ]);

        $stay = Stay::factory()->forBooking($booking)->create([
            'stay_status' => $stayStatus,
            'scheduled_check_in_at' => $checkInDate->copy()->setHour(14),
            'scheduled_check_out_at' => $checkOutDate->copy()->setHour(12),
            'actual_check_in_at' => $stayStatus->isInHouse() ? $checkInDate->copy()->setHour(14) : null,
        ]);

        RoomAssignment::create([
            'booking_id' => $booking->id,
            'stay_id' => $stay->id,
            'room_id' => $room->id,
            'assignment_type' => AssignmentType::ORIGINAL,
            'assignment_status' => AssignmentStatus::ACTIVE,
            'assigned_from' => $checkInDate->copy()->setHour(14),
            'assigned_until' => null,
        ]);

        return $stay->fresh('currentRoomAssignment.room');
    }
}
