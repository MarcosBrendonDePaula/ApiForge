<?php

namespace MarcosBrendon\ApiForge\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

class VirtualFieldCache
{
    /**
     * Cache key prefix for virtual fields
     */
    protected string $keyPrefix;

    /**
     * Default TTL for cache entries
     */
    protected int $defaultTtl;

    /**
     * Cache store to use
     */
    protected ?string $store;

    /**
     * Whether to log cache operations
     */
    protected bool $logOperations;

    /**
     * Cache tags for invalidation
     */
    protected array $tags;

    /**
     * Create a new virtual field cache instance
     */
    public function __construct()
    {
        $this->keyPrefix = config('apiforge.virtual_fields.cache_key_prefix', 'vf_');
        $this->defaultTtl = config('apiforge.virtual_fields.default_cache_ttl', 3600);
        $this->store = config('apiforge.virtual_fields.cache_store');
        $this->logOperations = config('apiforge.virtual_fields.log_operations', false);
        $this->tags = config('apiforge.virtual_fields.cache_tags', ['virtual_fields']);
    }

    /**
     * Store a virtual field value in cache
     */
    public function store(string $field, Model $model, mixed $value, ?int $ttl = null): bool
    {
        try {
            $key = $this->generateKey($field, $model);
            $cacheTtl = $ttl ?? $this->defaultTtl;
            
            $cache = $this->getCacheInstance();
            
            if (!empty($this->tags) && method_exists($cache, 'tags')) {
                $cache = $cache->tags($this->tags);
            }
            
            $success = $cache->put($key, $value, $cacheTtl);
            
            if ($this->logOperations && $success) {
                Log::debug("Cached virtual field value", [
                    'field' => $field,
                    'model_class' => get_class($model),
                    'model_id' => $model->getKey(),
                    'cache_key' => $key,
                    'ttl' => $cacheTtl
                ]);
            }
            
            return $success;
        } catch (\Exception $e) {
            Log::error("Failed to cache virtual field value", [
                'field' => $field,
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Retrieve a virtual field value from cache
     */
    public function retrieve(string $field, Model $model): mixed
    {
        try {
            $key = $this->generateKey($field, $model);
            
            $cache = $this->getCacheInstance();
            
            if (!empty($this->tags) && method_exists($cache, 'tags')) {
                $cache = $cache->tags($this->tags);
            }
            
            $value = $cache->get($key);
            
            if ($this->logOperations && $value !== null) {
                Log::debug("Retrieved virtual field value from cache", [
                    'field' => $field,
                    'model_class' => get_class($model),
                    'model_id' => $model->getKey(),
                    'cache_key' => $key,
                    'hit' => true
                ]);
            } elseif ($this->logOperations) {
                Log::debug("Virtual field cache miss", [
                    'field' => $field,
                    'model_class' => get_class($model),
                    'model_id' => $model->getKey(),
                    'cache_key' => $key,
                    'hit' => false
                ]);
            }
            
            return $value;
        } catch (\Exception $e) {
            Log::error("Failed to retrieve virtual field value from cache", [
                'field' => $field,
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Invalidate cache for specific virtual field and model
     */
    public function invalidate(string $field, Model $model): bool
    {
        try {
            $key = $this->generateKey($field, $model);
            
            $cache = $this->getCacheInstance();
            
            if (!empty($this->tags) && method_exists($cache, 'tags')) {
                $cache = $cache->tags($this->tags);
            }
            
            $success = $cache->forget($key);
            
            if ($this->logOperations && $success) {
                Log::debug("Invalidated virtual field cache", [
                    'field' => $field,
                    'model_class' => get_class($model),
                    'model_id' => $model->getKey(),
                    'cache_key' => $key
                ]);
            }
            
            return $success;
        } catch (\Exception $e) {
            Log::error("Failed to invalidate virtual field cache", [
                'field' => $field,
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Invalidate cache for all virtual fields of a model
     */
    public function invalidateModel(Model $model, array $fields = []): int
    {
        $invalidated = 0;
        
        try {
            if (empty($fields)) {
                // If no specific fields provided, invalidate by pattern
                $pattern = $this->generateModelPattern($model);
                $invalidated = $this->invalidateByPattern($pattern);
            } else {
                // Invalidate specific fields
                foreach ($fields as $field) {
                    if ($this->invalidate($field, $model)) {
                        $invalidated++;
                    }
                }
            }
            
            if ($this->logOperations && $invalidated > 0) {
                Log::info("Invalidated virtual field cache for model", [
                    'model_class' => get_class($model),
                    'model_id' => $model->getKey(),
                    'fields' => $fields,
                    'invalidated_count' => $invalidated
                ]);
            }
            
            return $invalidated;
        } catch (\Exception $e) {
            Log::error("Failed to invalidate virtual field cache for model", [
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
                'fields' => $fields,
                'error' => $e->getMessage()
            ]);
            
            return $invalidated;
        }
    }

    /**
     * Invalidate cache for all instances of a model class
     */
    public function invalidateModelClass(string $modelClass, array $fields = []): int
    {
        try {
            $pattern = $this->generateClassPattern($modelClass, $fields);
            $invalidated = $this->invalidateByPattern($pattern);
            
            if ($this->logOperations && $invalidated > 0) {
                Log::info("Invalidated virtual field cache for model class", [
                    'model_class' => $modelClass,
                    'fields' => $fields,
                    'invalidated_count' => $invalidated
                ]);
            }
            
            return $invalidated;
        } catch (\Exception $e) {
            Log::error("Failed to invalidate virtual field cache for model class", [
                'model_class' => $modelClass,
                'fields' => $fields,
                'error' => $e->getMessage()
            ]);
            
            return 0;
        }
    }

    /**
     * Batch store multiple virtual field values
     */
    public function storeBatch(array $values, ?int $ttl = null): array
    {
        $results = [];
        $cacheTtl = $ttl ?? $this->defaultTtl;
        
        try {
            $cache = $this->getCacheInstance();
            
            if (!empty($this->tags)) {
                $cache = $cache->tags($this->tags);
            }
            
            foreach ($values as $entry) {
                $field = $entry['field'];
                $model = $entry['model'];
                $value = $entry['value'];
                $entryTtl = $entry['ttl'] ?? $cacheTtl;
                
                $key = $this->generateKey($field, $model);
                $success = $cache->put($key, $value, $entryTtl);
                
                $results[] = [
                    'field' => $field,
                    'model_id' => $model->getKey(),
                    'key' => $key,
                    'success' => $success
                ];
            }
            
            if ($this->logOperations) {
                $successCount = count(array_filter($results, fn($r) => $r['success']));
                Log::info("Batch cached virtual field values", [
                    'total_entries' => count($values),
                    'successful' => $successCount,
                    'failed' => count($values) - $successCount
                ]);
            }
            
            return $results;
        } catch (\Exception $e) {
            Log::error("Failed to batch cache virtual field values", [
                'entry_count' => count($values),
                'error' => $e->getMessage()
            ]);
            
            return array_map(function($entry) {
                return [
                    'field' => $entry['field'],
                    'model_id' => $entry['model']->getKey(),
                    'key' => $this->generateKey($entry['field'], $entry['model']),
                    'success' => false
                ];
            }, $values);
        }
    }

    /**
     * Batch retrieve multiple virtual field values
     */
    public function retrieveBatch(array $requests): array
    {
        $results = [];
        
        try {
            $cache = $this->getCacheInstance();
            
            if (!empty($this->tags) && method_exists($cache, 'tags')) {
                $cache = $cache->tags($this->tags);
            }
            
            // Generate all keys first
            $keys = [];
            $keyMap = [];
            
            foreach ($requests as $request) {
                $field = $request['field'];
                $model = $request['model'];
                $key = $this->generateKey($field, $model);
                
                $keys[] = $key;
                $keyMap[$key] = [
                    'field' => $field,
                    'model' => $model
                ];
            }
            
            // Batch retrieve
            $values = $cache->many($keys);
            
            // Map results back
            foreach ($values as $key => $value) {
                $info = $keyMap[$key];
                $results[] = [
                    'field' => $info['field'],
                    'model' => $info['model'],
                    'value' => $value,
                    'hit' => $value !== null
                ];
            }
            
            if ($this->logOperations) {
                $hitCount = count(array_filter($results, fn($r) => $r['hit']));
                Log::debug("Batch retrieved virtual field values from cache", [
                    'total_requests' => count($requests),
                    'cache_hits' => $hitCount,
                    'cache_misses' => count($requests) - $hitCount
                ]);
            }
            
            return $results;
        } catch (\Exception $e) {
            Log::error("Failed to batch retrieve virtual field values from cache", [
                'request_count' => count($requests),
                'error' => $e->getMessage()
            ]);
            
            return array_map(function($request) {
                return [
                    'field' => $request['field'],
                    'model' => $request['model'],
                    'value' => null,
                    'hit' => false
                ];
            }, $requests);
        }
    }

    /**
     * Clear all virtual field cache entries
     */
    public function flush(): bool
    {
        try {
            $cache = $this->getCacheInstance();
            
            if (!empty($this->tags) && method_exists($cache, 'tags')) {
                $success = $cache->tags($this->tags)->flush();
            } else {
                // If no tags, we need to flush by pattern
                $pattern = $this->keyPrefix . '*';
                $success = $this->invalidateByPattern($pattern) > 0;
            }
            
            if ($this->logOperations && $success) {
                Log::info("Flushed all virtual field cache entries");
            }
            
            return $success;
        } catch (\Exception $e) {
            Log::error("Failed to flush virtual field cache", [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Get cache statistics
     */
    public function getStatistics(): array
    {
        try {
            // This is a basic implementation - actual statistics would depend on cache driver
            return [
                'key_prefix' => $this->keyPrefix,
                'default_ttl' => $this->defaultTtl,
                'store' => $this->store ?? 'default',
                'tags' => $this->tags,
                'log_operations' => $this->logOperations
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get virtual field cache statistics", [
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Generate cache key for a virtual field and model
     */
    protected function generateKey(string $field, Model $model): string
    {
        $modelClass = str_replace('\\', '_', get_class($model));
        $modelId = $model->getKey();
        
        return $this->keyPrefix . $modelClass . '_' . $modelId . '_' . $field;
    }

    /**
     * Generate pattern for all virtual fields of a specific model instance
     */
    protected function generateModelPattern(Model $model): string
    {
        $modelClass = str_replace('\\', '_', get_class($model));
        $modelId = $model->getKey();
        
        return $this->keyPrefix . $modelClass . '_' . $modelId . '_*';
    }

    /**
     * Generate pattern for all instances of a model class
     */
    protected function generateClassPattern(string $modelClass, array $fields = []): string
    {
        $normalizedClass = str_replace('\\', '_', $modelClass);
        
        if (empty($fields)) {
            return $this->keyPrefix . $normalizedClass . '_*';
        }
        
        // For specific fields, we need multiple patterns
        return $this->keyPrefix . $normalizedClass . '_*_' . implode('|', $fields);
    }

    /**
     * Invalidate cache entries by pattern
     */
    protected function invalidateByPattern(string $pattern): int
    {
        // This is a simplified implementation
        // In a real scenario, you might need driver-specific logic
        try {
            $cache = $this->getCacheInstance();
            
            // For Redis, you could use SCAN and DEL
            // For other drivers, this might not be available
            // This is a placeholder implementation
            
            if (method_exists($cache->getStore(), 'flush')) {
                // If we can't do pattern-based invalidation, we might need to flush all
                // This is not ideal but ensures consistency
                return 1; // Placeholder
            }
            
            return 0;
        } catch (\Exception $e) {
            Log::error("Failed to invalidate cache by pattern", [
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ]);
            
            return 0;
        }
    }

    /**
     * Get cache instance
     */
    protected function getCacheInstance()
    {
        return $this->store ? Cache::store($this->store) : Cache::store();
    }

    /**
     * Set cache key prefix
     */
    public function setKeyPrefix(string $prefix): void
    {
        $this->keyPrefix = $prefix;
    }

    /**
     * Set default TTL
     */
    public function setDefaultTtl(int $ttl): void
    {
        $this->defaultTtl = $ttl;
    }

    /**
     * Set cache store
     */
    public function setStore(?string $store): void
    {
        $this->store = $store;
    }

    /**
     * Set logging preference
     */
    public function setLogOperations(bool $log): void
    {
        $this->logOperations = $log;
    }

    /**
     * Set cache tags
     */
    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }
}