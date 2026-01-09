<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BookingConfirmed Notification
 * 
 * Uses Laravel's default notification system instead of custom Mailables.
 * Automatically queued for async delivery.
 * 
 * Usage:
 * ```php
 * $user->notify(new BookingConfirmed($booking));
 * // or
 * Notification::route('mail', $booking->guest_email)
 *     ->notify(new BookingConfirmed($booking));
 * ```
 */
class BookingConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Booking $booking
    ) {
        // Set queue name for better organization
        $this->onQueue('notifications');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Booking Confirmation - Soleil Hostel')
            ->greeting('Hello ' . $this->booking->guest_name . '!')
            ->line('Your booking has been confirmed.')
            ->line('**Booking Details:**')
            ->line('Room: ' . $this->booking->room->name)
            ->line('Check-in: ' . $this->booking->check_in)
            ->line('Check-out: ' . $this->booking->check_out)
            ->line('Total Price: $' . number_format($this->booking->total_price, 2))
            ->action('View Booking', url('/bookings/' . $this->booking->id))
            ->line('Thank you for choosing Soleil Hostel!')
            ->salutation('Best regards, Soleil Hostel Team');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'room_id' => $this->booking->room_id,
            'guest_name' => $this->booking->guest_name,
            'check_in' => $this->booking->check_in,
            'check_out' => $this->booking->check_out,
            'total_price' => $this->booking->total_price,
        ];
    }
}
