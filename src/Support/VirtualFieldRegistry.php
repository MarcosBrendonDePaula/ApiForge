<?php

namespace MarcosBrendon\ApiForge\Support;

use MarcosBrendon\ApiForge\Exceptions\FilterValidationException;

class VirtualFieldRegistry
{
    /**
     * Registered virtual fields
     */
    protected array $fields = [];

    /**
     * Add a virtual field definition to the registry
     */
    public function add(string $fieldName, VirtualFieldDefinition $definition): void
    {
        if ($this->has($fieldName)) {
            // Allow re-registration with a warning instead of throwing exception
            if (config('apiforge.debug.enabled', false)) {
                \Log::warning("Virtual field '{$fieldName}' is being re-registered");
            }
        }

        $this->fields[$fieldName] = $definition;
    }

    /**
     * Get a virtual field definition
     */
    public function get(string $fieldName): ?VirtualFieldDefinition
    {
        return $this->fields[$fieldName] ?? null;
    }

    /**
     * Check if a virtual field exists
     */
    public function has(string $fieldName): bool
    {
        return isset($this->fields[$fieldName]);
    }

    /**
     * Remove a virtual field from the registry
     */
    public function remove(string $fieldName): bool
    {
        if (isset($this->fields[$fieldName])) {
            unset($this->fields[$fieldName]);
            return true;
        }

        return false;
    }

    /**
     * Get all registered virtual fields
     */
    public function all(): array
    {
        return $this->fields;
    }

    /**
     * Get virtual fields by type
     */
    public function getByType(string $type): array
    {
        return array_filter($this->fields, function (VirtualFieldDefinition $field) use ($type) {
            return $field->type === $type;
        });
    }

    /**
     * Get virtual fields that support a specific operator
     */
    public function getByOperator(string $operator): array
    {
        return array_filter($this->fields, function (VirtualFieldDefinition $field) use ($operator) {
            return $field->supportsOperator($operator);
        });
    }

    /**
     * Get cacheable virtual fields
     */
    public function getCacheable(): array
    {
        return array_filter($this->fields, function (VirtualFieldDefinition $field) {
            return $field->cacheable;
        });
    }

    /**
     * Get sortable virtual fields
     */
    public function getSortable(): array
    {
        return array_filter($this->fields, function (VirtualFieldDefinition $field) {
            return $field->sortable;
        });
    }

    /**
     * Get searchable virtual fields
     */
    public function getSearchable(): array
    {
        return array_filter($this->fields, function (VirtualFieldDefinition $field) {
            return $field->searchable;
        });
    }

    /**
     * Get virtual fields that depend on specific database fields
     */
    public function getByDependency(string $dependency): array
    {
        return array_filter($this->fields, function (VirtualFieldDefinition $field) use ($dependency) {
            return in_array($dependency, $field->dependencies);
        });
    }

    /**
     * Get virtual fields that depend on specific relationships
     */
    public function getByRelationship(string $relationship): array
    {
        return array_filter($this->fields, function (VirtualFieldDefinition $field) use ($relationship) {
            return in_array($relationship, $field->relationships);
        });
    }

    /**
     * Get all dependencies for a set of virtual fields
     */
    public function getDependencies(array $fieldNames): array
    {
        $dependencies = [];

        foreach ($fieldNames as $fieldName) {
            $field = $this->get($fieldName);
            if ($field) {
                $dependencies = array_merge($dependencies, $field->dependencies);
            }
        }

        return array_unique($dependencies);
    }

    /**
     * Get all relationships for a set of virtual fields
     */
    public function getRelationships(array $fieldNames): array
    {
        $relationships = [];

        foreach ($fieldNames as $fieldName) {
            $field = $this->get($fieldName);
            if ($field) {
                $relationships = array_merge($relationships, $field->relationships);
            }
        }

        return array_unique($relationships);
    }

    /**
     * Get all dependencies (fields + relationships) for a set of virtual fields
     */
    public function getAllDependencies(array $fieldNames): array
    {
        $dependencies = [];
        $relationships = [];

        foreach ($fieldNames as $fieldName) {
            $field = $this->get($fieldName);
            if ($field) {
                $dependencies = array_merge($dependencies, $field->dependencies);
                $relationships = array_merge($relationships, $field->relationships);
            }
        }

        return [
            'fields' => array_unique($dependencies),
            'relationships' => array_unique($relationships)
        ];
    }

    /**
     * Clear all virtual fields
     */
    public function clear(): void
    {
        $this->fields = [];
    }

    /**
     * Get virtual fields count
     */
    public function count(): int
    {
        return count($this->fields);
    }

    /**
     * Get field names
     */
    public function getFieldNames(): array
    {
        return array_keys($this->fields);
    }

    /**
     * Register multiple virtual fields from configuration array
     */
    public function registerFromConfig(array $config): void
    {
        foreach ($config as $fieldName => $fieldConfig) {
            $definition = $this->createDefinitionFromConfig($fieldName, $fieldConfig);
            $this->add($fieldName, $definition);
        }
    }

    /**
     * Create a virtual field definition from configuration array
     */
    protected function createDefinitionFromConfig(string $fieldName, array $fieldConfig): VirtualFieldDefinition
    {
        if (!isset($fieldConfig['type'])) {
            throw new FilterValidationException("Virtual field '{$fieldName}' must have a 'type' specified");
        }

        if (!isset($fieldConfig['callback'])) {
            throw new FilterValidationException("Virtual field '{$fieldName}' must have a 'callback' specified");
        }

        return new VirtualFieldDefinition(
            $fieldName,
            $fieldConfig['type'],
            $fieldConfig['callback'],
            $fieldConfig['dependencies'] ?? [],
            $fieldConfig['relationships'] ?? [],
            $fieldConfig['operators'] ?? [],
            $fieldConfig['cacheable'] ?? false,
            $fieldConfig['cache_ttl'] ?? 3600,
            $fieldConfig['default_value'] ?? null,
            $fieldConfig['nullable'] ?? true,
            $fieldConfig['sortable'] ?? true,
            $fieldConfig['searchable'] ?? true,
            $fieldConfig['description'] ?? ''
        );
    }

    /**
     * Validate virtual field configuration
     */
    public function validateConfig(array $config): array
    {
        $errors = [];

        foreach ($config as $fieldName => $fieldConfig) {
            try {
                $this->createDefinitionFromConfig($fieldName, $fieldConfig);
            } catch (\Exception $e) {
                $errors[$fieldName] = $e->getMessage();
            }
        }

        return $errors;
    }

    /**
     * Get registry metadata for debugging
     */
    public function getMetadata(): array
    {
        $metadata = [
            'total_fields' => $this->count(),
            'fields_by_type' => [],
            'cacheable_count' => count($this->getCacheable()),
            'sortable_count' => count($this->getSortable()),
            'searchable_count' => count($this->getSearchable()),
            'fields' => []
        ];

        // Group by type
        foreach (VirtualFieldDefinition::getValidTypes() as $type) {
            $fieldsOfType = $this->getByType($type);
            $metadata['fields_by_type'][$type] = count($fieldsOfType);
        }

        // Add field details
        foreach ($this->fields as $fieldName => $field) {
            $metadata['fields'][$fieldName] = $field->toArray();
        }

        return $metadata;
    }
}