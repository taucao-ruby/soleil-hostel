<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Test helper: activate the AI harness kill switch without Redis.
 *
 * Background: AiHarnessEnabled middleware was migrated from
 * `config('ai_harness.enabled')` to `FeatureFlag::killSwitch('ai_harness.enabled')`
 * as part of AUTH-004 / Batch 8. killSwitch() is sticky-off and reads from Redis
 * after checking the local cache. Tests use the array cache driver, so seeding
 * the local cache lets route tests exercise the real middleware without a Redis
 * dependency.
 */
trait EnablesAiHarness
{
    protected function enableAiHarness(): void
    {
        Cache::put('feature_flag:local:ai_harness.enabled', 'on', 60);
    }
}
