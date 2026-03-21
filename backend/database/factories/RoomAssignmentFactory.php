<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Stay;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomAssignmentFactory extends Factory
{
    protected $model = RoomAssignment::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'stay_id' => Stay::factory(),
            'room_id' => Room::factory()->available(),
            'assignment_type' => AssignmentType::ORIGINAL,
            'assignment_status' => AssignmentStatus::ACTIVE,
            'assigned_from' => Carbon::now()->subHours($this->faker->numberBetween(1, 24)),
            'assigned_until' => null, // active by default
            'assigned_by' => null,
            'reason_code' => null,
            'notes' => null,
        ];
    }

    /**
     * State: currently active assignment (assigned_until IS NULL).
     * This is the default — listed explicitly for test clarity.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'assignment_status' => AssignmentStatus::ACTIVE,
            'assigned_until' => null,
        ]);
    }

    /**
     * State: closed assignment (assigned_until is set).
     */
    public function closed(): static
    {
        $assignedFrom = Carbon::now()->subDays($this->faker->numberBetween(2, 5));

        return $this->state(fn (array $attributes) => [
            'assignment_status' => AssignmentStatus::CLOSED,
            'assigned_from' => $assignedFrom,
            'assigned_until' => $assignedFrom->clone()->addDays($this->faker->numberBetween(1, 3)),
        ]);
    }

    /**
     * State: complimentary upgrade assignment (currently active).
     */
    public function complimentaryUpgrade(): static
    {
        return $this->state(fn (array $attributes) => [
            'assignment_type' => AssignmentType::COMPLIMENTARY_UPGRADE,
            'assignment_status' => AssignmentStatus::ACTIVE,
            'assigned_until' => null,
        ]);
    }

    /**
     * State: for a specific stay.
     */
    public function forStay(Stay $stay): static
    {
        return $this->state(fn (array $attributes) => [
            'stay_id' => $stay->id,
            'booking_id' => $stay->booking_id,
        ]);
    }

    /**
     * State: for a specific room.
     */
    public function forRoom(Room $room): static
    {
        return $this->state(fn (array $attributes) => [
            'room_id' => $room->id,
        ]);
    }
}
