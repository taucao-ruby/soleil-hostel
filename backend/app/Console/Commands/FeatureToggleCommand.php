<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AdminAuditService;
use App\Services\FeatureFlag;
use Illuminate\Console\Command;

/**
 * Operator UX for toggling Redis-backed feature flags.
 *
 * Batch 4 / 3E.
 *
 * Examples:
 *   php artisan feature:toggle ai_harness.enabled on
 *   php artisan feature:toggle booking.expire_pending off --ttl=86400
 *   php artisan feature:toggle ai_harness.enabled off --reason="incident-2026-04-29"
 *
 * Writes to `feature:{key}` in Redis and records the change in admin_audit_logs
 * so we have a chain of custody for kill-switch decisions.
 */
class FeatureToggleCommand extends Command
{
    protected $signature = 'feature:toggle
                            {key : Feature flag identifier (e.g. ai_harness.enabled)}
                            {state : on|off}
                            {--ttl= : Optional auto-expiry in seconds (operator-driven temporary toggle)}
                            {--reason= : Optional human-readable reason logged to admin_audit_logs}';

    protected $description = 'Flip a Redis-backed feature flag and audit the change.';

    public function handle(AdminAuditService $audit): int
    {
        $key = (string) $this->argument('key');
        $state = strtolower((string) $this->argument('state'));

        if (! in_array($state, ['on', 'off'], true)) {
            $this->error("State must be 'on' or 'off'. Got: {$state}");

            return self::INVALID;
        }

        $ttlOption = $this->option('ttl');
        $ttl = $ttlOption === null ? null : (int) $ttlOption;
        if ($ttl !== null && $ttl <= 0) {
            $this->error('--ttl must be a positive integer (seconds).');

            return self::INVALID;
        }

        $on = $state === 'on';

        try {
            FeatureFlag::set($key, $on, $ttl);
        } catch (\Throwable $e) {
            $this->error("Failed to set flag: {$e->getMessage()}");

            return self::FAILURE;
        }

        $reason = $this->option('reason');
        $metadata = [
            'flag_key' => $key,
            'state' => $state,
            'ttl_seconds' => $ttl,
        ];
        if (is_string($reason) && $reason !== '') {
            $metadata['reason'] = $reason;
        }

        try {
            $audit->log('feature_flag.toggle', 'feature_flag', null, $metadata);
        } catch (\Throwable $e) {
            // Audit write failure must NOT undo the Redis flip — the flag is the
            // source of truth and operators may be acting under incident pressure.
            // Surface the failure but keep going.
            $this->warn("Flag set, but audit log write failed: {$e->getMessage()}");
        }

        $ttlNote = $ttl !== null ? " (auto-expires in {$ttl}s)" : '';
        $this->info("Flag '{$key}' set to '{$state}'{$ttlNote}.");

        return self::SUCCESS;
    }
}
