<?php

namespace Tests\Feature\Stays;

use App\Enums\SettlementStatus;
use App\Models\Booking;
use App\Models\Location;
use App\Models\ServiceRecoveryCase;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialLifecycleTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function booking_deposit_scopes_filter_collected_and_applied_deposits(): void
    {
        $location = Location::factory()->create();
        $room = \App\Models\Room::factory()->forLocation($location)->ready()->create(['status' => 'available']);

        $collected = Booking::factory()->forRoom($room)->withDeposit(5000)->create([
            'location_id' => $location->id,
        ]);

        $applied = Booking::factory()->forRoom($room)->depositApplied(5000)->create([
            'location_id' => $location->id,
        ]);

        Booking::factory()->forRoom($room)->create([
            'location_id' => $location->id,
        ]);

        $this->assertSame([$collected->id], Booking::query()->withDeposit()->pluck('id')->all());
        $this->assertSame([$applied->id], Booking::query()->depositApplied()->pluck('id')->all());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function service_recovery_total_exposure_and_settlement_scopes_reflect_operational_financial_state(): void
    {
        $location = Location::factory()->create();
        $room = \App\Models\Room::factory()->forLocation($location)->ready()->create(['status' => 'available']);
        $booking = Booking::factory()->forRoom($room)->confirmed()->create([
            'location_id' => $location->id,
        ]);

        $unsettled = ServiceRecoveryCase::factory()->forBooking($booking)->create([
            'refund_amount' => 3000,
            'voucher_amount' => 1000,
            'cost_delta_absorbed' => 2000,
            'settled_amount' => 500,
            'settlement_status' => SettlementStatus::PARTIALLY_SETTLED,
        ]);

        $settled = ServiceRecoveryCase::factory()->forBooking($booking)->settled(6000)->create();

        $this->assertEquals(5500, $unsettled->fresh()->totalExposure());
        $this->assertSame([$unsettled->id], ServiceRecoveryCase::query()->unsettled()->pluck('id')->all());
        $this->assertSame([$settled->id], ServiceRecoveryCase::query()->settled()->pluck('id')->all());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function invalid_deposit_and_settlement_status_values_are_rejected_by_postgresql_checks(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('CHECK constraints require PostgreSQL');
        }

        $location = Location::factory()->create();
        $room = \App\Models\Room::factory()->forLocation($location)->ready()->create(['status' => 'available']);

        $this->expectException(QueryException::class);

        DB::table('bookings')->insert([
            'room_id' => $room->id,
            'location_id' => $location->id,
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'guest_name' => 'Invalid Deposit Guest',
            'guest_email' => 'deposit@example.test',
            'status' => 'confirmed',
            'deposit_status' => 'invalid_status',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function invalid_settlement_status_value_is_rejected_by_postgresql_check_constraint(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('CHECK constraints require PostgreSQL');
        }

        $location = Location::factory()->create();
        $room = \App\Models\Room::factory()->forLocation($location)->ready()->create(['status' => 'available']);
        $booking = Booking::factory()->forRoom($room)->confirmed()->create([
            'location_id' => $location->id,
        ]);

        $this->expectException(QueryException::class);

        DB::table('service_recovery_cases')->insert([
            'booking_id' => $booking->id,
            'stay_id' => null,
            'incident_type' => 'overbooking_no_room',
            'severity' => 'medium',
            'case_status' => 'open',
            'compensation_type' => 'none',
            'settlement_status' => 'invalid_status',
            'opened_at' => now()->toDateTimeString(),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }
}
