<?php

namespace Tests\Unit\Notifications;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BookingNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    /** @test */
    public function booking_confirmed_notification_can_be_sent()
    {
        // Arrange
        $room = Room::factory()->create(['name' => 'Deluxe Room']);
        $booking = Booking::factory()->create([
            'room_id' => $room->id,
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'check_in' => '2025-12-01',
            'check_out' => '2025-12-05',
            'total_price' => 400.00,
        ]);

        // Act
        Notification::route('mail', $booking->guest_email)
            ->notify(new BookingConfirmed($booking));

        // Assert
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable(),
            BookingConfirmed::class,
            function ($notification, $channels, $notifiable) use ($booking) {
                return $notifiable->routes['mail'] === $booking->guest_email;
            }
        );
    }

    /** @test */
    public function booking_cancelled_notification_can_be_sent()
    {
        // Arrange
        $room = Room::factory()->create();
        $booking = Booking::factory()->create([
            'room_id' => $room->id,
            'guest_email' => 'guest@example.com',
        ]);

        // Act
        Notification::route('mail', $booking->guest_email)
            ->notify(new BookingCancelled($booking));

        // Assert
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable(),
            BookingCancelled::class
        );
    }

    /** @test */
    public function booking_updated_notification_includes_changes()
    {
        // Arrange
        $room = Room::factory()->create();
        $booking = Booking::factory()->create([
            'room_id' => $room->id,
            'guest_email' => 'guest@example.com',
            'check_in' => '2025-12-01',
            'check_out' => '2025-12-05',
        ]);

        $changes = [
            'check_in' => '2025-12-02',
            'check_out' => '2025-12-06',
        ];

        // Act
        Notification::route('mail', $booking->guest_email)
            ->notify(new BookingUpdated($booking, $changes));

        // Assert
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable(),
            BookingUpdated::class,
            function ($notification) use ($changes) {
                return $notification->changes === $changes;
            }
        );
    }

    /** @test */
    public function booking_confirmation_contains_correct_details()
    {
        // Arrange
        $room = Room::factory()->create(['name' => 'Ocean View Suite']);
        $booking = Booking::factory()->create([
            'room_id' => $room->id,
            'guest_name' => 'Jane Smith',
            'guest_email' => 'jane@example.com',
            'check_in' => '2025-12-10',
            'check_out' => '2025-12-15',
            'total_price' => 750.00,
        ]);

        // Act
        $notification = new BookingConfirmed($booking);
        $mailMessage = $notification->toMail(new \Illuminate\Notifications\AnonymousNotifiable());

        // Assert
        $this->assertStringContainsString('Hello Jane Smith!', $mailMessage->greeting);
        $this->assertStringContainsString('Ocean View Suite', $mailMessage->introLines[1]);
        $this->assertStringContainsString('2025-12-10', $mailMessage->introLines[2]);
        $this->assertStringContainsString('2025-12-15', $mailMessage->introLines[3]);
        $this->assertStringContainsString('750.00', $mailMessage->introLines[4]);
    }

    /** @test */
    public function notifications_are_queued()
    {
        $notification = new BookingConfirmed(Booking::factory()->make());

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $notification);
    }

    /** @test */
    public function notifications_use_correct_queue()
    {
        $booking = Booking::factory()->make();
        $notification = new BookingConfirmed($booking);

        $this->assertEquals('notifications', $notification->queue);
    }
}
