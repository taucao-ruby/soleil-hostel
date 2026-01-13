<?php

namespace Tests\Unit\Mail;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Email Template Rendering Tests
 * 
 * Verifies that branded email templates render correctly with all required data.
 * These tests ensure templates don't break after updates and contain expected content.
 */
class EmailTemplateRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected Room $room;
    protected Booking $confirmedBooking;
    protected Booking $cancelledBooking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->room = Room::factory()->create(['name' => 'Sunset Suite']);

        $this->confirmedBooking = Booking::factory()->create([
            'room_id' => $this->room->id,
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'check_in' => '2026-03-01',
            'check_out' => '2026-03-05',
            'status' => BookingStatus::CONFIRMED,
            'amount' => 50000, // $500.00 in cents
        ]);

        $this->cancelledBooking = Booking::factory()->create([
            'room_id' => $this->room->id,
            'guest_name' => 'Jane Smith',
            'guest_email' => 'jane@example.com',
            'check_in' => '2026-04-01',
            'check_out' => '2026-04-03',
            'status' => BookingStatus::CANCELLED,
            'refund_amount' => 25000, // $250.00 refund
        ]);
    }

    /** @test */
    public function booking_confirmed_template_renders_with_all_data(): void
    {
        $notification = new BookingConfirmed($this->confirmedBooking);
        $mailMessage = $notification->toMail($this->confirmedBooking);

        $this->assertNotNull($mailMessage);
        $this->assertStringContainsString('Booking Confirmed', $mailMessage->subject);
        
        // Render the markdown template
        $rendered = $mailMessage->render();

        // Verify key content is present
        $this->assertStringContainsString('John Doe', $rendered);
        $this->assertStringContainsString('Sunset Suite', $rendered);
        $this->assertStringContainsString('Booking Confirmed', $rendered);
        $this->assertStringContainsString('View Your Booking', $rendered);
    }

    /** @test */
    public function booking_confirmed_template_contains_brand_elements(): void
    {
        $notification = new BookingConfirmed($this->confirmedBooking);
        $mailMessage = $notification->toMail($this->confirmedBooking);

        $rendered = $mailMessage->render();

        // Brand elements from config
        $this->assertStringContainsString(config('email-branding.name', 'Soleil Hostel'), $rendered);
        $this->assertStringContainsString(config('email-branding.tagline', 'Your Home Away From Home'), $rendered);
        $this->assertStringContainsString(config('email-branding.contact.email', 'support@soleilhostel.com'), $rendered);
    }

    /** @test */
    public function booking_cancelled_template_renders_with_refund_info(): void
    {
        $notification = new BookingCancelled($this->cancelledBooking);
        $mailMessage = $notification->toMail($this->cancelledBooking);

        $this->assertNotNull($mailMessage);
        $this->assertStringContainsString('Cancelled', $mailMessage->subject);

        $rendered = $mailMessage->render();

        // Verify refund information is present
        $this->assertStringContainsString('Jane Smith', $rendered);
        $this->assertStringContainsString('250.00', $rendered);
        $this->assertStringContainsString('refund', strtolower($rendered));
    }

    /** @test */
    public function booking_cancelled_template_renders_without_refund_when_none(): void
    {
        $bookingNoRefund = Booking::factory()->create([
            'room_id' => $this->room->id,
            'guest_name' => 'No Refund Guest',
            'guest_email' => 'norefund@example.com',
            'status' => BookingStatus::CANCELLED,
            'refund_amount' => 0,
            'payment_intent_id' => 'pi_test_123',
        ]);

        $notification = new BookingCancelled($bookingNoRefund);
        $mailMessage = $notification->toMail($bookingNoRefund);

        $rendered = $mailMessage->render();

        $this->assertStringContainsString('No Refund Guest', $rendered);
        $this->assertStringContainsString('no refund', strtolower($rendered));
    }

    /** @test */
    public function booking_updated_template_renders_with_changes(): void
    {
        $changes = [
            'check_in' => now()->addDays(10),
            'check_out' => now()->addDays(15),
        ];

        $notification = new BookingUpdated($this->confirmedBooking, $changes);
        $mailMessage = $notification->toMail($this->confirmedBooking);

        $this->assertNotNull($mailMessage);
        $this->assertStringContainsString('Updated', $mailMessage->subject);

        $rendered = $mailMessage->render();

        $this->assertStringContainsString('John Doe', $rendered);
        $this->assertStringContainsString('Changes Made', $rendered);
        $this->assertStringContainsString('Check in', $rendered);
        $this->assertStringContainsString('Check out', $rendered);
    }

    /** @test */
    public function booking_confirmed_skips_when_status_changed(): void
    {
        // Change status to cancelled after creating notification
        $this->confirmedBooking->status = BookingStatus::CANCELLED;
        $this->confirmedBooking->save();

        $notification = new BookingConfirmed($this->confirmedBooking->fresh());
        $mailMessage = $notification->toMail($this->confirmedBooking);

        $this->assertNull($mailMessage);
    }

    /** @test */
    public function booking_cancelled_skips_when_status_not_cancelled(): void
    {
        $confirmedBooking = Booking::factory()->create([
            'room_id' => $this->room->id,
            'status' => BookingStatus::CONFIRMED,
        ]);

        $notification = new BookingCancelled($confirmedBooking);
        $mailMessage = $notification->toMail($confirmedBooking);

        $this->assertNull($mailMessage);
    }

    /** @test */
    public function booking_updated_skips_when_booking_cancelled(): void
    {
        $notification = new BookingUpdated($this->cancelledBooking, ['notes' => 'test']);
        $mailMessage = $notification->toMail($this->cancelledBooking);

        $this->assertNull($mailMessage);
    }

    /** @test */
    public function email_branding_config_has_required_keys(): void
    {
        $config = config('email-branding');

        $this->assertArrayHasKey('name', $config);
        $this->assertArrayHasKey('tagline', $config);
        $this->assertArrayHasKey('colors', $config);
        $this->assertArrayHasKey('logo', $config);
        $this->assertArrayHasKey('contact', $config);
        $this->assertArrayHasKey('footer', $config);

        // Colors subkeys
        $this->assertArrayHasKey('primary', $config['colors']);
        $this->assertArrayHasKey('secondary', $config['colors']);

        // Contact subkeys
        $this->assertArrayHasKey('email', $config['contact']);
        $this->assertArrayHasKey('phone', $config['contact']);
    }

    /** @test */
    public function confirmed_template_view_exists(): void
    {
        $this->assertTrue(View::exists('mail.bookings.confirmed'));
    }

    /** @test */
    public function cancelled_template_view_exists(): void
    {
        $this->assertTrue(View::exists('mail.bookings.cancelled'));
    }

    /** @test */
    public function updated_template_view_exists(): void
    {
        $this->assertTrue(View::exists('mail.bookings.updated'));
    }

    /** @test */
    public function email_template_escapes_user_input(): void
    {
        // Create a booking directly without the Purifiable trait processing
        $maliciousBooking = Booking::factory()->create([
            'room_id' => $this->room->id,
            'guest_name' => 'Safe Name',
            'guest_email' => 'test@example.com',
            'status' => BookingStatus::CONFIRMED,
        ]);
        
        // Manually set a malicious name bypassing the Purifiable trait
        $maliciousBooking->setRawAttributes(array_merge(
            $maliciousBooking->getAttributes(),
            ['guest_name' => '<script>alert("xss")</script>']
        ));

        $notification = new BookingConfirmed($maliciousBooking);
        $mailMessage = $notification->toMail($maliciousBooking);
        $rendered = $mailMessage->render();

        // Script tags should be escaped, not rendered as raw HTML
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $rendered);
    }
}
