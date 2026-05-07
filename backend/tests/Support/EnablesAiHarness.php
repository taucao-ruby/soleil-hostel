<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\FeatureFlag;
use Illuminate\Support\Facades\Cache;

/**
 * Test helper: activate the AI harness kill switch via Redis.
 *
 * Background: AiHarnessEnabled middleware was migrated from
 * `config('ai_harness.enabled')` to `FeatureFlag::killSwitch('ai_harness.enabled')`
 * as part of AUTH-004 / Batch 8. killSwitch() is sticky-off and reads from Redis
 * only — the old `config()->set(...)` pattern in test setUp() no longer enables
 * the harness. This trait writes the Redis flag and clears the local cache so
 * the middleware sees the correct state for tests that require the harness ON.
 *
 * If Redis is unavailable the test is skipped rather than failing with a
 * misleading 404.
 */
trait EnablesAiHarness
{
    protected function enableAiHarness(): void
    {
        Cache::forget('feature_flag:local:ai_harness.enabled');

        try {
            FeatureFlag::set('ai_harness.enabled', true);
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                'Redis unavailable — cannot enable AI harness kill switch: '.$e->getMessage()
            );
        }
    }
}
