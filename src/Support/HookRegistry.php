<?php

namespace MarcosBrendon\ApiForge\Support;

use MarcosBrendon\ApiForge\Exceptions\ModelHookConfigurationException;

class HookRegistry
{
    /**
     * Registered hooks organized by hook type
     */
    protected array $hooks = [];

    /**
     * Valid hook types
     */
    protected array $validHookTypes = [
        'beforeStore',
        'afterStore',
        'beforeUpdate',
        'afterUpdate',
        'beforeDelete',
        'afterDelete',
    ];

    /**
     * Add a hook definition to the registry
     */
    public function add(string $hookType, ModelHookDefinition $definition): void
    {
        $this->validateHookType($hookType);

        if (!isset($this->hooks[$hookType])) {
            $this->hooks[$hookType] = [];
        }

        $this->hooks[$hookType][$definition->name] = $definition;

        // Sort hooks by priority after adding
        $this->sortHooksByPriority($hookType);
    }

    /**
     * Get all hooks for a specific type
     */
    public function get(string $hookType): array
    {
        $this->validateHookType($hookType);
        return $this->hooks[$hookType] ?? [];
    }

    /**
     * Check if a specific hook exists
     */
    public function has(string $hookType, string $hookName = null): bool
    {
        $this->validateHookType($hookType);

        if ($hookName === null) {
            return isset($this->hooks[$hookType]) && !empty($this->hooks[$hookType]);
        }

        return isset($this->hooks[$hookType][$hookName]);
    }

    /**
     * Remove a hook from the registry
     */
    public function remove(string $hookType, string $hookName): bool
    {
        $this->validateHookType($hookType);

        if (isset($this->hooks[$hookType][$hookName])) {
            unset($this->hooks[$hookType][$hookName]);
            return true;
        }

        return false;
    }

    /**
     * Get all registered hooks
     */
    public function all(): array
    {
        return $this->hooks;
    }

    /**
     * Clear all hooks for a specific type
     */
    public function clear(string $hookType = null): void
    {
        if ($hookType === null) {
            $this->hooks = [];
        } else {
            $this->validateHookType($hookType);
            $this->hooks[$hookType] = [];
        }
    }

    /**
     * Get hooks count for a specific type
     */
    public function count(string $hookType): int
    {
        $this->validateHookType($hookType);
        return count($this->hooks[$hookType] ?? []);
    }

    /**
     * Get total hooks count
     */
    public function totalCount(): int
    {
        $total = 0;
        foreach ($this->hooks as $hooks) {
            $total += count($hooks);
        }
        return $total;
    }

    /**
     * Get valid hook types
     */
    public function getValidHookTypes(): array
    {
        return $this->validHookTypes;
    }

    /**
     * Register multiple hooks from configuration array
     */
    public function registerFromConfig(array $config): void
    {
        foreach ($config as $hookType => $hooks) {
            $this->validateHookType($hookType);

            foreach ($hooks as $hookName => $hookConfig) {
                $definition = $this->createDefinitionFromConfig($hookName, $hookConfig);
                $this->add($hookType, $definition);
            }
        }
    }

    /**
     * Create a hook definition from configuration array
     */
    protected function createDefinitionFromConfig(string $hookName, $hookConfig): ModelHookDefinition
    {
        // Handle simple callback configuration
        if (is_callable($hookConfig)) {
            return new ModelHookDefinition($hookName, $hookConfig);
        }

        // Handle array configuration
        if (is_array($hookConfig)) {
            return new ModelHookDefinition(
                $hookName,
                $hookConfig['callback'] ?? null,
                $hookConfig['priority'] ?? 10,
                $hookConfig['stopOnFailure'] ?? false,
                $hookConfig['conditions'] ?? [],
                $hookConfig['description'] ?? ''
            );
        }

        throw new ModelHookConfigurationException(
            "Invalid hook configuration for '{$hookName}'. Must be callable or array."
        );
    }

    /**
     * Validate hook type
     */
    protected function validateHookType(string $hookType): void
    {
        if (!in_array($hookType, $this->validHookTypes)) {
            throw new ModelHookConfigurationException(
                "Invalid hook type '{$hookType}'. Valid types: " . implode(', ', $this->validHookTypes)
            );
        }
    }

    /**
     * Sort hooks by priority (lower number = higher priority)
     */
    protected function sortHooksByPriority(string $hookType): void
    {
        if (!isset($this->hooks[$hookType])) {
            return;
        }

        uasort($this->hooks[$hookType], function (ModelHookDefinition $a, ModelHookDefinition $b) {
            return $a->priority <=> $b->priority;
        });
    }

    /**
     * Get hooks metadata for debugging
     */
    public function getMetadata(): array
    {
        $metadata = [
            'total_hooks' => $this->totalCount(),
            'valid_hook_types' => $this->validHookTypes,
            'hooks_by_type' => [],
        ];

        foreach ($this->validHookTypes as $hookType) {
            $hooks = $this->get($hookType);
            $metadata['hooks_by_type'][$hookType] = [
                'count' => count($hooks),
                'hooks' => array_map(fn($hook) => $hook->toArray(), $hooks),
            ];
        }

        return $metadata;
    }
}