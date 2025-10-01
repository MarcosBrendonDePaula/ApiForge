<?php

namespace MarcosBrendon\ApiForge\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use MarcosBrendon\ApiForge\Exceptions\FilterValidationException;
use MarcosBrendon\ApiForge\Support\VirtualFieldDefinition;
use MarcosBrendon\ApiForge\Support\VirtualFieldProcessor;
use MarcosBrendon\ApiForge\Support\VirtualFieldRegistry;

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
     * Create a new virtual field service instance
     */
    public function __construct()
    {
        $this->registry = new VirtualFieldRegistry();
        $this->processor = new VirtualFieldProcessor($this->registry);
        $this->logOperations = $this->getConfig('apiforge.virtual_fields.log_operations', false);
        $this->throwOnFailure = $this->getConfig('apiforge.virtual_fields.throw_on_failure', true);
    }

    /**
     * Register a virtual field
     */
    public function register(string $field, array $config): void
    {
        try {
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
        } catch (\Exception $e) {
            if ($this->throwOnFailure) {
                throw new FilterValidationException(
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
                throw new FilterValidationException("Virtual field '{$field}' is not registered");
            }
            return null;
        }

        try {
            $value = $definition->compute($model, $context);

            if ($this->logOperations) {
                Log::debug("Computed virtual field '{$field}'", [
                    'field' => $field,
                    'model_class' => get_class($model),
                    'model_id' => $model->getKey(),
                    'value' => $value
                ]);
            }

            return $value;
        } catch (\Exception $e) {
            if ($this->throwOnFailure) {
                throw new FilterValidationException(
                    "Failed to compute virtual field '{$field}': " . $e->getMessage(),
                    ['field' => $field, 'original_error' => $e->getMessage()],
                    $e
                );
            }

            Log::error("Failed to compute virtual field '{$field}'", [
                'field' => $field,
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
                'error' => $e->getMessage()
            ]);

            return $definition->defaultValue;
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
                throw new FilterValidationException("Virtual field '{$field}' is not registered");
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
            if ($this->throwOnFailure) {
                throw new FilterValidationException(
                    "Failed to batch compute virtual field '{$field}': " . $e->getMessage(),
                    ['field' => $field, 'original_error' => $e->getMessage()],
                    $e
                );
            }

            Log::error("Failed to batch compute virtual field '{$field}'", [
                'field' => $field,
                'model_count' => $models->count(),
                'error' => $e->getMessage()
            ]);

            return [];
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