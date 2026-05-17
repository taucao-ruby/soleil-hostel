<?php

namespace App\Providers;

use App\Directives\PurifyDirective;
use App\Exceptions\EnvironmentConfigException;
use App\Macros\FormRequestPurifyMacro;
use App\Models\Booking;
use App\Models\PersonalAccessToken;
use App\Observers\BookingObserver;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\ContactMessageRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use App\Repositories\EloquentBookingRepository;
use App\Repositories\EloquentContactMessageRepository;
use App\Repositories\EloquentRoomRepository;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    // NOTE: $policies removed (Batch 4, 3A). All model-policy bindings live in
    // AuthServiceProvider — having two providers declare overlapping arrays is dead
    // code today (this class never calls parent::boot()) and silently doubles up the
    // moment someone adds parent::boot() here. AuthServiceProvider is the single source.

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind BookingRepositoryInterface to EloquentBookingRepository
        // This enables dependency injection of the repository in services/controllers
        $this->app->bind(
            BookingRepositoryInterface::class,
            EloquentBookingRepository::class
        );

        // Bind RoomRepositoryInterface to EloquentRoomRepository
        // This enables dependency injection of the repository in RoomService
        $this->app->bind(
            RoomRepositoryInterface::class,
            EloquentRoomRepository::class
        );

        // Bind ContactMessageRepositoryInterface to EloquentContactMessageRepository
        // This enables dependency injection of the repository in ContactMessageService
        $this->app->bind(
            ContactMessageRepositoryInterface::class,
            EloquentContactMessageRepository::class
        );

        // Bind AI Harness model provider interface to Anthropic implementation
        $this->app->bind(
            \App\AiHarness\Providers\ModelProviderInterface::class,
            \App\AiHarness\Providers\AnthropicProvider::class
        );

        // Bind Stripe\StripeClient directly (cannot use Cashier::stripe() here because
        // Cashier::stripe() calls app(StripeClient::class) which would cause infinite recursion).
        $this->app->bind(\Stripe\StripeClient::class, function ($app, array $params = []) {
            $secret = config('cashier.secret');
            // Stripe SDK rejects '' but accepts null. CI seeds .env.testing from .env.example
            // (STRIPE_SECRET=) so the raw config value is ''; normalize to null so the
            // container can construct the client. Methods still guard via shouldUseTestingFake().
            $config = $params['config'] ?? [
                'api_key' => $secret === '' ? null : $secret,
                'stripe_version' => \Laravel\Cashier\Cashier::STRIPE_VERSION,
            ];
            return new \Stripe\StripeClient($config);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->assertProductionSecureCookieConfiguration();
        $this->assertRedisAuthenticationConfiguration();

        // Register BookingObserver for automatic location_id population
        Booking::observe(BookingObserver::class);

        // Load RateLimiterServiceProvider early
        $this->app->register(RateLimiterServiceProvider::class);

        // Register custom PersonalAccessToken model for Sanctum
        // CRITICAL: This ensures our expiration logic is used
        /** @psalm-suppress InvalidArgument -- PersonalAccessToken extends Sanctum's model */
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // ========== Register @nonce Blade directive ==========
        // Usage: <script nonce="@nonce">...</script>
        // Automatically injects CSP nonce from request
        \Illuminate\Support\Facades\Blade::directive('nonce', function () {
            return "<?php echo request()->attributes->get('csp_nonce', '') ?>";
        });

        // ========== Register @purify Blade directive ==========
        // Usage: @purify($content) or {!! $content|purify !!}
        // Sanitizes HTML content using whitelist (NOT regex blacklist)
        // Regex XSS = 99% bypass rate. HTML Purifier = 0% bypass rate.
        PurifyDirective::register();

        // ========== Register FormRequest purify macros ==========
        // Usage: $request->purify(['field1', 'field2'])
        // Automatically sanitizes FormRequest data
        FormRequestPurifyMacro::register();

        // ========== Redirect verification email link to SPA ==========
        // Default Laravel VerifyEmail points to /api/email/verify/{id}/{hash} directly.
        // Clicking that link in a mail client opens a raw API URL with no auth cookie → 401.
        // Instead: send a frontend SPA URL. The SPA reads the params and calls the API
        // with its existing httpOnly cookie, then shows a proper success/error page.
        VerifyEmail::createUrlUsing(function ($notifiable) {
            $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
            $id = $notifiable->getKey();
            $hash = sha1($notifiable->getEmailForVerification());
            $expires = now()->addMinutes(config('auth.verification.expire', 60));

            // Generate the signed backend URL to extract the expires + signature params
            $backendUrl = URL::temporarySignedRoute(
                'verification.verify',
                $expires,
                ['id' => $id, 'hash' => $hash]
            );

            $parsedQuery = [];
            parse_str(parse_url($backendUrl, PHP_URL_QUERY) ?: '', $parsedQuery);

            return $frontendUrl.'/email/verify?'.http_build_query([
                'id' => $id,
                'hash' => $hash,
                'expires' => $parsedQuery['expires'],
                'signature' => $parsedQuery['signature'],
            ]);
        });
    }

    private function assertProductionSecureCookieConfiguration(): void
    {
        // HTTP-runtime invariant; an Artisan command re-checks before traffic admission.
        // Guard only for real console invocations — read the process-level APP_ENV so that
        // tests which override config(['app.env' => 'production']) still exercise this path.
        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            return;
        }

        if (config('app.env') !== 'production') {
            return;
        }

        if (config('session.secure') === true) {
            return;
        }

        throw new EnvironmentConfigException(
            'SESSION_SECURE_COOKIE must be true when APP_ENV=production.'
        );
    }

    // Refuse to boot in non-local environments when REDIS_PASSWORD is empty.
    // The 127.0.0.1 docker bind mitigates network-level exposure but does not
    // block localhost-accessible processes (compromised app container, sidecar,
    // CI runner with host network). The DB-level guarantee is enforced by the
    // Redis server itself via --requirepass; this is the application-side fence.
    private function assertRedisAuthenticationConfiguration(): void
    {
        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            return;
        }

        if (in_array(config('app.env'), ['local', 'testing'], true)) {
            return;
        }

        if (! empty(config('database.redis.default.password'))) {
            return;
        }

        throw new EnvironmentConfigException(
            'REDIS_PASSWORD must be set in non-local environments. '.
            'Refusing to start with unauthenticated Redis.'
        );
    }
}
