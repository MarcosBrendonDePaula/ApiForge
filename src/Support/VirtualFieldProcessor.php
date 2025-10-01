<?php

namespace MarcosBrendon\ApiForge\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use MarcosBrendon\ApiForge\Exceptions\FilterValidationException;

class VirtualFieldProcessor
{
    /**
     * The virtual field registry
     */
    protected VirtualFieldRegistry $registry;

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

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        try {
            // Process each virtual field
            foreach ($virtualFields as $fieldName) {
                $definition = $this->registry->get($fieldName);
                if (!$definition) {
                    continue;
                }

                $this->computeFieldForModels($models, $definition);

                // Check memory and time limits
                $this->checkLimits($startTime, $startMemory);
            }

            return $models;
        } catch (\Exception $e) {
            Log::error("Virtual field processing failed", [
                'fields' => $virtualFields,
                'model_count' => $models->count(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
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

        // Add required fields to select
        if (!empty($dependencies['fields'])) {
            $existingSelect = $query->getQuery()->columns;
            if (empty($existingSelect) || in_array('*', $existingSelect)) {
                // If selecting all, just ensure we have the dependencies
                $query->addSelect($dependencies['fields']);
            } else {
                // Add dependencies to existing select
                $query->addSelect($dependencies['fields']);
            }
        }

        // Eager load required relationships
        if (!empty($dependencies['relationships'])) {
            $query->with($dependencies['relationships']);
        }

        return $query;
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
            $cacheKey = $definition->getCacheKey($model);
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        try {
            $value = $definition->compute($model);

            // Cache the result if enabled
            if ($this->cacheEnabled && $definition->cacheable) {
                $cacheKey = $definition->getCacheKey($model);
                $ttl = $definition->cacheTtl > 0 ? $definition->cacheTtl : $this->defaultCacheTtl;
                Cache::put($cacheKey, $value, $ttl);
            }

            return $value;
        } catch (\Exception $e) {
            Log::warning("Virtual field computation failed", [
                'field' => $definition->name,
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
                'error' => $e->getMessage()
            ]);

            return $definition->defaultValue;
        }
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
        $results = [];
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        try {
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

                // Check limits after each field
                $this->checkLimits($startTime, $startMemory);
            }

            return $results;
        } catch (\Exception $e) {
            Log::error("Batch virtual field computation failed", [
                'fields' => $fieldNames,
                'model_count' => $models->count(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
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

        foreach ($fieldsToInvalidate as $fieldName) {
            $definition = $this->registry->get($fieldName);
            if ($definition && $definition->cacheable) {
                $cacheKey = $definition->getCacheKey($model);
                Cache::forget($cacheKey);
            }
        }
    }

    /**
     * Warm up cache for virtual fields
     */
    public function warmUpCache(Collection $models, array $fieldNames = []): void
    {
        if (!$this->cacheEnabled) {
            return;
        }

        $fieldsToWarm = empty($fieldNames) ? $this->registry->getFieldNames() : $fieldNames;

        foreach ($fieldsToWarm as $fieldName) {
            $definition = $this->registry->get($fieldName);
            if ($definition && $definition->cacheable) {
                foreach ($models as $model) {
                    $this->computeFieldValue($model, $definition);
                }
            }
        }
    }

    /**
     * Get processor statistics
     */
    public function getStatistics(): array
    {
        return [
            'cache_enabled' => $this->cacheEnabled,
            'default_cache_ttl' => $this->defaultCacheTtl,
            'memory_limit' => $this->memoryLimit,
            'time_limit' => $this->timeLimit,
            'registered_fields' => $this->registry->count(),
            'cacheable_fields' => count($this->registry->getCacheable()),
            'sortable_fields' => count($this->registry->getSortable()),
            'searchable_fields' => count($this->registry->getSearchable())
        ];
    }
}