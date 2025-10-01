<?php

namespace MarcosBrendon\ApiForge\Exceptions;

abstract class VirtualFieldException extends ApiForgeException
{
    protected string $errorCode = 'VIRTUAL_FIELD_ERROR';
    protected int $statusCode = 400;
    protected bool $shouldLog = true;
    protected string $logLevel = 'error';
}