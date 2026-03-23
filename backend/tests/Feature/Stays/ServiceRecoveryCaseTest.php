<?php

namespace Tests\Feature\Stays;

use App\Enums\CaseStatus;
use App\Enums\CompensationType;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentType;
use App\Models\Booking;
use App\Models\ServiceRecoveryCase;
use App\Models\Stay;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Service recovery case tests — incident tracking and compensation audit.
 *
 * Covers:
 * - Case with null stay_id (incident may predate stay)
 * - Compensation amounts stored and retrieved correctly (cents)
 * - scopeOpen excludes resolved and closed cases
 * - scopeBySeverity filters correctly
 * - CHECK constraints (PostgreSQL only)
 */
class ServiceRecoveryCaseTest extends TestCase
{
    use RefreshDatabase;

    private function isPgsql(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }

    // ===== NULL STAY_ID ALLOWED =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_case_with_null_stay_id_is_valid(): void
    {
        $booking = Booking::factory()->confirmed()->create();

        $case = ServiceRecoveryCase::factory()->forBooking($booking)->create([
            'stay_id' => null,
        ]);

        $this->assertDatabaseHas('service_recovery_cases', [
            'id' => $case->id,
            'stay_id' => null,
        ]);
        $this->assertNull($case->stay_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_case_can_be_linked_to_a_stay(): void
    {
        $stay = Stay::factory()->inHouse()->create();

        $case = ServiceRecoveryCase::factory()->forStay($stay)->create();

        $this->assertDatabaseHas('service_recovery_cases', [
            'id' => $case->id,
            'stay_id' => $stay->id,
        ]);
        $this->assertEquals($stay->id, $case->stay->id);
    }

    // ===== COMPENSATION AMOUNTS (CENTS) =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_compensation_amounts_stored_and_retrieved_in_cents(): void
    {
        $booking = Booking::factory()->confirmed()->create();

        $case = ServiceRecoveryCase::factory()->forBooking($booking)->create([
            'compensation_type' => CompensationType::REFUND_PLUS_VOUCHER,
            'refund_amount' => 5000,       // $50.00
            'voucher_amount' => 2500,      // $25.00
            'cost_delta_absorbed' => 15000, // $150.00
        ]);

        $case->refresh();

        $this->assertEquals(5000, $case->refund_amount);
        $this->assertEquals(2500, $case->voucher_amount);
        $this->assertEquals(15000, $case->cost_delta_absorbed);
        $this->assertEquals(CompensationType::REFUND_PLUS_VOUCHER, $case->compensation_type);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_compensation_amounts_can_be_null(): void
    {
        $booking = Booking::factory()->confirmed()->create();

        $case = ServiceRecoveryCase::factory()->forBooking($booking)->create([
            'compensation_type' => CompensationType::NONE,
            'refund_amount' => null,
            'voucher_amount' => null,
            'cost_delta_absorbed' => null,
        ]);

        $this->assertNull($case->refund_amount);
        $this->assertNull($case->voucher_amount);
        $this->assertNull($case->cost_delta_absorbed);
    }

    // ===== scopeOpen =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_scope_open_excludes_resolved_and_closed(): void
    {
        $booking = Booking::factory()->confirmed()->create();

        ServiceRecoveryCase::factory()->forBooking($booking)->open()->create();
        ServiceRecoveryCase::factory()->forBooking($booking)->create(['case_status' => CaseStatus::INVESTIGATING]);
        ServiceRecoveryCase::factory()->forBooking($booking)->create(['case_status' => CaseStatus::ACTION_IN_PROGRESS]);
        ServiceRecoveryCase::factory()->forBooking($booking)->resolved()->create();
        ServiceRecoveryCase::factory()->forBooking($booking)->create(['case_status' => CaseStatus::CLOSED]);

        $openCases = ServiceRecoveryCase::open()->get();

        $this->assertCount(3, $openCases);

        foreach ($openCases as $case) {
            $this->assertNotContains($case->case_status, [CaseStatus::RESOLVED, CaseStatus::CLOSED]);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_scope_open_returns_empty_when_all_closed(): void
    {
        $booking = Booking::factory()->confirmed()->create();

        ServiceRecoveryCase::factory()->forBooking($booking)->resolved()->create();
        ServiceRecoveryCase::factory()->forBooking($booking)->create(['case_status' => CaseStatus::CLOSED]);

        $this->assertCount(0, ServiceRecoveryCase::open()->get());
    }

    // ===== scopeBySeverity =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_scope_by_severity_filters_correctly(): void
    {
        $booking = Booking::factory()->confirmed()->create();

        ServiceRecoveryCase::factory()->forBooking($booking)->create(['severity' => IncidentSeverity::LOW]);
        ServiceRecoveryCase::factory()->forBooking($booking)->create(['severity' => IncidentSeverity::MEDIUM]);
        ServiceRecoveryCase::factory()->forBooking($booking)->critical()->create();
        ServiceRecoveryCase::factory()->forBooking($booking)->critical()->create();

        $criticalCases = ServiceRecoveryCase::bySeverity(IncidentSeverity::CRITICAL->value)->get();

        $this->assertCount(2, $criticalCases);

        foreach ($criticalCases as $case) {
            $this->assertEquals(IncidentSeverity::CRITICAL, $case->severity);
        }
    }

    // ===== CHECK CONSTRAINTS (PostgreSQL only) =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_invalid_incident_type_rejected_by_check_constraint(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('CHECK constraints require PostgreSQL');
        }

        $booking = Booking::factory()->confirmed()->create();

        $this->expectException(QueryException::class);

        // Bypass Eloquent enum cast with raw DB insert to exercise the DB CHECK constraint
        DB::table('service_recovery_cases')->insert([
            'booking_id' => $booking->id,
            'stay_id' => null,
            'incident_type' => 'not_a_valid_type',
            'severity' => 'medium',
            'case_status' => 'open',
            'compensation_type' => 'none',
            'opened_at' => now()->toDateTimeString(),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_invalid_severity_rejected_by_check_constraint(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('CHECK constraints require PostgreSQL');
        }

        $booking = Booking::factory()->confirmed()->create();

        $this->expectException(QueryException::class);

        // Bypass Eloquent enum cast with raw DB insert to exercise the DB CHECK constraint
        DB::table('service_recovery_cases')->insert([
            'booking_id' => $booking->id,
            'stay_id' => null,
            'incident_type' => 'overbooking_no_room',
            'severity' => 'ultra',
            'case_status' => 'open',
            'compensation_type' => 'none',
            'opened_at' => now()->toDateTimeString(),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_invalid_case_status_rejected_by_check_constraint(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('CHECK constraints require PostgreSQL');
        }

        $booking = Booking::factory()->confirmed()->create();

        $this->expectException(QueryException::class);

        // Bypass Eloquent enum cast with raw DB insert to exercise the DB CHECK constraint
        // 'pending' is valid for bookings.status but not in chk_src_case_status
        DB::table('service_recovery_cases')->insert([
            'booking_id' => $booking->id,
            'stay_id' => null,
            'incident_type' => 'overbooking_no_room',
            'severity' => 'medium',
            'case_status' => 'pending',
            'compensation_type' => 'none',
            'opened_at' => now()->toDateTimeString(),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    // ===== ENUM CASTS =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_enum_fields_are_cast_to_enum_instances(): void
    {
        $booking = Booking::factory()->confirmed()->create();

        $case = ServiceRecoveryCase::factory()->forBooking($booking)->create([
            'incident_type' => IncidentType::OVERBOOKING_NO_ROOM,
            'severity' => IncidentSeverity::HIGH,
            'case_status' => CaseStatus::INVESTIGATING,
            'compensation_type' => CompensationType::VOUCHER,
        ]);

        $case->refresh();

        $this->assertInstanceOf(IncidentType::class, $case->incident_type);
        $this->assertInstanceOf(IncidentSeverity::class, $case->severity);
        $this->assertInstanceOf(CaseStatus::class, $case->case_status);
        $this->assertInstanceOf(CompensationType::class, $case->compensation_type);

        $this->assertEquals(IncidentType::OVERBOOKING_NO_ROOM, $case->incident_type);
        $this->assertEquals(IncidentSeverity::HIGH, $case->severity);
        $this->assertEquals(CaseStatus::INVESTIGATING, $case->case_status);
        $this->assertEquals(CompensationType::VOUCHER, $case->compensation_type);
    }
}
