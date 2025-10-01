<?php

namespace MarcosBrendon\ApiForge\Support;

use MarcosBrendon\ApiForge\Exceptions\FilterValidationException;

class VirtualFieldDefinition
{
    /**
     * The virtual field name
     */
    public string $name;

    /**
     * The field type (string, integer, float, boolean, date, enum)
     */
    public string $type;

    /**
     * The callback function to compute the field value
     */
    public $callback;

    /**
     * Database fields this virtual field depends on
     */
    public array $dependencies;

    /**
     * Relationships this virtual field depends on
     */
    public array $relationships;

    /**
     * Supported operators for filtering
     */
    public array $operators;

    /**
     * Whether the field can be cached
     */
    public bool $cacheable;

    /**
     * Cache TTL in seconds
     */
    public int $cacheTtl;

    /**
     * Default value when computation fails
     */
    public mixed $defaultValue;

    /**
     * Whether the field can be null
     */
    public bool $nullable;

    /**
     * Whether the field can be used for sorting
     */
    public bool $sortable;

    /**
     * Whether the field can be searched
     */
    public bool $searchable;

    /**
     * Description of the virtual field
     */
    public string $description;

    /**
     * Valid field types
     */
    protected static array $validTypes = [
        'string', 'integer', 'float', 'boolean', 'date', 'datetime', 'enum', 'array', 'object'
    ];

    /**
     * Valid operators by field type
     */
    protected static array $operatorsByType = [
        'string' => ['eq', 'ne', 'like', 'not_like', 'in', 'not_in', 'null', 'not_null'],
        'integer' => ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'between', 'not_between', 'in', 'not_in', 'null', 'not_null'],
        'float' => ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'between', 'not_between', 'in', 'not_in', 'null', 'not_null'],
        'boolean' => ['eq', 'ne', 'null', 'not_null'],
        'date' => ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'between', 'not_between', 'null', 'not_null'],
        'datetime' => ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'between', 'not_between', 'null', 'not_null'],
        'enum' => ['eq', 'ne', 'in', 'not_in', 'null', 'not_null'],
        'array' => ['in', 'not_in', 'contains', 'not_contains', 'null', 'not_null'],
        'object' => ['null', 'not_null']
    ];

    /**
     * Create a new virtual field definition
     */
    public function __construct(
        string $name,
        string $type,
        $callback,
        array $dependencies = [],
        array $relationships = [],
        array $operators = [],
        bool $cacheable = false,
        int $cacheTtl = 3600,
        mixed $defaultValue = null,
        bool $nullable = true,
        bool $sortable = true,
        bool $searchable = true,
        string $description = ''
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->callback = $callback;
        $this->dependencies = $dependencies;
        $this->relationships = $relationships;
        $this->operators = $operators;
        $this->cacheable = $cacheable;
        $this->cacheTtl = $cacheTtl;
        $this->defaultValue = $defaultValue;
        $this->nullable = $nullable;
        $this->sortable = $sortable;
        $this->searchable = $searchable;
        $this->description = $description;

        $this->validate();
    }

    /**
     * Validate the virtual field definition
     */
    protected function validate(): void
    {
        if (empty($this->name)) {
            throw new FilterValidationException('Virtual field name cannot be empty');
        }

        if (!in_array($this->type, self::$validTypes)) {
            throw new FilterValidationException(
                "Invalid virtual field type '{$this->type}' for field '{$this->name}'. " .
                "Valid types: " . implode(', ', self::$validTypes)
            );
        }

        if (!is_callable($this->callback)) {
            throw new FilterValidationException("Virtual field callback for '{$this->name}' is not callable");
        }

        if ($this->cacheTtl < 0) {
            throw new FilterValidationException("Cache TTL for virtual field '{$this->name}' must be non-negative");
        }

        $this->validateOperators();
        $this->validateDependencies();
    }

    /**
     * Validate operators for the field type
     */
    protected function validateOperators(): void
    {
        if (empty($this->operators)) {
            // Use default operators for the field type
            $this->operators = self::$operatorsByType[$this->type] ?? [];
            return;
        }

        $validOperators = self::$operatorsByType[$this->type] ?? [];
        $invalidOperators = array_diff($this->operators, $validOperators);

        if (!empty($invalidOperators)) {
            throw new FilterValidationException(
                "Invalid operators for virtual field '{$this->name}' of type '{$this->type}': " .
                implode(', ', $invalidOperators) . ". Valid operators: " . implode(', ', $validOperators)
            );
        }
    }

    /**
     * Validate dependencies
     */
    protected function validateDependencies(): void
    {
        foreach ($this->dependencies as $dependency) {
            if (!is_string($dependency) || empty($dependency)) {
                throw new FilterValidationException(
                    "Invalid dependency for virtual field '{$this->name}'. Dependencies must be non-empty strings."
                );
            }
        }

        foreach ($this->relationships as $relationship) {
            if (!is_string($relationship) || empty($relationship)) {
                throw new FilterValidationException(
                    "Invalid relationship for virtual field '{$this->name}'. Relationships must be non-empty strings."
                );
            }
        }
    }

    /**
     * Check if an operator is supported
     */
    public function supportsOperator(string $operator): bool
    {
        return in_array($operator, $this->operators);
    }

    /**
     * Get all dependencies (fields + relationships)
     */
    public function getAllDependencies(): array
    {
        return array_merge($this->dependencies, $this->relationships);
    }

    /**
     * Check if the field has relationship dependencies
     */
    public function hasRelationshipDependencies(): bool
    {
        return !empty($this->relationships);
    }

    /**
     * Execute the callback to compute the field value
     */
    public function compute($model, array $context = []): mixed
    {
        try {
            return call_user_func($this->callback, $model, $context);
        } catch (\Exception $e) {
            if ($this->nullable) {
                return $this->defaultValue;
            }
            throw $e;
        }
    }

    /**
     * Get cache key for this field and model
     */
    public function getCacheKey($model): string
    {
        $modelClass = get_class($model);
        $modelId = $model->getKey();
        return "virtual_field:{$modelClass}:{$modelId}:{$this->name}";
    }

    /**
     * Convert to array for serialization
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'dependencies' => $this->dependencies,
            'relationships' => $this->relationships,
            'operators' => $this->operators,
            'cacheable' => $this->cacheable,
            'cache_ttl' => $this->cacheTtl,
            'default_value' => $this->defaultValue,
            'nullable' => $this->nullable,
            'sortable' => $this->sortable,
            'searchable' => $this->searchable,
            'description' => $this->description,
            'callback_type' => is_string($this->callback) ? 'string' : gettype($this->callback),
        ];
    }

    /**
     * Get valid types
     */
    public static function getValidTypes(): array
    {
        return self::$validTypes;
    }

    /**
     * Get valid operators for a type
     */
    public static function getValidOperators(string $type): array
    {
        return self::$operatorsByType[$type] ?? [];
    }
}