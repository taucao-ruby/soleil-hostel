<?php

declare(strict_types=1);

namespace App\AiHarness\Services;

use App\AiHarness\DTOs\GroundedContext;
use App\AiHarness\DTOs\HarnessRequest;
use App\AiHarness\Exceptions\ProviderTimeoutException;
use App\AiHarness\Exceptions\ProviderUnavailableException;
use App\AiHarness\Providers\ModelProviderInterface;
use App\AiHarness\Providers\RawModelResponse;
use Illuminate\Support\Facades\Log;

/**
 * L3 — Model Execution Service.
 *
 * Selects provider, enforces timeout, handles failover.
 * Does NOT validate output — that's PolicyEnforcementService's job.
 */
class ModelExecutionService
{
    /**
     * Provider instances keyed by name.
     *
     * @var array<string, ModelProviderInterface>
     */
    private array $providers = [];

    public function __construct(ModelProviderInterface $defaultProvider)
    {
        $this->providers[$defaultProvider->getProviderName()] = $defaultProvider;
    }

    /**
     * Register an additional provider for failover.
     */
    public function registerProvider(ModelProviderInterface $provider): void
    {
        $this->providers[$provider->getProviderName()] = $provider;
    }

    /**
     * Execute model completion with failover.
     *
     * @throws ProviderUnavailableException if all providers fail
     */
    public function execute(HarnessRequest $request, GroundedContext $context): RawModelResponse
    {
        $defaultName = config('ai_harness.default_provider', 'anthropic');
        $providerOrder = $this->buildProviderOrder($defaultName);
        $lastException = null;

        foreach ($providerOrder as $providerName) {
            $provider = $this->providers[$providerName] ?? null;
            if ($provider === null || ! $provider->isAvailable()) {
                Log::channel('ai')->info('Provider skipped (unavailable or not registered)', [
                    'provider' => $providerName,
                    'request_id' => $request->requestId,
                ]);

                continue;
            }

            try {
                return $provider->complete($request, $context);
            } catch (ProviderTimeoutException $e) {
                $lastException = $e;
                Log::channel('ai')->warning('Provider timed out, trying fallback', [
                    'provider' => $providerName,
                    'request_id' => $request->requestId,
                    'timeout' => $e->timeoutSeconds,
                ]);
            } catch (ProviderUnavailableException $e) {
                $lastException = $e;
                Log::channel('ai')->warning('Provider unavailable, trying fallback', [
                    'provider' => $providerName,
                    'request_id' => $request->requestId,
                ]);
            }
        }

        throw $lastException ?? new ProviderUnavailableException(
            $defaultName,
            'All AI providers are unavailable.',
        );
    }

    /**
     * Build provider failover order: default first, then others.
     *
     * @return list<string>
     */
    private function buildProviderOrder(string $defaultName): array
    {
        $order = [$defaultName];

        foreach (array_keys($this->providers) as $name) {
            if ($name !== $defaultName) {
                $order[] = $name;
            }
        }

        return $order;
    }
}
