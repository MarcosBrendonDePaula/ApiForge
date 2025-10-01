<?php

namespace MarcosBrendon\ApiForge\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MarcosBrendon\ApiForge\Exceptions\ModelHookExecutionException;
use MarcosBrendon\ApiForge\Exceptions\ModelHookConfigurationException;
use MarcosBrendon\ApiForge\Support\HookContext;
use MarcosBrendon\ApiForge\Support\HookRegistry;
use MarcosBrendon\ApiForge\Support\ModelHookDefinition;

class ModelHookService
{
    /**
     * The hook registry instance
     */
    protected HookRegistry $registry;

    /**
     * Whether to log hook execution
     */
    protected bool $logExecution;

    /**
     * Whether to throw exceptions on hook failures
     */
    protected bool $throwOnFailure;

    /**
     * Create a new model hook service instance
     */
    public function __construct()
    {
        $this->registry = new HookRegistry();
        $this->logExecution = $this->getConfig('apiforge.hooks.log_execution', false);
        $this->throwOnFailure = $this->getConfig('apiforge.hooks.throw_on_failure', true);
    }

    /**
     * Register a hook
     */
    public function register(string $hookType, string $hookName, $callback, array $options = []): void
    {
        $definition = new ModelHookDefinition(
            $hookName,
            $callback,
            $options['priority'] ?? 10,
            $options['stopOnFailure'] ?? false,
            $options['conditions'] ?? [],
            $options['description'] ?? ''
        );

        $this->registry->add($hookType, $definition);

        if ($this->logExecution) {
            Log::info("Registered hook '{$hookName}' for '{$hookType}'", [
                'hook_type' => $hookType,
                'hook_name' => $hookName,
                'priority' => $definition->priority,
            ]);
        }
    }

    /**
     * Execute hooks for a specific type
     */
    public function execute(string $hookType, $model, Request $request, array $data = []): mixed
    {
        if (!$this->hasHook($hookType)) {
            return null;
        }

        $context = new HookContext($model, $request, $data, $hookType);
        $hooks = $this->registry->get($hookType);
        $results = [];

        if ($this->logExecution) {
            Log::info("Executing {$hookType} hooks", [
                'hook_type' => $hookType,
                'hook_count' => count($hooks),
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
            ]);
        }

        foreach ($hooks as $hookName => $definition) {
            try {
                // Check if hook should execute based on conditions
                if (!$definition->shouldExecute($context)) {
                    if ($this->logExecution) {
                        Log::debug("Skipping hook '{$hookName}' - conditions not met");
                    }
                    continue;
                }

                if ($this->logExecution) {
                    Log::debug("Executing hook '{$hookName}'");
                }

                $result = $definition->execute($context);
                $results[$hookName] = $result;

                // Handle special return values for certain hook types
                if ($this->shouldStopExecution($hookType, $result, $definition)) {
                    if ($this->logExecution) {
                        Log::info("Stopping hook execution after '{$hookName}'");
                    }
                    break;
                }

            } catch (\Exception $e) {
                $this->handleHookException($hookName, $hookType, $e, $definition);

                if ($definition->stopOnFailure) {
                    break;
                }
            }
        }

        return count($results) === 1 ? reset($results) : $results;
    }

    /**
     * Check if hooks exist for a specific type
     */
    public function hasHook(string $hookType): bool
    {
        return $this->registry->has($hookType);
    }

    /**
     * Get all hooks for a specific type
     */
    public function getHooks(string $hookType = null): array
    {
        if ($hookType === null) {
            return $this->registry->all();
        }

        return $this->registry->get($hookType);
    }

    /**
     * Execute beforeStore hooks
     */
    public function executeBeforeStore($model, Request $request): void
    {
        $this->execute('beforeStore', $model, $request);
    }

    /**
     * Execute afterStore hooks
     */
    public function executeAfterStore($model, Request $request): void
    {
        $this->execute('afterStore', $model, $request);
    }

    /**
     * Execute beforeUpdate hooks
     */
    public function executeBeforeUpdate($model, Request $request, array $data = []): void
    {
        $this->execute('beforeUpdate', $model, $request, $data);
    }

    /**
     * Execute afterUpdate hooks
     */
    public function executeAfterUpdate($model, Request $request, array $data = []): void
    {
        $this->execute('afterUpdate', $model, $request, $data);
    }

    /**
     * Execute beforeDelete hooks
     */
    public function executeBeforeDelete($model, Request $request): bool
    {
        $result = $this->execute('beforeDelete', $model, $request);

        // If any hook returns false, prevent deletion
        if (is_array($result)) {
            foreach ($result as $hookResult) {
                if ($hookResult === false) {
                    return false;
                }
            }
        } elseif ($result === false) {
            return false;
        }

        return true;
    }

    /**
     * Execute afterDelete hooks
     */
    public function executeAfterDelete($model, Request $request): void
    {
        $this->execute('afterDelete', $model, $request);
    }

    /**
     * Execute beforeValidation hooks
     */
    public function executeBeforeValidation($model, Request $request, array $data = []): array
    {
        $result = $this->execute('beforeValidation', $model, $request, $data);
        
        // If hooks return modified data, use it
        if (is_array($result) && !empty($result)) {
            return array_merge($data, $result);
        }
        
        return $data;
    }

    /**
     * Execute afterValidation hooks
     */
    public function executeAfterValidation($model, Request $request, array $validatedData = []): void
    {
        $this->execute('afterValidation', $model, $request, $validatedData);
    }

    /**
     * Execute beforeTransform hooks
     */
    public function executeBeforeTransform($model, Request $request, array $data = []): array
    {
        $result = $this->execute('beforeTransform', $model, $request, $data);
        
        // If hooks return modified data, use it
        if (is_array($result) && !empty($result)) {
            return array_merge($data, $result);
        }
        
        return $data;
    }

    /**
     * Execute afterTransform hooks
     */
    public function executeAfterTransform($model, Request $request, array $transformedData = []): void
    {
        $this->execute('afterTransform', $model, $request, $transformedData);
    }

    /**
     * Execute beforeAuthorization hooks
     */
    public function executeBeforeAuthorization($model, Request $request, string $action = ''): bool
    {
        $result = $this->execute('beforeAuthorization', $model, $request, ['action' => $action]);
        
        // If any hook returns false, deny authorization
        if (is_array($result)) {
            foreach ($result as $hookResult) {
                if ($hookResult === false) {
                    return false;
                }
            }
        } elseif ($result === false) {
            return false;
        }
        
        return true;
    }

    /**
     * Execute afterAuthorization hooks
     */
    public function executeAfterAuthorization($model, Request $request, bool $authorized, string $action = ''): void
    {
        $this->execute('afterAuthorization', $model, $request, [
            'action' => $action,
            'authorized' => $authorized
        ]);
    }

    /**
     * Execute beforeAudit hooks
     */
    public function executeBeforeAudit($model, Request $request, array $changes = []): void
    {
        $this->execute('beforeAudit', $model, $request, ['changes' => $changes]);
    }

    /**
     * Execute afterAudit hooks
     */
    public function executeAfterAudit($model, Request $request, array $auditData = []): void
    {
        $this->execute('afterAudit', $model, $request, $auditData);
    }

    /**
     * Execute beforeNotification hooks
     */
    public function executeBeforeNotification($model, Request $request, array $notificationData = []): array
    {
        $result = $this->execute('beforeNotification', $model, $request, $notificationData);
        
        // If hooks return modified notification data, use it
        if (is_array($result) && !empty($result)) {
            return array_merge($notificationData, $result);
        }
        
        return $notificationData;
    }

    /**
     * Execute afterNotification hooks
     */
    public function executeAfterNotification($model, Request $request, array $notificationResult = []): void
    {
        $this->execute('afterNotification', $model, $request, $notificationResult);
    }

    /**
     * Execute beforeCache hooks
     */
    public function executeBeforeCache($model, Request $request, array $cacheData = []): array
    {
        $result = $this->execute('beforeCache', $model, $request, $cacheData);
        
        // If hooks return modified cache data, use it
        if (is_array($result) && !empty($result)) {
            return array_merge($cacheData, $result);
        }
        
        return $cacheData;
    }

    /**
     * Execute afterCache hooks
     */
    public function executeAfterCache($model, Request $request, array $cacheResult = []): void
    {
        $this->execute('afterCache', $model, $request, $cacheResult);
    }

    /**
     * Execute beforeQuery hooks (for filtering and searching)
     */
    public function executeBeforeQuery($model, Request $request, $query): void
    {
        $this->execute('beforeQuery', $model, $request, ['query' => $query]);
    }

    /**
     * Execute afterQuery hooks
     */
    public function executeAfterQuery($model, Request $request, $results): void
    {
        $this->execute('afterQuery', $model, $request, ['results' => $results]);
    }

    /**
     * Execute beforeResponse hooks
     */
    public function executeBeforeResponse($model, Request $request, array $responseData = []): array
    {
        $result = $this->execute('beforeResponse', $model, $request, $responseData);
        
        // If hooks return modified response data, use it
        if (is_array($result) && !empty($result)) {
            return array_merge($responseData, $result);
        }
        
        return $responseData;
    }

    /**
     * Execute afterResponse hooks
     */
    public function executeAfterResponse($model, Request $request, array $responseData = []): void
    {
        $this->execute('afterResponse', $model, $request, $responseData);
    }

    /**
     * Register hooks from configuration array
     */
    public function registerFromConfig(array $config): void
    {
        $this->registry->registerFromConfig($config);
    }

    /**
     * Clear all hooks or hooks for a specific type
     */
    public function clearHooks(string $hookType = null): void
    {
        $this->registry->clear($hookType);
    }

    /**
     * Get hook registry instance
     */
    public function getRegistry(): HookRegistry
    {
        return $this->registry;
    }

    /**
     * Get hooks metadata for debugging
     */
    public function getMetadata(): array
    {
        return $this->registry->getMetadata();
    }

    /**
     * Set logging preference
     */
    public function setLogExecution(bool $log): void
    {
        $this->logExecution = $log;
    }

    /**
     * Set exception throwing preference
     */
    public function setThrowOnFailure(bool $throw): void
    {
        $this->throwOnFailure = $throw;
    }

    /**
     * Determine if execution should stop based on hook result
     */
    protected function shouldStopExecution(string $hookType, $result, ModelHookDefinition $definition): bool
    {
        // For beforeDelete hooks, if any hook returns false, stop execution
        if ($hookType === 'beforeDelete' && $result === false) {
            return true;
        }

        // Stop if the hook definition says to stop on failure and result indicates failure
        if ($definition->stopOnFailure && $this->isFailureResult($result)) {
            return true;
        }

        return false;
    }

    /**
     * Check if a result indicates failure
     */
    protected function isFailureResult($result): bool
    {
        return $result === false || $result === null;
    }

    /**
     * Handle hook execution exceptions
     */
    protected function handleHookException(string $hookName, string $hookType, \Exception $e, ModelHookDefinition $definition): void
    {
        $context = [
            'hook_name' => $hookName,
            'hook_type' => $hookType,
            'exception' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];

        Log::error("Hook execution failed: {$hookName}", $context);

        if ($this->throwOnFailure) {
            throw new ModelHookExecutionException(
                "Hook '{$hookName}' failed during '{$hookType}': " . $e->getMessage(),
                $context,
                $e
            );
        }
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