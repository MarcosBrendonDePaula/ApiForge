<?php

namespace MarcosBrendon\ApiForge\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use MarcosBrendon\ApiForge\Exceptions\VirtualFieldComputationException;
use MarcosBrendon\ApiForge\Exceptions\ModelHookExecutionException;

class RuntimeErrorHandler
{
    /**
     * Whether to throw exceptions on failures
     */
    protected bool $throwOnFailure;

    /**
     * Whether to log errors
     */
    protected bool $logErrors;

    /**
     * Whether to use database transactions for rollback
     */
    protected bool $useTransactions;

    /**
     * Maximum retry attempts for failed operations
     */
    protected int $maxRetries;

    /**
     * Error statistics
     */
    protected array $errorStats = [
        'virtual_field_errors' => 0,
        'hook_errors' => 0,
        'total_errors' => 0,
        'last_error_time' => null
    ];

    /**
     * Create a new runtime error handler instance
     */
    public function __construct()
    {
        $this->throwOnFailure = $this->getConfig('apiforge.error_handling.throw_on_failure', true);
        $this->logErrors = $this->getConfig('apiforge.error_handling.log_errors', true);
        $this->useTransactions = $this->getConfig('apiforge.error_handling.use_transactions', true);
        $this->maxRetries = $this->getConfig('apiforge.error_handling.max_retries', 3);
    }

    /**
     * Handle virtual field computation error
     */
    public function handleVirtualFieldError(
        string $fieldName,
        $model,
        \Exception $exception,
        array $context = []
    ): mixed {
        $this->errorStats['virtual_field_errors']++;
        $this->errorStats['total_errors']++;
        $this->errorStats['last_error_time'] = now();

        $errorContext = array_merge($context, [
            'field_name' => $fieldName,
            'model_class' => get_class($model),
            'model_id' => $model->getKey(),
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'timestamp' => now()->toISOString()
        ]);

        if ($this->logErrors) {
            Log::error("Virtual field computation failed", $errorContext);
        }

        // Try to get default value from field definition
        $defaultValue = $this->getVirtualFieldDefaultValue($fieldName);

        if ($this->throwOnFailure) {
            if ($exception instanceof VirtualFieldComputationException) {
                throw $exception;
            }
            
            throw VirtualFieldComputationException::callbackFailed($fieldName, $model, $exception);
        }

        return $defaultValue;
    }

    /**
     * Handle model hook execution error
     */
    public function handleHookError(
        string $hookType,
        string $hookName,
        $model,
        \Exception $exception,
        array $context = []
    ): void {
        $this->errorStats['hook_errors']++;
        $this->errorStats['total_errors']++;
        $this->errorStats['last_error_time'] = now();

        $errorContext = array_merge($context, [
            'hook_type' => $hookType,
            'hook_name' => $hookName,
            'model_class' => get_class($model),
            'model_id' => $model->getKey(),
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'timestamp' => now()->toISOString()
        ]);

        if ($this->logErrors) {
            Log::error("Model hook execution failed", $errorContext);
        }

        // Rollback transaction if enabled and we're in a transaction
        if ($this->useTransactions && DB::transactionLevel() > 0) {
            try {
                DB::rollBack();
                
                if ($this->logErrors) {
                    Log::info("Transaction rolled back due to hook failure", [
                        'hook_type' => $hookType,
                        'hook_name' => $hookName,
                        'transaction_level' => DB::transactionLevel()
                    ]);
                }
            } catch (\Exception $rollbackException) {
                Log::error("Failed to rollback transaction after hook failure", [
                    'hook_type' => $hookType,
                    'hook_name' => $hookName,
                    'rollback_error' => $rollbackException->getMessage()
                ]);
            }
        }

        if ($this->throwOnFailure) {
            if ($exception instanceof ModelHookExecutionException) {
                throw $exception;
            }
            
            throw new ModelHookExecutionException(
                "Hook '{$hookName}' failed during '{$hookType}': " . $exception->getMessage(),
                $errorContext,
                $exception
            );
        }
    }

    /**
     * Handle batch operation errors
     */
    public function handleBatchError(
        string $operation,
        array $items,
        \Exception $exception,
        array $context = []
    ): array {
        $this->errorStats['total_errors']++;
        $this->errorStats['last_error_time'] = now();

        $errorContext = array_merge($context, [
            'operation' => $operation,
            'item_count' => count($items),
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'timestamp' => now()->toISOString()
        ]);

        if ($this->logErrors) {
            Log::error("Batch operation failed", $errorContext);
        }

        // Return empty results for failed batch operations
        $results = [];
        foreach ($items as $key => $item) {
            $results[$key] = null;
        }

        if ($this->throwOnFailure) {
            throw $exception;
        }

        return $results;
    }

    /**
     * Execute operation with retry logic
     */
    public function executeWithRetry(callable $operation, array $context = []): mixed
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxRetries) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $attempts++;
                $lastException = $e;

                if ($this->logErrors) {
                    Log::warning("Operation failed, attempt {$attempts}/{$this->maxRetries}", [
                        'exception' => $e->getMessage(),
                        'context' => $context,
                        'attempt' => $attempts
                    ]);
                }

                // Wait before retrying (exponential backoff)
                if ($attempts < $this->maxRetries) {
                    usleep(pow(2, $attempts) * 100000); // 0.1s, 0.2s, 0.4s, etc.
                }
            }
        }

        // All retries failed
        if ($this->logErrors) {
            Log::error("Operation failed after {$this->maxRetries} attempts", [
                'exception' => $lastException->getMessage(),
                'context' => $context
            ]);
        }

        if ($this->throwOnFailure) {
            throw $lastException;
        }

        return null;
    }

    /**
     * Execute operation with timeout
     */
    public function executeWithTimeout(callable $operation, int $timeoutSeconds, array $context = []): mixed
    {
        $startTime = microtime(true);
        
        try {
            // Set time limit for the operation
            $originalTimeLimit = ini_get('max_execution_time');
            set_time_limit($timeoutSeconds);

            $result = $operation();

            // Restore original time limit
            set_time_limit($originalTimeLimit);

            $executionTime = microtime(true) - $startTime;

            if ($this->logErrors && $executionTime > ($timeoutSeconds * 0.8)) {
                Log::warning("Operation took significant time", [
                    'execution_time' => $executionTime,
                    'timeout_seconds' => $timeoutSeconds,
                    'context' => $context
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;

            if ($executionTime >= $timeoutSeconds) {
                if ($this->logErrors) {
                    Log::error("Operation timed out", [
                        'execution_time' => $executionTime,
                        'timeout_seconds' => $timeoutSeconds,
                        'context' => $context
                    ]);
                }

                if ($this->throwOnFailure) {
                    throw new \RuntimeException(
                        "Operation timed out after {$timeoutSeconds} seconds",
                        0,
                        $e
                    );
                }

                return null;
            }

            // Re-throw if not a timeout
            throw $e;
        }
    }

    /**
     * Execute operation with memory limit monitoring
     */
    public function executeWithMemoryLimit(callable $operation, int $memoryLimitMb, array $context = []): mixed
    {
        $startMemory = memory_get_usage(true);
        $memoryLimitBytes = $memoryLimitMb * 1024 * 1024;

        try {
            $result = $operation();

            $endMemory = memory_get_usage(true);
            $memoryUsed = $endMemory - $startMemory;

            if ($this->logErrors && $memoryUsed > ($memoryLimitBytes * 0.8)) {
                Log::warning("Operation used significant memory", [
                    'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
                    'memory_limit_mb' => $memoryLimitMb,
                    'context' => $context
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            $endMemory = memory_get_usage(true);
            $memoryUsed = $endMemory - $startMemory;

            if ($memoryUsed >= $memoryLimitBytes) {
                if ($this->logErrors) {
                    Log::error("Operation exceeded memory limit", [
                        'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
                        'memory_limit_mb' => $memoryLimitMb,
                        'context' => $context
                    ]);
                }

                if ($this->throwOnFailure) {
                    throw new \RuntimeException(
                        "Operation exceeded memory limit of {$memoryLimitMb}MB",
                        0,
                        $e
                    );
                }

                return null;
            }

            // Re-throw if not a memory limit issue
            throw $e;
        }
    }

    /**
     * Get error statistics
     */
    public function getErrorStats(): array
    {
        return $this->errorStats;
    }

    /**
     * Reset error statistics
     */
    public function resetErrorStats(): void
    {
        $this->errorStats = [
            'virtual_field_errors' => 0,
            'hook_errors' => 0,
            'total_errors' => 0,
            'last_error_time' => null
        ];
    }

    /**
     * Check if error rate is too high
     */
    public function isErrorRateHigh(int $thresholdPerMinute = 10): bool
    {
        if ($this->errorStats['last_error_time'] === null) {
            return false;
        }

        $secondsAgo = now()->diffInSeconds($this->errorStats['last_error_time']);
        $minutesAgo = max(1, ceil($secondsAgo / 60)); // At least 1 minute, rounded up
        
        $errorRate = $this->errorStats['total_errors'] / $minutesAgo;
        
        return $errorRate > $thresholdPerMinute;
    }

    /**
     * Set configuration options
     */
    public function setThrowOnFailure(bool $throw): void
    {
        $this->throwOnFailure = $throw;
    }

    public function setLogErrors(bool $log): void
    {
        $this->logErrors = $log;
    }

    public function setUseTransactions(bool $use): void
    {
        $this->useTransactions = $use;
    }

    public function setMaxRetries(int $retries): void
    {
        $this->maxRetries = max(0, $retries);
    }

    /**
     * Get virtual field default value
     */
    protected function getVirtualFieldDefaultValue(string $fieldName): mixed
    {
        try {
            $virtualFieldService = app(VirtualFieldService::class);
            $definition = $virtualFieldService->getDefinition($fieldName);
            return $definition ? $definition->defaultValue : null;
        } catch (\Exception $e) {
            return null;
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