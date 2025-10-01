<?php

namespace MarcosBrendon\ApiForge\Support;

use Illuminate\Http\Request;

class HookContext
{
    /**
     * The model instance
     */
    public $model;

    /**
     * The HTTP request
     */
    public Request $request;

    /**
     * Additional data passed to the hook
     */
    public array $data;

    /**
     * The operation being performed
     */
    public string $operation;

    /**
     * Additional metadata
     */
    public array $metadata;

    /**
     * Create a new hook context instance
     */
    public function __construct(
        $model,
        Request $request,
        array $data = [],
        string $operation = '',
        array $metadata = []
    ) {
        $this->model = $model;
        $this->request = $request;
        $this->data = $data;
        $this->operation = $operation;
        $this->metadata = $metadata;
    }

    /**
     * Get a value from the context data
     */
    public function get(string $key, $default = null)
    {
        return data_get($this->data, $key, $default);
    }

    /**
     * Set a value in the context data
     */
    public function set(string $key, $value): void
    {
        data_set($this->data, $key, $value);
    }

    /**
     * Check if a key exists in the context data
     */
    public function has(string $key): bool
    {
        return data_get($this->data, $key) !== null;
    }

    /**
     * Get a metadata value
     */
    public function getMetadata(string $key, $default = null)
    {
        return data_get($this->metadata, $key, $default);
    }

    /**
     * Set a metadata value
     */
    public function setMetadata(string $key, $value): void
    {
        data_set($this->metadata, $key, $value);
    }

    /**
     * Check if a metadata key exists
     */
    public function hasMetadata(string $key): bool
    {
        return data_get($this->metadata, $key) !== null;
    }

    /**
     * Get all context data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get all metadata
     */
    public function getAllMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get the model class name
     */
    public function getModelClass(): string
    {
        return get_class($this->model);
    }

    /**
     * Get the model primary key value
     */
    public function getModelId()
    {
        return $this->model->getKey();
    }

    /**
     * Check if the model is being created (doesn't exist yet)
     */
    public function isCreating(): bool
    {
        return !$this->model->exists;
    }

    /**
     * Check if the model is being updated (already exists)
     */
    public function isUpdating(): bool
    {
        return $this->model->exists;
    }

    /**
     * Get the authenticated user from the request
     */
    public function getUser()
    {
        return $this->request->user();
    }

    /**
     * Get the user ID from the request
     */
    public function getUserId()
    {
        return $this->request->user()?->getKey();
    }

    /**
     * Convert the context to an array for logging
     */
    public function toArray(): array
    {
        return [
            'model_class' => $this->getModelClass(),
            'model_id' => $this->getModelId(),
            'operation' => $this->operation,
            'user_id' => $this->getUserId(),
            'data' => $this->data,
            'metadata' => $this->metadata,
            'is_creating' => $this->isCreating(),
            'is_updating' => $this->isUpdating(),
        ];
    }
}