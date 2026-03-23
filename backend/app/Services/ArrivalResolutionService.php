<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BlockerType;
use App\Enums\CaseStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentType;
use App\Enums\ResolutionStep;
use App\Enums\RoomReadinessStatus;
use App\Enums\StayStatus;
use App\Models\Location;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\ServiceRecoveryCase;
use App\Models\Stay;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Recommendation-only blocked-arrival escalation.
 *
 * The resolver never auto-writes assignments or recovery cases.
 * Operators must explicitly accept a recommendation through applyAcceptedRecommendation().
 */
class ArrivalResolutionService
{
    public function resolve(Stay $stay): ArrivalResolutionResult
    {
        $sourceRoom = $this->sourceRoomForStay($stay);
        $blocker = $this->blockerFor($stay);

        if ($blocker === null) {
            throw new RuntimeException(sprintf(
                'Stay %d is not currently blocked by late checkout or room readiness.',
                $stay->id
            ));
        }

        $sameLocationEquivalent = Room::query()
            ->atLocation($sourceRoom->location_id)
            ->ready()
            ->equivalentTo($sourceRoom)
            ->orderBy('max_guests')
            ->orderBy('room_tier')
            ->first();

        if ($sameLocationEquivalent instanceof Room) {
            return $this->buildResult($blocker, ResolutionStep::EQUIVALENT_SAME_LOCATION, $sameLocationEquivalent);
        }

        $sameLocationUpgrade = Room::query()
            ->atLocation($sourceRoom->location_id)
            ->ready()
            ->upgradeOver($sourceRoom)
            ->orderBy('room_tier')
            ->orderBy('max_guests')
            ->first();

        if ($sameLocationUpgrade instanceof Room) {
            return $this->buildResult($blocker, ResolutionStep::UPGRADE_SAME_LOCATION, $sameLocationUpgrade);
        }

        foreach ($this->alternateLocationsFor($sourceRoom) as $location) {
            $equivalentCandidate = $sourceRoom->equivalentCandidatesAt($location)->first();

            if ($equivalentCandidate instanceof Room) {
                return $this->buildResult($blocker, ResolutionStep::EQUIVALENT_CROSS_LOCATION, $equivalentCandidate);
            }
        }

        foreach ($this->alternateLocationsFor($sourceRoom) as $location) {
            $upgradeCandidate = $sourceRoom->upgradeCandidatesAt($location)->first();

            if ($upgradeCandidate instanceof Room) {
                return $this->buildResult($blocker, ResolutionStep::UPGRADE_CROSS_LOCATION, $upgradeCandidate);
            }
        }

        return new ArrivalResolutionResult(
            blockerType: BlockerType::NO_INTERNAL_ROOM,
            step: ResolutionStep::EXTERNAL_ESCALATION,
            recommendedRoom: null,
            assignmentType: ResolutionStep::EXTERNAL_ESCALATION->assignmentType(),
            requiresOperatorApproval: true,
            externalEscalationNote: sprintf(
                'No internal candidate found for stay %d after blocker %s; manual external relocation review required.',
                $stay->id,
                $blocker->value
            ),
        );
    }

    public function blockerFor(Stay $stay): ?BlockerType
    {
        $sourceRoom = $this->sourceRoomForStay($stay);
        $readinessStatus = $this->normalizeReadinessStatus($sourceRoom);

        if ($readinessStatus === RoomReadinessStatus::OUT_OF_SERVICE) {
            return BlockerType::OUT_OF_SERVICE;
        }

        if ($this->hasLateCheckoutConflict($stay, $sourceRoom)) {
            return BlockerType::LATE_CHECKOUT;
        }

        if (in_array($readinessStatus, [
            RoomReadinessStatus::DIRTY,
            RoomReadinessStatus::CLEANING,
            RoomReadinessStatus::INSPECTED,
        ], true)) {
            return BlockerType::ROOM_NOT_READY;
        }

        return null;
    }

    /**
     * Persist an operator-approved internal resolution.
     *
     * @return array{room_assignment: RoomAssignment, service_recovery_case: ServiceRecoveryCase}
     */
    public function applyAcceptedRecommendation(
        Stay $stay,
        ArrivalResolutionResult $result,
        ?int $operatorId = null
    ): array {
        if ($result->step === ResolutionStep::EXTERNAL_ESCALATION || ! $result->recommendedRoom instanceof Room) {
            throw new RuntimeException(
                'External escalation cannot create a room assignment because no internal room candidate exists.'
            );
        }

        $actorId = $operatorId ?? auth()->id();

        return DB::transaction(function () use ($stay, $result, $actorId): array {
            $timestamp = now();

            $candidateRoom = Room::query()
                ->lockForUpdate()
                ->findOrFail($result->recommendedRoom->id);

            if ($this->normalizeReadinessStatus($candidateRoom) !== RoomReadinessStatus::READY) {
                throw new RuntimeException(sprintf(
                    'Recommended room %d is no longer ready for arrival.',
                    $candidateRoom->id
                ));
            }

            $currentAssignment = $stay->currentRoomAssignment()->first();

            if ($currentAssignment instanceof RoomAssignment) {
                $currentAssignment->update([
                    'assigned_until' => $timestamp,
                    'assignment_status' => 'closed',
                ]);
            }

            $roomAssignment = RoomAssignment::create([
                'booking_id' => $stay->booking_id,
                'stay_id' => $stay->id,
                'room_id' => $candidateRoom->id,
                'assignment_type' => $result->assignmentType,
                'assignment_status' => 'active',
                'assigned_from' => $timestamp,
                'assigned_by' => $actorId,
                'reason_code' => $result->blockerType->value,
                'notes' => sprintf(
                    'Operator-approved arrival resolution via %s.',
                    $result->step->value
                ),
            ]);

            $candidateRoom->update([
                'readiness_status' => RoomReadinessStatus::OCCUPIED,
                'readiness_updated_at' => $timestamp,
                'readiness_updated_by' => $actorId,
            ]);

            $serviceRecoveryCase = ServiceRecoveryCase::create([
                'booking_id' => $stay->booking_id,
                'stay_id' => $stay->id,
                'incident_type' => $this->incidentTypeFor($result->blockerType),
                'severity' => $this->severityFor($result),
                'case_status' => CaseStatus::OPEN,
                'action_taken' => sprintf(
                    'Arrival resolution approved via %s for room %d.',
                    $result->step->value,
                    $candidateRoom->id
                ),
                'opened_at' => $timestamp,
                'handled_by' => $actorId,
                'notes' => $result->externalEscalationNote
                    ?? sprintf(
                        'Blocked arrival resolved from blocker %s with assignment type %s.',
                        $result->blockerType->value,
                        $result->assignmentType->value
                    ),
            ]);

            return [
                'room_assignment' => $roomAssignment,
                'service_recovery_case' => $serviceRecoveryCase,
            ];
        });
    }

    /**
     * @return EloquentCollection<int, Location>
     */
    private function alternateLocationsFor(Room $sourceRoom): EloquentCollection
    {
        return Location::query()
            ->active()
            ->where('id', '!=', $sourceRoom->location_id)
            ->orderBy('id')
            ->get();
    }

    private function buildResult(
        BlockerType $blocker,
        ResolutionStep $step,
        Room $room
    ): ArrivalResolutionResult {
        return new ArrivalResolutionResult(
            blockerType: $blocker,
            step: $step,
            recommendedRoom: $room,
            assignmentType: $step->assignmentType(),
            requiresOperatorApproval: $step->requiresOperatorApproval(),
            externalEscalationNote: $step === ResolutionStep::EXTERNAL_ESCALATION
                ? 'Manual external relocation review required.'
                : null,
        );
    }

    private function sourceRoomForStay(Stay $stay): Room
    {
        $stay->loadMissing([
            'booking.room',
            'currentRoomAssignment.room',
        ]);

        $room = $stay->currentRoomAssignment?->room ?? $stay->booking?->room;

        if (! $room instanceof Room) {
            throw new RuntimeException(sprintf(
                'Stay %d has no source room available for arrival resolution.',
                $stay->id
            ));
        }

        return $room;
    }

    private function normalizeReadinessStatus(Room $room): RoomReadinessStatus
    {
        return $room->readiness_status instanceof RoomReadinessStatus
            ? $room->readiness_status
            : RoomReadinessStatus::from((string) $room->readiness_status);
    }

    private function hasLateCheckoutConflict(Stay $stay, Room $sourceRoom): bool
    {
        return RoomAssignment::query()
            ->active()
            ->where('room_id', $sourceRoom->id)
            ->where('stay_id', '!=', $stay->id)
            ->whereHas('stay', fn ($query) => $query->where('stay_status', StayStatus::LATE_CHECKOUT->value))
            ->exists();
    }

    private function incidentTypeFor(BlockerType $blockerType): IncidentType
    {
        return match ($blockerType) {
            BlockerType::LATE_CHECKOUT => IncidentType::LATE_CHECKOUT_BLOCKING_ARRIVAL,
            BlockerType::ROOM_NOT_READY,
            BlockerType::OUT_OF_SERVICE => IncidentType::ROOM_UNAVAILABLE_MAINTENANCE,
            BlockerType::NO_INTERNAL_ROOM => IncidentType::OVERBOOKING_NO_ROOM,
        };
    }

    private function severityFor(ArrivalResolutionResult $result): IncidentSeverity
    {
        return match ($result->step) {
            ResolutionStep::EQUIVALENT_SAME_LOCATION => IncidentSeverity::LOW,
            ResolutionStep::UPGRADE_SAME_LOCATION,
            ResolutionStep::EQUIVALENT_CROSS_LOCATION => IncidentSeverity::MEDIUM,
            ResolutionStep::UPGRADE_CROSS_LOCATION,
            ResolutionStep::EXTERNAL_ESCALATION => IncidentSeverity::HIGH,
        };
    }
}
