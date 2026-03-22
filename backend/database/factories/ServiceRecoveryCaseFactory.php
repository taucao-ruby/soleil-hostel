<?php

namespace Database\Factories;

use App\Enums\CaseStatus;
use App\Enums\CompensationType;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentType;
use App\Models\Booking;
use App\Models\ServiceRecoveryCase;
use App\Models\Stay;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceRecoveryCaseFactory extends Factory
{
    protected $model = ServiceRecoveryCase::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'stay_id' => null, // nullable — incident may predate stay creation
            'incident_type' => IncidentType::ROOM_UNAVAILABLE_MAINTENANCE,
            'severity' => IncidentSeverity::MEDIUM,
            'case_status' => CaseStatus::OPEN,
            'action_taken' => null,
            'external_hotel_name' => null,
            'external_booking_reference' => null,
            'compensation_type' => CompensationType::NONE,
            'refund_amount' => null,
            'voucher_amount' => null,
            'cost_delta_absorbed' => null,
            'handled_by' => null,
            'opened_at' => Carbon::now()->subHours($this->faker->numberBetween(1, 24)),
            'resolved_at' => null,
            'notes' => null,
        ];
    }

    /**
     * State: open case (no resolution yet).
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'case_status' => CaseStatus::OPEN,
            'resolved_at' => null,
        ]);
    }

    /**
     * State: resolved case with compensation.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'case_status' => CaseStatus::RESOLVED,
            'action_taken' => $this->faker->sentence(),
            'compensation_type' => CompensationType::REFUND_PARTIAL,
            'refund_amount' => $this->faker->numberBetween(1000, 10000), // $10-$100 in cents
            'resolved_at' => Carbon::now()->subHours($this->faker->numberBetween(1, 12)),
        ]);
    }

    /**
     * State: for a specific booking.
     */
    public function forBooking(Booking $booking): static
    {
        return $this->state(fn (array $attributes) => [
            'booking_id' => $booking->id,
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
     * State: critical severity.
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => IncidentSeverity::CRITICAL,
        ]);
    }

    /**
     * State: external relocation incident with hotel details.
     */
    public function externalRelocation(): static
    {
        return $this->state(fn (array $attributes) => [
            'incident_type' => IncidentType::EXTERNAL_RELOCATION,
            'external_hotel_name' => $this->faker->company().' Hotel',
            'external_booking_reference' => strtoupper($this->faker->bothify('??######')),
            'compensation_type' => CompensationType::REFUND_PLUS_VOUCHER,
            'cost_delta_absorbed' => $this->faker->numberBetween(5000, 50000), // $50-$500 in cents
        ]);
    }
}
