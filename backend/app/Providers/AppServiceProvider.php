<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Booking;
use App\Models\Room;
use App\Models\PersonalAccessToken;
use App\Policies\BookingPolicy;
use App\Policies\RoomPolicy;
use App\Directives\PurifyDirective;
use App\Macros\FormRequestPurifyMacro;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\EloquentBookingRepository;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        Booking::class => BookingPolicy::class,
        Room::class => RoomPolicy::class,
    ];

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load RateLimiterServiceProvider early
        $this->app->register(RateLimiterServiceProvider::class);

        // Register custom PersonalAccessToken model for Sanctum
        // CRITICAL: This ensures our expiration logic is used
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
    }
}

