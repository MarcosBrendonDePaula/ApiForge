<?php

namespace MarcosBrendon\ApiForge\Exceptions;

class VirtualFieldComputationException extends VirtualFieldException
{
    protected string $errorCode = 'VIRTUAL_FIELD_COMPUTATION_ERROR';
    protected int $statusCode = 500;
    protected bool $shouldLog = true;
    protected string $logLevel = 'error';

    public static function callbackFailed(string $fieldName, $model, \Exception $originalException): self
    {
        return new self(
            "Failed to compute virtual field '{$fieldName}': " . $originalException->getMessage(),
            [
                'field_name' => $fieldName,
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
                'original_error' => $originalException->getMessage(),
                'original_file' => $originalException->getFile(),
                'original_line' => $originalException->getLine()
            ],
            $originalException
        );
    }

    public static function missingDependency(string $fieldName, string $dependency, $model): self
    {
        return new self(
            "Missing dependency '{$dependency}' for virtual field '{$fieldName}'",
            [
                'field_name' => $fieldName,
                'missing_dependency' => $dependency,
                'model_class' => get_class($model),
                'model_id' => $model->getKey()
            ]
        );
    }

    public static function missingRelationship(string $fieldName, string $relationship, $model): self
    {
        return new self(
            "Missing relationship '{$relationship}' for virtual field '{$fieldName}'",
            [
                'field_name' => $fieldName,
                'missing_relationship' => $relationship,
                'model_class' => get_class($model),
                'model_id' => $model->getKey()
            ]
        );
    }

    public static function invalidReturnType(string $fieldName, string $expectedType, $actualValue, $model): self
    {
        return new self(
            "Virtual field '{$fieldName}' returned invalid type. Expected '{$expectedType}', got '" . gettype($actualValue) . "'",
            [
                'field_name' => $fieldName,
                'expected_type' => $expectedType,
                'actual_type' => gettype($actualValue),
                'actual_value' => is_scalar($actualValue) ? $actualValue : '[non-scalar]',
                'model_class' => get_class($model),
                'model_id' => $model->getKey()
            ]
        );
    }

    public static function timeoutExceeded(string $fieldName, int $timeoutSeconds, $model): self
    {
        return new self(
            "Virtual field '{$fieldName}' computation exceeded timeout of {$timeoutSeconds} seconds",
            [
                'field_name' => $fieldName,
                'timeout_seconds' => $timeoutSeconds,
                'model_class' => get_class($model),
                'model_id' => $model->getKey()
            ]
        );
    }

    public static function memoryLimitExceeded(string $fieldName, int $memoryLimitMb, $model): self
    {
        return new self(
            "Virtual field '{$fieldName}' computation exceeded memory limit of {$memoryLimitMb}MB",
            [
                'field_name' => $fieldName,
                'memory_limit_mb' => $memoryLimitMb,
                'model_class' => get_class($model),
                'model_id' => $model->getKey()
            ]
        );
    }

    public static function batchProcessingFailed(string $fieldName, int $modelCount, \Exception $originalException): self
    {
        return new self(
            "Failed to batch process virtual field '{$fieldName}' for {$modelCount} models: " . $originalException->getMessage(),
            [
                'field_name' => $fieldName,
                'model_count' => $modelCount,
                'original_error' => $originalException->getMessage()
            ],
            $originalException
        );
    }
}