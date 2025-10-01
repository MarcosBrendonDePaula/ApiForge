<?php

namespace MarcosBrendon\ApiForge\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class VirtualFieldMonitor
{
    /**
     * Whether monitoring is enabled
     */
    protected bool $enabled;

    /**
     * Slow computation threshold in milliseconds
     */
    protected int $slowThreshold;

    /**
     * Whether to track memory usage
     */
    protected bool $trackMemory;

    /**
     * Whether to track computation time
     */
    protected bool $trackTime;

    /**
     * Whether to log slow computations
     */
    protected bool $logSlowComputations;

    /**
     * Metrics storage
     */
    protected array $metrics = [];

    /**
     * Active operations tracking
     */
    protected array $activeOperations = [];

    /**
     * Create a new virtual field monitor
     */
    public function __construct()
    {
        $this->enabled = config('apiforge.virtual_fields.enable_monitoring', false);
        $this->slowThreshold = config('apiforge.virtual_fields.slow_computation_threshold', 1000);
        $this->trackMemory = config('apiforge.virtual_fields.track_memory_usage', true);
        $this->trackTime = config('apiforge.virtual_fields.track_computation_time', true);
        $this->logSlowComputations = config('apiforge.virtual_fields.log_slow_computations', true);
    }

    /**
     * Start monitoring an operation
     */
    public function startOperation(string $operationType, string $fieldName, array $context = []): string
    {
        if (!$this->enabled) {
            return '';
        }

        $operationId = uniqid($operationType . '_' . $fieldName . '_');
        
        $this->activeOperations[$operationId] = [
            'type' => $operationType,
            'field' => $fieldName,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'context' => $context
        ];

        return $operationId;
    }

    /**
     * End monitoring an operation
     */
    public function endOperation(string $operationId, bool $success = true, ?string $error = null): array
    {
        if (!$this->enabled || !isset($this->activeOperations[$operationId])) {
            return [];
        }

        $operation = $this->activeOperations[$operationId];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $metrics = [
            'operation_id' => $operationId,
            'type' => $operation['type'],
            'field' => $operation['field'],
            'start_time' => $operation['start_time'],
            'end_time' => $endTime,
            'duration_ms' => ($endTime - $operation['start_time']) * 1000,
            'start_memory' => $operation['start_memory'],
            'end_memory' => $endMemory,
            'memory_used' => $endMemory - $operation['start_memory'],
            'success' => $success,
            'error' => $error,
            'context' => $operation['context']
        ];

        // Store metrics
        $this->storeMetrics($metrics);

        // Check for slow operations
        if ($this->logSlowComputations && $metrics['duration_ms'] > $this->slowThreshold) {
            $this->logSlowOperation($metrics);
        }

        // Clean up
        unset($this->activeOperations[$operationId]);

        return $metrics;
    }

    /**
     * Monitor a virtual field computation
     */
    public function monitorComputation(string $fieldName, callable $computation, array $context = []): mixed
    {
        if (!$this->enabled) {
            return $computation();
        }

        $operationId = $this->startOperation('computation', $fieldName, $context);
        
        try {
            $result = $computation();
            $this->endOperation($operationId, true);
            return $result;
        } catch (\Exception $e) {
            $this->endOperation($operationId, false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Monitor batch operations
     */
    public function monitorBatch(string $operationType, array $fieldNames, int $modelCount, callable $operation): mixed
    {
        if (!$this->enabled) {
            return $operation();
        }

        $operationId = $this->startOperation('batch_' . $operationType, implode(',', $fieldNames), [
            'field_count' => count($fieldNames),
            'model_count' => $modelCount
        ]);
        
        try {
            $result = $operation();
            $this->endOperation($operationId, true);
            return $result;
        } catch (\Exception $e) {
            $this->endOperation($operationId, false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Track cache operations
     */
    public function trackCacheOperation(string $operation, string $fieldName, bool $hit = null): void
    {
        if (!$this->enabled) {
            return;
        }

        $key = 'cache_' . $operation;
        
        if (!isset($this->metrics[$key])) {
            $this->metrics[$key] = [
                'total' => 0,
                'by_field' => []
            ];
        }

        $this->metrics[$key]['total']++;
        
        if (!isset($this->metrics[$key]['by_field'][$fieldName])) {
            $this->metrics[$key]['by_field'][$fieldName] = 0;
        }
        
        $this->metrics[$key]['by_field'][$fieldName]++;

        // Track cache hits/misses
        if ($operation === 'retrieve' && $hit !== null) {
            $hitKey = $hit ? 'cache_hits' : 'cache_misses';
            
            if (!isset($this->metrics[$hitKey])) {
                $this->metrics[$hitKey] = [
                    'total' => 0,
                    'by_field' => []
                ];
            }
            
            $this->metrics[$hitKey]['total']++;
            
            if (!isset($this->metrics[$hitKey]['by_field'][$fieldName])) {
                $this->metrics[$hitKey]['by_field'][$fieldName] = 0;
            }
            
            $this->metrics[$hitKey]['by_field'][$fieldName]++;
        }
    }

    /**
     * Track memory usage at a specific point
     */
    public function trackMemoryUsage(string $label, array $context = []): void
    {
        if (!$this->enabled || !$this->trackMemory) {
            return;
        }

        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        
        if (!isset($this->metrics['memory_tracking'])) {
            $this->metrics['memory_tracking'] = [];
        }
        
        $this->metrics['memory_tracking'][] = [
            'label' => $label,
            'timestamp' => microtime(true),
            'memory_usage' => $memoryUsage,
            'peak_memory' => $peakMemory,
            'context' => $context
        ];

        // Keep only the last 100 memory tracking points
        if (count($this->metrics['memory_tracking']) > 100) {
            $this->metrics['memory_tracking'] = array_slice($this->metrics['memory_tracking'], -100);
        }
    }

    /**
     * Get comprehensive performance statistics
     */
    public function getStatistics(): array
    {
        if (!$this->enabled) {
            return ['monitoring_enabled' => false];
        }

        $stats = [
            'monitoring_enabled' => true,
            'slow_threshold_ms' => $this->slowThreshold,
            'track_memory' => $this->trackMemory,
            'track_time' => $this->trackTime,
            'active_operations' => count($this->activeOperations),
            'total_operations' => 0,
            'successful_operations' => 0,
            'failed_operations' => 0,
            'slow_operations' => 0,
            'average_duration_ms' => 0,
            'max_duration_ms' => 0,
            'min_duration_ms' => 0,
            'total_memory_used' => 0,
            'peak_memory_usage' => 0,
            'cache_hit_rate' => 0,
            'operations_by_type' => [],
            'operations_by_field' => [],
            'slow_operations_by_field' => []
        ];

        // Analyze stored metrics
        if (isset($this->metrics['operations'])) {
            $operations = $this->metrics['operations'];
            $stats['total_operations'] = count($operations);
            
            $successful = array_filter($operations, fn($op) => $op['success']);
            $failed = array_filter($operations, fn($op) => !$op['success']);
            $slow = array_filter($operations, fn($op) => $op['duration_ms'] > $this->slowThreshold);
            
            $stats['successful_operations'] = count($successful);
            $stats['failed_operations'] = count($failed);
            $stats['slow_operations'] = count($slow);
            
            // Duration statistics
            $durations = array_column($operations, 'duration_ms');
            if (!empty($durations)) {
                $stats['average_duration_ms'] = array_sum($durations) / count($durations);
                $stats['max_duration_ms'] = max($durations);
                $stats['min_duration_ms'] = min($durations);
            }
            
            // Memory statistics
            $memoryUsages = array_column($operations, 'memory_used');
            if (!empty($memoryUsages)) {
                $stats['total_memory_used'] = array_sum($memoryUsages);
                $stats['peak_memory_usage'] = max(array_column($operations, 'end_memory'));
            }
            
            // Group by type and field
            foreach ($operations as $operation) {
                $type = $operation['type'];
                $field = $operation['field'];
                
                if (!isset($stats['operations_by_type'][$type])) {
                    $stats['operations_by_type'][$type] = 0;
                }
                $stats['operations_by_type'][$type]++;
                
                if (!isset($stats['operations_by_field'][$field])) {
                    $stats['operations_by_field'][$field] = 0;
                }
                $stats['operations_by_field'][$field]++;
                
                // Track slow operations by field
                if ($operation['duration_ms'] > $this->slowThreshold) {
                    if (!isset($stats['slow_operations_by_field'][$field])) {
                        $stats['slow_operations_by_field'][$field] = 0;
                    }
                    $stats['slow_operations_by_field'][$field]++;
                }
            }
        }

        // Cache statistics
        $cacheHits = $this->metrics['cache_hits']['total'] ?? 0;
        $cacheMisses = $this->metrics['cache_misses']['total'] ?? 0;
        $totalCacheOperations = $cacheHits + $cacheMisses;
        
        if ($totalCacheOperations > 0) {
            $stats['cache_hit_rate'] = ($cacheHits / $totalCacheOperations) * 100;
        }
        
        $stats['cache_operations'] = [
            'hits' => $cacheHits,
            'misses' => $cacheMisses,
            'stores' => $this->metrics['cache_store']['total'] ?? 0,
            'invalidations' => $this->metrics['cache_invalidate']['total'] ?? 0
        ];

        return $stats;
    }

    /**
     * Get detailed metrics for a specific field
     */
    public function getFieldMetrics(string $fieldName): array
    {
        if (!$this->enabled) {
            return [];
        }

        $fieldMetrics = [
            'field_name' => $fieldName,
            'total_operations' => 0,
            'successful_operations' => 0,
            'failed_operations' => 0,
            'slow_operations' => 0,
            'average_duration_ms' => 0,
            'total_memory_used' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'recent_operations' => []
        ];

        if (isset($this->metrics['operations'])) {
            $fieldOperations = array_filter(
                $this->metrics['operations'],
                fn($op) => $op['field'] === $fieldName
            );
            
            $fieldMetrics['total_operations'] = count($fieldOperations);
            $fieldMetrics['successful_operations'] = count(array_filter($fieldOperations, fn($op) => $op['success']));
            $fieldMetrics['failed_operations'] = count(array_filter($fieldOperations, fn($op) => !$op['success']));
            $fieldMetrics['slow_operations'] = count(array_filter($fieldOperations, fn($op) => $op['duration_ms'] > $this->slowThreshold));
            
            $durations = array_column($fieldOperations, 'duration_ms');
            if (!empty($durations)) {
                $fieldMetrics['average_duration_ms'] = array_sum($durations) / count($durations);
            }
            
            $memoryUsages = array_column($fieldOperations, 'memory_used');
            if (!empty($memoryUsages)) {
                $fieldMetrics['total_memory_used'] = array_sum($memoryUsages);
            }
            
            // Get recent operations (last 10)
            $fieldMetrics['recent_operations'] = array_slice(
                array_reverse($fieldOperations),
                0,
                10
            );
        }

        // Cache metrics for this field
        $fieldMetrics['cache_hits'] = $this->metrics['cache_hits']['by_field'][$fieldName] ?? 0;
        $fieldMetrics['cache_misses'] = $this->metrics['cache_misses']['by_field'][$fieldName] ?? 0;

        return $fieldMetrics;
    }

    /**
     * Clear all metrics
     */
    public function clearMetrics(): void
    {
        $this->metrics = [];
        $this->activeOperations = [];
    }

    /**
     * Export metrics to cache for persistence
     */
    public function exportMetrics(): void
    {
        if (!$this->enabled) {
            return;
        }

        $cacheKey = 'virtual_field_metrics_' . date('Y-m-d-H');
        Cache::put($cacheKey, $this->metrics, 3600); // Store for 1 hour
    }

    /**
     * Import metrics from cache
     */
    public function importMetrics(string $date = null): void
    {
        if (!$this->enabled) {
            return;
        }

        $cacheKey = 'virtual_field_metrics_' . ($date ?? date('Y-m-d-H'));
        $cachedMetrics = Cache::get($cacheKey);
        
        if ($cachedMetrics) {
            $this->metrics = array_merge($this->metrics, $cachedMetrics);
        }
    }

    /**
     * Store operation metrics
     */
    protected function storeMetrics(array $metrics): void
    {
        if (!isset($this->metrics['operations'])) {
            $this->metrics['operations'] = [];
        }
        
        $this->metrics['operations'][] = $metrics;
        
        // Keep only the last 1000 operations to prevent memory issues
        if (count($this->metrics['operations']) > 1000) {
            $this->metrics['operations'] = array_slice($this->metrics['operations'], -1000);
        }
    }

    /**
     * Log slow operation
     */
    protected function logSlowOperation(array $metrics): void
    {
        Log::warning("Slow virtual field operation detected", [
            'field' => $metrics['field'],
            'operation_type' => $metrics['type'],
            'duration_ms' => $metrics['duration_ms'],
            'threshold_ms' => $this->slowThreshold,
            'memory_used' => $this->formatBytes($metrics['memory_used']),
            'context' => $metrics['context']
        ]);
    }

    /**
     * Format bytes to human readable string
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Enable or disable monitoring
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Set slow computation threshold
     */
    public function setSlowThreshold(int $milliseconds): void
    {
        $this->slowThreshold = $milliseconds;
    }

    /**
     * Enable or disable memory tracking
     */
    public function setTrackMemory(bool $track): void
    {
        $this->trackMemory = $track;
    }

    /**
     * Enable or disable time tracking
     */
    public function setTrackTime(bool $track): void
    {
        $this->trackTime = $track;
    }

    /**
     * Enable or disable slow computation logging
     */
    public function setLogSlowComputations(bool $log): void
    {
        $this->logSlowComputations = $log;
    }
}