<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $code,
        private readonly int $expiryMinutes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $formatted = substr($this->code, 0, 3).' '.substr($this->code, 3, 3);

        return (new MailMessage)
            ->subject('Mã xác minh email — Soleil Hostel')
            ->greeting("Xin chào, {$notifiable->name}!")
            ->line('Mã xác minh email của bạn là:')
            ->line("**{$formatted}**")
            ->line("Mã này có hiệu lực trong {$this->expiryMinutes} phút.")
            ->line('Nếu bạn không yêu cầu xác minh này, vui lòng bỏ qua email này.')
            ->salutation('Trân trọng, Soleil Hostel');
    }
}
