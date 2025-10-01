<?php

namespace MarcosBrendon\ApiForge\Exceptions;

class ModelHookConfigurationException extends ModelHookException
{
    protected string $errorCode = 'MODEL_HOOK_CONFIGURATION_ERROR';
    protected int $statusCode = 400;
    protected bool $shouldLog = true;
    protected string $logLevel = 'warning';
}