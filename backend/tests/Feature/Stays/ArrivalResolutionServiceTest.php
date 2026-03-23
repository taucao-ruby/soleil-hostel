<?php

namespace Tests\Feature\Stays;

use App\Enums\AssignmentType;
use App\Enums\BlockerType;
use App\Enums\IncidentType;
use App\Enums\ResolutionStep;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Stay;
use App\Services\ArrivalResolutionResult;
use App\Services\ArrivalResolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ArrivalResolutionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ArrivalResolutionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ArrivalResolutionService::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function resolve_prefers_same_location_equivalent_before_upgrade_and_cross_location(): void
    {
        [$sourceLocation, $sourceRoom, $stay] = $this->makeBlockedExpectedArrival('dirty');

        $sameLocationEquivalent = Room::factory()
            ->forLocation($sourceLocation)
            ->classified('dorm_4bed', 1)
            ->ready()
            ->create([
                'max_guests' => 4,
                'status' => 'available',
            ]);

        Room::factory()
            ->forLocation($sourceLocation)
            ->classified('private_deluxe', 3)
            ->ready()
            ->create([
                'max_guests' => 4,
                'status' => 'available',
            ]);

        $otherLocation = Location::factory()->create();
        Room::factory()
            ->forLocation($otherLocation)
            ->classified('dorm_4bed', 1)
            ->ready()
            ->create([
                'max_guests' => 4,
                'status' => 'available',
            ]);

        $result = $this->service->resolve($stay);

        $this->assertEquals(BlockerType::ROOM_NOT_READY, $result->blockerType);
        $this->assertEquals(ResolutionStep::EQUIVALENT_SAME_LOCATION, $result->step);
        $this->assertEquals($sameLocationEquivalent->id, $result->recommendedRoom?->id);
        $this->assertEquals(AssignmentType::EQUIVALENT_SWAP, $result->assignmentType);
        $this->assertFalse($result->requiresOperatorApproval);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function blocker_for_detects_late_checkout_conflicts_from_active_assignments(): void
    {
        [$location, $sourceRoom, $stay] = $this->makeBlockedExpectedArrival('ready');

        $lateCheckoutBooking = Booking::factory()->confirmed()->create([
            'room_id' => $sourceRoom->id,
            'location_id' => $location->id,
            'check_in' => now()->subDays(2)->toDateString(),
            'check_out' => now()->toDateString(),
        ]);

        $lateCheckoutStay = Stay::factory()->forBooking($lateCheckoutBooking)->lateCheckout()->create();

        RoomAssignment::factory()->active()->create([
            'booking_id' => $lateCheckoutBooking->id,
            'stay_id' => $lateCheckoutStay->id,
            'room_id' => $sourceRoom->id,
            'assignment_type' => AssignmentType::ORIGINAL,
            'assigned_from' => now()->subDay(),
            'assigned_until' => null,
        ]);

        $this->assertEquals(BlockerType::LATE_CHECKOUT, $this->service->blockerFor($stay));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function resolve_escalates_to_external_when_no_internal_candidate_exists(): void
    {
        [, , $stay] = $this->makeBlockedExpectedArrival('out_of_service');

        $result = $this->service->resolve($stay);

        $this->assertEquals(BlockerType::NO_INTERNAL_ROOM, $result->blockerType);
        $this->assertEquals(ResolutionStep::EXTERNAL_ESCALATION, $result->step);
        $this->assertNull($result->recommendedRoom);
        $this->assertEquals(AssignmentType::OVERFLOW_RELOCATION, $result->assignmentType);
        $this->assertTrue($result->requiresOperatorApproval);
        $this->assertNotNull($result->externalEscalationNote);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function apply_accepted_recommendation_creates_internal_assignment_and_recovery_case(): void
    {
        [$sourceLocation, $sourceRoom, $stay] = $this->makeBlockedExpectedArrival('dirty');

        $recommendedRoom = Room::factory()
            ->forLocation($sourceLocation)
            ->classified('dorm_4bed', 1)
            ->ready()
            ->create([
                'max_guests' => 4,
                'status' => 'available',
            ]);

        $result = $this->service->resolve($stay);
        $artifacts = $this->service->applyAcceptedRecommendation($stay, $result);

        $this->assertDatabaseHas('room_assignments', [
            'id' => $artifacts['room_assignment']->id,
            'stay_id' => $stay->id,
            'room_id' => $recommendedRoom->id,
            'assignment_type' => AssignmentType::EQUIVALENT_SWAP->value,
            'reason_code' => BlockerType::ROOM_NOT_READY->value,
        ]);

        $this->assertDatabaseHas('service_recovery_cases', [
            'id' => $artifacts['service_recovery_case']->id,
            'booking_id' => $stay->booking_id,
            'stay_id' => $stay->id,
            'incident_type' => IncidentType::ROOM_UNAVAILABLE_MAINTENANCE->value,
        ]);

        $this->assertEquals('occupied', $recommendedRoom->fresh()->readiness_status->value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function apply_accepted_recommendation_rejects_external_escalation_without_internal_room(): void
    {
        [, , $stay] = $this->makeBlockedExpectedArrival('out_of_service');

        $result = new ArrivalResolutionResult(
            blockerType: BlockerType::NO_INTERNAL_ROOM,
            step: ResolutionStep::EXTERNAL_ESCALATION,
            recommendedRoom: null,
            assignmentType: AssignmentType::OVERFLOW_RELOCATION,
            requiresOperatorApproval: true,
            externalEscalationNote: 'Manual external relocation review required.',
        );

        $this->expectException(RuntimeException::class);

        $this->service->applyAcceptedRecommendation($stay, $result);
    }

    /**
     * @return array{0: Location, 1: Room, 2: Stay}
     */
    private function makeBlockedExpectedArrival(string $readinessStatus): array
    {
        $location = Location::factory()->create();

        $room = Room::factory()
            ->forLocation($location)
            ->classified('dorm_4bed', 1)
            ->create([
                'max_guests' => 4,
                'status' => 'available',
                'readiness_status' => $readinessStatus,
            ]);

        $booking = Booking::factory()->confirmed()->create([
            'room_id' => $room->id,
            'location_id' => $location->id,
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
        ]);

        $stay = Stay::factory()->forBooking($booking)->expected()->create([
            'scheduled_check_in_at' => now()->setTime(14, 0),
            'scheduled_check_out_at' => now()->addDay()->setTime(12, 0),
        ]);

        return [$location, $room, $stay];
    }
}
