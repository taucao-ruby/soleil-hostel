<?php

namespace Tests\Feature\Listeners;

use App\Events\BookingCreated;
use App\Events\BookingDeleted;
use App\Events\BookingUpdated as BookingUpdatedEvent;
use App\Listeners\SendBookingConfirmation;
use App\Listeners\SendBookingCancellation;
use App\Listeners\SendBookingUpdateNotification;
use App\Models\Booking;
use App\Models\Room;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingUpdated as BookingUpdatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BookingNotificationListenerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    /** @test */
    public function send_booking_confirmation_listener_sends_notification()
    {
        // Arrange
        $room = Room::factory()->create();
        $booking = Booking::factory()->create([
            'room_id' => $room->id,
            'guest_email' => 'guest@example.com',
        ]);
        $event = new BookingCreated($booking);
        $listener = new SendBookingConfirmation();

        // Act
        $listener->handle($event);

        // Assert
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable(),
            BookingConfirmed::class,
            function ($notification, $channels, $notifiable) use ($booking) {
                return $notifiable->routes['mail'] === $booking->guest_email
                    && $notification->booking->id === $booking->id;
            }
        );
    }

    /** @test */
    public function send_booking_cancellation_listener_sends_notification()
    {
        // Arrange
        $room = Room::factory()->create();
        $booking = Booking::factory()->create([
            'room_id' => $room->id,
            'guest_email' => 'guest@example.com',
        ]);
        $event = new BookingDeleted($booking);
        $listener = new SendBookingCancellation();

        // Act
        $listener->handle($event);

        // Assert
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable(),
            BookingCancelled::class,
            function ($notification, $channels, $notifiable) use ($booking) {
                return $notifiable->routes['mail'] === $booking->guest_email
                    && $notification->booking->id === $booking->id;
            }
        );
    }

    /** @test */
    public function send_booking_update_listener_sends_notification_with_changes()
    {
        // Arrange
        $room = Room::factory()->create();
        $oldBooking = (object) [
            'check_in' => '2025-12-01',
            'check_out' => '2025-12-05',
        ];
        $newBooking = Booking::factory()->create([
            'room_id' => $room->id,
            'guest_email' => 'guest@example.com',
            'check_in' => '2025-12-02',
            'check_out' => '2025-12-06',
        ]);

        $event = new BookingUpdatedEvent($newBooking, $oldBooking);
        $listener = new SendBookingUpdateNotification();

        // Act
        $listener->handle($event);

        // Assert
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable(),
            BookingUpdatedNotification::class,
            function ($notification, $channels, $notifiable) use ($newBooking) {
                return $notifiable->routes['mail'] === $newBooking->guest_email
                    && isset($notification->changes['check_in'])
                    && isset($notification->changes['check_out']);
            }
        );
    }

    /** @test */
    public function send_booking_update_listener_does_not_send_if_no_changes()
    {
        // Arrange
        $room = Room::factory()->create();
        $oldBooking = (object) [
            'check_in' => '2025-12-01',
            'check_out' => '2025-12-05',
        ];
        $newBooking = Booking::factory()->create([
            'room_id' => $room->id,
            'guest_email' => 'guest@example.com',
            'check_in' => '2025-12-01',  // Same as old
            'check_out' => '2025-12-05',  // Same as old
        ]);

        $event = new BookingUpdatedEvent($newBooking, $oldBooking);
        $listener = new SendBookingUpdateNotification();

        // Act
        $listener->handle($event);

        // Assert
        Notification::assertNothingSent();
    }

    /** @test */
    public function booking_created_event_triggers_confirmation_email()
    {
        // Arrange
        $room = Room::factory()->create();
        $booking = Booking::factory()->create([
            'room_id' => $room->id,
            'guest_email' => 'newbooking@example.com',
        ]);

        // Act
        event(new BookingCreated($booking));

        // Assert - Event listener should have sent the notification
        // Note: In a real scenario, you'd need to process the queue
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable(),
            BookingConfirmed::class
        );
    }
}
