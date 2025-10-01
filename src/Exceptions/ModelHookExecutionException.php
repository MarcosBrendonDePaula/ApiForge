<?php

namespace MarcosBrendon\ApiForge\Exceptions;

class ModelHookExecutionException extends ModelHookException
{
    protected string $errorCode = 'MODEL_HOOK_EXECUTION_ERROR';
    protected int $statusCode = 500;
    protected bool $shouldLog = true;
    protected string $logLevel = 'error';
}