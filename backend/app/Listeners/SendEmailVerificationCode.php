<?php

namespace App\Listeners;

use App\Services\EmailVerificationCodeService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Log;

class SendEmailVerificationCode
{
    public function __construct(
        private readonly EmailVerificationCodeService $service,
    ) {}

    public function handle(Registered $event): void
    {
        if (! $event->user instanceof MustVerifyEmail) {
            return;
        }

        if ($event->user->hasVerifiedEmail()) {
            return;
        }

        try {
            $this->service->issue($event->user);
        } catch (\Exception $e) {
            Log::warning('Failed to send verification code on registration', [
                'user_id' => $event->user->id,
                'email' => $event->user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
