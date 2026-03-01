<?php

namespace Tests\Unit\Models;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for H-01: cancellation_reason must be in Booking::$fillable.
 *
 * Without this, mass assignment via $booking->update(['cancellation_reason' => '...'])
 * silently drops the field.
 */
class BookingFillableTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancellation_reason_is_fillable(): void
    {
        $booking = new Booking;
        $fillable = $booking->getFillable();

        $this->assertContains('cancellation_reason', $fillable);
    }

    public function test_cancellation_reason_persists_via_mass_assignment(): void
    {
        $booking = Booking::factory()->create([
            'status' => 'pending',
        ]);

        $booking->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Changed travel plans',
        ]);

        $booking->refresh();

        $this->assertEquals('Changed travel plans', $booking->cancellation_reason);
    }

    public function test_cancellation_audit_fields_all_fillable(): void
    {
        $booking = new Booking;
        $fillable = $booking->getFillable();

        $this->assertContains('cancelled_at', $fillable);
        $this->assertContains('cancelled_by', $fillable);
        $this->assertContains('cancellation_reason', $fillable);
    }
}
