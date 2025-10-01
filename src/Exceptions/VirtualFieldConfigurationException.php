<?php

namespace MarcosBrendon\ApiForge\Exceptions;

class VirtualFieldConfigurationException extends VirtualFieldException
{
    protected string $errorCode = 'VIRTUAL_FIELD_CONFIGURATION_ERROR';
    protected int $statusCode = 400;
    protected bool $shouldLog = true;
    protected string $logLevel = 'warning';

    public static function invalidFieldName(string $fieldName): self
    {
        return new self(
            "Invalid virtual field name '{$fieldName}'. Field names must be non-empty strings.",
            ['field_name' => $fieldName]
        );
    }

    public static function invalidFieldType(string $fieldName, string $type, array $validTypes): self
    {
        return new self(
            "Invalid type '{$type}' for virtual field '{$fieldName}'. Valid types: " . implode(', ', $validTypes),
            [
                'field_name' => $fieldName,
                'invalid_type' => $type,
                'valid_types' => $validTypes
            ]
        );
    }

    public static function invalidCallback(string $fieldName, $callback): self
    {
        return new self(
            "Invalid callback for virtual field '{$fieldName}'. Callback must be callable.",
            [
                'field_name' => $fieldName,
                'callback_type' => gettype($callback)
            ]
        );
    }

    public static function invalidOperators(string $fieldName, string $type, array $invalidOperators, array $validOperators): self
    {
        return new self(
            "Invalid operators for virtual field '{$fieldName}' of type '{$type}': " . implode(', ', $invalidOperators) . 
            ". Valid operators: " . implode(', ', $validOperators),
            [
                'field_name' => $fieldName,
                'field_type' => $type,
                'invalid_operators' => $invalidOperators,
                'valid_operators' => $validOperators
            ]
        );
    }

    public static function invalidDependency(string $fieldName, $dependency): self
    {
        return new self(
            "Invalid dependency for virtual field '{$fieldName}'. Dependencies must be non-empty strings.",
            [
                'field_name' => $fieldName,
                'dependency' => $dependency,
                'dependency_type' => gettype($dependency)
            ]
        );
    }

    public static function invalidRelationship(string $fieldName, $relationship): self
    {
        return new self(
            "Invalid relationship for virtual field '{$fieldName}'. Relationships must be non-empty strings.",
            [
                'field_name' => $fieldName,
                'relationship' => $relationship,
                'relationship_type' => gettype($relationship)
            ]
        );
    }

    public static function invalidCacheTtl(string $fieldName, $ttl): self
    {
        return new self(
            "Invalid cache TTL for virtual field '{$fieldName}'. TTL must be a non-negative integer.",
            [
                'field_name' => $fieldName,
                'ttl' => $ttl,
                'ttl_type' => gettype($ttl)
            ]
        );
    }

    public static function duplicateField(string $fieldName): self
    {
        return new self(
            "Virtual field '{$fieldName}' is already registered.",
            ['field_name' => $fieldName]
        );
    }

    public static function circularDependency(string $fieldName, array $dependencyChain): self
    {
        return new self(
            "Circular dependency detected for virtual field '{$fieldName}'. Dependency chain: " . implode(' -> ', $dependencyChain),
            [
                'field_name' => $fieldName,
                'dependency_chain' => $dependencyChain
            ]
        );
    }

    public static function missingRequiredConfig(string $fieldName, array $missingKeys): self
    {
        return new self(
            "Missing required configuration for virtual field '{$fieldName}': " . implode(', ', $missingKeys),
            [
                'field_name' => $fieldName,
                'missing_keys' => $missingKeys
            ]
        );
    }

    public static function invalidEnumValues(string $fieldName, array $invalidValues): self
    {
        return new self(
            "Invalid enum values for virtual field '{$fieldName}': " . implode(', ', $invalidValues),
            [
                'field_name' => $fieldName,
                'invalid_values' => $invalidValues
            ]
        );
    }
}