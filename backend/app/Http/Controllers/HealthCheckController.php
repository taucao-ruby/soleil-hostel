<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthCheckController extends Controller
{
    /**
     * Health check endpoint
     * Returns 200 if all services are healthy
     * Returns 503 if any service is down
     *
     * Checks:
     * - Database connectivity (ping)
     * - Redis connectivity (ping)
     * - Application status
     */
    public function check(): Response
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'services' => [],
        ];

        // ========== DATABASE CHECK ==========
        try {
            DB::connection()->getPdo();
            $health['services']['database'] = [
                'status' => 'up',
                'connection' => config('database.default'),
            ];
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['services']['database'] = [
                'status' => 'down',
                'error' => $e->getMessage(),
            ];
        }

        // ========== REDIS CHECK ==========
        try {
            // Check if Redis extension is loaded
            if (!extension_loaded('redis')) {
                $health['status'] = 'unhealthy';
                $health['services']['redis'] = [
                    'status' => 'down',
                    'error' => 'Redis PHP extension not loaded',
                ];
            } else {
                // Ping Redis directly
                Redis::ping();

                $health['services']['redis'] = [
                    'status' => 'up',
                    'connections' => ['default'],
                ];
            }
        } catch (\Throwable $e) {
            $health['status'] = 'unhealthy';
            $health['services']['redis'] = [
                'status' => 'down',
                'error' => $e->getMessage(),
            ];
        }

        // ========== MEMORY CHECK ==========
        $health['services']['memory'] = [
            'status' => 'ok',
            'usage_mb' => round(memory_get_usage() / 1024 / 1024, 2),
            'limit_mb' => (int) ini_get('memory_limit'),
        ];

        $statusCode = $health['status'] === 'healthy' ? 200 : 503;

        return response(json_encode($health), $statusCode, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Detailed health check (for monitoring dashboards)
     * Can be rate limited separately if needed
     */
    public function detailed(): Response
    {
        $health = $this->check();
        $data = json_decode($health->getContent(), true);

        // Add detailed Redis info
        try {
            if (extension_loaded('redis')) {
                $info = Redis::info('stats');

                $data['services']['redis']['stats'] = [
                    'connected_clients' => $info['connected_clients'] ?? 'N/A',
                    'used_memory_mb' => round(($info['used_memory'] ?? 0) / 1024 / 1024, 2),
                    'total_commands_processed' => $info['total_commands_processed'] ?? 'N/A',
                ];
            }
        } catch (\Throwable $e) {
            // Silently fail detailed Redis stats
        }

        return response(json_encode($data), $health->getStatusCode(), [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
