<?php

namespace App\Console\Commands;

use App\Services\Cache\CacheWarmer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Cache Warmup Command
 * 
 * Warms up critical caches after deployment to prevent cold-start latency spikes.
 * 
 * Usage:
 *   php artisan cache:warmup                    # Warm all cache groups
 *   php artisan cache:warmup --dry-run          # Preview what would be warmed
 *   php artisan cache:warmup --group=rooms      # Warm specific group only
 *   php artisan cache:warmup --force            # Override existing cache
 *   php artisan cache:warmup --chunk=50         # Process in smaller chunks
 *   php artisan cache:warmup -v                 # Verbose output
 * 
 * @see docs/backend/CACHE_WARMUP_STRATEGY.md
 */
class CacheWarmupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cache:warmup
                            {--dry-run : Preview what would be cached without executing}
                            {--group=* : Warm specific cache groups only (can be used multiple times)}
                            {--force : Override existing cache entries}
                            {--chunk=100 : Process large datasets in chunks of this size}
                            {--timeout=300 : Maximum execution time in seconds}
                            {--no-progress : Disable progress bar}';

    /**
     * The console command description.
     */
    protected $description = 'Warm up critical caches after deployment to prevent cold-start latency spikes';

    /**
     * Available cache groups for reference
     */
    private const AVAILABLE_GROUPS = ['config', 'rooms', 'users', 'bookings', 'static', 'computed'];

    private CacheWarmer $cacheWarmer;
    private float $startTime;
    private bool $hasErrors = false;

    public function __construct(CacheWarmer $cacheWarmer)
    {
        parent::__construct();
        $this->cacheWarmer = $cacheWarmer;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->startTime = microtime(true);
        
        // Set timeout
        $timeout = (int) $this->option('timeout');
        set_time_limit($timeout);

        // Header
        $this->displayHeader();

        // Pre-flight checks
        if (!$this->runPreflightChecks()) {
            return Command::FAILURE;
        }

        // Parse options
        $groups = $this->parseGroups();
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $chunkSize = (int) $this->option('chunk');

        // Configure warmer
        $this->cacheWarmer->configure([
            'dry_run' => $dryRun,
            'force' => $force,
            'chunk_size' => $chunkSize,
        ]);

        // Display configuration
        $this->displayConfiguration($groups, $dryRun, $force, $chunkSize);

        // Log start
        Log::info('[cache:warmup] Command started', [
            'groups' => $groups,
            'dry_run' => $dryRun,
            'force' => $force,
            'chunk_size' => $chunkSize,
        ]);

        // Execute warmup
        $results = $this->executeWarmup($groups);

        // Display results
        $this->displayResults($results);

        // Log completion
        Log::info('[cache:warmup] Command completed', [
            'duration_ms' => $results['total_duration_ms'],
            'success' => $results['success'],
            'failed' => $results['failed'],
            'skipped' => $results['skipped'],
        ]);

        // Determine exit code
        if ($results['failed'] > 0) {
            // Check if any critical group failed
            $criticalFailed = $this->hasCriticalFailure($results['groups']);
            
            if ($criticalFailed) {
                $this->error('❌ Critical cache group(s) failed to warm up!');
                return Command::FAILURE;
            }

            $this->warn('⚠️  Some non-critical cache groups failed.');
            return Command::SUCCESS; // Non-critical failures don't block deployment
        }

        $this->info('✅ Cache warmup completed successfully!');
        return Command::SUCCESS;
    }

    /**
     * Display command header
     */
    private function displayHeader(): void
    {
        $this->newLine();
        $this->line('╔══════════════════════════════════════════════════════════╗');
        $this->line('║           🔥 CACHE WARMUP - Soleil Hostel 🔥              ║');
        $this->line('╚══════════════════════════════════════════════════════════╝');
        $this->newLine();
    }

    /**
     * Run preflight health checks
     */
    private function runPreflightChecks(): bool
    {
        $this->info('🔍 Running preflight checks...');

        $health = $this->cacheWarmer->healthCheck();

        if (!$health['healthy']) {
            $this->error('Preflight checks failed:');

            if (!$health['cache_connection']) {
                $this->error('  ❌ Cache connection failed: ' . ($health['cache_error'] ?? 'Unknown error'));
            }

            if (!$health['database_connection']) {
                $this->error('  ❌ Database connection failed: ' . ($health['database_error'] ?? 'Unknown error'));
            }

            Log::error('[cache:warmup] Preflight checks failed', $health);
            return false;
        }

        $this->info('  ✅ Cache connection: OK');
        $this->info('  ✅ Database connection: OK');
        $this->info("  ✅ Memory available: {$health['memory_available_mb']} MB / {$health['memory_limit_mb']} MB");
        $this->newLine();

        return true;
    }

    /**
     * Parse and validate group option
     */
    private function parseGroups(): ?array
    {
        $groups = $this->option('group');

        if (empty($groups)) {
            return null; // Warm all groups
        }

        // Validate groups
        $invalidGroups = array_diff($groups, self::AVAILABLE_GROUPS);
        if (!empty($invalidGroups)) {
            $this->warn('Unknown groups will be skipped: ' . implode(', ', $invalidGroups));
        }

        $validGroups = array_intersect($groups, self::AVAILABLE_GROUPS);

        if (empty($validGroups)) {
            $this->error('No valid groups specified. Available: ' . implode(', ', self::AVAILABLE_GROUPS));
            return [];
        }

        return $validGroups;
    }

    /**
     * Display configuration
     */
    private function displayConfiguration(
        ?array $groups,
        bool $dryRun,
        bool $force,
        int $chunkSize
    ): void {
        $this->info('📋 Configuration:');
        
        $targetGroups = $groups ?? self::AVAILABLE_GROUPS;
        $this->line('  Groups: ' . implode(', ', $targetGroups));
        $this->line('  Dry Run: ' . ($dryRun ? 'Yes (no changes will be made)' : 'No'));
        $this->line('  Force: ' . ($force ? 'Yes (override existing cache)' : 'No'));
        $this->line('  Chunk Size: ' . $chunkSize);
        $this->newLine();

        if ($dryRun) {
            $this->warn('🧪 DRY RUN MODE - No cache will be written');
            $this->newLine();
        }
    }

    /**
     * Execute cache warmup with progress display
     */
    private function executeWarmup(?array $groups): array
    {
        $targetGroups = $groups ?? array_keys(CacheWarmer::CACHE_GROUPS);
        $showProgress = !$this->option('no-progress') && !$this->option('quiet');

        if ($showProgress) {
            return $this->executeWithProgress($targetGroups);
        }

        return $this->cacheWarmer->warmAll($groups);
    }

    /**
     * Execute with progress bar
     */
    private function executeWithProgress(array $groups): array
    {
        $this->info('🚀 Warming up cache groups...');
        $this->newLine();

        $allResults = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'groups' => [],
            'total_duration_ms' => 0,
            'memory_peak_mb' => 0,
        ];

        $groupInfo = CacheWarmer::getGroupInfo();
        
        // Sort groups by priority
        usort($groups, function ($a, $b) use ($groupInfo) {
            return ($groupInfo[$a]['priority'] ?? 99) <=> ($groupInfo[$b]['priority'] ?? 99);
        });

        foreach ($groups as $group) {
            $info = $groupInfo[$group] ?? [];
            $critical = $info['critical'] ?? false;
            $criticalBadge = $critical ? ' [CRITICAL]' : '';
            
            $this->line("  ⏳ Warming {$group}{$criticalBadge}...");
            
            $result = $this->cacheWarmer->warmGroup($group);
            $allResults['groups'][$group] = $result;
            
            $status = $result['status'];
            $duration = $result['duration_ms'] ?? 0;
            $warmed = $result['warmed_count'] ?? 0;
            
            $statusIcon = match ($status) {
                'success' => '✅',
                'dry_run' => '🧪',
                'skipped' => '⏭️',
                'failed' => '❌',
                default => '❓',
            };

            $this->line("     {$statusIcon} {$group}: {$status} ({$warmed} items, {$duration}ms)");
            
            if ($this->output->isVerbose() && isset($result['items'])) {
                foreach ((array) $result['items'] as $item) {
                    $this->line("        - {$item}");
                }
            }

            // Count by status
            match ($status) {
                'success', 'dry_run' => $allResults['success']++,
                'failed' => $allResults['failed']++,
                default => $allResults['skipped']++,
            };

            $allResults['total_duration_ms'] += $duration;
        }

        $allResults['memory_peak_mb'] = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        $this->newLine();

        return $allResults;
    }

    /**
     * Display final results
     */
    private function displayResults(array $results): void
    {
        $this->line('═══════════════════════════════════════════════════════════');
        $this->info('📊 Results Summary');
        $this->line('═══════════════════════════════════════════════════════════');
        
        $totalDuration = round((microtime(true) - $this->startTime) * 1000, 2);
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Successful Groups', $results['success']],
                ['Failed Groups', $results['failed']],
                ['Skipped Groups', $results['skipped']],
                ['Total Duration', "{$totalDuration}ms"],
                ['Warmer Duration', "{$results['total_duration_ms']}ms"],
                ['Peak Memory', "{$results['memory_peak_mb']} MB"],
            ]
        );

        $this->newLine();

        // Detailed group results
        if ($this->output->isVerbose()) {
            $this->info('📋 Group Details:');
            
            $rows = [];
            foreach ($results['groups'] as $group => $result) {
                $rows[] = [
                    $group,
                    $result['status'],
                    $result['warmed_count'] ?? '-',
                    "{$result['duration_ms']}ms",
                    $result['critical'] ? 'Yes' : 'No',
                    $result['error'] ?? '-',
                ];
            }

            $this->table(
                ['Group', 'Status', 'Items', 'Duration', 'Critical', 'Error'],
                $rows
            );
        }
    }

    /**
     * Check if any critical group failed
     */
    private function hasCriticalFailure(array $groups): bool
    {
        foreach ($groups as $group => $result) {
            if ($result['status'] === 'failed' && ($result['critical'] ?? false)) {
                return true;
            }
        }
        return false;
    }
}
