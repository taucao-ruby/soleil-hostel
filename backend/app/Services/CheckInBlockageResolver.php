<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Enums\CaseStatus;
use App\Enums\CompensationType;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentType;
use App\Enums\RoomReadinessStatus;
use App\Enums\StayStatus;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\ServiceRecoveryCase;
use App\Models\Stay;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CheckInBlockageResolver
{
    public const ROOM_TYPE_FIELD = 'room_type_code';

    /**
     * @return array{
     *     blockage_type: string|null,
     *     resolution: string,
     *     new_room_id: int|null,
     *     case_id: int|null,
     *     requires_manual_action: bool,
     *     manual_action_description: string,
     *     attempted_steps: list<array<string, mixed>>,
     *     destination_location_id?: int,
     *     destination_room_id?: int
     * }
     */
    public function resolve(Stay $stay): array
    {
        $stay->loadMissing('booking', 'currentRoomAssignment.room');

        $blockageType = $this->detectBlockageType($stay);

        if ($blockageType === null) {
            return [
                'blockage_type' => null,
                'resolution' => 'no_blockage',
                'new_room_id' => null,
                'case_id' => null,
                'requires_manual_action' => false,
                'manual_action_description' => '',
                'attempted_steps' => [['step' => 'detect', 'outcome' => 'no_blockage']],
            ];
        }

        $currentAssignment = $stay->currentRoomAssignment;
        assert($currentAssignment !== null);
        $originalRoom = $currentAssignment->room;
        assert($originalRoom !== null);
        $checkInDate = $stay->scheduled_check_in_at ?? Carbon::today();
        $attemptedSteps = [['step' => 'detect', 'outcome' => "blockage={$blockageType}"]];

        // Step 1: Equivalent swap in same location
        $candidates = $this->findCandidateRooms(
            locationId: $originalRoom->location_id,
            typeCode: $originalRoom->room_type_code->value,
            minTier: $originalRoom->room_tier,
            exactTier: true,
            excludeRoomId: $originalRoom->id,
        );

        if ($candidates->isNotEmpty()) {
            /** @var Room $newRoom */
            $newRoom = $candidates->first();
            $result = DB::transaction(function () use ($stay, $newRoom, $blockageType) {
                // Close existing active assignment before creating new one
                $this->closeActiveAssignment($stay);

                $assignment = RoomAssignment::create([
                    'booking_id' => $stay->booking_id,
                    'stay_id' => $stay->id,
                    'room_id' => $newRoom->id,
                    'assignment_type' => AssignmentType::EQUIVALENT_SWAP,
                    'assignment_status' => AssignmentStatus::ACTIVE,
                    'assigned_from' => now(),
                    'assigned_until' => null,
                    'notes' => "Automatic equivalent swap due to {$blockageType}.",
                ]);

                $case = ServiceRecoveryCase::create([
                    'booking_id' => $stay->booking_id,
                    'stay_id' => $stay->id,
                    'incident_type' => IncidentType::EQUIVALENT_SWAP,
                    'severity' => IncidentSeverity::MEDIUM,
                    'case_status' => CaseStatus::ACTION_IN_PROGRESS,
                    'action_taken' => "Equivalent swap: moved to room {$newRoom->name} (#{$newRoom->room_number}) due to {$blockageType}.",
                    'compensation_type' => CompensationType::NONE,
                    'opened_at' => now(),
                ]);

                return ['assignment' => $assignment, 'case' => $case];
            });

            $attemptedSteps[] = ['step' => 'equivalent_swap', 'outcome' => 'resolved'];

            return [
                'blockage_type' => $blockageType,
                'resolution' => 'equivalent_swap',
                'new_room_id' => $newRoom->id,
                'case_id' => (int) $result['case']->id,
                'requires_manual_action' => false,
                'manual_action_description' => '',
                'attempted_steps' => $attemptedSteps,
            ];
        }
        $attemptedSteps[] = ['step' => 'equivalent_swap', 'outcome' => 'no_candidates'];

        // Step 2: Complimentary upgrade in same location (any type, higher tier)
        $candidates = $this->findCandidateRooms(
            locationId: $originalRoom->location_id,
            typeCode: null,
            minTier: $originalRoom->room_tier + 1,
            exactTier: false,
            excludeRoomId: $originalRoom->id,
        );

        if ($candidates->isNotEmpty()) {
            /** @var Room $newRoom */
            $newRoom = $candidates->first(); // ordered by tier ASC = minimum upgrade
            $result = DB::transaction(function () use ($stay, $newRoom, $originalRoom, $blockageType) {
                // Close existing active assignment before creating new one
                $this->closeActiveAssignment($stay);

                $assignment = RoomAssignment::create([
                    'booking_id' => $stay->booking_id,
                    'stay_id' => $stay->id,
                    'room_id' => $newRoom->id,
                    'assignment_type' => AssignmentType::COMPLIMENTARY_UPGRADE,
                    'assignment_status' => AssignmentStatus::ACTIVE,
                    'assigned_from' => now(),
                    'assigned_until' => null,
                    'notes' => "Complimentary upgrade from tier {$originalRoom->room_tier} to tier {$newRoom->room_tier} due to {$blockageType}.",
                ]);

                $case = ServiceRecoveryCase::create([
                    'booking_id' => $stay->booking_id,
                    'stay_id' => $stay->id,
                    'incident_type' => IncidentType::COMPLIMENTARY_UPGRADE,
                    'severity' => IncidentSeverity::MEDIUM,
                    'case_status' => CaseStatus::ACTION_IN_PROGRESS,
                    'action_taken' => "Complimentary upgrade: tier {$originalRoom->room_tier} → tier {$newRoom->room_tier}, room {$newRoom->name} (#{$newRoom->room_number}) due to {$blockageType}.",
                    'compensation_type' => CompensationType::COMPLIMENTARY_UPGRADE,
                    'opened_at' => now(),
                ]);

                return ['assignment' => $assignment, 'case' => $case];
            });

            $attemptedSteps[] = ['step' => 'complimentary_upgrade', 'outcome' => 'resolved'];

            return [
                'blockage_type' => $blockageType,
                'resolution' => 'complimentary_upgrade',
                'new_room_id' => $newRoom->id,
                'case_id' => (int) $result['case']->id,
                'requires_manual_action' => false,
                'manual_action_description' => '',
                'attempted_steps' => $attemptedSteps,
            ];
        }
        $attemptedSteps[] = ['step' => 'complimentary_upgrade', 'outcome' => 'no_candidates'];

        // Step 3: Internal relocation to another location (same type, >= tier)
        $candidates = $this->findCandidateRooms(
            locationId: null,
            typeCode: $originalRoom->room_type_code->value,
            minTier: $originalRoom->room_tier,
            exactTier: false,
            excludeRoomId: $originalRoom->id,
            excludeLocationId: $originalRoom->location_id,
        );

        if ($candidates->isNotEmpty()) {
            /** @var Room $destRoom */
            $destRoom = $candidates->first();
            $case = DB::transaction(function () use ($stay, $destRoom, $blockageType) {
                return ServiceRecoveryCase::create([
                    'booking_id' => $stay->booking_id,
                    'stay_id' => $stay->id,
                    'incident_type' => IncidentType::INTERNAL_RELOCATION,
                    'severity' => IncidentSeverity::HIGH,
                    'case_status' => CaseStatus::ACTION_IN_PROGRESS,
                    'action_taken' => "Internal relocation recommended to location {$destRoom->location_id}, room {$destRoom->name} (#{$destRoom->room_number}) due to {$blockageType}.",
                    'compensation_type' => CompensationType::NONE,
                    'opened_at' => now(),
                    'escalated_at' => now(),
                ]);
            });

            $attemptedSteps[] = [
                'step' => 'internal_relocation',
                'outcome' => 'resolved',
                'destination_location_id' => $destRoom->location_id,
            ];

            return [
                'blockage_type' => $blockageType,
                'resolution' => 'internal_relocation_recommended',
                'destination_location_id' => $destRoom->location_id,
                'destination_room_id' => $destRoom->id,
                'new_room_id' => null,
                'case_id' => (int) $case->id,
                'requires_manual_action' => true,
                'manual_action_description' => 'Transfer booking to destination location. RoomAssignment must be created after booking transfer is confirmed by staff.',
                'attempted_steps' => $attemptedSteps,
            ];
        }
        $attemptedSteps[] = ['step' => 'internal_relocation', 'outcome' => 'no_candidates'];

        // Step 4: External relocation escalation (fallback)
        $case = DB::transaction(function () use ($stay, $blockageType) {
            return ServiceRecoveryCase::create([
                'booking_id' => $stay->booking_id,
                'stay_id' => $stay->id,
                'incident_type' => IncidentType::EXTERNAL_RELOCATION,
                'severity' => IncidentSeverity::HIGH,
                'case_status' => CaseStatus::ACTION_IN_PROGRESS,
                'action_taken' => "Automatic blockage escalation triggered by {$blockageType}.",
                'compensation_type' => CompensationType::NONE,
                'opened_at' => now(),
                'escalated_at' => now(),
                'notes' => 'No internal rooms available (equivalent swap, upgrade, or cross-location). Manual operator review required before arranging external accommodation.',
            ]);
        });

        $attemptedSteps[] = ['step' => 'external_relocation_escalation', 'outcome' => 'created_case'];

        return [
            'blockage_type' => $blockageType,
            'resolution' => 'external_relocation_escalated',
            'new_room_id' => null,
            'case_id' => (int) $case->id,
            'requires_manual_action' => true,
            'manual_action_description' => 'No internal rooms available. Review external accommodation options and arrange transfer manually.',
            'attempted_steps' => $attemptedSteps,
        ];
    }

    /**
     * Find candidate rooms matching the given criteria.
     *
     * @return Collection<int, Room>
     */
    private function findCandidateRooms(
        ?int $locationId,
        ?string $typeCode,
        int $minTier,
        bool $exactTier,
        int $excludeRoomId,
        ?int $excludeLocationId = null,
    ): Collection {
        $query = Room::query()
            ->where('readiness_status', RoomReadinessStatus::READY->value)
            ->where('id', '!=', $excludeRoomId);

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        if ($excludeLocationId !== null) {
            $query->where('location_id', '!=', $excludeLocationId);
        }

        if ($typeCode !== null) {
            $query->where('room_type_code', $typeCode);
        }

        if ($exactTier) {
            $query->where('room_tier', $minTier);
        } else {
            $query->where('room_tier', '>=', $minTier);
        }

        // Exclude rooms with active room assignments (assigned_until IS NULL)
        $query->whereDoesntHave('roomAssignments', function ($q) {
            $q->whereNull('assigned_until');
        });

        return $query->orderBy('room_tier', 'asc')->get();
    }

    /**
     * Close the current active assignment for a stay (set assigned_until = now).
     * Must be called before creating a new active assignment due to the
     * udx_room_assignments_one_active_per_stay partial unique constraint.
     */
    private function closeActiveAssignment(Stay $stay): void
    {
        RoomAssignment::where('stay_id', $stay->id)
            ->whereNull('assigned_until')
            ->update([
                'assigned_until' => now(),
                'assignment_status' => AssignmentStatus::CLOSED,
            ]);
    }

    private function detectBlockageType(Stay $stay): ?string
    {
        $assignment = $stay->currentRoomAssignment;

        if (! $assignment) {
            return 'no_assignment';
        }

        $room = $assignment->room;

        if (! $room) {
            return 'no_assignment';
        }

        $blockingStayExists = Stay::query()
            ->whereKeyNot($stay->id)
            ->whereIn('stay_status', array_map(
                static fn (StayStatus $status) => $status->value,
                StayStatus::inHouseStatuses()
            ))
            ->whereHas('currentRoomAssignment', fn ($query) => $query->where('room_id', $room->id))
            ->exists();

        if ($blockingStayExists || $room->readiness_status === RoomReadinessStatus::OCCUPIED) {
            return 'late_checkout';
        }

        return match ($room->readiness_status) {
            RoomReadinessStatus::DIRTY => 'room_dirty',
            RoomReadinessStatus::CLEANING => 'room_cleaning',
            RoomReadinessStatus::INSPECTED => 'room_inspected_pending',
            RoomReadinessStatus::OUT_OF_SERVICE => 'out_of_service',
            default => null,
        };
    }
}
