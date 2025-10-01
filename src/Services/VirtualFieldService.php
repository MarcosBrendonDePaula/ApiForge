<?php

namespace MarcosBrendon\ApiForge\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use MarcosBrendon\ApiForge\Exceptions\FilterValidationException;
use MarcosBrendon\ApiForge\Exceptions\VirtualFieldConfigurationException;
use MarcosBrendon\ApiForge\Exceptions\VirtualFieldComputationException;
use MarcosBrendon\ApiForge\Support\VirtualFieldDefinition;
use MarcosBrendon\ApiForge\Support\VirtualFieldProcessor;
use MarcosBrendon\ApiForge\Support\VirtualFieldRegistry;
use MarcosBrendon\ApiForge\Support\VirtualFieldMonitor;
use MarcosBrendon\ApiForge\Support\VirtualFieldValidator;
use MarcosBrendon\ApiForge\Services\RuntimeErrorHandler;

class VirtualFieldService
{
    /**
     * The virtual field registry
     */
    protected VirtualFieldRegistry $registry;

    /**
     * The virtual field processor
     */
    protected VirtualFieldProcessor $processor;

    /**
     * Whether to log virtual field operations
     */
    protected bool $logOperations;

    /**
     * Whether to throw exceptions on failures
     */
    protected bool $throwOnFailure;

    /**
     * Runtime error handler
     */
    protected RuntimeErrorHandler $errorHandler;

    /**
     * Create a new virtual field service instance
     */
    public function __construct(RuntimeErrorHandler $errorHandler = null)
    {
        $this->registry = new VirtualFieldRegistry();
        $this->processor = new VirtualFieldProcessor($this->registry);
        $this->logOperations = $this->getConfig('apiforge.virtual_fields.log_operations', false);
        $this->throwOnFailure = $this->getConfig('apiforge.virtual_fields.throw_on_failure', true);
        $this->errorHandler = $errorHandler ?? new RuntimeErrorHandler();
    }

    /**
     * Register a virtual field
     */
    public function register(string $field, array $config): void
    {
        try {
            // Validate configuration before creating definition
            $errors = VirtualFieldValidator::validateFieldConfig($field, $config);
            if (!empty($errors)) {
                throw new VirtualFieldConfigurationException(
                    "Invalid configuration for virtual field '{$field}': " . implode(', ', $errors),
                    ['field' => $field, 'validation_errors' => $errors]
                );
            }

            $definition = new VirtualFieldDefinition(
                $field,
                $config['type'],
                $config['callback'],
                $config['dependencies'] ?? [],
                $config['relationships'] ?? [],
                $config['operators'] ?? [],
                $config['cacheable'] ?? false,
                $config['cache_ttl'] ?? 3600,
                $config['default_value'] ?? null,
                $config['nullable'] ?? true,
                $config['sortable'] ?? true,
                $config['searchable'] ?? true,
                $config['description'] ?? ''
            );

            $this->registry->add($field, $definition);

            if ($this->logOperations) {
                Log::info("Registered virtual field '{$field}'", [
                    'field' => $field,
                    'type' => $config['type'],
                    'dependencies' => $config['dependencies'] ?? [],
                    'relationships' => $config['relationships'] ?? [],
                    'cacheable' => $config['cacheable'] ?? false
                ]);
            }
        } catch (VirtualFieldConfigurationException $e) {
            if ($this->throwOnFailure) {
                throw $e;
            }

            Log::error("Failed to register virtual field '{$field}' due to configuration error", [
                'field' => $field,
                'error' => $e->getMessage(),
                'context' => $e->getContext()
            ]);
        } catch (\Exception $e) {
            if ($this->throwOnFailure) {
                throw new VirtualFieldConfigurationException(
                    "Failed to register virtual field '{$field}': " . $e->getMessage(),
                    ['field' => $field, 'original_error' => $e->getMessage()],
                    $e
                );
            }

            Log::error("Failed to register virtual field '{$field}'", [
                'field' => $field,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Compute a virtual field value for a single model
     */
    public function compute(string $field, $model, array $context = []): mixed
    {
        $definition = $this->registry->get($field);
        if (!$definition) {
            if ($this->throwOnFailure) {
                throw new VirtualFieldConfigurationException(
                    "Virtual field '{$field}' is not registered",
                    ['field' => $field]
                );
            }
            return null;
        }

        try {
            // Check for missing dependencies
            $this->validateDependencies($field, $definition, $model);

            $value = $definition->compute($model, $context);

            // Validate return type if strict validation is enabled
            if ($this->getConfig('apiforge.virtual_fields.validate_return_types', false)) {
                $this->validateReturnType($field, $definition, $value, $model);
            }

            if ($this->logOperations) {
                Log::debug("Computed virtual field '{$field}'", [
                    'field' => $field,
                    'model_class' => get_class($model),
                    'model_id' => $model->getKey(),
                    'value' => is_scalar($value) ? $value : '[non-scalar]'
                ]);
            }

            return $value;
        } catch (\Exception $e) {
            return $this->errorHandler->handleVirtualFieldError($field, $model, $e, $context);
        }
    }

    /**
     * Compute virtual field values for multiple models (batch processing)
     */
    public function computeBatch(string $field, Collection $models): array
    {
        $definition = $this->registry->get($field);
        if (!$definition) {
            if ($this->throwOnFailure) {
                throw new VirtualFieldConfigurationException(
                    "Virtual field '{$field}' is not registered",
                    ['field' => $field]
                );
            }
            return [];
        }

        try {
            $results = $this->processor->computeBatch([$field], $models);

            if ($this->logOperations) {
                Log::info("Batch computed virtual field '{$field}'", [
                    'field' => $field,
                    'model_count' => $models->count(),
                    'results_count' => count($results[$field] ?? [])
                ]);
            }

            return $results[$field] ?? [];
        } catch (\Exception $e) {
            return $this->errorHandler->handleBatchError(
                "batch_compute_virtual_field",
                $models->toArray(),
                $e,
                ['field' => $field]
            );
        }
    }

    /**
     * Get dependencies for a virtual field
     */
    public function getDependencies(string $field): array
    {
        $definition = $this->registry->get($field);
        if (!$definition) {
            return [];
        }

        return [
            'fields' => $definition->dependencies,
            'relationships' => $definition->relationships,
            'all' => $definition->getAllDependencies()
        ];
    }

    /**
     * Check if a field is a virtual field
     */
    public function isVirtualField(string $field): bool
    {
        return $this->registry->has($field);
    }

    /**
     * Get all registered virtual fields
     */
    public function getVirtualFields(): array
    {
        return $this->registry->getFieldNames();
    }

    /**
     * Get virtual field definition
     */
    public function getDefinition(string $field): ?VirtualFieldDefinition
    {
        return $this->registry->get($field);
    }

    /**
     * Get virtual fields by type
     */
    public function getFieldsByType(string $type): array
    {
        return array_keys($this->registry->getByType($type));
    }

    /**
     * Get virtual fields that support a specific operator
     */
    public function getFieldsByOperator(string $operator): array
    {
        return array_keys($this->registry->getByOperator($operator));
    }

    /**
     * Get sortable virtual fields
     */
    public function getSortableFields(): array
    {
        return array_keys($this->registry->getSortable());
    }

    /**
     * Get searchable virtual fields
     */
    public function getSearchableFields(): array
    {
        return array_keys($this->registry->getSearchable());
    }

    /**
     * Get cacheable virtual fields
     */
    public function getCacheableFields(): array
    {
        return array_keys($this->registry->getCacheable());
    }

    /**
     * Check if a virtual field supports a specific operator
     */
    public function supportsOperator(string $field, string $operator): bool
    {
        $definition = $this->registry->get($field);
        return $definition ? $definition->supportsOperator($operator) : false;
    }

    /**
     * Check if a virtual field is sortable
     */
    public function isSortable(string $field): bool
    {
        $definition = $this->registry->get($field);
        return $definition ? $definition->sortable : false;
    }

    /**
     * Check if a virtual field is searchable
     */
    public function isSearchable(string $field): bool
    {
        $definition = $this->registry->get($field);
        return $definition ? $definition->searchable : false;
    }

    /**
     * Check if a virtual field is cacheable
     */
    public function isCacheable(string $field): bool
    {
        $definition = $this->registry->get($field);
        return $definition ? $definition->cacheable : false;
    }

    /**
     * Register multiple virtual fields from configuration
     */
    public function registerFromConfig(array $config): void
    {
        try {
            $this->registry->registerFromConfig($config);

            if ($this->logOperations) {
                Log::info("Registered virtual fields from config", [
                    'field_count' => count($config),
                    'fields' => array_keys($config)
                ]);
            }
        } catch (\Exception $e) {
            if ($this->throwOnFailure) {
                throw new FilterValidationException(
                    "Failed to register virtual fields from config: " . $e->getMessage(),
                    ['original_error' => $e->getMessage()],
                    $e
                );
            }

            Log::error("Failed to register virtual fields from config", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Validate virtual field configuration
     */
    public function validateConfig(array $config): array
    {
        return $this->registry->validateConfig($config);
    }

    /**
     * Clear all virtual fields
     */
    public function clearFields(): void
    {
        $this->registry->clear();

        if ($this->logOperations) {
            Log::info("Cleared all virtual fields");
        }
    }

    /**
     * Remove a specific virtual field
     */
    public function removeField(string $field): bool
    {
        $removed = $this->registry->remove($field);

        if ($removed && $this->logOperations) {
            Log::info("Removed virtual field '{$field}'");
        }

        return $removed;
    }

    /**
     * Get virtual field metadata
     */
    public function getMetadata(string $field = null): array
    {
        if ($field !== null) {
            $definition = $this->registry->get($field);
            return $definition ? $definition->toArray() : [];
        }

        return $this->registry->getMetadata();
    }

    /**
     * Get service statistics
     */
    public function getStatistics(): array
    {
        $registryStats = $this->registry->getMetadata();
        $processorStats = $this->processor->getStatistics();

        return array_merge($registryStats, $processorStats, [
            'log_operations' => $this->logOperations,
            'throw_on_failure' => $this->throwOnFailure
        ]);
    }

    /**
     * Get detailed metrics for a specific virtual field
     */
    public function getFieldMetrics(string $field): array
    {
        return $this->processor->getFieldMetrics($field);
    }

    /**
     * Clear all performance and monitoring data
     */
    public function clearMetrics(): void
    {
        $this->processor->clearMetrics();
    }

    /**
     * Export metrics for persistence
     */
    public function exportMetrics(): void
    {
        $this->processor->exportMetrics();
    }

    /**
     * Get the monitor instance for advanced monitoring operations
     */
    public function getMonitor(): VirtualFieldMonitor
    {
        return $this->processor->getMonitor();
    }

    /**
     * Get the registry instance
     */
    public function getRegistry(): VirtualFieldRegistry
    {
        return $this->registry;
    }

    /**
     * Get the processor instance
     */
    public function getProcessor(): VirtualFieldProcessor
    {
        return $this->processor;
    }

    /**
     * Set logging preference
     */
    public function setLogOperations(bool $log): void
    {
        $this->logOperations = $log;
    }

    /**
     * Set exception throwing preference
     */
    public function setThrowOnFailure(bool $throw): void
    {
        $this->throwOnFailure = $throw;
    }

    /**
     * Invalidate cache for virtual fields
     */
    public function invalidateCache($model, array $fields = []): void
    {
        $this->processor->invalidateCache($model, $fields);
    }

    /**
     * Warm up cache for virtual fields
     */
    public function warmUpCache(Collection $models, array $fields = []): void
    {
        $this->processor->warmUpCache($models, $fields);
    }

    /**
     * Optimize query for virtual field selection
     */
    public function optimizeQueryForSelection($query, array $virtualFields): void
    {
        if (empty($virtualFields)) {
            return;
        }

        // Get all dependencies for the virtual fields
        $allDependencies = $this->registry->getAllDependencies($virtualFields);

        // Add required database fields to the select
        if (!empty($allDependencies['fields'])) {
            $existingSelect = $query->getQuery()->columns;
            
            if (empty($existingSelect)) {
                // No specific fields selected, add required fields
                $query->addSelect($allDependencies['fields']);
            } elseif (!in_array('*', $existingSelect)) {
                // Specific fields selected, add missing dependencies
                $missingFields = array_diff($allDependencies['fields'], $existingSelect);
                if (!empty($missingFields)) {
                    $query->addSelect($missingFields);
                }
            }
        }

        // Eager load required relationships
        if (!empty($allDependencies['relationships'])) {
            $existingWith = $query->getEagerLoads();
            $newRelationships = [];
            
            foreach ($allDependencies['relationships'] as $relationship) {
                if (!isset($existingWith[$relationship])) {
                    $newRelationships[] = $relationship;
                }
            }

            if (!empty($newRelationships)) {
                $query->with($newRelationships);
            }
        }

        if ($this->logOperations) {
            Log::info('Optimized query for virtual field selection', [
                'virtual_fields' => $virtualFields,
                'dependencies' => $allDependencies
            ]);
        }
    }

    /**
     * Process virtual fields for selected models
     */
    public function processSelectedFields(Collection $models, array $virtualFields): Collection
    {
        if ($models->isEmpty() || empty($virtualFields)) {
            return $models;
        }

        try {
            // Use the processor to compute virtual fields for all models
            $this->processor->processForSelection($models, $virtualFields);

            if ($this->logOperations) {
                Log::info('Processed virtual fields for selection', [
                    'virtual_fields' => $virtualFields,
                    'model_count' => $models->count()
                ]);
            }

            return $models;
        } catch (\Exception $e) {
            if ($this->throwOnFailure) {
                throw $e;
            }

            Log::error('Failed to process virtual fields for selection', [
                'virtual_fields' => $virtualFields,
                'model_count' => $models->count(),
                'error' => $e->getMessage()
            ]);

            return $models;
        }
    }

    /**
     * Check if a virtual field is sortable
     */
    public function isVirtualFieldSortable(string $field): bool
    {
        $definition = $this->registry->get($field);
        return $definition && $definition->sortable;
    }

    /**
     * Validate dependencies for a virtual field
     */
    protected function validateDependencies(string $field, VirtualFieldDefinition $definition, $model): void
    {
        // Check database field dependencies
        foreach ($definition->dependencies as $dependency) {
            if (!isset($model->{$dependency})) {
                throw VirtualFieldComputationException::missingDependency($field, $dependency, $model);
            }
        }

        // Check relationship dependencies
        foreach ($definition->relationships as $relationship) {
            if (!method_exists($model, $relationship)) {
                throw VirtualFieldComputationException::missingRelationship($field, $relationship, $model);
            }
        }
    }

    /**
     * Validate return type for a virtual field
     */
    protected function validateReturnType(string $field, VirtualFieldDefinition $definition, $value, $model): void
    {
        $expectedType = $definition->type;
        $actualType = gettype($value);

        // Type mapping for validation
        $typeMap = [
            'string' => ['string'],
            'integer' => ['integer'],
            'float' => ['double', 'float'],
            'boolean' => ['boolean'],
            'array' => ['array'],
            'object' => ['object'],
            'date' => ['string', 'object'], // Can be string or DateTime object
            'datetime' => ['string', 'object'], // Can be string or DateTime object
            'enum' => ['string', 'integer'] // Can be string or integer
        ];

        $validTypes = $typeMap[$expectedType] ?? [$expectedType];

        if (!in_array($actualType, $validTypes) && !($definition->nullable && is_null($value))) {
            throw VirtualFieldComputationException::invalidReturnType($field, $expectedType, $value, $model);
        }
    }

    /**
     * Validate configuration with detailed error reporting
     */
    public function validateConfigurationWithDetails(array $config): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'suggestions' => []
        ];

        $errors = VirtualFieldValidator::validateConfig($config);

        if (!empty($errors)) {
            $results['valid'] = false;
            $results['errors'] = $errors;

            // Generate suggestions based on errors
            foreach ($errors as $fieldName => $fieldErrors) {
                foreach ($fieldErrors as $error) {
                    if (str_contains($error, 'Invalid type')) {
                        $results['suggestions'][] = "For field '{$fieldName}': Use one of the valid types: " . 
                            implode(', ', VirtualFieldValidator::getValidTypes());
                    }

                    if (str_contains($error, 'Invalid operators')) {
                        $fieldConfig = $config[$fieldName] ?? [];
                        $type = $fieldConfig['type'] ?? 'string';
                        $validOperators = VirtualFieldValidator::getValidOperators($type);
                        $results['suggestions'][] = "For field '{$fieldName}' of type '{$type}': Use operators: " . 
                            implode(', ', $validOperators);
                    }

                    if (str_contains($error, 'not callable')) {
                        $results['suggestions'][] = "For field '{$fieldName}': Ensure the callback is a valid callable (function, closure, or method reference)";
                    }
                }
            }
        }

        // Add warnings for potential issues
        foreach ($config as $fieldName => $fieldConfig) {
            // Warn about performance implications
            if (isset($fieldConfig['relationships']) && count($fieldConfig['relationships']) > 2) {
                $results['warnings'][] = "Field '{$fieldName}' depends on many relationships which may impact performance";
            }

            // Warn about caching disabled for expensive operations
            if (isset($fieldConfig['relationships']) && !($fieldConfig['cacheable'] ?? false)) {
                $results['warnings'][] = "Field '{$fieldName}' uses relationships but caching is disabled - consider enabling caching";
            }

            // Warn about missing descriptions
            if (empty($fieldConfig['description'] ?? '')) {
                $results['warnings'][] = "Field '{$fieldName}' has no description - consider adding one for documentation";
            }
        }

        return $results;
    }

    /**
     * Compute virtual field with timeout protection
     */
    public function computeWithTimeout(string $field, $model, int $timeoutSeconds = 30, array $context = []): mixed
    {
        return $this->errorHandler->executeWithTimeout(
            fn() => $this->compute($field, $model, $context),
            $timeoutSeconds,
            ['field' => $field, 'model_class' => get_class($model)]
        );
    }

    /**
     * Compute virtual field with memory limit protection
     */
    public function computeWithMemoryLimit(string $field, $model, int $memoryLimitMb = 128, array $context = []): mixed
    {
        return $this->errorHandler->executeWithMemoryLimit(
            fn() => $this->compute($field, $model, $context),
            $memoryLimitMb,
            ['field' => $field, 'model_class' => get_class($model)]
        );
    }

    /**
     * Compute virtual field with retry logic
     */
    public function computeWithRetry(string $field, $model, array $context = []): mixed
    {
        return $this->errorHandler->executeWithRetry(
            fn() => $this->compute($field, $model, $context),
            ['field' => $field, 'model_class' => get_class($model)]
        );
    }

    /**
     * Get error statistics from the error handler
     */
    public function getErrorStats(): array
    {
        return $this->errorHandler->getErrorStats();
    }

    /**
     * Check if virtual field error rate is high
     */
    public function isErrorRateHigh(int $thresholdPerMinute = 10): bool
    {
        return $this->errorHandler->isErrorRateHigh($thresholdPerMinute);
    }

    /**
     * Reset error statistics
     */
    public function resetErrorStats(): void
    {
        $this->errorHandler->resetErrorStats();
    }

    /**
     * Get the error handler instance
     */
    public function getErrorHandler(): RuntimeErrorHandler
    {
        return $this->errorHandler;
    }

    /**
     * Get configuration value with fallback
     */
    protected function getConfig(string $key, $default = null)
    {
        try {
            return config($key, $default);
        } catch (\Exception $e) {
            return $default;
        }
    }
}