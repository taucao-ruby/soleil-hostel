<?php

namespace Tests\Feature\Stays;

use App\Enums\AssignmentType;
use App\Enums\CaseStatus;
use App\Enums\IncidentType;
use App\Enums\SettlementStatus;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\ServiceRecoveryCase;
use App\Models\Stay;
use App\Services\OperationalDashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private OperationalDashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OperationalDashboardService::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function arrival_and_departure_board_queries_return_expected_stays(): void
    {
        $location = Location::factory()->create();
        $otherLocation = Location::factory()->create();
        $date = Carbon::today();

        $expectedStay = $this->createStayForBoard($location, 'ready', 'expected', $date, $date->copy()->addDay());
        $inHouseStay = $this->createStayForBoard($location, 'occupied', 'in_house', $date->copy()->subDay(), $date);
        $lateCheckoutStay = $this->createStayForBoard($location, 'occupied', 'late_checkout', $date->copy()->subDay(), $date);
        $otherLocationStay = $this->createStayForBoard($otherLocation, 'ready', 'expected', $date, $date->copy()->addDay());

        RoomAssignment::factory()->active()->create([
            'booking_id' => $inHouseStay->booking_id,
            'stay_id' => $inHouseStay->id,
            'room_id' => $inHouseStay->booking->room_id,
            'assignment_type' => AssignmentType::ORIGINAL,
            'assigned_from' => now()->subDay(),
            'assigned_until' => null,
        ]);

        RoomAssignment::factory()->active()->create([
            'booking_id' => $lateCheckoutStay->booking_id,
            'stay_id' => $lateCheckoutStay->id,
            'room_id' => $lateCheckoutStay->booking->room_id,
            'assignment_type' => AssignmentType::ORIGINAL,
            'assigned_from' => now()->subDay(),
            'assigned_until' => null,
        ]);

        $this->assertSame([$expectedStay->id], $this->service->expectedArrivals($location, $date)->pluck('id')->all());
        $this->assertEqualsCanonicalizing(
            [$inHouseStay->id, $lateCheckoutStay->id],
            $this->service->inHouseGuests($location)->pluck('id')->all()
        );
        $this->assertSame([$inHouseStay->id], $this->service->dueOuts($location, $date)->pluck('id')->all());
        $this->assertSame([$lateCheckoutStay->id], $this->service->lateCheckouts($location)->pluck('id')->all());
        $this->assertNotContains($otherLocationStay->id, $this->service->expectedArrivals($location, $date)->pluck('id')->all());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function room_readiness_board_queries_return_expected_rooms(): void
    {
        $location = Location::factory()->create();

        $readyRoom = Room::factory()->forLocation($location)->ready()->create(['status' => 'available']);
        $dirtyRoom = Room::factory()->forLocation($location)->dirty()->create(['status' => 'available']);
        $inspectedRoom = Room::factory()->forLocation($location)->create([
            'status' => 'available',
            'readiness_status' => 'inspected',
        ]);
        $outOfServiceRoom = Room::factory()->forLocation($location)->outOfService()->create(['status' => 'maintenance']);

        $this->assertSame([$readyRoom->id], $this->service->readyRooms($location)->pluck('id')->all());
        $this->assertEqualsCanonicalizing(
            [$dirtyRoom->id, $inspectedRoom->id],
            $this->service->notReadyRooms($location)->pluck('id')->all()
        );
        $this->assertSame([$outOfServiceRoom->id], $this->service->outOfServiceRooms($location)->pluck('id')->all());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function service_recovery_board_returns_open_cases_blocked_arrivals_and_manual_review_count(): void
    {
        $location = Location::factory()->create();
        $date = Carbon::today();

        $blockedStay = $this->createStayForBoard($location, 'out_of_service', 'expected', $date, $date->copy()->addDay(), [
            'room_type_code' => 'dorm_4bed',
            'room_tier' => 1,
        ]);

        $openCase = ServiceRecoveryCase::factory()->forBooking($blockedStay->booking)->create([
            'case_status' => CaseStatus::OPEN,
            'incident_type' => IncidentType::ROOM_UNAVAILABLE_MAINTENANCE,
        ]);

        ServiceRecoveryCase::factory()->forBooking($blockedStay->booking)->create([
            'case_status' => CaseStatus::RESOLVED,
        ]);

        $this->assertSame([$blockedStay->id], $this->service->blockedArrivals($location, $date)->pluck('id')->all());
        $this->assertSame([$openCase->id], $this->service->openRecoveryCases($location)->pluck('id')->all());
        $this->assertEquals(1, $this->service->manualReviewRequired($location, $date));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function financial_queries_return_expected_operational_totals(): void
    {
        $location = Location::factory()->create();
        $room = Room::factory()->forLocation($location)->ready()->create(['status' => 'available']);
        $secondRoom = Room::factory()->forLocation($location)->ready()->create(['status' => 'available']);
        $from = Carbon::today()->subDay();
        $to = Carbon::today()->addDay();

        $depositBooking = Booking::factory()->forRoom($room)->withDeposit(7000)->create([
            'location_id' => $location->id,
            'check_in' => Carbon::today()->toDateString(),
            'check_out' => Carbon::tomorrow()->toDateString(),
        ]);

        $settledBooking = Booking::factory()->forRoom($secondRoom)->confirmed()->create([
            'location_id' => $location->id,
            'check_in' => Carbon::today()->toDateString(),
            'check_out' => Carbon::tomorrow()->toDateString(),
        ]);

        ServiceRecoveryCase::factory()->forBooking($depositBooking)->create([
            'refund_amount' => 4000,
            'voucher_amount' => 2000,
            'cost_delta_absorbed' => 1000,
            'settled_amount' => 1000,
            'settlement_status' => SettlementStatus::UNSETTLED,
            'incident_type' => IncidentType::INTERNAL_RELOCATION,
            'opened_at' => Carbon::today()->setHour(9),
        ]);

        ServiceRecoveryCase::factory()->forBooking($settledBooking)->create([
            'refund_amount' => 0,
            'voucher_amount' => 3000,
            'cost_delta_absorbed' => 5000,
            'settled_amount' => 6000,
            'settlement_status' => SettlementStatus::SETTLED,
            'settled_at' => Carbon::today()->setHour(10),
            'incident_type' => IncidentType::EXTERNAL_RELOCATION,
            'opened_at' => Carbon::today()->setHour(10),
        ]);

        ServiceRecoveryCase::factory()->forBooking($depositBooking)->create([
            'refund_amount' => 1000,
            'voucher_amount' => 500,
            'cost_delta_absorbed' => 0,
            'settled_amount' => 200,
            'settlement_status' => SettlementStatus::PARTIALLY_SETTLED,
            'incident_type' => IncidentType::ROOM_UNAVAILABLE_MAINTENANCE,
            'opened_at' => Carbon::today()->setHour(11),
        ]);

        $this->assertEquals(7000, $this->service->depositCollected($location, $from, $to));
        $this->assertEquals(7300, $this->service->openCompensationExposure($location));
        $this->assertEquals(6000, $this->service->settledCompensation($location, $from, $to));
        $this->assertEquals(6000, $this->service->unsettledExposure($location));
        $this->assertEquals(2500, $this->service->voucherExposure($location));
        $this->assertEquals(6000, $this->service->relocationDeltaAbsorbed($location, $from, $to));
    }

    private function createStayForBoard(
        Location $location,
        string $readinessStatus,
        string $stayStatus,
        Carbon $checkInDate,
        Carbon $checkOutDate,
        array $roomOverrides = []
    ): Stay {
        $room = Room::factory()->forLocation($location)->create(array_merge([
            'status' => 'available',
            'readiness_status' => $readinessStatus,
            'room_type_code' => 'dorm_4bed',
            'room_tier' => 1,
            'max_guests' => 4,
        ], $roomOverrides));

        $booking = Booking::factory()->confirmed()->create([
            'room_id' => $room->id,
            'location_id' => $location->id,
            'check_in' => $checkInDate->toDateString(),
            'check_out' => $checkOutDate->toDateString(),
        ]);

        return Stay::factory()->forBooking($booking)->create([
            'stay_status' => $stayStatus,
            'scheduled_check_in_at' => $checkInDate->copy()->setTime(14, 0),
            'scheduled_check_out_at' => $checkOutDate->copy()->setTime(12, 0),
            'actual_check_in_at' => $stayStatus === 'expected' ? null : $checkInDate->copy()->setTime(14, 0),
            'actual_check_out_at' => null,
        ]);
    }
}
