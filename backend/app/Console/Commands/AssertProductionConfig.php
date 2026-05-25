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
