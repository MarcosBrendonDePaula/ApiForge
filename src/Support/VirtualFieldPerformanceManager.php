<?php

namespace MarcosBrendon\ApiForge\Support;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use MarcosBrendon\ApiForge\Exceptions\FilterValidationException;

class VirtualFieldPerformanceManager
{
    /**
     * Memory limit in bytes
     */
    protected int $memoryLimit;

    /**
     * Time limit in seconds
     */
    protected int $timeLimit;

    /**
     * Batch size for processing
     */
    protected int $batchSize;

    /**
     * Whether to enable lazy loading
     */
    protected bool $lazyLoadingEnabled;

    /**
     * Maximum records for virtual field sorting
     */
    protected int $maxSortRecords;

    /**
     * Whether to log performance metrics
     */
    protected bool $logPerformance;

    /**
     * Performance tracking data
     */
    protected array $performanceData = [];

    /**
     * Create a new performance manager
     */
    public function __construct()
    {
        $this->memoryLimit = $this->convertToBytes(config('apiforge.virtual_fields.memory_limit', 128) . 'M');
        $this->timeLimit = config('apiforge.virtual_fields.time_limit', 30);
        $this->batchSize = config('apiforge.virtual_fields.batch_size', 100);
        $this->lazyLoadingEnabled = config('apiforge.virtual_fields.lazy_loading_enabled', true);
        $this->maxSortRecords = config('apiforge.virtual_fields.max_sort_records', 10000);
        $this->logPerformance = config('apiforge.virtual_fields.log_performance', false);
    }

    /**
     * Execute a virtual field operation with performance monitoring
     */
    public function executeWithMonitoring(string $operation, callable $callback, array $context = []): mixed
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $operationId = uniqid($operation . '_');

        try {
            // Check initial limits
            $this->checkMemoryLimit($startMemory, $operationId);
            
            // Execute the operation
            $result = $callback();
            
            // Record performance metrics
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            
            $this->recordPerformanceMetrics($operationId, [
                'operation' => $operation,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration' => $endTime - $startTime,
                'start_memory' => $startMemory,
                'end_memory' => $endMemory,
                'memory_used' => $endMemory - $startMemory,
                'context' => $context,
                'success' => true
            ]);
            
            return $result;
        } catch (\Exception $e) {
            // Record failure metrics
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            
            $this->recordPerformanceMetrics($operationId, [
                'operation' => $operation,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration' => $endTime - $startTime,
                'start_memory' => $startMemory,
                'end_memory' => $endMemory,
                'memory_used' => $endMemory - $startMemory,
                'context' => $context,
                'success' => false,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Process collection in batches with memory and time limits
     */
    public function processBatches(Collection $models, callable $processor, array $options = []): array
    {
        $batchSize = $options['batch_size'] ?? $this->batchSize;
        $maxMemory = $options['memory_limit'] ?? $this->memoryLimit;
        $maxTime = $options['time_limit'] ?? $this->timeLimit;
        
        $startTime = microtime(true);
        $results = [];
        $processedCount = 0;
        
        // Split into batches
        $batches = $models->chunk($batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            // Check time limit
            $elapsed = microtime(true) - $startTime;
            if ($elapsed > $maxTime) {
                Log::warning("Virtual field batch processing exceeded time limit", [
                    'elapsed_time' => $elapsed,
                    'time_limit' => $maxTime,
                    'processed_batches' => $batchIndex,
                    'processed_records' => $processedCount
                ]);
                
                if ($options['strict_limits'] ?? true) {
                    throw new FilterValidationException(
                        "Virtual field processing exceeded time limit of {$maxTime} seconds"
                    );
                }
                break;
            }
            
            // Check memory limit
            $currentMemory = memory_get_usage(true);
            if ($currentMemory > $maxMemory) {
                Log::warning("Virtual field batch processing exceeded memory limit", [
                    'current_memory' => $this->formatBytes($currentMemory),
                    'memory_limit' => $this->formatBytes($maxMemory),
                    'processed_batches' => $batchIndex,
                    'processed_records' => $processedCount
                ]);
                
                if ($options['strict_limits'] ?? true) {
                    throw new FilterValidationException(
                        "Virtual field processing exceeded memory limit of " . $this->formatBytes($maxMemory)
                    );
                }
                break;
            }
            
            try {
                // Process the batch
                $batchResult = $processor($batch, $batchIndex);
                $results[] = $batchResult;
                $processedCount += $batch->count();
                
                // Force garbage collection periodically
                if ($batchIndex % 10 === 0) {
                    $this->forceGarbageCollection();
                }
            } catch (\Exception $e) {
                Log::error("Error processing virtual field batch", [
                    'batch_index' => $batchIndex,
                    'batch_size' => $batch->count(),
                    'error' => $e->getMessage()
                ]);
                
                if ($options['continue_on_error'] ?? false) {
                    continue;
                }
                throw $e;
            }
        }
        
        $totalTime = microtime(true) - $startTime;
        
        if ($this->logPerformance) {
            Log::info("Virtual field batch processing completed", [
                'total_records' => $models->count(),
                'processed_records' => $processedCount,
                'total_batches' => $batches->count(),
                'batch_size' => $batchSize,
                'total_time' => $totalTime,
                'average_time_per_batch' => $totalTime / max(1, count($results)),
                'memory_peak' => $this->formatBytes(memory_get_peak_usage(true))
            ]);
        }
        
        return $results;
    }

    /**
     * Implement lazy loading for virtual fields
     */
    public function lazyLoadVirtualFields(Collection $models, array $virtualFields, VirtualFieldRegistry $registry): \Closure
    {
        if (!$this->lazyLoadingEnabled) {
            // If lazy loading is disabled, compute all fields immediately
            return function() use ($models, $virtualFields, $registry) {
                $results = [];
                foreach ($virtualFields as $fieldName) {
                    $definition = $registry->get($fieldName);
                    if ($definition) {
                        $results[$fieldName] = [];
                        foreach ($models as $model) {
                            $results[$fieldName][$model->getKey()] = $definition->compute($model);
                        }
                    }
                }
                return $results;
            };
        }
        
        // Return a closure that computes fields on demand
        return function(array $requestedFields = null) use ($models, $virtualFields, $registry) {
            $fieldsToCompute = $requestedFields ?? $virtualFields;
            $results = [];
            
            foreach ($fieldsToCompute as $fieldName) {
                if (!in_array($fieldName, $virtualFields)) {
                    continue;
                }
                
                $definition = $registry->get($fieldName);
                if ($definition) {
                    $results[$fieldName] = [];
                    foreach ($models as $model) {
                        try {
                            $results[$fieldName][$model->getKey()] = $definition->compute($model);
                        } catch (\Exception $e) {
                            Log::warning("Failed to compute virtual field in lazy loading", [
                                'field' => $fieldName,
                                'model_id' => $model->getKey(),
                                'error' => $e->getMessage()
                            ]);
                            $results[$fieldName][$model->getKey()] = $definition->defaultValue;
                        }
                    }
                }
            }
            
            return $results;
        };
    }

    /**
     * Optimize virtual field sorting for large datasets
     */
    public function optimizeSorting(Collection $models, string $virtualField, string $direction, VirtualFieldRegistry $registry): Collection
    {
        if ($models->count() > $this->maxSortRecords) {
            Log::warning("Virtual field sorting requested for large dataset", [
                'record_count' => $models->count(),
                'max_sort_records' => $this->maxSortRecords,
                'virtual_field' => $virtualField
            ]);
            
            // For very large datasets, we might want to use a different strategy
            // such as database-level sorting or pagination-based sorting
            if (config('apiforge.virtual_fields.sort_fallback_enabled', true)) {
                Log::info("Falling back to regular sorting due to dataset size");
                return $models; // Return unsorted - let the caller handle fallback
            }
        }
        
        $definition = $registry->get($virtualField);
        if (!$definition || !$definition->sortable) {
            return $models;
        }
        
        return $this->executeWithMonitoring('virtual_field_sorting', function() use ($models, $definition, $direction) {
            // Compute virtual field values for all models
            $computedValues = [];
            
            foreach ($models as $model) {
                try {
                    $computedValues[$model->getKey()] = $definition->compute($model);
                } catch (\Exception $e) {
                    Log::warning("Failed to compute virtual field for sorting", [
                        'field' => $definition->name,
                        'model_id' => $model->getKey(),
                        'error' => $e->getMessage()
                    ]);
                    $computedValues[$model->getKey()] = $definition->defaultValue;
                }
            }
            
            // Sort models based on computed values
            return $models->sort(function($a, $b) use ($computedValues, $direction) {
                $valueA = $computedValues[$a->getKey()];
                $valueB = $computedValues[$b->getKey()];
                
                if ($valueA === $valueB) {
                    return 0;
                }
                
                $comparison = $valueA <=> $valueB;
                return $direction === 'desc' ? -$comparison : $comparison;
            });
        }, [
            'virtual_field' => $definition->name,
            'direction' => $direction,
            'record_count' => $models->count()
        ]);
    }

    /**
     * Check memory limit and throw exception if exceeded
     */
    public function checkMemoryLimit(?int $startMemory = null, string $operationId = ''): void
    {
        $currentMemory = memory_get_usage(true);
        
        if ($currentMemory > $this->memoryLimit) {
            $message = "Virtual field processing exceeded memory limit";
            $context = [
                'current_memory' => $this->formatBytes($currentMemory),
                'memory_limit' => $this->formatBytes($this->memoryLimit),
                'operation_id' => $operationId
            ];
            
            if ($startMemory) {
                $context['memory_used'] = $this->formatBytes($currentMemory - $startMemory);
            }
            
            Log::error($message, $context);
            throw new FilterValidationException($message);
        }
    }

    /**
     * Check time limit and throw exception if exceeded
     */
    public function checkTimeLimit(float $startTime, string $operationId = ''): void
    {
        $elapsed = microtime(true) - $startTime;
        
        if ($elapsed > $this->timeLimit) {
            $message = "Virtual field processing exceeded time limit";
            $context = [
                'elapsed_time' => $elapsed,
                'time_limit' => $this->timeLimit,
                'operation_id' => $operationId
            ];
            
            Log::error($message, $context);
            throw new FilterValidationException($message);
        }
    }

    /**
     * Force garbage collection to free memory
     */
    public function forceGarbageCollection(): void
    {
        if (function_exists('gc_collect_cycles')) {
            $collected = gc_collect_cycles();
            
            if ($this->logPerformance && $collected > 0) {
                Log::debug("Garbage collection freed cycles", [
                    'cycles_collected' => $collected,
                    'memory_usage' => $this->formatBytes(memory_get_usage(true))
                ]);
            }
        }
    }

    /**
     * Record performance metrics
     */
    protected function recordPerformanceMetrics(string $operationId, array $metrics): void
    {
        $this->performanceData[$operationId] = $metrics;
        
        if ($this->logPerformance) {
            Log::info("Virtual field performance metrics", array_merge(
                ['operation_id' => $operationId],
                $metrics
            ));
        }
        
        // Keep only the last 100 operations to prevent memory leaks
        if (count($this->performanceData) > 100) {
            $this->performanceData = array_slice($this->performanceData, -100, null, true);
        }
    }

    /**
     * Get performance statistics
     */
    public function getPerformanceStatistics(): array
    {
        if (empty($this->performanceData)) {
            return [
                'total_operations' => 0,
                'successful_operations' => 0,
                'failed_operations' => 0,
                'average_duration' => 0,
                'average_memory_usage' => 0,
                'peak_memory_usage' => 0,
                'memory_limit' => $this->memoryLimit,
                'time_limit' => $this->timeLimit,
                'batch_size' => $this->batchSize,
                'lazy_loading_enabled' => $this->lazyLoadingEnabled
            ];
        }
        
        $successful = array_filter($this->performanceData, fn($data) => $data['success']);
        $failed = array_filter($this->performanceData, fn($data) => !$data['success']);
        
        $durations = array_column($this->performanceData, 'duration');
        $memoryUsages = array_column($this->performanceData, 'memory_used');
        
        return [
            'total_operations' => count($this->performanceData),
            'successful_operations' => count($successful),
            'failed_operations' => count($failed),
            'average_duration' => count($durations) > 0 ? array_sum($durations) / count($durations) : 0,
            'max_duration' => count($durations) > 0 ? max($durations) : 0,
            'min_duration' => count($durations) > 0 ? min($durations) : 0,
            'average_memory_usage' => count($memoryUsages) > 0 ? array_sum($memoryUsages) / count($memoryUsages) : 0,
            'peak_memory_usage' => count($memoryUsages) > 0 ? max($memoryUsages) : 0,
            'memory_limit' => $this->memoryLimit,
            'time_limit' => $this->timeLimit,
            'batch_size' => $this->batchSize,
            'lazy_loading_enabled' => $this->lazyLoadingEnabled
        ];
    }

    /**
     * Clear performance data
     */
    public function clearPerformanceData(): void
    {
        $this->performanceData = [];
    }

    /**
     * Convert memory string to bytes
     */
    protected function convertToBytes(string $value): int
    {
        $value = trim($value);
        $unit = strtolower(substr($value, -1));
        $number = (int) substr($value, 0, -1);
        
        switch ($unit) {
            case 'g':
                return $number * 1024 * 1024 * 1024;
            case 'm':
                return $number * 1024 * 1024;
            case 'k':
                return $number * 1024;
            default:
                return (int) $value;
        }
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
     * Set memory limit
     */
    public function setMemoryLimit(int $bytes): void
    {
        $this->memoryLimit = $bytes;
    }

    /**
     * Set time limit
     */
    public function setTimeLimit(int $seconds): void
    {
        $this->timeLimit = $seconds;
    }

    /**
     * Set batch size
     */
    public function setBatchSize(int $size): void
    {
        $this->batchSize = $size;
    }

    /**
     * Enable or disable lazy loading
     */
    public function setLazyLoadingEnabled(bool $enabled): void
    {
        $this->lazyLoadingEnabled = $enabled;
    }

    /**
     * Set performance logging
     */
    public function setLogPerformance(bool $log): void
    {
        $this->logPerformance = $log;
    }
}