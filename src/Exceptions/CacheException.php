<?php

namespace MarcosBrendon\ApiForge\Exceptions;

class CacheException extends ApiForgeException
{
    protected string $errorCode = 'CACHE_ERROR';
    protected int $statusCode = 500;
    protected string $logLevel = 'error';

    public static function storeFailure(string $key, string $reason = ''): self
    {
        $message = "Failed to store cache key: {$key}";
        
        if ($reason) {
            $message .= ". Reason: {$reason}";
        }

        return new self($message, [
            'cache_key' => $key,
            'reason' => $reason,
        ]);
    }

    public static function invalidationFailure(string $model, string $reason = ''): self
    {
        $message = "Failed to invalidate cache for model: {$model}";
        
        if ($reason) {
            $message .= ". Reason: {$reason}";
        }

        return new self($message, [
            'model' => $model,
            'reason' => $reason,
        ]);
    }

    public static function configurationError(string $setting, $value = null): self
    {
        return new self(
            "Invalid cache configuration for setting: {$setting}",
            [
                'setting' => $setting,
                'value' => $value,
            ]
        );
    }

    public static function driverNotSupported(string $driver, array $supportedDrivers = []): self
    {
        $message = "Cache driver '{$driver}' is not supported";
        
        if (!empty($supportedDrivers)) {
            $message .= ". Supported drivers: " . implode(', ', $supportedDrivers);
        }

        return new self($message, [
            'driver' => $driver,
            'supported_drivers' => $supportedDrivers,
        ]);
    }
}