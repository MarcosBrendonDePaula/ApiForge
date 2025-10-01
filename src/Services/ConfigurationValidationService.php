<?php

namespace MarcosBrendon\ApiForge\Services;

use Illuminate\Support\Facades\Log;
use MarcosBrendon\ApiForge\Exceptions\VirtualFieldConfigurationException;
use MarcosBrendon\ApiForge\Exceptions\ModelHookConfigurationException;
use MarcosBrendon\ApiForge\Support\VirtualFieldValidator;
use MarcosBrendon\ApiForge\Support\ModelHookValidator;

class ConfigurationValidationService
{
    /**
     * Whether to throw exceptions on validation failures
     */
    protected bool $throwOnFailure;

    /**
     * Whether to log validation results
     */
    protected bool $logValidation;

    /**
     * Validation results
     */
    protected array $validationResults = [];

    /**
     * Create a new configuration validation service instance
     */
    public function __construct()
    {
        $this->throwOnFailure = $this->getConfig('apiforge.validation.throw_on_failure', true);
        $this->logValidation = $this->getConfig('apiforge.validation.log_validation', true);
    }

    /**
     * Validate all configurations at startup
     */
    public function validateStartup(): array
    {
        $this->validationResults = [
            'virtual_fields' => [],
            'model_hooks' => [],
            'success' => true,
            'errors' => [],
            'warnings' => []
        ];

        try {
            // Validate virtual fields configuration
            $this->validateVirtualFieldsConfig();

            // Validate model hooks configuration
            $this->validateModelHooksConfig();

            // Log validation results
            if ($this->logValidation) {
                $this->logValidationResults();
            }

        } catch (\Exception $e) {
            $this->validationResults['success'] = false;
            $this->validationResults['errors'][] = $e->getMessage();

            if ($this->throwOnFailure) {
                throw $e;
            }

            Log::error('Configuration validation failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }

        return $this->validationResults;
    }

    /**
     * Validate virtual fields configuration
     */
    public function validateVirtualFieldsConfig(array $config = null): array
    {
        if ($config === null) {
            $config = $this->getConfig('apiforge.virtual_fields', []);
        }

        $errors = VirtualFieldValidator::validateConfig($config);

        if (!empty($errors)) {
            $this->validationResults['virtual_fields'] = $errors;
            $this->validationResults['success'] = false;

            $errorMessage = "Virtual fields configuration validation failed:\n";
            foreach ($errors as $fieldName => $fieldErrors) {
                $errorMessage .= "- {$fieldName}: " . implode(', ', $fieldErrors) . "\n";
            }

            if ($this->throwOnFailure) {
                throw new VirtualFieldConfigurationException(
                    trim($errorMessage),
                    ['validation_errors' => $errors]
                );
            }

            $this->validationResults['errors'][] = trim($errorMessage);
        }

        return $errors;
    }

    /**
     * Validate model hooks configuration
     */
    public function validateModelHooksConfig(array $config = null): array
    {
        if ($config === null) {
            $config = $this->getConfig('apiforge.model_hooks', []);
        }

        $errors = ModelHookValidator::validateConfig($config);

        if (!empty($errors)) {
            $this->validationResults['model_hooks'] = $errors;
            $this->validationResults['success'] = false;

            $errorMessage = "Model hooks configuration validation failed:\n";
            foreach ($errors as $hookType => $hookErrors) {
                if (is_array($hookErrors)) {
                    foreach ($hookErrors as $hookName => $hookNameErrors) {
                        if (is_array($hookNameErrors)) {
                            $errorMessage .= "- {$hookType}.{$hookName}: " . implode(', ', $hookNameErrors) . "\n";
                        } else {
                            $errorMessage .= "- {$hookType}.{$hookName}: {$hookNameErrors}\n";
                        }
                    }
                } else {
                    $errorMessage .= "- {$hookType}: {$hookErrors}\n";
                }
            }

            if ($this->throwOnFailure) {
                throw new ModelHookConfigurationException(
                    trim($errorMessage),
                    ['validation_errors' => $errors]
                );
            }

            $this->validationResults['errors'][] = trim($errorMessage);
        }

        return $errors;
    }

    /**
     * Validate a single virtual field configuration
     */
    public function validateVirtualField(string $fieldName, array $config): array
    {
        return VirtualFieldValidator::validateFieldConfig($fieldName, $config);
    }

    /**
     * Validate a single model hook configuration
     */
    public function validateModelHook(string $hookType, string $hookName, $hookConfig): array
    {
        return ModelHookValidator::validateHookConfig($hookType, $hookName, $hookConfig);
    }

    /**
     * Check if configuration is valid
     */
    public function isValid(): bool
    {
        return $this->validationResults['success'] ?? true;
    }

    /**
     * Get validation results
     */
    public function getValidationResults(): array
    {
        return $this->validationResults;
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->validationResults['errors'] ?? [];
    }

    /**
     * Get validation warnings
     */
    public function getWarnings(): array
    {
        return $this->validationResults['warnings'] ?? [];
    }

    /**
     * Clear validation results
     */
    public function clearResults(): void
    {
        $this->validationResults = [
            'virtual_fields' => [],
            'model_hooks' => [],
            'success' => true,
            'errors' => [],
            'warnings' => []
        ];
    }

    /**
     * Set whether to throw exceptions on validation failures
     */
    public function setThrowOnFailure(bool $throw): void
    {
        $this->throwOnFailure = $throw;
    }

    /**
     * Set whether to log validation results
     */
    public function setLogValidation(bool $log): void
    {
        $this->logValidation = $log;
    }

    /**
     * Validate configuration and provide suggestions
     */
    public function validateWithSuggestions(array $virtualFields = [], array $modelHooks = []): array
    {
        $results = [
            'virtual_fields' => [],
            'model_hooks' => [],
            'suggestions' => [],
            'success' => true
        ];

        // Validate virtual fields
        if (!empty($virtualFields)) {
            $virtualFieldErrors = $this->validateVirtualFieldsConfig($virtualFields);
            $results['virtual_fields'] = $virtualFieldErrors;
            
            if (!empty($virtualFieldErrors)) {
                $results['success'] = false;
                $results['suggestions'] = array_merge(
                    $results['suggestions'],
                    $this->generateVirtualFieldSuggestions($virtualFields, $virtualFieldErrors)
                );
            }
        }

        // Validate model hooks
        if (!empty($modelHooks)) {
            $modelHookErrors = $this->validateModelHooksConfig($modelHooks);
            $results['model_hooks'] = $modelHookErrors;
            
            if (!empty($modelHookErrors)) {
                $results['success'] = false;
                $results['suggestions'] = array_merge(
                    $results['suggestions'],
                    $this->generateModelHookSuggestions($modelHooks, $modelHookErrors)
                );
            }
        }

        return $results;
    }

    /**
     * Generate suggestions for virtual field configuration issues
     */
    protected function generateVirtualFieldSuggestions(array $config, array $errors): array
    {
        $suggestions = [];

        foreach ($errors as $fieldName => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                if (str_contains($error, 'Invalid type')) {
                    $suggestions[] = "For field '{$fieldName}': Use one of the valid types: " . 
                        implode(', ', VirtualFieldValidator::getValidTypes());
                }

                if (str_contains($error, 'Invalid operators')) {
                    $fieldConfig = $config[$fieldName] ?? [];
                    $type = $fieldConfig['type'] ?? 'string';
                    $validOperators = VirtualFieldValidator::getValidOperators($type);
                    $suggestions[] = "For field '{$fieldName}' of type '{$type}': Use operators: " . 
                        implode(', ', $validOperators);
                }

                if (str_contains($error, 'not callable')) {
                    $suggestions[] = "For field '{$fieldName}': Ensure the callback is a valid callable (function, closure, or method reference)";
                }

                if (str_contains($error, 'Circular dependency')) {
                    $suggestions[] = "For field '{$fieldName}': Remove circular dependencies by restructuring field relationships";
                }
            }
        }

        return $suggestions;
    }

    /**
     * Generate suggestions for model hook configuration issues
     */
    protected function generateModelHookSuggestions(array $config, array $errors): array
    {
        $suggestions = [];

        foreach ($errors as $hookType => $hookErrors) {
            if (is_array($hookErrors)) {
                foreach ($hookErrors as $hookName => $hookNameErrors) {
                    if (is_array($hookNameErrors)) {
                        foreach ($hookNameErrors as $error) {
                            if (str_contains($error, 'Invalid hook type')) {
                                $suggestions[] = "Use one of the valid hook types: " . 
                                    implode(', ', ModelHookValidator::getValidHookTypes());
                            }

                            if (str_contains($error, 'not callable')) {
                                $suggestions[] = "For hook '{$hookType}.{$hookName}': Ensure the callback is a valid callable";
                            }

                            if (str_contains($error, 'Invalid operator')) {
                                $suggestions[] = "For hook '{$hookType}.{$hookName}': Use valid condition operators: " . 
                                    implode(', ', ModelHookValidator::getValidConditionOperators());
                            }
                        }
                    }
                }
            }
        }

        return $suggestions;
    }

    /**
     * Log validation results
     */
    protected function logValidationResults(): void
    {
        if ($this->validationResults['success']) {
            Log::info('Configuration validation passed', [
                'virtual_fields_count' => count($this->getConfig('apiforge.virtual_fields', [])),
                'model_hooks_count' => $this->countModelHooks($this->getConfig('apiforge.model_hooks', []))
            ]);
        } else {
            Log::warning('Configuration validation failed', [
                'errors' => $this->validationResults['errors'],
                'virtual_field_errors' => $this->validationResults['virtual_fields'],
                'model_hook_errors' => $this->validationResults['model_hooks']
            ]);
        }
    }

    /**
     * Count total model hooks in configuration
     */
    protected function countModelHooks(array $config): int
    {
        $count = 0;
        foreach ($config as $hooks) {
            if (is_array($hooks)) {
                $count += count($hooks);
            }
        }
        return $count;
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