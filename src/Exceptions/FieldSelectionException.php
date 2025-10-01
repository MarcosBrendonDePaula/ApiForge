<?php

namespace MarcosBrendon\ApiForge\Exceptions;

class FieldSelectionException extends ApiForgeException
{
    protected string $errorCode = 'FIELD_SELECTION_ERROR';
    protected int $statusCode = 422;
    protected string $logLevel = 'warning';

    public static function blockedField(string $field): self
    {
        return new self(
            "Field '{$field}' is not allowed in field selection",
            [
                'blocked_field' => $field,
            ]
        );
    }

    public static function tooManyFields(int $requested, int $maxAllowed): self
    {
        return new self(
            "Too many fields requested. Maximum {$maxAllowed} allowed, {$requested} requested",
            [
                'requested_count' => $requested,
                'max_allowed' => $maxAllowed,
            ]
        );
    }

    public static function invalidFieldFormat(string $field, string $reason = ''): self
    {
        $message = "Invalid field format: '{$field}'";
        
        if ($reason) {
            $message .= ". {$reason}";
        }

        return new self($message, [
            'invalid_field' => $field,
            'reason' => $reason,
        ]);
    }

    public static function relationshipTooDeep(string $field, int $depth, int $maxDepth): self
    {
        return new self(
            "Relationship field '{$field}' exceeds maximum depth. Depth: {$depth}, Max: {$maxDepth}",
            [
                'field' => $field,
                'depth' => $depth,
                'max_depth' => $maxDepth,
            ]
        );
    }

    public static function invalidRelationship(string $field, string $model): self
    {
        return new self(
            "Invalid relationship '{$field}' for model '{$model}'",
            [
                'field' => $field,
                'model' => $model,
            ]
        );
    }

    public static function multipleBlockedFields(array $blockedFields): self
    {
        return new self(
            'Multiple blocked fields in selection: ' . implode(', ', $blockedFields),
            [
                'blocked_fields' => $blockedFields,
            ]
        );
    }
}