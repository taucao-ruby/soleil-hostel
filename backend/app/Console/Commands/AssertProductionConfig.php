<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class AssertProductionConfig extends Command
{
    protected $signature = 'app:assert-production-config';

    protected $description = 'Assert production HTTP-runtime invariants. Run after migrations, before admitting traffic.';

    public function handle(): int
    {
        $failures = [];

        if (! app()->isProduction()) {
            $failures[] = 'APP_ENV must resolve to production for this production assertion.';
        }

        if (app()->isProduction() && config('session.secure') !== true) {
            $failures[] = 'SESSION_SECURE_COOKIE must be true when APP_ENV=production.';
        }

        if (app()->isProduction() && config('app.debug') !== false) {
            $failures[] = 'APP_DEBUG must resolve to false in production.';
        }

        if (! app()->environment(['local', 'testing'])
            && empty(config('database.redis.default.password'))) {
            $failures[] = 'REDIS_PASSWORD must be set in non-local environments.';
        }

        // HTTP-runtime origin/secret invariants only make sense for a real
        // production boot. When APP_ENV is not production the assertion above
        // already fails the command, so these are scoped to production to keep
        // the staging/local diagnostic output focused on the env mismatch.
        if (app()->isProduction()) {
            foreach ($this->appUrlFailures() as $failure) {
                $failures[] = $failure;
            }

            foreach ($this->corsFailures() as $failure) {
                $failures[] = $failure;
            }

            foreach ($this->stripeWebhookSecretFailures() as $failure) {
                $failures[] = $failure;
            }
        }

        if ($failures !== []) {
            $this->error('Production configuration assertion failed.');

            foreach ($failures as $failure) {
                $this->line("- {$failure}");
            }

            $this->line("- APP_ENV: {$this->formatDiagnosticValue(app()->environment())}");
            $this->line("- config('app.debug'): {$this->formatDiagnosticValue(config('app.debug'))}");
            $this->line("- env('APP_DEBUG'): {$this->formatDiagnosticValue($this->rawAppDebugEnvironmentValue())}");

            return Command::FAILURE;
        }

        $this->info('Production config OK.');

        return Command::SUCCESS;
    }

    /**
     * APP_URL must be an HTTPS, non-loopback origin in production. A localhost
     * or http:// APP_URL leaks into signed URLs, password-reset/mail links and
     * redirects, so treat it as a deploy-blocking misconfiguration.
     *
     * @return list<string>
     */
    private function appUrlFailures(): array
    {
        $failures = [];
        $url = (string) config('app.url');

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $host = is_string($host) && $host !== '' ? $host : $url;

        if ($scheme !== 'https') {
            $failures[] = 'APP_URL must use HTTPS in production.';
        }

        if ($this->isLoopbackHost($host)) {
            $failures[] = 'APP_URL must not point at localhost/loopback in production.';
        }

        return $failures;
    }

    /**
     * CORS allowed origins must be an explicit allow-list of HTTPS, non-loopback
     * origins, with no regex patterns. Because cors.supports_credentials is true,
     * a wildcard, localhost, or http:// entry would let a hostile or
     * origin-spoofed page ride the SameSite=Strict auth cookie.
     *
     * @return list<string>
     */
    private function corsFailures(): array
    {
        $failures = [];

        /** @var array<int, mixed> $origins */
        $origins = (array) config('cors.allowed_origins', []);

        foreach ($origins as $origin) {
            $origin = is_string($origin) ? trim($origin) : '';

            if ($origin === '') {
                continue;
            }

            if ($origin === '*') {
                $failures[] = "CORS_ALLOWED_ORIGINS must not contain the wildcard '*' in production.";

                continue;
            }

            $scheme = parse_url($origin, PHP_URL_SCHEME);
            $host = parse_url($origin, PHP_URL_HOST);
            $host = is_string($host) && $host !== '' ? $host : $origin;

            if ($this->isLoopbackHost($host)) {
                $failures[] = "CORS_ALLOWED_ORIGINS must not contain localhost/loopback origins in production: {$origin}";
            }

            if ($scheme !== 'https') {
                $failures[] = "CORS_ALLOWED_ORIGINS entries must use HTTPS in production: {$origin}";
            }
        }

        /** @var array<int, mixed> $patterns */
        $patterns = (array) config('cors.allowed_origins_patterns', []);

        if ($patterns !== []) {
            $failures[] = 'CORS_ALLOWED_ORIGINS_PATTERNS must be empty in production; regex origin patterns can silently admit unsafe origins.';
        }

        return $failures;
    }

    /**
     * The Stripe webhook secret is the value StripeWebhookController verifies
     * every inbound signature against (config('cashier.webhook.secret'), fed by
     * STRIPE_WEBHOOK_SECRET). If it is empty in production the controller
     * fail-closes every webhook with HTTP 500, so payment lifecycle events
     * (charge.refunded, payment_intent.succeeded, ...) silently stop applying.
     *
     * @return list<string>
     */
    private function stripeWebhookSecretFailures(): array
    {
        $secret = config('cashier.webhook.secret');

        if (! is_string($secret) || $secret === '') {
            return ['STRIPE_WEBHOOK_SECRET (cashier.webhook.secret) must be configured in production.'];
        }

        if (! str_starts_with($secret, 'whsec_')) {
            return ['STRIPE_WEBHOOK_SECRET (cashier.webhook.secret) must look like a Stripe signing secret (whsec_...).'];
        }

        return [];
    }

    /**
     * Loopback / non-routable / Docker-internal hosts that must never appear in
     * a production origin allow-list or APP_URL.
     */
    private function isLoopbackHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));

        $forbidden = ['localhost', '127.0.0.1', '0.0.0.0', '::1', 'host.docker.internal'];

        return in_array($host, $forbidden, true) || str_ends_with($host, '.localhost');
    }

    private function formatDiagnosticValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        return (string) $value;
    }

    private function rawAppDebugEnvironmentValue(): mixed
    {
        $value = $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? getenv('APP_DEBUG');

        return $value === false ? null : $value;
    }
}
