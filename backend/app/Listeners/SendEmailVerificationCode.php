<?php

namespace App\Listeners;

use App\Exceptions\OtpCooldownException;
use App\Models\User;
use App\Services\EmailVerificationCodeService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\MustVerifyEmail;

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

        // All authenticatables in this application are App\Models\User (auth.php uses a single
        // Eloquent provider with App\Models\User). This instanceof check narrows the type safely
        // and is a truthful runtime guard — the service depends on notify() which is not in
        // the Authenticatable or MustVerifyEmail interfaces.
        if (! $event->user instanceof User) {
            return;
        }

        if ($event->user->hasVerifiedEmail()) {
            return;
        }

        try {
            $this->service->issue($event->user);
        } catch (OtpCooldownException) {
            // Cooldown active — registration is a one-shot path, but if the same user
            // somehow triggers a second Registered event inside the cooldown window we
            // skip silently rather than logging spurious warnings.
        }
    }
}
