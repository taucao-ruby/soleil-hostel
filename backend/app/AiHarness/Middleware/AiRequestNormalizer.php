<?php

declare(strict_types=1);

namespace App\AiHarness\Middleware;

use App\AiHarness\DTOs\HarnessRequest;
use App\AiHarness\Enums\RiskTier;
use App\AiHarness\Enums\TaskType;
use App\AiHarness\PromptRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * L1 — AI Request Normalizer.
 *
 * Reads authenticated user, role, locale, and correlation_id from the existing
 * middleware pipeline. Builds a HarnessRequest DTO and attaches it to the request.
 *
 * Rejects unknown task_type values with 422.
 */
class AiRequestNormalizer
{
    /**
     * Map TaskType → RiskTier.
     *
     * @var array<string, RiskTier>
     */
    private const RISK_MAP = [
        'faq_lookup' => RiskTier::LOW,
        'room_discovery' => RiskTier::LOW,
        'booking_status' => RiskTier::MEDIUM,
        'admin_draft' => RiskTier::MEDIUM,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $taskTypeValue = $request->route('task_type');
        $taskType = TaskType::tryFrom($taskTypeValue);

        if ($taskType === null) {
            abort(422, 'Invalid task type.');
        }

        $user = $request->user();
        $correlationId = $request->attributes->get('correlation_id', '');

        $harnessRequest = new HarnessRequest(
            requestId: 'ai-'.bin2hex(random_bytes(12)),
            correlationId: $correlationId,
            taskType: $taskType,
            riskTier: self::RISK_MAP[$taskType->value] ?? RiskTier::HIGH,
            promptVersion: PromptRegistry::getVersion($taskType),
            userId: $user->id,
            userRole: $this->resolveRole($user),
            userInput: (string) $request->input('message', ''),
            locale: $request->getPreferredLanguage(['vi', 'en']) ?? 'vi',
            featureRoute: 'ai.'.$taskType->value,
        );

        $request->attributes->set('harness_request', $harnessRequest);

        return $next($request);
    }

    private function resolveRole(mixed $user): string
    {
        if (method_exists($user, 'getRoleName')) {
            return $user->getRoleName();
        }

        $role = $user->role ?? 'guest';

        if ($role instanceof \BackedEnum) {
            return (string) $role->value;
        }

        return (string) $role;
    }
}
