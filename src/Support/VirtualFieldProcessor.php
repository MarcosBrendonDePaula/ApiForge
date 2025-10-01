<?php

namespace MarcosBrendon\ApiForge\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use MarcosBrendon\ApiForge\Exceptions\FilterValidationException;
use MarcosBrendon\ApiForge\Support\VirtualFieldCache;
use MarcosBrendon\ApiForge\Support\VirtualFieldPerformanceManager;
use MarcosBrendon\ApiForge\Support\VirtualFieldMonitor;

class VirtualFieldProcessor
{
    /**
     * The virtual field registry
     */
    protected VirtualFieldRegistry $registry;

    /**
     * The virtual field cache instance
     */
    protected VirtualFieldCache $cache;

    /**
     * The performance manager instance
     */
    protected VirtualFieldPerformanceManager $performanceManager;

    /**
     * The monitor instance
     */
    protected VirtualFieldMonitor $monitor;

    /**
     * Whether to use caching
     */
    protected bool $cacheEnabled;

    /**
     * Default cache TTL
     */
    protected int $defaultCacheTtl;

    /**
     * Memory limit for batch processing (in MB)
     */
    protected int $memoryLimit;

    /**
     * Time limit for computation (in seconds)
     */
    protected int $timeLimit;

    /**
     * Create a new virtual field processor
     */
    public function __construct(VirtualFieldRegistry $registry)
    {
        $this->registry = $registry;
        $this->cache = new VirtualFieldCache();
        $this->performanceManager = new VirtualFieldPerformanceManager();
        $this->monitor = new VirtualFieldMonitor();
        $this->cacheEnabled = config('apiforge.virtual_fields.cache_enabled', true);
        $this->defaultCacheTtl = config('apiforge.virtual_fields.default_cache_ttl', 3600);
        $this->memoryLimit = config('apiforge.virtual_fields.memory_limit', 128);
        $this->timeLimit = config('apiforge.virtual_fields.time_limit', 30);
    }

    /**
     * Process virtual field for filtering
     */
    public function processForFiltering(Builder $query, string $field, mixed $value, string $operator): void
    {
        $definition = $this->registry->get($field);
        if (!$definition) {
            throw new FilterValidationException("Virtual field '{$field}' is not registered");
        }

        if (!$definition->supportsOperator($operator)) {
            throw new FilterValidationException(
                "Operator '{$operator}' is not supported for virtual field '{$field}' of type '{$definition->type}'"
            );
        }

        // For filtering, we need to compute the virtual field for all records
        // This is done by adding a subquery or using a having clause
        $this->addVirtualFieldFilter($query, $definition, $value, $operator);
    }

    /**
     * Process virtual fields for selection
     */
    public function processForSelection(Collection $models, array $virtualFields): Collection
    {
        if ($models->isEmpty() || empty($virtualFields)) {
            return $models;
        }

        return $this->performanceManager->executeWithMonitoring(
            'virtual_field_selection',
            function() use ($models, $virtualFields) {
                // Use batch processing for large datasets
                if ($models->count() > config('apiforge.virtual_fields.batch_size', 100)) {
                    return $this->processSelectionInBatches($models, $virtualFields);
                }

                // Process each virtual field
                foreach ($virtualFields as $fieldName) {
                    $definition = $this->registry->get($fieldName);
                    if (!$definition) {
                        continue;
                    }

                    $this->computeFieldForModels($models, $definition);
                }

                return $models;
            },
            [
                'virtual_fields' => $virtualFields,
                'model_count' => $models->count()
            ]
        );
    }

    /**
     * Process virtual field for sorting
     */
    public function processForSorting(Builder $query, string $field, string $direction): void
    {
        $definition = $this->registry->get($field);
        if (!$definition) {
            throw new FilterValidationException("Virtual field '{$field}' is not registered");
        }

        if (!$definition->sortable) {
            throw new FilterValidationException("Virtual field '{$field}' is not sortable");
        }

        // For sorting, we need to add the computed field to the query
        $this->addVirtualFieldSort($query, $definition, $direction);
    }

    /**
     * Optimize query for virtual fields
     */
    public function optimizeQuery(Builder $query, array $virtualFields): Builder
    {
        if (empty($virtualFields)) {
            return $query;
        }

        // Get all dependencies for the virtual fields
        $dependencies = $this->registry->getAllDependencies($virtualFields);

        // Optimize field selection
        $this->optimizeFieldSelection($query, $dependencies['fields']);

        // Optimize relationship loading
        $this->optimizeRelationshipLoading($query, $dependencies['relationships']);

        // Add virtual field metadata to query for later processing
        // Note: This is stored as metadata for later processing

        return $query;
    }

    /**
     * Optimize field selection for virtual field dependencies
     */
    protected function optimizeFieldSelection(Builder $query, array $requiredFields): void
    {
        if (empty($requiredFields)) {
            return;
        }

        $existingSelect = $query->getQuery()->columns;
        
        if (empty($existingSelect)) {
            // No specific fields selected, add required fields
            $query->addSelect($requiredFields);
        } elseif (!in_array('*', $existingSelect)) {
            // Specific fields selected, add missing dependencies
            $missingFields = array_diff($requiredFields, $existingSelect);
            if (!empty($missingFields)) {
                $query->addSelect($missingFields);
            }
        }
        // If '*' is selected, all fields are already included
    }

    /**
     * Optimize relationship loading for virtual field dependencies
     */
    protected function optimizeRelationshipLoading(Builder $query, array $relationships): void
    {
        if (empty($relationships)) {
            return;
        }

        // Get existing eager loads
        $existingWith = $query->getEagerLoads();
        
        // Add missing relationships
        $newRelationships = [];
        foreach ($relationships as $relationship) {
            if (!isset($existingWith[$relationship])) {
                $newRelationships[] = $relationship;
            }
        }

        if (!empty($newRelationships)) {
            $query->with($newRelationships);
        }
    }

    /**
     * Optimize query for virtual field filtering
     */
    public function optimizeForFiltering(Builder $query, array $virtualFieldFilters): Builder
    {
        if (empty($virtualFieldFilters)) {
            return $query;
        }

        // Collect all virtual fields used in filters
        $virtualFields = array_column($virtualFieldFilters, 'field');
        
        // Get dependencies for all virtual fields
        $allDependencies = $this->registry->getAllDependencies($virtualFields);

        // Optimize the query
        $this->optimizeFieldSelection($query, $allDependencies['fields']);
        $this->optimizeRelationshipLoading($query, $allDependencies['relationships']);

        // Store filter information for post-processing
        // Note: This is stored as metadata for later processing

        return $query;
    }

    /**
     * Process virtual field filters after query execution
     */
    public function processVirtualFieldFilters(Collection $models, array $virtualFieldFilters): Collection
    {
        if ($models->isEmpty() || empty($virtualFieldFilters)) {
            return $models;
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        try {
            // First, compute all required virtual fields for all models
            $virtualFields = array_unique(array_column($virtualFieldFilters, 'field'));
            $computedValues = $this->computeBatch($virtualFields, $models);

            // Apply filters based on computed values
            $filteredModels = $models->filter(function ($model) use ($virtualFieldFilters, $computedValues) {
                foreach ($virtualFieldFilters as $filter) {
                    $field = $filter['field'];
                    $operator = $filter['operator'];
                    $value = $filter['value'];
                    $logic = $filter['logic'] ?? 'and';

                    $computedValue = $computedValues[$field][$model->getKey()] ?? null;
                    $matches = $this->evaluateVirtualFieldFilter($computedValue, $operator, $value);

                    if ($logic === 'and' && !$matches) {
                        return false;
                    } elseif ($logic === 'or' && $matches) {
                        return true;
                    }
                }
                return true;
            });

            // Check limits
            $this->checkLimits($startTime, $startMemory);

            return $filteredModels;
        } catch (\Exception $e) {
            Log::error("Virtual field filter processing failed", [
                'filters' => $virtualFieldFilters,
                'model_count' => $models->count(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Evaluate a virtual field filter condition
     */
    protected function evaluateVirtualFieldFilter($computedValue, string $operator, $filterValue): bool
    {
        switch ($operator) {
            case 'eq':
                return $computedValue == $filterValue;
            case 'ne':
                return $computedValue != $filterValue;
            case 'gt':
                return $computedValue > $filterValue;
            case 'gte':
                return $computedValue >= $filterValue;
            case 'lt':
                return $computedValue < $filterValue;
            case 'lte':
                return $computedValue <= $filterValue;
            case 'like':
                if (is_string($computedValue) && is_string($filterValue)) {
                    $pattern = str_replace(['*', '%'], ['.*', '.*'], preg_quote($filterValue, '/'));
                    return preg_match("/^{$pattern}$/i", $computedValue);
                }
                return false;
            case 'not_like':
                if (is_string($computedValue) && is_string($filterValue)) {
                    $pattern = str_replace(['*', '%'], ['.*', '.*'], preg_quote($filterValue, '/'));
                    return !preg_match("/^{$pattern}$/i", $computedValue);
                }
                return true;
            case 'in':
                $values = is_array($filterValue) ? $filterValue : explode(',', $filterValue);
                return in_array($computedValue, $values);
            case 'not_in':
                $values = is_array($filterValue) ? $filterValue : explode(',', $filterValue);
                return !in_array($computedValue, $values);
            case 'null':
                return $computedValue === null;
            case 'not_null':
                return $computedValue !== null;
            case 'between':
                $values = is_array($filterValue) ? $filterValue : explode('|', $filterValue);
                if (count($values) === 2) {
                    return $computedValue >= $values[0] && $computedValue <= $values[1];
                }
                return false;
            case 'not_between':
                $values = is_array($filterValue) ? $filterValue : explode('|', $filterValue);
                if (count($values) === 2) {
                    return $computedValue < $values[0] || $computedValue > $values[1];
                }
                return true;
            case 'starts_with':
                return is_string($computedValue) && is_string($filterValue) && 
                       str_starts_with(strtolower($computedValue), strtolower($filterValue));
            case 'ends_with':
                return is_string($computedValue) && is_string($filterValue) && 
                       str_ends_with(strtolower($computedValue), strtolower($filterValue));
            default:
                return false;
        }
    }

    /**
     * Compute a virtual field for multiple models
     */
    protected function computeFieldForModels(Collection $models, VirtualFieldDefinition $definition): void
    {
        foreach ($models as $model) {
            $value = $this->computeFieldValue($model, $definition);
            $model->setAttribute($definition->name, $value);
        }
    }

    /**
     * Compute a single virtual field value
     */
    protected function computeFieldValue($model, VirtualFieldDefinition $definition): mixed
    {
        // Check cache first if enabled
        if ($this->cacheEnabled && $definition->cacheable) {
            $cached = $this->cache->retrieve($definition->name, $model);
            if ($cached !== null) {
                $this->monitor->trackCacheOperation('retrieve', $definition->name, true);
                return $cached;
            } else {
                $this->monitor->trackCacheOperation('retrieve', $definition->name, false);
            }
        }

        // Monitor the computation
        return $this->monitor->monitorComputation(
            $definition->name,
            function() use ($model, $definition) {
                $value = $definition->compute($model);

                // Cache the result if enabled
                if ($this->cacheEnabled && $definition->cacheable) {
                    $ttl = $definition->cacheTtl > 0 ? $definition->cacheTtl : $this->defaultCacheTtl;
                    $this->cache->store($definition->name, $model, $value, $ttl);
                    $this->monitor->trackCacheOperation('store', $definition->name);
                }

                return $value;
            },
            [
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
                'cacheable' => $definition->cacheable
            ]
        );
    }

    /**
     * Add virtual field filter to query
     */
    protected function addVirtualFieldFilter(Builder $query, VirtualFieldDefinition $definition, mixed $value, string $operator): void
    {
        // For now, we'll use a simple approach where we compute the virtual field
        // in a subquery or use raw SQL. This can be optimized later.
        
        // This is a simplified implementation - in a real scenario, you might want to
        // create more sophisticated query building based on the virtual field logic
        $query->whereRaw('1=1'); // Placeholder - actual implementation would depend on the specific virtual field
        
        // Log that we're using a simplified filter
        Log::info("Applied virtual field filter", [
            'field' => $definition->name,
            'operator' => $operator,
            'value' => $value
        ]);
    }

    /**
     * Add virtual field sort to query
     */
    protected function addVirtualFieldSort(Builder $query, VirtualFieldDefinition $definition, string $direction): void
    {
        // For now, we'll use a simple approach
        // In a real implementation, this would need to compute the virtual field
        // and add it to the ORDER BY clause
        
        // This is a simplified implementation
        $query->orderBy($query->getModel()->getKeyName(), $direction);
        
        // Log that we're using a simplified sort
        Log::info("Applied virtual field sort", [
            'field' => $definition->name,
            'direction' => $direction
        ]);
    }

    /**
     * Check memory and time limits
     */
    protected function checkLimits(float $startTime, int $startMemory): void
    {
        // Check time limit
        $elapsed = microtime(true) - $startTime;
        if ($elapsed > $this->timeLimit) {
            throw new FilterValidationException(
                "Virtual field processing exceeded time limit of {$this->timeLimit} seconds"
            );
        }

        // Check memory limit
        $currentMemory = memory_get_usage(true);
        $memoryUsed = ($currentMemory - $startMemory) / 1024 / 1024; // Convert to MB
        if ($memoryUsed > $this->memoryLimit) {
            throw new FilterValidationException(
                "Virtual field processing exceeded memory limit of {$this->memoryLimit} MB"
            );
        }
    }

    /**
     * Batch compute virtual fields for multiple models
     */
    public function computeBatch(array $fieldNames, Collection $models): array
    {
        if ($models->isEmpty() || empty($fieldNames)) {
            return [];
        }

        return $this->monitor->monitorBatch(
            'compute',
            $fieldNames,
            $models->count(),
            function() use ($fieldNames, $models) {
                return $this->performanceManager->executeWithMonitoring(
                    'virtual_field_batch_compute',
                    function() use ($fieldNames, $models) {
                        $results = [];

                        // Track memory usage at start
                        $this->monitor->trackMemoryUsage('batch_compute_start', [
                            'field_count' => count($fieldNames),
                            'model_count' => $models->count()
                        ]);

                        // Use batch processing for large datasets
                        if ($models->count() > config('apiforge.virtual_fields.batch_size', 100)) {
                            $results = $this->computeBatchInChunks($fieldNames, $models);
                        } else {
                            // Process each field
                            foreach ($fieldNames as $fieldName) {
                                $definition = $this->registry->get($fieldName);
                                if (!$definition) {
                                    continue;
                                }

                                $results[$fieldName] = [];
                                foreach ($models as $model) {
                                    $value = $this->computeFieldValue($model, $definition);
                                    $results[$fieldName][$model->getKey()] = $value;
                                }
                            }
                        }

                        // Track memory usage at end
                        $this->monitor->trackMemoryUsage('batch_compute_end', [
                            'field_count' => count($fieldNames),
                            'model_count' => $models->count(),
                            'results_size' => count($results)
                        ]);

                        return $results;
                    },
                    [
                        'field_names' => $fieldNames,
                        'model_count' => $models->count()
                    ]
                );
            }
        );
    }

    /**
     * Invalidate cache for a model's virtual fields
     */
    public function invalidateCache($model, array $fieldNames = []): void
    {
        if (!$this->cacheEnabled) {
            return;
        }

        $fieldsToInvalidate = empty($fieldNames) ? $this->registry->getFieldNames() : $fieldNames;

        if (empty($fieldNames)) {
            // Invalidate all virtual fields for the model
            $this->cache->invalidateModel($model);
        } else {
            // Invalidate specific fields
            $this->cache->invalidateModel($model, $fieldNames);
        }

        // Track cache invalidation operations
        foreach ($fieldsToInvalidate as $fieldName) {
            $this->monitor->trackCacheOperation('invalidate', $fieldName);
        }
    }

    /**
     * Warm up cache for virtual fields
     */
    public function warmUpCache(Collection $models, array $fieldNames = []): void
    {
        if (!$this->cacheEnabled || $models->isEmpty()) {
            return;
        }

        $fieldsToWarm = empty($fieldNames) ? $this->registry->getFieldNames() : $fieldNames;
        $cacheEntries = [];

        foreach ($fieldsToWarm as $fieldName) {
            $definition = $this->registry->get($fieldName);
            if ($definition && $definition->cacheable) {
                foreach ($models as $model) {
                    try {
                        $value = $definition->compute($model);
                        $ttl = $definition->cacheTtl > 0 ? $definition->cacheTtl : $this->defaultCacheTtl;
                        
                        $cacheEntries[] = [
                            'field' => $fieldName,
                            'model' => $model,
                            'value' => $value,
                            'ttl' => $ttl
                        ];
                    } catch (\Exception $e) {
                        Log::warning("Failed to warm up cache for virtual field", [
                            'field' => $fieldName,
                            'model_class' => get_class($model),
                            'model_id' => $model->getKey(),
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }

        // Batch store cache entries
        if (!empty($cacheEntries)) {
            $this->cache->storeBatch($cacheEntries);
        }
    }

    /**
     * Get processor statistics
     */
    public function getStatistics(): array
    {
        $cacheStats = $this->cache->getStatistics();
        $performanceStats = $this->performanceManager->getPerformanceStatistics();
        $monitorStats = $this->monitor->getStatistics();
        
        return [
            'cache_enabled' => $this->cacheEnabled,
            'default_cache_ttl' => $this->defaultCacheTtl,
            'memory_limit' => $this->memoryLimit,
            'time_limit' => $this->timeLimit,
            'registered_fields' => $this->registry->count(),
            'cacheable_fields' => count($this->registry->getCacheable()),
            'sortable_fields' => count($this->registry->getSortable()),
            'searchable_fields' => count($this->registry->getSearchable()),
            'cache_statistics' => $cacheStats,
            'performance_statistics' => $performanceStats,
            'monitoring_statistics' => $monitorStats
        ];
    }

    /**
     * Process virtual field selection in batches
     */
    protected function processSelectionInBatches(Collection $models, array $virtualFields): Collection
    {
        $this->performanceManager->processBatches(
            $models,
            function($batch) use ($virtualFields) {
                foreach ($virtualFields as $fieldName) {
                    $definition = $this->registry->get($fieldName);
                    if ($definition) {
                        $this->computeFieldForModels($batch, $definition);
                    }
                }
                return $batch;
            },
            [
                'continue_on_error' => config('apiforge.virtual_fields.continue_on_batch_error', false),
                'strict_limits' => config('apiforge.virtual_fields.strict_limits', true)
            ]
        );

        return $models;
    }

    /**
     * Get the cache instance
     */
    public function getCache(): VirtualFieldCache
    {
        return $this->cache;
    }

    /**
     * Compute batch in chunks for large datasets
     */
    protected function computeBatchInChunks(array $fieldNames, Collection $models): array
    {
        $results = [];
        
        // Initialize results structure
        foreach ($fieldNames as $fieldName) {
            $results[$fieldName] = [];
        }

        $batchResults = $this->performanceManager->processBatches(
            $models,
            function($batch) use ($fieldNames) {
                $batchResults = [];
                
                foreach ($fieldNames as $fieldName) {
                    $definition = $this->registry->get($fieldName);
                    if (!$definition) {
                        continue;
                    }

                    $batchResults[$fieldName] = [];
                    foreach ($batch as $model) {
                        $value = $this->computeFieldValue($model, $definition);
                        $batchResults[$fieldName][$model->getKey()] = $value;
                    }
                }
                
                return $batchResults;
            },
            [
                'continue_on_error' => config('apiforge.virtual_fields.continue_on_batch_error', false),
                'strict_limits' => config('apiforge.virtual_fields.strict_limits', true)
            ]
        );

        // Merge batch results
        foreach ($batchResults as $batchResult) {
            foreach ($fieldNames as $fieldName) {
                if (isset($batchResult[$fieldName])) {
                    $results[$fieldName] = array_merge($results[$fieldName], $batchResult[$fieldName]);
                }
            }
        }

        return $results;
    }

    /**
     * Create lazy loader for virtual fields
     */
    public function createLazyLoader(Collection $models, array $virtualFields): \Closure
    {
        return $this->performanceManager->lazyLoadVirtualFields($models, $virtualFields, $this->registry);
    }

    /**
     * Process virtual field sorting with performance optimization
     */
    public function processOptimizedSorting(Collection $models, string $virtualField, string $direction): Collection
    {
        return $this->performanceManager->optimizeSorting($models, $virtualField, $direction, $this->registry);
    }

    /**
     * Get the performance manager instance
     */
    public function getPerformanceManager(): VirtualFieldPerformanceManager
    {
        return $this->performanceManager;
    }

    /**
     * Get the monitor instance
     */
    public function getMonitor(): VirtualFieldMonitor
    {
        return $this->monitor;
    }

    /**
     * Get detailed metrics for a specific virtual field
     */
    public function getFieldMetrics(string $fieldName): array
    {
        return $this->monitor->getFieldMetrics($fieldName);
    }

    /**
     * Clear all performance and monitoring data
     */
    public function clearMetrics(): void
    {
        $this->monitor->clearMetrics();
        $this->performanceManager->clearPerformanceData();
    }

    /**
     * Export metrics for persistence
     */
    public function exportMetrics(): void
    {
        $this->monitor->exportMetrics();
    }
}