<?php

namespace MarcosBrendon\ApiForge\Support;

use MarcosBrendon\ApiForge\Exceptions\VirtualFieldConfigurationException;

class VirtualFieldValidator
{
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
        'string' => ['eq', 'ne', 'like', 'not_like', 'in', 'not_in', 'null', 'not_null', 'starts_with', 'ends_with'],
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
     * Required configuration keys
     */
    protected static array $requiredKeys = ['type', 'callback'];

    /**
     * Optional configuration keys with their default values
     */
    protected static array $optionalKeys = [
        'dependencies' => [],
        'relationships' => [],
        'operators' => [],
        'cacheable' => false,
        'cache_ttl' => 3600,
        'default_value' => null,
        'nullable' => true,
        'sortable' => true,
        'searchable' => true,
        'description' => '',
        'enum_values' => []
    ];

    /**
     * Validate a single virtual field configuration
     */
    public static function validateFieldConfig(string $fieldName, array $config): array
    {
        $errors = [];

        try {
            // Validate field name
            self::validateFieldName($fieldName);

            // Validate required keys
            $missingKeys = self::validateRequiredKeys($config);
            if (!empty($missingKeys)) {
                throw VirtualFieldConfigurationException::missingRequiredConfig($fieldName, $missingKeys);
            }

            // Validate field type
            self::validateFieldType($fieldName, $config['type']);

            // Validate callback
            self::validateCallback($fieldName, $config['callback']);

            // Validate dependencies
            if (isset($config['dependencies'])) {
                self::validateDependencies($fieldName, $config['dependencies']);
            }

            // Validate relationships
            if (isset($config['relationships'])) {
                self::validateRelationships($fieldName, $config['relationships']);
            }

            // Validate operators
            if (isset($config['operators'])) {
                self::validateOperators($fieldName, $config['type'], $config['operators']);
            }

            // Validate cache TTL
            if (isset($config['cache_ttl'])) {
                self::validateCacheTtl($fieldName, $config['cache_ttl']);
            }

            // Validate enum values for enum type
            if ($config['type'] === 'enum' && isset($config['enum_values'])) {
                self::validateEnumValues($fieldName, $config['enum_values']);
            }

            // Validate boolean flags
            self::validateBooleanFlags($fieldName, $config);

        } catch (VirtualFieldConfigurationException $e) {
            $errors[] = $e->getMessage();
        }

        return $errors;
    }

    /**
     * Validate multiple virtual field configurations
     */
    public static function validateConfig(array $config): array
    {
        $allErrors = [];

        foreach ($config as $fieldName => $fieldConfig) {
            $fieldErrors = self::validateFieldConfig($fieldName, $fieldConfig);
            if (!empty($fieldErrors)) {
                $allErrors[$fieldName] = $fieldErrors;
            }
        }

        // Check for circular dependencies
        $circularDependencies = self::detectCircularDependencies($config);
        if (!empty($circularDependencies)) {
            foreach ($circularDependencies as $fieldName => $chain) {
                if (!isset($allErrors[$fieldName])) {
                    $allErrors[$fieldName] = [];
                }
                $allErrors[$fieldName][] = "Circular dependency detected: " . implode(' -> ', $chain);
            }
        }

        return $allErrors;
    }

    /**
     * Validate field name
     */
    protected static function validateFieldName(string $fieldName): void
    {
        if (empty($fieldName) || !is_string($fieldName)) {
            throw VirtualFieldConfigurationException::invalidFieldName($fieldName);
        }

        // Check for reserved field names
        $reservedNames = ['id', 'created_at', 'updated_at', 'deleted_at'];
        if (in_array($fieldName, $reservedNames)) {
            throw new VirtualFieldConfigurationException(
                "Virtual field name '{$fieldName}' is reserved and cannot be used.",
                ['field_name' => $fieldName, 'reserved_names' => $reservedNames]
            );
        }

        // Check for valid field name format (alphanumeric and underscores only)
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $fieldName)) {
            throw new VirtualFieldConfigurationException(
                "Virtual field name '{$fieldName}' contains invalid characters. Use only letters, numbers, and underscores.",
                ['field_name' => $fieldName]
            );
        }
    }

    /**
     * Validate required configuration keys
     */
    protected static function validateRequiredKeys(array $config): array
    {
        $missingKeys = [];

        foreach (self::$requiredKeys as $key) {
            if (!array_key_exists($key, $config)) {
                $missingKeys[] = $key;
            }
        }

        return $missingKeys;
    }

    /**
     * Validate field type
     */
    protected static function validateFieldType(string $fieldName, $type): void
    {
        if (!is_string($type) || !in_array($type, self::$validTypes)) {
            throw VirtualFieldConfigurationException::invalidFieldType($fieldName, $type, self::$validTypes);
        }
    }

    /**
     * Validate callback
     */
    protected static function validateCallback(string $fieldName, $callback): void
    {
        if (!is_callable($callback)) {
            throw VirtualFieldConfigurationException::invalidCallback($fieldName, $callback);
        }
    }

    /**
     * Validate dependencies
     */
    protected static function validateDependencies(string $fieldName, $dependencies): void
    {
        if (!is_array($dependencies)) {
            throw new VirtualFieldConfigurationException(
                "Dependencies for virtual field '{$fieldName}' must be an array.",
                ['field_name' => $fieldName, 'dependencies' => $dependencies]
            );
        }

        foreach ($dependencies as $dependency) {
            if (!is_string($dependency) || empty($dependency)) {
                throw VirtualFieldConfigurationException::invalidDependency($fieldName, $dependency);
            }
        }
    }

    /**
     * Validate relationships
     */
    protected static function validateRelationships(string $fieldName, $relationships): void
    {
        if (!is_array($relationships)) {
            throw new VirtualFieldConfigurationException(
                "Relationships for virtual field '{$fieldName}' must be an array.",
                ['field_name' => $fieldName, 'relationships' => $relationships]
            );
        }

        foreach ($relationships as $relationship) {
            if (!is_string($relationship) || empty($relationship)) {
                throw VirtualFieldConfigurationException::invalidRelationship($fieldName, $relationship);
            }
        }
    }

    /**
     * Validate operators
     */
    protected static function validateOperators(string $fieldName, string $type, $operators): void
    {
        if (!is_array($operators)) {
            throw new VirtualFieldConfigurationException(
                "Operators for virtual field '{$fieldName}' must be an array.",
                ['field_name' => $fieldName, 'operators' => $operators]
            );
        }

        $validOperators = self::$operatorsByType[$type] ?? [];
        $invalidOperators = array_diff($operators, $validOperators);

        if (!empty($invalidOperators)) {
            throw VirtualFieldConfigurationException::invalidOperators($fieldName, $type, $invalidOperators, $validOperators);
        }
    }

    /**
     * Validate cache TTL
     */
    protected static function validateCacheTtl(string $fieldName, $ttl): void
    {
        if (!is_int($ttl) || $ttl < 0) {
            throw VirtualFieldConfigurationException::invalidCacheTtl($fieldName, $ttl);
        }
    }

    /**
     * Validate enum values
     */
    protected static function validateEnumValues(string $fieldName, $enumValues): void
    {
        if (!is_array($enumValues)) {
            throw new VirtualFieldConfigurationException(
                "Enum values for virtual field '{$fieldName}' must be an array.",
                ['field_name' => $fieldName, 'enum_values' => $enumValues]
            );
        }

        if (empty($enumValues)) {
            throw new VirtualFieldConfigurationException(
                "Enum values for virtual field '{$fieldName}' cannot be empty.",
                ['field_name' => $fieldName]
            );
        }

        $invalidValues = [];
        foreach ($enumValues as $value) {
            if (!is_scalar($value)) {
                $invalidValues[] = $value;
            }
        }

        if (!empty($invalidValues)) {
            throw VirtualFieldConfigurationException::invalidEnumValues($fieldName, $invalidValues);
        }
    }

    /**
     * Validate boolean flags
     */
    protected static function validateBooleanFlags(string $fieldName, array $config): void
    {
        $booleanFlags = ['cacheable', 'nullable', 'sortable', 'searchable'];

        foreach ($booleanFlags as $flag) {
            if (isset($config[$flag]) && !is_bool($config[$flag])) {
                throw new VirtualFieldConfigurationException(
                    "Configuration '{$flag}' for virtual field '{$fieldName}' must be a boolean.",
                    ['field_name' => $fieldName, 'flag' => $flag, 'value' => $config[$flag]]
                );
            }
        }
    }

    /**
     * Detect circular dependencies in virtual field configurations
     */
    protected static function detectCircularDependencies(array $config): array
    {
        $circularDependencies = [];
        $visited = [];
        $recursionStack = [];

        foreach (array_keys($config) as $fieldName) {
            if (!isset($visited[$fieldName])) {
                $path = self::detectCircularDependenciesRecursive($fieldName, $config, $visited, $recursionStack, []);
                if ($path !== null) {
                    $circularDependencies[$fieldName] = $path;
                }
            }
        }

        return $circularDependencies;
    }

    /**
     * Recursive helper for circular dependency detection
     */
    protected static function detectCircularDependenciesRecursive(
        string $fieldName,
        array $config,
        array &$visited,
        array &$recursionStack,
        array $currentPath
    ): ?array {
        $visited[$fieldName] = true;
        $recursionStack[$fieldName] = true;
        $currentPath[] = $fieldName;

        // Check dependencies (only virtual field dependencies, not database fields)
        if (isset($config[$fieldName]['dependencies'])) {
            foreach ($config[$fieldName]['dependencies'] as $dependency) {
                // Only check if dependency is also a virtual field
                if (isset($config[$dependency])) {
                    if (isset($recursionStack[$dependency])) {
                        // Found circular dependency
                        $circleStart = array_search($dependency, $currentPath);
                        return array_slice($currentPath, $circleStart);
                    }

                    if (!isset($visited[$dependency])) {
                        $path = self::detectCircularDependenciesRecursive($dependency, $config, $visited, $recursionStack, $currentPath);
                        if ($path !== null) {
                            return $path;
                        }
                    }
                }
            }
        }

        unset($recursionStack[$fieldName]);
        return null;
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

    /**
     * Get required configuration keys
     */
    public static function getRequiredKeys(): array
    {
        return self::$requiredKeys;
    }

    /**
     * Get optional configuration keys with defaults
     */
    public static function getOptionalKeys(): array
    {
        return self::$optionalKeys;
    }

    /**
     * Normalize configuration by adding default values
     */
    public static function normalizeConfig(array $config): array
    {
        $normalized = [];

        foreach ($config as $fieldName => $fieldConfig) {
            $normalized[$fieldName] = array_merge(self::$optionalKeys, $fieldConfig);

            // Set default operators based on type if not specified
            if (empty($normalized[$fieldName]['operators'])) {
                $normalized[$fieldName]['operators'] = self::$operatorsByType[$fieldConfig['type']] ?? [];
            }
        }

        return $normalized;
    }
}