<?php

namespace MarcosBrendon\ApiForge\Exceptions;

class FilterValidationException extends ApiForgeException
{
    protected string $errorCode = 'FILTER_VALIDATION_ERROR';
    protected int $statusCode = 422;
    protected string $logLevel = 'warning';

    public static function invalidOperator(string $field, string $operator, array $validOperators = []): self
    {
        $message = "Invalid operator '{$operator}' for field '{$field}'";
        
        if (!empty($validOperators)) {
            $message .= ". Valid operators: " . implode(', ', $validOperators);
        }

        return new self($message, [
            'field' => $field,
            'invalid_operator' => $operator,
            'valid_operators' => $validOperators,
        ]);
    }

    public static function invalidFieldType(string $field, string $expectedType, $actualValue): self
    {
        return new self(
            "Invalid value type for field '{$field}'. Expected '{$expectedType}'",
            [
                'field' => $field,
                'expected_type' => $expectedType,
                'actual_value' => $actualValue,
                'actual_type' => gettype($actualValue),
            ]
        );
    }

    public static function blockedValue(string $field, $value, string $reason = 'Security policy'): self
    {
        return new self(
            "Value for field '{$field}' was blocked: {$reason}",
            [
                'field' => $field,
                'blocked_value' => is_string($value) ? substr($value, 0, 100) : $value,
                'reason' => $reason,
            ]
        );
    }

    public static function requiredFilterMissing(array $missingFilters): self
    {
        return new self(
            'Required filters are missing: ' . implode(', ', $missingFilters),
            [
                'missing_filters' => $missingFilters,
            ]
        );
    }

    public static function tooManyFilters(int $count, int $maxAllowed): self
    {
        return new self(
            "Too many filters applied. Maximum {$maxAllowed} allowed, {$count} provided",
            [
                'filter_count' => $count,
                'max_allowed' => $maxAllowed,
            ]
        );
    }

    public static function invalidDateFormat(string $field, $value, array $acceptedFormats = []): self
    {
        $message = "Invalid date format for field '{$field}'";
        
        if (!empty($acceptedFormats)) {
            $message .= ". Accepted formats: " . implode(', ', $acceptedFormats);
        }

        return new self($message, [
            'field' => $field,
            'invalid_value' => $value,
            'accepted_formats' => $acceptedFormats,
        ]);
    }

    public static function enumValueNotAllowed(string $field, $value, array $allowedValues): self
    {
        return new self(
            "Value '{$value}' is not allowed for enum field '{$field}'",
            [
                'field' => $field,
                'invalid_value' => $value,
                'allowed_values' => $allowedValues,
            ]
        );
    }
}