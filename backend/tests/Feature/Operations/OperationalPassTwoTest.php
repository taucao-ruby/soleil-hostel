<?php

namespace Tests\Feature\Operations;

use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Enums\CaseStatus;
use App\Enums\CompensationType;
use App\Enums\IncidentType;
use App\Enums\RoomReadinessStatus;
use App\Enums\SettlementStatus;
use App\Enums\StayStatus;
use App\Exceptions\RoomNotReadyForAssignmentException;
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

class OperationalPassTwoTest extends TestCase
{
    use RefreshDatabase;

    private OperationalDashboardService $dashboardService;

    private CheckInBlockageResolver $blockageResolver;

    private FinancialOperationsService $financialOperationsService;

    protected function setUp(): void
    {
        parent::setUp();

        Location::query()->delete();

        $this->dashboardService = app(OperationalDashboardService::class);
        $this->blockageResolver = app(CheckInBlockageResolver::class);
        $this->financialOperationsService = app(FinancialOperationsService::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_room_transitions_to_dirty_when_stay_checks_out(): void
    {
        $staff = User::factory()->admin()->create();
        $location = Location::factory()->create();
        $room = $this->makeRoom($location, RoomReadinessStatus::READY);
        $stay = $this->makeStayWithAssignment(
            room: $room,
            stayStatus: StayStatus::IN_HOUSE,
            checkIn: Carbon::today()->subDay()->setHour(14),
            checkOut: Carbon::today()->setHour(12)
        );
        $this->setRoomReadiness($room, RoomReadinessStatus::OCCUPIED);

        $stay->update([
            'stay_status' => StayStatus::CHECKED_OUT,
            'checked_out_by' => $staff->id,
            'actual_check_out_at' => now(),
        ]);

        $room->refresh();

        $this->assertEquals(RoomReadinessStatus::DIRTY, $room->readiness_status);
        $this->assertDatabaseHas('room_readiness_logs', [
            'room_id' => $room->id,
            'stay_id' => $stay->id,
            'from_status' => RoomReadinessStatus::OCCUPIED->value,
            'to_status' => RoomReadinessStatus::DIRTY->value,
            'changed_by' => $staff->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_room_transitions_to_occupied_when_stay_checks_in(): void
    {
        $staff = User::factory()->admin()->create();
        $location = Location::factory()->create();
        $room = $this->makeRoom($location, RoomReadinessStatus::READY);
        $stay = $this->makeStayWithAssignment(
            room: $room,
            stayStatus: StayStatus::EXPECTED,
            checkIn: Carbon::today()->setHour(14),
            checkOut: Carbon::tomorrow()->setHour(12)
        );

        $stay->update([
            'stay_status' => StayStatus::IN_HOUSE,
            'checked_in_by' => $staff->id,
            'actual_check_in_at' => now(),
        ]);

        $room->refresh();

        $this->assertEquals(RoomReadinessStatus::OCCUPIED, $room->readiness_status);
        $this->assertDatabaseHas('room_readiness_logs', [
            'room_id' => $room->id,
            'stay_id' => $stay->id,
            'from_status' => RoomReadinessStatus::READY->value,
            'to_status' => RoomReadinessStatus::OCCUPIED->value,
            'changed_by' => $staff->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_room_assignment_creation_blocked_when_room_not_ready(): void
    {
        $location = Location::factory()->create();
        $room = $this->makeRoom($location, RoomReadinessStatus::DIRTY);
        $booking = Booking::factory()
            ->confirmed()
            ->forRoom($room)
            ->create([
                'check_in' => Carbon::today()->toDateString(),
                'check_out' => Carbon::tomorrow()->toDateString(),
            ]);

        $stay = Stay::factory()->forBooking($booking)->create([
            'stay_status' => StayStatus::EXPECTED,
            'scheduled_check_in_at' => Carbon::today()->setHour(14),
            'scheduled_check_out_at' => Carbon::tomorrow()->setHour(12),
        ]);

        $this->expectException(RoomNotReadyForAssignmentException::class);

        RoomAssignment::create([
            'booking_id' => $booking->id,
            'stay_id' => $stay->id,
            'room_id' => $room->id,
            'assignment_type' => AssignmentType::ORIGINAL,
            'assignment_status' => AssignmentStatus::ACTIVE,
            'assigned_from' => now(),
            'assigned_until' => null,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_out_of_service_room_excluded_from_available_rooms(): void
    {
        $location = Location::factory()->create();
        $readyRoom = $this->makeRoom($location, RoomReadinessStatus::READY);
        $outOfServiceRoom = $this->makeRoom($location, RoomReadinessStatus::OUT_OF_SERVICE);

        $availableRooms = Room::query()
            ->where('location_id', $location->id)
            ->availableBetween('2026-06-10', '2026-06-12')
            ->get();

        $availableIds = $availableRooms->pluck('id')->all();

        $this->assertContains($readyRoom->id, $availableIds);
        $this->assertNotContains($outOfServiceRoom->id, $availableIds);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_readiness_log_records_each_transition(): void
    {
        $location = Location::factory()->create();
        $room = $this->makeRoom($location, RoomReadinessStatus::READY);
        $stay = $this->makeStayWithAssignment(
            room: $room,
            stayStatus: StayStatus::EXPECTED,
            checkIn: Carbon::today()->setHour(14),
            checkOut: Carbon::tomorrow()->setHour(12)
        );

        $stay->update([
            'stay_status' => StayStatus::IN_HOUSE,
            'actual_check_in_at' => now(),
        ]);

        $stay->update([
            'stay_status' => StayStatus::CHECKED_OUT,
            'actual_check_out_at' => now(),
        ]);

        $this->assertDatabaseCount('room_readiness_logs', 2);
        $this->assertDatabaseHas('room_readiness_logs', [
            'room_id' => $room->id,
            'stay_id' => $stay->id,
            'to_status' => RoomReadinessStatus::OCCUPIED->value,
        ]);
        $this->assertDatabaseHas('room_readiness_logs', [
            'room_id' => $room->id,
            'stay_id' => $stay->id,
            'to_status' => RoomReadinessStatus::DIRTY->value,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_arrival_board_returns_arrivals_for_correct_location_and_date(): void
    {
        $location = Location::factory()->create();
        $otherLocation = Location::factory()->create();
        $date = Carbon::parse('2026-06-15');

        $this->makeStayWithAssignment(
            room: $this->makeRoom($location, RoomReadinessStatus::READY),
            stayStatus: StayStatus::EXPECTED,
            checkIn: $date->copy()->setHour(14),
            checkOut: $date->copy()->addDay()->setHour(12)
        );

        $this->makeStayWithAssignment(
            room: $this->makeRoom($otherLocation, RoomReadinessStatus::READY),
            stayStatus: StayStatus::EXPECTED,
            checkIn: $date->copy()->setHour(14),
            checkOut: $date->copy()->addDay()->setHour(12)
        );

        $board = $this->dashboardService->arrivalDepartureBoard($location->id, $date->toDateString());

        $this->assertSame(1, $board['expected_arrivals_today']['count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_arrival_board_excludes_arrivals_for_other_locations(): void
    {
        $location = Location::factory()->create();
        $otherLocation = Location::factory()->create();
        $date = Carbon::parse('2026-07-01');

        $this->makeStayWithAssignment(
            room: $this->makeRoom($otherLocation, RoomReadinessStatus::READY),
            stayStatus: StayStatus::EXPECTED,
            checkIn: $date->copy()->setHour(14),
            checkOut: $date->copy()->addDay()->setHour(12)
        );

        $board = $this->dashboardService->arrivalDepartureBoard($location->id, $date->toDateString());

        $this->assertSame(0, $board['expected_arrivals_today']['count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_late_checkout_detection(): void
    {
        $location = Location::factory()->create();
        $room = $this->makeRoom($location, RoomReadinessStatus::READY);

        $this->makeStayWithAssignment(
            room: $room,
            stayStatus: StayStatus::LATE_CHECKOUT,
            checkIn: Carbon::today()->subDays(2)->setHour(14),
            checkOut: Carbon::today()->subHour()
        );
        $this->setRoomReadiness($room, RoomReadinessStatus::OCCUPIED);

        $board = $this->dashboardService->arrivalDepartureBoard($location->id, Carbon::today()->toDateString());

        $this->assertSame(1, $board['late_checkouts']['count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_blocked_arrival_flagged_when_room_not_ready(): void
    {
        $location = Location::factory()->create();
        $room = $this->makeRoom($location, RoomReadinessStatus::READY);
        $stay = $this->makeStayWithAssignment(
            room: $room,
            stayStatus: StayStatus::EXPECTED,
            checkIn: Carbon::today()->setHour(14),
            checkOut: Carbon::tomorrow()->setHour(12)
        );

        $this->setRoomReadiness($room, RoomReadinessStatus::DIRTY);

        $board = $this->dashboardService->arrivalDepartureBoard($location->id, Carbon::today()->toDateString());

        $this->assertSame(1, $board['arrivals_blocked_by_room_not_ready']['count']);
        $this->assertSame($stay->id, $board['arrivals_blocked_by_room_not_ready']['stays'][0]['stay_id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_room_readiness_board_groups_rooms_by_status(): void
    {
        $location = Location::factory()->create();

        foreach (RoomReadinessStatus::cases() as $status) {
            $this->makeRoom($location, $status);
        }

        $board = $this->dashboardService->roomReadinessBoard($location->id);

        foreach (RoomReadinessStatus::cases() as $status) {
            $this->assertSame(1, $board['states'][$status->value]['count']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_exception_board_surfaces_open_recovery_cases(): void
    {
        $location = Location::factory()->create();
        $room = $this->makeRoom($location, RoomReadinessStatus::READY);
        $stay = $this->makeStayWithAssignment(
            room: $room,
            stayStatus: StayStatus::EXPECTED,
            checkIn: Carbon::today()->setHour(14),
            checkOut: Carbon::tomorrow()->setHour(12)
        );

        ServiceRecoveryCase::create([
            'booking_id' => $stay->booking_id,
            'stay_id' => $stay->id,
            'incident_type' => IncidentType::LATE_CHECKOUT_BLOCKING_ARRIVAL,
            'case_status' => CaseStatus::OPEN,
            'compensation_type' => CompensationType::NONE,
            'opened_at' => now(),
            'resolved_at' => null,
        ]);

        $board = $this->dashboardService->exceptionBoard($location->id, Carbon::today()->toDateString());

        $this->assertSame(1, $board['open_recovery_cases']['count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_exposure_board_counts_confirmed_bookings_without_stay_row(): void
    {
        $location = Location::factory()->create();
        $room = $this->makeRoom($location, RoomReadinessStatus::READY);
        $checkIn = Carbon::parse('2026-08-10');
        $checkOut = Carbon::parse('2026-08-12');

        Booking::factory()
            ->confirmed()
            ->forRoom($room)
            ->create([
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
            ]);

        $this->makeStayWithAssignment(
            room: $room,
            stayStatus: StayStatus::EXPECTED,
            checkIn: Carbon::parse('2026-08-14 14:00:00'),
            checkOut: Carbon::parse('2026-08-15 12:00:00')
        );

        $board = $this->dashboardService->operationalExposureBoard(
            $location->id,
            '2026-08-01',
            '2026-08-31'
        );

        $this->assertSame(1, $board['confirmed_bookings_without_stay_count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_equivalent_swap_resolves_dirty_room_blockage(): void
    {
        $location = Location::factory()->create();
        $originalRoom = $this->makeRoom($location, RoomReadinessStatus::READY);
        $originalRoom->forceFill(['room_type_code' => 'private_double', 'room_tier' => 2])->saveQuietly();

        $swapRoom = $this->makeRoom($location, RoomReadinessStatus::READY);
        $swapRoom->forceFill(['room_type_code' => 'private_double', 'room_tier' => 2])->saveQuietly();

        $stay = $this->makeStayWithAssignment(
            room: $originalRoom,
            stayStatus: StayStatus::EXPECTED,
            checkIn: Carbon::today()->setHour(14),
            checkOut: Carbon::tomorrow()->setHour(12)
        );

        $this->setRoomReadiness($originalRoom, RoomReadinessStatus::DIRTY);

        $result = $this->blockageResolver->resolve($stay->fresh('currentRoomAssignment.room'));

        $this->assertSame('equivalent_swap', $result['resolution']);
        $this->assertSame($swapRoom->id, $result['new_room_id']);
        $this->assertFalse($result['requires_manual_action']);
        $this->assertNotNull($result['case_id']);

        $case = ServiceRecoveryCase::findOrFail($result['case_id']);
        $this->assertEquals(IncidentType::EQUIVALENT_SWAP, $case->incident_type);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_complimentary_upgrade_when_no_equivalent_available(): void
    {
        $location = Location::factory()->create();
        $originalRoom = $this->makeRoom($location, RoomReadinessStatus::READY);
        $originalRoom->forceFill(['room_type_code' => 'private_single', 'room_tier' => 2])->saveQuietly();

        // No equivalent room — create a higher-tier room
        $upgradeRoom = $this->makeRoom($location, RoomReadinessStatus::READY);
        $upgradeRoom->forceFill(['room_type_code' => 'private_suite', 'room_tier' => 3])->saveQuietly();

        $stay = $this->makeStayWithAssignment(
            room: $originalRoom,
            stayStatus: StayStatus::EXPECTED,
            checkIn: Carbon::today()->setHour(14),
            checkOut: Carbon::tomorrow()->setHour(12)
        );

        $this->setRoomReadiness($originalRoom, RoomReadinessStatus::OUT_OF_SERVICE);

        $result = $this->blockageResolver->resolve($stay->fresh('currentRoomAssignment.room'));

        $this->assertSame('complimentary_upgrade', $result['resolution']);
        $this->assertSame($upgradeRoom->id, $result['new_room_id']);
        $this->assertFalse($result['requires_manual_action']);

        $case = ServiceRecoveryCase::findOrFail($result['case_id']);
        $this->assertEquals(IncidentType::COMPLIMENTARY_UPGRADE, $case->incident_type);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_internal_relocation_recommended_when_no_room_in_location(): void
    {
        $location = Location::factory()->create();
        $otherLocation = Location::factory()->create();

        $originalRoom = $this->makeRoom($location, RoomReadinessStatus::READY);
        $originalRoom->forceFill(['room_type_code' => 'private_double', 'room_tier' => 2])->saveQuietly();

        // No rooms available in same location — create one in other location
        $destRoom = $this->makeRoom($otherLocation, RoomReadinessStatus::READY);
        $destRoom->forceFill(['room_type_code' => 'private_double', 'room_tier' => 2])->saveQuietly();

        $stay = $this->makeStayWithAssignment(
            room: $originalRoom,
            stayStatus: StayStatus::EXPECTED,
            checkIn: Carbon::today()->setHour(14),
            checkOut: Carbon::tomorrow()->setHour(12)
        );

        $this->setRoomReadiness($originalRoom, RoomReadinessStatus::OUT_OF_SERVICE);

        $result = $this->blockageResolver->resolve($stay->fresh('currentRoomAssignment.room'));

        $this->assertSame('internal_relocation_recommended', $result['resolution']);
        $this->assertTrue($result['requires_manual_action']);
        $this->assertSame($otherLocation->id, $result['destination_location_id']);
        $this->assertNull($result['new_room_id']); // No RoomAssignment created

        $case = ServiceRecoveryCase::findOrFail($result['case_id']);
        $this->assertEquals(IncidentType::INTERNAL_RELOCATION, $case->incident_type);
        $this->assertNotNull($case->escalated_at);

        // Verify no new RoomAssignment was created for this step
        $this->assertSame(1, RoomAssignment::where('stay_id', $stay->id)->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_external_relocation_escalated_when_no_internal_option(): void
    {
        $location = Location::factory()->create();
        $room = $this->makeRoom($location, RoomReadinessStatus::READY);
        $stay = $this->makeStayWithAssignment(
            room: $room,
            stayStatus: StayStatus::EXPECTED,
            checkIn: Carbon::today()->setHour(14),
            checkOut: Carbon::tomorrow()->setHour(12)
        );

        $this->setRoomReadiness($room, RoomReadinessStatus::OUT_OF_SERVICE);

        $result = $this->blockageResolver->resolve($stay->fresh('currentRoomAssignment.room'));
        $case = ServiceRecoveryCase::findOrFail($result['case_id']);

        $this->assertSame('external_relocation_escalated', $result['resolution']);
        $this->assertTrue($result['requires_manual_action']);
        $this->assertNotNull($case->escalated_at);
        $this->assertEquals(IncidentType::EXTERNAL_RELOCATION, $case->incident_type);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_blockage_type_detected_as_late_checkout(): void
    {
        $location = Location::factory()->create();
        $room = $this->makeRoom($location, RoomReadinessStatus::READY);

        $occupyingStay = $this->makeStayWithAssignment(
            room: $room,
            stayStatus: StayStatus::EXPECTED,
            checkIn: Carbon::today()->subDay()->setHour(14),
            checkOut: Carbon::today()->setHour(11)
        );

        $arrivingStay = $this->makeStayWithAssignment(
            room: $room,
            stayStatus: StayStatus::EXPECTED,
            checkIn: Carbon::today()->setHour(14),
            checkOut: Carbon::tomorrow()->setHour(12)
        );

        $occupyingStay->update([
            'stay_status' => StayStatus::IN_HOUSE,
            'actual_check_in_at' => now()->subDay(),
        ]);

        $result = $this->blockageResolver->resolve($arrivingStay->fresh('currentRoomAssignment.room'));

        $this->assertSame('late_checkout', $result['blockage_type']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_blockage_type_detected_as_out_of_service(): void
    {
        $location = Location::factory()->create();
        $room = $this->makeRoom($location, RoomReadinessStatus::READY);
        $stay = $this->makeStayWithAssignment(
            room: $room,
            stayStatus: StayStatus::EXPECTED,
            checkIn: Carbon::today()->setHour(14),
            checkOut: Carbon::tomorrow()->setHour(12)
        );

        $this->setRoomReadiness($room, RoomReadinessStatus::OUT_OF_SERVICE);

        $result = $this->blockageResolver->resolve($stay->fresh('currentRoomAssignment.room'));

        $this->assertSame('out_of_service', $result['blockage_type']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_no_blockage_when_room_is_ready(): void
    {
        $location = Location::factory()->create();
        $room = $this->makeRoom($location, RoomReadinessStatus::READY);
        $stay = $this->makeStayWithAssignment(
            room: $room,
            stayStatus: StayStatus::EXPECTED,
            checkIn: Carbon::today()->setHour(14),
            checkOut: Carbon::tomorrow()->setHour(12)
        );

        $result = $this->blockageResolver->resolve($stay->fresh('currentRoomAssignment.room'));

        $this->assertSame('no_blockage', $result['resolution']);
        $this->assertNull($result['case_id']);
        $this->assertDatabaseCount('service_recovery_cases', 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_deposit_summary_sums_correctly_for_date_range(): void
    {
        $location = Location::factory()->create();
        $room = $this->makeRoom($location, RoomReadinessStatus::READY);

        Booking::factory()->confirmed()->forRoom($room)->create([
            'check_in' => '2026-09-05',
            'check_out' => '2026-09-07',
            'deposit_amount' => 5000,
            'deposit_collected_at' => now(),
        ]);

        Booking::factory()->confirmed()->forRoom($room)->create([
            'check_in' => '2026-09-08',
            'check_out' => '2026-09-10',
            'deposit_amount' => 3000,
            'deposit_collected_at' => now(),
        ]);

        // Outside range
        Booking::factory()->confirmed()->forRoom($room)->create([
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-03',
            'deposit_amount' => 9999,
        ]);

        $result = $this->financialOperationsService->depositSummary(
            $location->id,
            '2026-09-01',
            '2026-09-30'
        );

        $this->assertSame(8000, $result['deposit_amount_total']);
        $this->assertSame(2, $result['bookings_with_deposit_count']);
        $this->assertNull($result['blocked_reason']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_compensation_exposure_separates_unresolved_from_resolved(): void
    {
        $location = Location::factory()->create();
        $room = $this->makeRoom($location, RoomReadinessStatus::READY);
        $stay = $this->makeStayWithAssignment(
            room: $room,
            stayStatus: StayStatus::EXPECTED,
            checkIn: Carbon::parse('2026-09-10 14:00:00'),
            checkOut: Carbon::parse('2026-09-11 12:00:00')
        );

        ServiceRecoveryCase::create([
            'booking_id' => $stay->booking_id,
            'stay_id' => $stay->id,
            'incident_type' => IncidentType::LATE_CHECKOUT_BLOCKING_ARRIVAL,
            'case_status' => CaseStatus::OPEN,
            'compensation_type' => CompensationType::REFUND_PARTIAL,
            'refund_amount' => 2500,
            'opened_at' => Carbon::parse('2026-09-10 10:00:00'),
        ]);

        ServiceRecoveryCase::create([
            'booking_id' => $stay->booking_id,
            'stay_id' => $stay->id,
            'incident_type' => IncidentType::EXTERNAL_RELOCATION,
            'case_status' => CaseStatus::RESOLVED,
            'compensation_type' => CompensationType::REFUND_PARTIAL,
            'refund_amount' => 4000,
            'opened_at' => Carbon::parse('2026-09-10 12:00:00'),
            'resolved_at' => Carbon::parse('2026-09-10 15:00:00'),
        ]);

        $result = $this->financialOperationsService->compensationExposure(
            $location->id,
            '2026-09-01',
            '2026-09-30'
        );

        $this->assertSame(2500, $result['unresolved_refund_amount']);
        $this->assertSame(4000, $result['resolved_refund_amount']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_compensation_exposure_groups_by_case_type(): void
    {
        $location = Location::factory()->create();
        $room = $this->makeRoom($location, RoomReadinessStatus::READY);
        $stay = $this->makeStayWithAssignment(
            room: $room,
            stayStatus: StayStatus::EXPECTED,
            checkIn: Carbon::parse('2026-10-10 14:00:00'),
            checkOut: Carbon::parse('2026-10-11 12:00:00')
        );

        ServiceRecoveryCase::create([
            'booking_id' => $stay->booking_id,
            'stay_id' => $stay->id,
            'incident_type' => IncidentType::INTERNAL_RELOCATION,
            'case_status' => CaseStatus::OPEN,
            'compensation_type' => CompensationType::NONE,
            'opened_at' => Carbon::parse('2026-10-10 09:00:00'),
        ]);

        ServiceRecoveryCase::create([
            'booking_id' => $stay->booking_id,
            'stay_id' => $stay->id,
            'incident_type' => IncidentType::INTERNAL_RELOCATION,
            'case_status' => CaseStatus::OPEN,
            'compensation_type' => CompensationType::NONE,
            'opened_at' => Carbon::parse('2026-10-10 10:00:00'),
        ]);

        ServiceRecoveryCase::create([
            'booking_id' => $stay->booking_id,
            'stay_id' => $stay->id,
            'incident_type' => IncidentType::EXTERNAL_RELOCATION,
            'case_status' => CaseStatus::OPEN,
            'compensation_type' => CompensationType::NONE,
            'opened_at' => Carbon::parse('2026-10-10 11:00:00'),
        ]);

        $result = $this->financialOperationsService->compensationExposure(
            $location->id,
            '2026-10-01',
            '2026-10-31'
        );

        $this->assertSame(2, $result['cases_by_incident_type'][IncidentType::INTERNAL_RELOCATION->value]);
        $this->assertSame(1, $result['cases_by_incident_type'][IncidentType::EXTERNAL_RELOCATION->value]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_relocation_cost_summary_counts_cases_by_type(): void
    {
        $location = Location::factory()->create();
        $room = $this->makeRoom($location, RoomReadinessStatus::READY);
        $stay = $this->makeStayWithAssignment(
            room: $room,
            stayStatus: StayStatus::EXPECTED,
            checkIn: Carbon::parse('2026-11-10 14:00:00'),
            checkOut: Carbon::parse('2026-11-11 12:00:00')
        );

        $openCase = ServiceRecoveryCase::create([
            'booking_id' => $stay->booking_id,
            'stay_id' => $stay->id,
            'incident_type' => IncidentType::INTERNAL_RELOCATION,
            'case_status' => CaseStatus::OPEN,
            'compensation_type' => CompensationType::NONE,
            'cost_delta_absorbed' => 5000,
            'opened_at' => Carbon::parse('2026-11-10 09:00:00'),
        ]);

        ServiceRecoveryCase::create([
            'booking_id' => $stay->booking_id,
            'stay_id' => $stay->id,
            'incident_type' => IncidentType::EXTERNAL_RELOCATION,
            'case_status' => CaseStatus::RESOLVED,
            'compensation_type' => CompensationType::NONE,
            'cost_delta_absorbed' => 12000,
            'opened_at' => Carbon::parse('2026-11-10 10:00:00'),
            'resolved_at' => Carbon::parse('2026-11-10 15:00:00'),
            'settlement_status' => SettlementStatus::SETTLED,
            'settled_at' => Carbon::parse('2026-11-10 16:00:00'),
            'settled_amount' => 0,
        ]);

        $result = $this->financialOperationsService->relocationCostSummary(
            $location->id,
            '2026-11-01',
            '2026-11-30'
        );

        $this->assertSame(2, $result['relocation_cases_count']);
        $this->assertSame(17000, $result['absorbed_cost_total']);
        $this->assertSame(1, $result['unresolved_cases_count']);
        $this->assertSame(1, $result['resolved_cases_count']);
        $this->assertSame([$openCase->id], $result['manual_financial_settlement_case_ids']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_financial_treatment_docblock_present_in_source(): void
    {
        $contents = file_get_contents(app_path('Services/FinancialOperationsService.php'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('FINANCIAL TREATMENT BOUNDARY - SOLEIL HOSTEL OPERATIONAL VISIBILITY', $contents);
        $this->assertStringContainsString('This service provides OPERATIONAL VISIBILITY only.', $contents);
    }

    private function makeRoom(Location $location, RoomReadinessStatus $readinessStatus): Room
    {
        $room = Room::factory()->create([
            'location_id' => $location->id,
            'status' => $readinessStatus === RoomReadinessStatus::OUT_OF_SERVICE ? 'maintenance' : 'available',
        ]);

        return $this->setRoomReadiness($room, $readinessStatus);
    }

    private function setRoomReadiness(Room $room, RoomReadinessStatus $readinessStatus): Room
    {
        $room->forceFill([
            'readiness_status' => $readinessStatus,
            'readiness_changed_at' => now()->subMinutes(120),
            'readiness_changed_by' => null,
            'out_of_service_reason' => $readinessStatus === RoomReadinessStatus::OUT_OF_SERVICE
                ? 'Test maintenance block.'
                : null,
        ])->saveQuietly();

        return $room->refresh();
    }

    private function makeStayWithAssignment(
        Room $room,
        StayStatus $stayStatus,
        Carbon $checkIn,
        Carbon $checkOut
    ): Stay {
        $booking = Booking::factory()
            ->confirmed()
            ->forRoom($room)
            ->create([
                'check_in' => $checkIn->copy()->startOfDay()->toDateString(),
                'check_out' => $checkOut->copy()->startOfDay()->toDateString(),
            ]);

        $stay = Stay::factory()->forBooking($booking)->create([
            'stay_status' => $stayStatus,
            'scheduled_check_in_at' => $checkIn,
            'scheduled_check_out_at' => $checkOut,
            'actual_check_in_at' => $stayStatus->isInHouse() ? $checkIn : null,
            'actual_check_out_at' => $stayStatus === StayStatus::CHECKED_OUT ? $checkOut : null,
        ]);

        RoomAssignment::create([
            'booking_id' => $booking->id,
            'stay_id' => $stay->id,
            'room_id' => $room->id,
            'assignment_type' => AssignmentType::ORIGINAL,
            'assignment_status' => AssignmentStatus::ACTIVE,
            'assigned_from' => $checkIn,
            'assigned_until' => null,
        ]);

        return $stay->fresh('currentRoomAssignment.room');
    }
}
