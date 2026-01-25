<?php

namespace App\Services\Cache;

use App\Models\Room;
use App\Models\User;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\RoomAvailabilityService;
use App\Traits\HasCacheTagSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cache Warmer Service
 * 
 * Warms up critical caches after deployment to prevent cold-start spikes.
 * Designed to be:
 * - Idempotent: Safe to run multiple times
 * - Graceful: Continues on individual cache failures
 * - Memory-safe: Uses chunking for large datasets
 * - Observable: Logs all operations for debugging
 * 
 * @see docs/backend/CACHE_WARMUP_STRATEGY.md
 */
class CacheWarmer
{
    use HasCacheTagSupport;

    /**
     * Warmup configuration
     */
    private const DEFAULT_CHUNK_SIZE = 100;
    private const DEFAULT_DATE_RANGE_DAYS = 30;
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_MS = 100;

    /**
     * Cache groups with their priorities (lower = higher priority)
     */
    public const CACHE_GROUPS = [
        'config' => [
            'priority' => 1,
            'description' => 'Application configuration and feature flags',
            'critical' => true,
        ],
        'rooms' => [
            'priority' => 2,
            'description' => 'Room catalog and availability',
            'critical' => true,
        ],
        'users' => [
            'priority' => 3,
            'description' => 'Active user profiles and permissions',
            'critical' => false,
        ],
        'bookings' => [
            'priority' => 4,
            'description' => 'Recent bookings cache',
            'critical' => false,
        ],
        'static' => [
            'priority' => 5,
            'description' => 'Static content and translations',
            'critical' => false,
        ],
        'computed' => [
            'priority' => 6,
            'description' => 'Heavy aggregations and statistics',
            'critical' => false,
        ],
    ];

    private RoomAvailabilityCache $roomAvailabilityCache;
    private RoomAvailabilityService $roomAvailabilityService;
    private BookingService $bookingService;
    
    private int $chunkSize;
    private bool $dryRun = false;
    private bool $force = false;
    private array $results = [];
    private float $startTime;

    public function __construct(
        RoomAvailabilityCache $roomAvailabilityCache,
        RoomAvailabilityService $roomAvailabilityService,
        BookingService $bookingService
    ) {
        $this->roomAvailabilityCache = $roomAvailabilityCache;
        $this->roomAvailabilityService = $roomAvailabilityService;
        $this->bookingService = $bookingService;
        $this->chunkSize = self::DEFAULT_CHUNK_SIZE;
    }

    /**
     * Configure warmer options
     */
    public function configure(array $options): self
    {
        $this->chunkSize = $options['chunk_size'] ?? self::DEFAULT_CHUNK_SIZE;
        $this->dryRun = $options['dry_run'] ?? false;
        $this->force = $options['force'] ?? false;

        return $this;
    }

    /**
     * Warm all cache groups (or specific ones)
     * 
     * @param array|null $groups Specific groups to warm, or null for all
     * @return array Results with success/failure stats per group
     */
    public function warmAll(?array $groups = null): array
    {
        $this->startTime = microtime(true);
        $this->results = [];

        // Determine which groups to warm
        $targetGroups = $groups ?? array_keys(self::CACHE_GROUPS);
        
        // Sort by priority
        usort($targetGroups, function ($a, $b) {
            $priorityA = self::CACHE_GROUPS[$a]['priority'] ?? 99;
            $priorityB = self::CACHE_GROUPS[$b]['priority'] ?? 99;
            return $priorityA <=> $priorityB;
        });

        Log::info('[CacheWarmer] Starting cache warmup', [
            'groups' => $targetGroups,
            'dry_run' => $this->dryRun,
            'force' => $this->force,
            'chunk_size' => $this->chunkSize,
        ]);

        foreach ($targetGroups as $group) {
            if (!isset(self::CACHE_GROUPS[$group])) {
                $this->results[$group] = [
                    'status' => 'skipped',
                    'reason' => 'Unknown cache group',
                    'duration_ms' => 0,
                ];
                continue;
            }

            $this->warmGroup($group);
        }

        $totalDuration = (microtime(true) - $this->startTime) * 1000;

        Log::info('[CacheWarmer] Cache warmup completed', [
            'total_duration_ms' => round($totalDuration, 2),
            'results' => $this->results,
        ]);

        return [
            'success' => $this->countByStatus('success'),
            'failed' => $this->countByStatus('failed'),
            'skipped' => $this->countByStatus('skipped'),
            'total_duration_ms' => round($totalDuration, 2),
            'groups' => $this->results,
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];
    }

    /**
     * Warm a specific cache group
     */
    public function warmGroup(string $group): array
    {
        $groupStart = microtime(true);
        $groupConfig = self::CACHE_GROUPS[$group] ?? null;

        if (!$groupConfig) {
            return [
                'status' => 'skipped',
                'reason' => 'Unknown group',
            ];
        }

        try {
            $result = match ($group) {
                'config' => $this->warmConfigCache(),
                'rooms' => $this->warmRoomsCache(),
                'users' => $this->warmUsersCache(),
                'bookings' => $this->warmBookingsCache(),
                'static' => $this->warmStaticCache(),
                'computed' => $this->warmComputedCache(),
                default => ['status' => 'skipped', 'reason' => 'No warmer defined'],
            };

            $result['duration_ms'] = round((microtime(true) - $groupStart) * 1000, 2);
            $result['critical'] = $groupConfig['critical'];
            
            $this->results[$group] = $result;

            Log::info("[CacheWarmer] Group '{$group}' completed", $result);

            return $result;

        } catch (\Throwable $e) {
            $result = [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $groupStart) * 1000, 2),
                'critical' => $groupConfig['critical'],
            ];

            $this->results[$group] = $result;

            Log::error("[CacheWarmer] Group '{$group}' failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw if critical and not in dry-run mode
            if ($groupConfig['critical'] && !$this->dryRun) {
                // Log but don't throw - we want deployment to continue
                Log::critical("[CacheWarmer] Critical cache group failed: {$group}");
            }

            return $result;
        }
    }

    /**
     * Warm application config cache
     * - Feature flags
     * - Rate limits
     * - App settings
     */
    protected function warmConfigCache(): array
    {
        $warmed = 0;
        $items = [];

        if ($this->dryRun) {
            return [
                'status' => 'dry_run',
                'would_warm' => ['app_config', 'feature_flags', 'rate_limits'],
            ];
        }

        // Cache application config
        $configKeys = [
            'booking' => config('booking'),
            'rate-limits' => config('rate-limits'),
            'services' => config('services'),
        ];

        foreach ($configKeys as $key => $value) {
            $cacheKey = "config:{$key}";
            
            if ($this->force || !Cache::has($cacheKey)) {
                Cache::put($cacheKey, $value, now()->addHours(24));
                $warmed++;
                $items[] = $key;
            }
        }

        return [
            'status' => 'success',
            'warmed_count' => $warmed,
            'items' => $items,
        ];
    }

    /**
     * Warm rooms cache
     * - All active rooms
     * - Room availability for next 30 days
     * - Popular room queries
     */
    protected function warmRoomsCache(): array
    {
        $warmed = 0;

        if ($this->dryRun) {
            $roomCount = Room::active()->count();
            return [
                'status' => 'dry_run',
                'would_warm' => [
                    'all_active_rooms',
                    'room_availability_30_days',
                ],
                'estimated_items' => $roomCount * self::DEFAULT_DATE_RANGE_DAYS,
            ];
        }

        // Warm all rooms with availability
        $rooms = $this->roomAvailabilityService->getAllRoomsWithAvailability();
        $warmed++;

        // Warm room availability for next 30 days
        $today = Carbon::today();
        $endDate = $today->copy()->addDays(self::DEFAULT_DATE_RANGE_DAYS);

        $cacheEntries = $this->roomAvailabilityCache->warmUpCache($today, $endDate);
        $warmed += $cacheEntries;

        // Warm individual room caches
        Room::active()->chunk($this->chunkSize, function ($rooms) use (&$warmed) {
            foreach ($rooms as $room) {
                $this->roomAvailabilityService->getRoomAvailability($room->id);
                $warmed++;
            }
        });

        return [
            'status' => 'success',
            'warmed_count' => $warmed,
            'rooms_count' => $rooms->count(),
            'date_range_days' => self::DEFAULT_DATE_RANGE_DAYS,
        ];
    }

    /**
     * Warm users cache
     * - Recently active users
     * - Admin/moderator profiles
     * - User permissions
     */
    protected function warmUsersCache(): array
    {
        $warmed = 0;

        if ($this->dryRun) {
            $activeUserCount = User::where('updated_at', '>=', now()->subDays(7))->count();
            $adminCount = User::where('role', 'admin')->orWhere('role', 'moderator')->count();
            return [
                'status' => 'dry_run',
                'would_warm' => [
                    'admin_users',
                    'active_users_7d',
                ],
                'estimated_items' => $adminCount + min($activeUserCount, 500),
            ];
        }

        // Warm admin/moderator users (always needed for authorization)
        $admins = User::where('role', 'admin')
            ->orWhere('role', 'moderator')
            ->get();

        foreach ($admins as $admin) {
            $cacheKey = "user:profile:{$admin->id}";
            Cache::put($cacheKey, $admin->toArray(), now()->addHours(1));
            $warmed++;
        }

        // Warm recently active users (last 7 days, max 500)
        User::where('updated_at', '>=', now()->subDays(7))
            ->orderBy('updated_at', 'desc')
            ->limit(500)
            ->chunk($this->chunkSize, function ($users) use (&$warmed) {
                foreach ($users as $user) {
                    $cacheKey = "user:profile:{$user->id}";
                    if ($this->force || !Cache::has($cacheKey)) {
                        Cache::put($cacheKey, $user->toArray(), now()->addHours(1));
                        $warmed++;
                    }
                }
            });

        return [
            'status' => 'success',
            'warmed_count' => $warmed,
            'admin_count' => $admins->count(),
        ];
    }

    /**
     * Warm bookings cache
     * - Today's bookings (check-ins/check-outs)
     * - Recent bookings per active user
     */
    protected function warmBookingsCache(): array
    {
        $warmed = 0;

        if ($this->dryRun) {
            $todayBookings = Booking::whereDate('check_in', today())
                ->orWhereDate('check_out', today())
                ->count();
            return [
                'status' => 'dry_run',
                'would_warm' => [
                    'today_checkins',
                    'today_checkouts',
                    'recent_user_bookings',
                ],
                'estimated_items' => $todayBookings,
            ];
        }

        // Warm today's check-ins
        $todayCheckIns = Booking::whereDate('check_in', today())
            ->with(['room', 'user'])
            ->get();

        $cacheKey = 'bookings:today:check_ins';
        Cache::put($cacheKey, $todayCheckIns, now()->addHours(2));
        $warmed++;

        // Warm today's check-outs
        $todayCheckOuts = Booking::whereDate('check_out', today())
            ->with(['room', 'user'])
            ->get();

        $cacheKey = 'bookings:today:check_outs';
        Cache::put($cacheKey, $todayCheckOuts, now()->addHours(2));
        $warmed++;

        // Warm recent user bookings (for active users)
        $activeUserIds = User::where('updated_at', '>=', now()->subDays(7))
            ->limit(100)
            ->pluck('id');

        foreach ($activeUserIds as $userId) {
            // This uses the BookingService's internal caching
            $this->bookingService->getUserBookings($userId);
            $warmed++;
        }

        return [
            'status' => 'success',
            'warmed_count' => $warmed,
            'today_check_ins' => $todayCheckIns->count(),
            'today_check_outs' => $todayCheckOuts->count(),
        ];
    }

    /**
     * Warm static content cache
     * - Translations (if using cached translations)
     * - Static pages
     * - FAQ content
     */
    protected function warmStaticCache(): array
    {
        $warmed = 0;

        if ($this->dryRun) {
            return [
                'status' => 'dry_run',
                'would_warm' => ['translations', 'static_pages'],
            ];
        }

        // Cache supported locales
        $locales = ['en', 'vi', 'ja'];
        foreach ($locales as $locale) {
            $cacheKey = "translations:{$locale}";
            if ($this->force || !Cache::has($cacheKey)) {
                // In real app, load translation files here
                Cache::put($cacheKey, ['locale' => $locale], now()->addHours(24));
                $warmed++;
            }
        }

        return [
            'status' => 'success',
            'warmed_count' => $warmed,
            'locales' => $locales,
        ];
    }

    /**
     * Warm computed/aggregated data cache
     * - Room statistics
     * - Booking statistics
     * - Dashboard data
     */
    protected function warmComputedCache(): array
    {
        $warmed = 0;

        if ($this->dryRun) {
            return [
                'status' => 'dry_run',
                'would_warm' => [
                    'room_statistics',
                    'booking_statistics',
                    'dashboard_metrics',
                ],
            ];
        }

        // Room statistics
        $roomStats = [
            'total_rooms' => Room::count(),
            'active_rooms' => Room::active()->count(),
            'total_capacity' => Room::active()->sum('max_guests'),
        ];
        Cache::put('stats:rooms', $roomStats, now()->addHours(1));
        $warmed++;

        // Booking statistics (current month)
        $bookingStats = [
            'total_bookings_month' => Booking::whereMonth('created_at', now()->month)->count(),
            'confirmed_bookings' => Booking::where('status', 'confirmed')->count(),
            'pending_bookings' => Booking::where('status', 'pending')->count(),
            'occupancy_today' => $this->calculateOccupancyRate(today()),
        ];
        Cache::put('stats:bookings', $bookingStats, now()->addMinutes(30));
        $warmed++;

        // Dashboard metrics (for admin)
        $dashboardMetrics = [
            'rooms' => $roomStats,
            'bookings' => $bookingStats,
            'generated_at' => now()->toISOString(),
        ];
        Cache::put('dashboard:metrics', $dashboardMetrics, now()->addMinutes(15));
        $warmed++;

        return [
            'status' => 'success',
            'warmed_count' => $warmed,
            'metrics' => array_keys($dashboardMetrics),
        ];
    }

    /**
     * Calculate occupancy rate for a given date
     */
    private function calculateOccupancyRate(Carbon $date): float
    {
        $totalRooms = Room::active()->count();
        if ($totalRooms === 0) {
            return 0.0;
        }

        $occupiedRooms = Booking::whereIn('status', ['confirmed', 'pending'])
            ->whereDate('check_in', '<=', $date)
            ->whereDate('check_out', '>', $date)
            ->distinct('room_id')
            ->count('room_id');

        return round(($occupiedRooms / $totalRooms) * 100, 2);
    }

    /**
     * Count results by status
     */
    private function countByStatus(string $status): int
    {
        return count(array_filter($this->results, fn($r) => $r['status'] === $status));
    }

    /**
     * Get cache group info
     */
    public static function getGroupInfo(): array
    {
        return self::CACHE_GROUPS;
    }

    /**
     * Check if cache warmer is healthy (can connect to cache)
     */
    public function healthCheck(): array
    {
        $checks = [];

        // Test cache connection
        try {
            $testKey = 'cache_warmer_health_check_' . time();
            Cache::put($testKey, 'ok', 10);
            $value = Cache::get($testKey);
            Cache::forget($testKey);

            $checks['cache_connection'] = $value === 'ok';
        } catch (\Throwable $e) {
            $checks['cache_connection'] = false;
            $checks['cache_error'] = $e->getMessage();
        }

        // Test database connection
        try {
            DB::connection()->getPdo();
            $checks['database_connection'] = true;
        } catch (\Throwable $e) {
            $checks['database_connection'] = false;
            $checks['database_error'] = $e->getMessage();
        }

        // Memory check
        $memoryLimit = $this->getMemoryLimitBytes();
        $currentMemory = memory_get_usage(true);
        $checks['memory_available_mb'] = round(($memoryLimit - $currentMemory) / 1024 / 1024, 2);
        $checks['memory_limit_mb'] = round($memoryLimit / 1024 / 1024, 2);

        $checks['healthy'] = $checks['cache_connection'] && $checks['database_connection'];

        return $checks;
    }

    /**
     * Get PHP memory limit in bytes
     */
    private function getMemoryLimitBytes(): int
    {
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit === '-1') {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int) $memoryLimit;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
