<?php

namespace MarcosBrendon\ApiForge\Console\Commands;

use Illuminate\Console\Command;
use MarcosBrendon\ApiForge\Services\CacheService;

class CacheManagementCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'apiforge:cache 
                            {action : Action to perform (clear, stats, gc, monitor)}
                            {--model= : Specific model to target}
                            {--tags= : Comma-separated tags to target}
                            {--force : Force action without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Manage ApiForge cache system';

    /**
     * Cache service instance
     */
    protected CacheService $cacheService;

    /**
     * Create a new command instance.
     */
    public function __construct(CacheService $cacheService)
    {
        parent::__construct();
        $this->cacheService = $cacheService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'clear':
                return $this->handleClear();
            
            case 'stats':
                return $this->handleStats();
            
            case 'gc':
                return $this->handleGarbageCollection();
            
            case 'monitor':
                return $this->handleMonitor();
            
            default:
                $this->error("Unknown action: {$action}");
                $this->showUsage();
                return 1;
        }
    }

    /**
     * Handle cache clearing
     */
    protected function handleClear(): int
    {
        $model = $this->option('model');
        $tags = $this->option('tags');
        $force = $this->option('force');

        if ($model) {
            if (!$force && !$this->confirm("Clear cache for model {$model}?")) {
                $this->info('Operation cancelled.');
                return 0;
            }

            $this->info("Clearing cache for model: {$model}");
            $result = $this->cacheService->invalidateByModel($model);
            
            if ($result) {
                $this->info("✅ Cache cleared for model: {$model}");
            } else {
                $this->error("❌ Failed to clear cache for model: {$model}");
                return 1;
            }
        } elseif ($tags) {
            $tagArray = array_map('trim', explode(',', $tags));
            
            if (!$force && !$this->confirm("Clear cache for tags: " . implode(', ', $tagArray) . "?")) {
                $this->info('Operation cancelled.');
                return 0;
            }

            $this->info("Clearing cache for tags: " . implode(', ', $tagArray));
            $result = $this->cacheService->invalidateByTags($tagArray);
            
            if ($result) {
                $this->info("✅ Cache cleared for tags: " . implode(', ', $tagArray));
            } else {
                $this->error("❌ Failed to clear cache for tags");
                return 1;
            }
        } else {
            if (!$force && !$this->confirm('Clear ALL ApiForge cache?')) {
                $this->info('Operation cancelled.');
                return 0;
            }

            $this->info('Clearing all ApiForge cache...');
            $result = $this->cacheService->flush();
            
            if ($result) {
                $this->info('✅ All ApiForge cache cleared');
            } else {
                $this->error('❌ Failed to clear cache');
                return 1;
            }
        }

        return 0;
    }

    /**
     * Handle cache statistics
     */
    protected function handleStats(): int
    {
        $this->info('📊 ApiForge Cache Statistics');
        $this->line('');

        $stats = $this->cacheService->getStatistics();

        // Basic stats
        $this->table(['Metric', 'Value'], [
            ['Total Keys', number_format($stats['total_keys'])],
            ['Total Size', $this->formatBytes($stats['total_size'])],
            ['Expired Keys', number_format($stats['expired_keys'])],
            ['Hit Rate', $stats['hit_rate'] . '%'],
            ['Oldest Entry', $stats['oldest_entry'] ?? 'N/A'],
            ['Newest Entry', $stats['newest_entry'] ?? 'N/A'],
        ]);

        // Model breakdown
        if (!empty($stats['models'])) {
            $this->line('');
            $this->info('📋 Cache by Model:');
            
            $modelData = [];
            foreach ($stats['models'] as $model => $count) {
                $modelData[] = [class_basename($model), number_format($count)];
            }
            
            $this->table(['Model', 'Keys'], $modelData);
        }

        // Recommendations
        $this->line('');
        $this->info('💡 Recommendations:');
        
        if ($stats['expired_keys'] > 0) {
            $this->warn("• Run garbage collection to remove {$stats['expired_keys']} expired keys");
        }
        
        if ($stats['hit_rate'] < 70) {
            $this->warn('• Hit rate is low - consider adjusting TTL or cache strategy');
        }
        
        if ($stats['total_keys'] > 10000) {
            $this->warn('• Large number of cache keys - consider implementing cache partitioning');
        }

        if ($stats['total_size'] > 100 * 1024 * 1024) { // 100MB
            $this->warn('• Large cache size - monitor memory usage');
        }

        return 0;
    }

    /**
     * Handle garbage collection
     */
    protected function handleGarbageCollection(): int
    {
        $this->info('🧹 Running cache garbage collection...');
        
        $removed = $this->cacheService->garbageCollect();
        
        if ($removed > 0) {
            $this->info("✅ Removed {$removed} expired cache entries");
        } else {
            $this->info('✅ No expired entries found');
        }

        return 0;
    }

    /**
     * Handle cache monitoring
     */
    protected function handleMonitor(): int
    {
        $this->info('👀 Cache Monitor - Press Ctrl+C to stop');
        $this->line('');

        $previousStats = $this->cacheService->getStatistics();
        $startTime = now();

        while (true) {
            sleep(5); // Update every 5 seconds
            
            $currentStats = $this->cacheService->getStatistics();
            $elapsed = $startTime->diffInSeconds(now());
            
            // Clear screen and show header
            if (function_exists('system')) {
                system('clear');
            }
            
            $this->info("📊 ApiForge Cache Monitor (Running for {$elapsed}s)");
            $this->line(str_repeat('=', 60));
            
            // Current stats
            $this->table(['Metric', 'Current', 'Change'], [
                [
                    'Total Keys',
                    number_format($currentStats['total_keys']),
                    $this->formatChange($currentStats['total_keys'] - $previousStats['total_keys'])
                ],
                [
                    'Cache Size',
                    $this->formatBytes($currentStats['total_size']),
                    $this->formatBytes($currentStats['total_size'] - $previousStats['total_size'])
                ],
                [
                    'Hit Rate',
                    $currentStats['hit_rate'] . '%',
                    $this->formatChange($currentStats['hit_rate'] - $previousStats['hit_rate'], '%')
                ],
                [
                    'Expired Keys',
                    number_format($currentStats['expired_keys']),
                    $this->formatChange($currentStats['expired_keys'] - $previousStats['expired_keys'])
                ]
            ]);

            $previousStats = $currentStats;
        }

        return 0;
    }

    /**
     * Show command usage
     */
    protected function showUsage(): void
    {
        $this->line('');
        $this->info('Available actions:');
        $this->line('  clear   - Clear cache (optionally by model or tags)');
        $this->line('  stats   - Show cache statistics');
        $this->line('  gc      - Run garbage collection');
        $this->line('  monitor - Monitor cache in real-time');
        $this->line('');
        $this->info('Examples:');
        $this->line('  php artisan apiforge:cache clear');
        $this->line('  php artisan apiforge:cache clear --model="App\\Models\\User"');
        $this->line('  php artisan apiforge:cache clear --tags="api,users"');
        $this->line('  php artisan apiforge:cache stats');
        $this->line('  php artisan apiforge:cache gc');
        $this->line('  php artisan apiforge:cache monitor');
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes, 1024));
        
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }

    /**
     * Format change with color
     */
    protected function formatChange($change, string $suffix = ''): string
    {
        if ($change > 0) {
            return "<fg=green>+{$change}{$suffix}</>";
        } elseif ($change < 0) {
            return "<fg=red>{$change}{$suffix}</>";
        } else {
            return "<fg=gray>0{$suffix}</>";
        }
    }
}