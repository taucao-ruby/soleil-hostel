<?php

declare(strict_types=1);

namespace App\AiHarness\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Canary routing middleware for AI harness.
 *
 * Routes a configurable percentage of requests to the AI pipeline.
 * Non-canary requests bypass to the existing support/contact flow.
 *
 * Canary percentage is per task type:
 *   config('ai_harness.canary.{task_type}_percentage')
 */
class AiCanaryRouter
{
    public function handle(Request $request, Closure $next): Response
    {
        $taskType = $request->route('task_type');
        $percentage = (int) config("ai_harness.canary.{$taskType}_percentage", 5);

        $roll = random_int(0, 99);
        $isCanary = $roll < $percentage;

        $request->attributes->set('ai_canary', $isCanary);

        Log::channel('ai')->info('Canary routing decision', [
            'task_type' => $taskType,
            'percentage' => $percentage,
            'roll' => $roll,
            'is_canary' => $isCanary,
            'correlation_id' => $request->attributes->get('correlation_id', ''),
        ]);

        if (! $isCanary) {
            return response()->json([
                'success' => true,
                'data' => [
                    'canary' => false,
                    'message' => 'Vui lòng liên hệ bộ phận hỗ trợ.',
                    'support_contact' => config('app.support_contact', 'support@soleilhostel.vn | Hotline: 0909-123-456'),
                ],
            ]);
        }

        return $next($request);
    }
}
