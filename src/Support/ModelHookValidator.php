<?php

namespace MarcosBrendon\ApiForge\Support;

use MarcosBrendon\ApiForge\Exceptions\ModelHookConfigurationException;

class ModelHookValidator
{
    /**
     * Valid hook types
     */
    protected static array $validHookTypes = [
        'beforeStore',
        'afterStore',
        'beforeUpdate',
        'afterUpdate',
        'beforeDelete',
        'afterDelete',
        'beforeValidation',
        'afterValidation',
        'beforeTransform',
        'afterTransform',
        'beforeAuthorization',
        'afterAuthorization',
        'beforeAudit',
        'afterAudit',
        'beforeNotification',
        'afterNotification',
        'beforeCache',
        'afterCache',
        'beforeQuery',
        'afterQuery',
        'beforeResponse',
        'afterResponse'
    ];

    /**
     * Valid condition operators
     */
    protected static array $validConditionOperators = [
        'eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in', 'like', 'not_like', 'null', 'not_null'
    ];

    /**
     * Required configuration keys for array-based hook configuration
     */
    protected static array $requiredKeys = ['callback'];

    /**
     * Optional configuration keys with their default values
     */
    protected static array $optionalKeys = [
        'priority' => 10,
        'stopOnFailure' => false,
        'conditions' => [],
        'description' => ''
    ];

    /**
     * Validate a single hook configuration
     */
    public static function validateHookConfig(string $hookType, string $hookName, $hookConfig): array
    {
        $errors = [];

        try {
            // Validate hook type
            self::validateHookType($hookType);

            // Validate hook name
            self::validateHookName($hookName);

            // Handle simple callable configuration
            if (is_callable($hookConfig)) {
                return $errors; // Simple callable is valid
            }

            // Handle array configuration
            if (!is_array($hookConfig)) {
                throw new ModelHookConfigurationException(
                    "Hook configuration for '{$hookName}' must be callable or array.",
                    ['hook_type' => $hookType, 'hook_name' => $hookName, 'config_type' => gettype($hookConfig)]
                );
            }

            // Validate required keys
            $missingKeys = self::validateRequiredKeys($hookConfig);
            if (!empty($missingKeys)) {
                throw new ModelHookConfigurationException(
                    "Missing required configuration for hook '{$hookName}': " . implode(', ', $missingKeys),
                    ['hook_type' => $hookType, 'hook_name' => $hookName, 'missing_keys' => $missingKeys]
                );
            }

            // Validate callback
            self::validateCallback($hookType, $hookName, $hookConfig['callback']);

            // Validate priority
            if (isset($hookConfig['priority'])) {
                self::validatePriority($hookType, $hookName, $hookConfig['priority']);
            }

            // Validate stopOnFailure
            if (isset($hookConfig['stopOnFailure'])) {
                self::validateStopOnFailure($hookType, $hookName, $hookConfig['stopOnFailure']);
            }

            // Validate conditions
            if (isset($hookConfig['conditions'])) {
                self::validateConditions($hookType, $hookName, $hookConfig['conditions']);
            }

            // Validate description
            if (isset($hookConfig['description'])) {
                self::validateDescription($hookType, $hookName, $hookConfig['description']);
            }

        } catch (ModelHookConfigurationException $e) {
            $errors[] = $e->getMessage();
        }

        return $errors;
    }

    /**
     * Validate multiple hook configurations
     */
    public static function validateConfig(array $config): array
    {
        $allErrors = [];

        foreach ($config as $hookType => $hooks) {
            if (!is_array($hooks)) {
                $allErrors[$hookType] = ["Hook type '{$hookType}' must contain an array of hooks."];
                continue;
            }

            foreach ($hooks as $hookName => $hookConfig) {
                $hookErrors = self::validateHookConfig($hookType, $hookName, $hookConfig);
                if (!empty($hookErrors)) {
                    if (!isset($allErrors[$hookType])) {
                        $allErrors[$hookType] = [];
                    }
                    $allErrors[$hookType][$hookName] = $hookErrors;
                }
            }
        }

        return $allErrors;
    }

    /**
     * Validate hook type
     */
    protected static function validateHookType(string $hookType): void
    {
        if (!in_array($hookType, self::$validHookTypes)) {
            throw new ModelHookConfigurationException(
                "Invalid hook type '{$hookType}'. Valid types: " . implode(', ', self::$validHookTypes),
                ['hook_type' => $hookType, 'valid_types' => self::$validHookTypes]
            );
        }
    }

    /**
     * Validate hook name
     */
    protected static function validateHookName(string $hookName): void
    {
        if (empty($hookName) || !is_string($hookName)) {
            throw new ModelHookConfigurationException(
                "Hook name cannot be empty and must be a string.",
                ['hook_name' => $hookName]
            );
        }

        // Check for valid hook name format (alphanumeric and underscores only)
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $hookName)) {
            throw new ModelHookConfigurationException(
                "Hook name '{$hookName}' contains invalid characters. Use only letters, numbers, and underscores.",
                ['hook_name' => $hookName]
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
     * Validate callback
     */
    protected static function validateCallback(string $hookType, string $hookName, $callback): void
    {
        if (!is_callable($callback)) {
            throw new ModelHookConfigurationException(
                "Hook callback for '{$hookName}' is not callable.",
                ['hook_type' => $hookType, 'hook_name' => $hookName, 'callback_type' => gettype($callback)]
            );
        }
    }

    /**
     * Validate priority
     */
    protected static function validatePriority(string $hookType, string $hookName, $priority): void
    {
        if (!is_int($priority) || $priority < 0) {
            throw new ModelHookConfigurationException(
                "Hook priority for '{$hookName}' must be a non-negative integer.",
                ['hook_type' => $hookType, 'hook_name' => $hookName, 'priority' => $priority]
            );
        }
    }

    /**
     * Validate stopOnFailure
     */
    protected static function validateStopOnFailure(string $hookType, string $hookName, $stopOnFailure): void
    {
        if (!is_bool($stopOnFailure)) {
            throw new ModelHookConfigurationException(
                "Hook stopOnFailure for '{$hookName}' must be a boolean.",
                ['hook_type' => $hookType, 'hook_name' => $hookName, 'stopOnFailure' => $stopOnFailure]
            );
        }
    }

    /**
     * Validate conditions
     */
    protected static function validateConditions(string $hookType, string $hookName, $conditions): void
    {
        if (!is_array($conditions)) {
            throw new ModelHookConfigurationException(
                "Hook conditions for '{$hookName}' must be an array.",
                ['hook_type' => $hookType, 'hook_name' => $hookName, 'conditions' => $conditions]
            );
        }

        foreach ($conditions as $index => $condition) {
            self::validateCondition($hookType, $hookName, $condition, $index);
        }
    }

    /**
     * Validate a single condition
     */
    protected static function validateCondition(string $hookType, string $hookName, $condition, int $index): void
    {
        if (!is_array($condition)) {
            throw new ModelHookConfigurationException(
                "Hook condition #{$index} for '{$hookName}' must be an array.",
                ['hook_type' => $hookType, 'hook_name' => $hookName, 'condition_index' => $index, 'condition' => $condition]
            );
        }

        // Validate required condition keys
        $requiredConditionKeys = ['field', 'operator', 'value'];
        foreach ($requiredConditionKeys as $key) {
            if (!array_key_exists($key, $condition)) {
                throw new ModelHookConfigurationException(
                    "Hook condition #{$index} for '{$hookName}' must have a '{$key}' key.",
                    ['hook_type' => $hookType, 'hook_name' => $hookName, 'condition_index' => $index, 'missing_key' => $key]
                );
            }
        }

        // Validate field
        if (!is_string($condition['field']) || empty($condition['field'])) {
            throw new ModelHookConfigurationException(
                "Hook condition #{$index} field for '{$hookName}' must be a non-empty string.",
                ['hook_type' => $hookType, 'hook_name' => $hookName, 'condition_index' => $index, 'field' => $condition['field']]
            );
        }

        // Validate operator
        if (!in_array($condition['operator'], self::$validConditionOperators)) {
            throw new ModelHookConfigurationException(
                "Invalid operator '{$condition['operator']}' for hook condition #{$index} in '{$hookName}'. " .
                "Valid operators: " . implode(', ', self::$validConditionOperators),
                [
                    'hook_type' => $hookType,
                    'hook_name' => $hookName,
                    'condition_index' => $index,
                    'invalid_operator' => $condition['operator'],
                    'valid_operators' => self::$validConditionOperators
                ]
            );
        }

        // Value can be any type, so no validation needed
    }

    /**
     * Validate description
     */
    protected static function validateDescription(string $hookType, string $hookName, $description): void
    {
        if (!is_string($description)) {
            throw new ModelHookConfigurationException(
                "Hook description for '{$hookName}' must be a string.",
                ['hook_type' => $hookType, 'hook_name' => $hookName, 'description' => $description]
            );
        }
    }

    /**
     * Get valid hook types
     */
    public static function getValidHookTypes(): array
    {
        return self::$validHookTypes;
    }

    /**
     * Get valid condition operators
     */
    public static function getValidConditionOperators(): array
    {
        return self::$validConditionOperators;
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

        foreach ($config as $hookType => $hooks) {
            $normalized[$hookType] = [];

            foreach ($hooks as $hookName => $hookConfig) {
                if (is_callable($hookConfig)) {
                    // Simple callable configuration
                    $normalized[$hookType][$hookName] = array_merge(self::$optionalKeys, [
                        'callback' => $hookConfig
                    ]);
                } elseif (is_array($hookConfig)) {
                    // Array configuration
                    $normalized[$hookType][$hookName] = array_merge(self::$optionalKeys, $hookConfig);
                } else {
                    // Invalid configuration, keep as-is for validation to catch
                    $normalized[$hookType][$hookName] = $hookConfig;
                }
            }
        }

        return $normalized;
    }

    /**
     * Check if hook type supports return value handling
     */
    public static function supportsReturnValue(string $hookType): bool
    {
        $returnValueHooks = [
            'beforeDelete',
            'beforeValidation',
            'beforeTransform',
            'beforeAuthorization',
            'beforeNotification',
            'beforeCache',
            'beforeResponse'
        ];

        return in_array($hookType, $returnValueHooks);
    }

    /**
     * Check if hook type supports stopping execution
     */
    public static function supportsStopExecution(string $hookType): bool
    {
        $stopExecutionHooks = [
            'beforeStore',
            'beforeUpdate',
            'beforeDelete',
            'beforeValidation',
            'beforeAuthorization'
        ];

        return in_array($hookType, $stopExecutionHooks);
    }
}