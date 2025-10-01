<?php

namespace MarcosBrendon\ApiForge\Support;

use MarcosBrendon\ApiForge\Exceptions\ModelHookConfigurationException;

class ModelHookDefinition
{
    /**
     * The hook name
     */
    public string $name;

    /**
     * The callback function to execute
     */
    public $callback;

    /**
     * The priority of the hook (lower number = higher priority)
     */
    public int $priority;

    /**
     * Whether to stop execution if this hook fails
     */
    public bool $stopOnFailure;

    /**
     * Conditions that must be met for the hook to execute
     */
    public array $conditions;

    /**
     * Description of what the hook does
     */
    public string $description;

    /**
     * Create a new hook definition
     */
    public function __construct(
        string $name,
        $callback,
        int $priority = 10,
        bool $stopOnFailure = false,
        array $conditions = [],
        string $description = ''
    ) {
        $this->name = $name;
        $this->callback = $callback;
        $this->priority = $priority;
        $this->stopOnFailure = $stopOnFailure;
        $this->conditions = $conditions;
        $this->description = $description;

        $this->validate();
    }

    /**
     * Validate the hook definition
     */
    protected function validate(): void
    {
        if (empty($this->name)) {
            throw new ModelHookConfigurationException('Hook name cannot be empty');
        }

        if (!is_callable($this->callback)) {
            throw new ModelHookConfigurationException("Hook callback for '{$this->name}' is not callable");
        }

        if ($this->priority < 0) {
            throw new ModelHookConfigurationException("Hook priority for '{$this->name}' must be non-negative");
        }

        $this->validateConditions();
    }

    /**
     * Validate hook conditions
     */
    protected function validateConditions(): void
    {
        foreach ($this->conditions as $condition) {
            if (!is_array($condition)) {
                throw new ModelHookConfigurationException("Hook condition for '{$this->name}' must be an array");
            }

            if (!isset($condition['field'])) {
                throw new ModelHookConfigurationException("Hook condition for '{$this->name}' must have a 'field' key");
            }

            if (!isset($condition['operator'])) {
                throw new ModelHookConfigurationException("Hook condition for '{$this->name}' must have an 'operator' key");
            }

            if (!isset($condition['value'])) {
                throw new ModelHookConfigurationException("Hook condition for '{$this->name}' must have a 'value' key");
            }

            $validOperators = ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in', 'like', 'not_like', 'null', 'not_null'];
            if (!in_array($condition['operator'], $validOperators)) {
                throw new ModelHookConfigurationException(
                    "Invalid operator '{$condition['operator']}' for hook condition in '{$this->name}'. " .
                    "Valid operators: " . implode(', ', $validOperators)
                );
            }
        }
    }

    /**
     * Check if the hook should execute based on conditions
     */
    public function shouldExecute(HookContext $context): bool
    {
        if (empty($this->conditions)) {
            return true;
        }

        foreach ($this->conditions as $condition) {
            if (!$this->evaluateCondition($condition, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition
     */
    protected function evaluateCondition(array $condition, HookContext $context): bool
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $expectedValue = $condition['value'];

        // Get the actual value from the model or context
        $actualValue = $this->getFieldValue($field, $context);

        return $this->compareValues($actualValue, $operator, $expectedValue);
    }

    /**
     * Get the field value from the model or context
     */
    protected function getFieldValue(string $field, HookContext $context)
    {
        // First try to get from model
        if (isset($context->model->{$field})) {
            return $context->model->{$field};
        }

        // Then try from context data
        if ($context->has($field)) {
            return $context->get($field);
        }

        // Finally try from request
        if ($context->request->has($field)) {
            return $context->request->input($field);
        }

        return null;
    }

    /**
     * Compare values based on operator
     */
    protected function compareValues($actualValue, string $operator, $expectedValue): bool
    {
        switch ($operator) {
            case 'eq':
                return $actualValue == $expectedValue;
            case 'ne':
                return $actualValue != $expectedValue;
            case 'gt':
                return $actualValue > $expectedValue;
            case 'gte':
                return $actualValue >= $expectedValue;
            case 'lt':
                return $actualValue < $expectedValue;
            case 'lte':
                return $actualValue <= $expectedValue;
            case 'in':
                $values = is_array($expectedValue) ? $expectedValue : explode(',', $expectedValue);
                return in_array($actualValue, $values);
            case 'not_in':
                $values = is_array($expectedValue) ? $expectedValue : explode(',', $expectedValue);
                return !in_array($actualValue, $values);
            case 'like':
                return str_contains(strtolower($actualValue), strtolower($expectedValue));
            case 'not_like':
                return !str_contains(strtolower($actualValue), strtolower($expectedValue));
            case 'null':
                return is_null($actualValue) || $actualValue === '';
            case 'not_null':
                return !is_null($actualValue) && $actualValue !== '';
            default:
                return false;
        }
    }

    /**
     * Execute the hook callback
     */
    public function execute(HookContext $context)
    {
        return call_user_func($this->callback, $context->model, $context);
    }

    /**
     * Convert to array for serialization
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'priority' => $this->priority,
            'stop_on_failure' => $this->stopOnFailure,
            'conditions' => $this->conditions,
            'description' => $this->description,
            'callback_type' => is_string($this->callback) ? 'string' : gettype($this->callback),
        ];
    }
}