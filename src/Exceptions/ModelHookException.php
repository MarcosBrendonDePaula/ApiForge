<?php

namespace MarcosBrendon\ApiForge\Exceptions;

class ModelHookException extends ApiForgeException
{
    protected string $errorCode = 'MODEL_HOOK_ERROR';
    protected int $statusCode = 500;
    protected bool $shouldLog = true;
    protected string $logLevel = 'error';
}