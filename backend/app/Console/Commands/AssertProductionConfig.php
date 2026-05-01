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
        if (config('app.env') === 'production' && config('session.secure') !== true) {
            $this->error('Config assertion failed: SESSION_SECURE_COOKIE must be true when APP_ENV=production.');

            return Command::FAILURE;
        }

        if (! in_array(config('app.env'), ['local', 'testing'], true)
            && empty(config('database.redis.default.password'))) {
            $this->error('Config assertion failed: REDIS_PASSWORD must be set in non-local environments.');

            return Command::FAILURE;
        }

        $this->info('Production config OK.');

        return Command::SUCCESS;
    }
}
