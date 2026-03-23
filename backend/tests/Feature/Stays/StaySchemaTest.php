<?php

namespace Tests\Feature\Stays;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StaySchemaTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_stays_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('stays'));
        $this->assertTrue(Schema::hasColumns('stays', [
            'id',
            'booking_id',
            'stay_status',
            'scheduled_check_in_at',
            'scheduled_check_out_at',
            'actual_check_in_at',
            'actual_check_out_at',
            'late_checkout_minutes',
            'late_checkout_fee_amount',
            'no_show_at',
            'checked_in_by',
            'checked_out_by',
            'created_at',
            'updated_at',
        ]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_room_assignments_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('room_assignments'));
        $this->assertTrue(Schema::hasColumns('room_assignments', [
            'id',
            'booking_id',
            'stay_id',
            'room_id',
            'assignment_type',
            'assignment_status',
            'assigned_from',
            'assigned_until',
            'assigned_by',
            'reason_code',
            'notes',
            'created_at',
            'updated_at',
        ]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_service_recovery_cases_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('service_recovery_cases'));
        $this->assertTrue(Schema::hasColumns('service_recovery_cases', [
            'id',
            'booking_id',
            'stay_id',
            'incident_type',
            'severity',
            'case_status',
            'action_taken',
            'external_hotel_name',
            'external_booking_reference',
            'compensation_type',
            'refund_amount',
            'voucher_amount',
            'cost_delta_absorbed',
            'handled_by',
            'opened_at',
            'resolved_at',
            'notes',
            'created_at',
            'updated_at',
        ]));
    }
}
