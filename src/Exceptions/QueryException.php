<?php

namespace MarcosBrendon\ApiForge\Exceptions;

class QueryException extends ApiForgeException
{
    protected string $errorCode = 'QUERY_ERROR';
    protected int $statusCode = 500;
    protected string $logLevel = 'error';

    public static function executionFailure(string $query, string $error): self
    {
        return new self(
            "Query execution failed: {$error}",
            [
                'query' => $query,
                'error' => $error,
            ]
        );
    }

    public static function invalidModel(string $model): self
    {
        return new self(
            "Invalid or non-existent model: {$model}",
            [
                'model' => $model,
            ]
        );
    }

    public static function relationshipNotFound(string $relationship, string $model): self
    {
        return new self(
            "Relationship '{$relationship}' not found on model '{$model}'",
            [
                'relationship' => $relationship,
                'model' => $model,
            ]
        );
    }

    public static function tooManyResults(int $count, int $maxAllowed): self
    {
        return new self(
            "Query returned too many results. Count: {$count}, Max allowed: {$maxAllowed}",
            [
                'result_count' => $count,
                'max_allowed' => $maxAllowed,
            ]
        );
    }

    public static function paginationError(string $reason): self
    {
        return new self(
            "Pagination error: {$reason}",
            [
                'reason' => $reason,
            ]
        );
    }
}