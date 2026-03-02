<?php

namespace App\Http\Controllers;

use App\Services\HealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Health check controller for monitoring endpoints.
 *
 * Thin HTTP layer — all check logic lives in HealthService.
 *
 * FAILURE SEMANTICS for Soleil Hostel Booking System:
 * - Database: CRITICAL — 503 if down (booking engine, optimistic locking, money paths)
 * - Cache: DEGRADED — 200 with warning (system still operable, reduced performance)
 * - Queue: DEGRADED — 200 with warning (async jobs delayed, OTA sync may lag)
 */
class HealthController extends Controller
{
    public function __construct(
        private HealthService $healthService
    ) {}

    /**
     * Basic health check endpoint.
     */
    public function check(): JsonResponse
    {
        $result = $this->healthService->basicCheck();

        return response()->json([
            'status' => $result['status'],
            'timestamp' => $result['timestamp'],
            'services' => $result['services'],
        ], $result['status_code'], [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Basic liveness probe.
     */
    public function liveness(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Readiness probe with dependency checks.
     */
    public function readiness(): JsonResponse
    {
        $result = $this->healthService->readinessCheck();

        return response()->json([
            'status' => $result['status'],
            'timestamp' => $result['timestamp'],
            'checks' => $result['checks'],
            'failure_semantics' => $result['failure_semantics'],
        ], $result['status_code']);
    }

    /**
     * Detailed health check with metrics (admin only).
     */
    public function detailed(Request $request): JsonResponse
    {
        $result = $this->healthService->detailedCheck();

        return response()->json([
            'status' => $result['status'],
            'timestamp' => $result['timestamp'],
            'app' => $result['app'],
            'checks' => $result['checks'],
            'summary' => $result['summary'],
            'failure_semantics' => $result['failure_semantics'],
            'metrics' => $result['metrics'],
        ], $result['status_code']);
    }

    /**
     * Individual database health check endpoint.
     */
    public function database(): JsonResponse
    {
        $check = $this->healthService->checkComponent('database');

        return response()->json([
            'component' => 'database',
            'criticality' => 'critical',
            'timestamp' => now()->toIso8601String(),
            ...$check,
        ], $check['healthy'] ? 200 : 503);
    }

    /**
     * Individual cache health check endpoint.
     */
    public function cache(): JsonResponse
    {
        $check = $this->healthService->checkComponent('cache');

        return response()->json([
            'component' => 'cache',
            'criticality' => 'degraded',
            'timestamp' => now()->toIso8601String(),
            ...$check,
        ], 200);
    }

    /**
     * Individual queue health check endpoint.
     */
    public function queue(): JsonResponse
    {
        $check = $this->healthService->checkComponent('queue');

        return response()->json([
            'component' => 'queue',
            'criticality' => 'degraded',
            'timestamp' => now()->toIso8601String(),
            ...$check,
        ], 200);
    }
}
