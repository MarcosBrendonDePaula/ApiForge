<?php

namespace MarcosBrendon\ApiForge\Tests\Fixtures;

use Illuminate\Http\Request;
use MarcosBrendon\ApiForge\Http\Controllers\BaseApiController;
use MarcosBrendon\ApiForge\Traits\HasAdvancedFilters;

class TestControllerWithHooks
{
    use HasAdvancedFilters;

    /**
     * Get the model class for this controller
     */
    protected function getModelClass(): string
    {
        return TestModel::class;
    }

    /**
     * Setup filter configuration
     */
    protected function setupFilterConfiguration(): void
    {
        $this->configureFilters([
            'name' => [
                'type' => 'string',
                'operators' => ['eq', 'like'],
                'searchable' => true,
                'sortable' => true
            ],
            'email' => [
                'type' => 'string',
                'operators' => ['eq', 'like'],
                'searchable' => true
            ],
            'active' => [
                'type' => 'boolean',
                'operators' => ['eq']
            ]
        ]);

        $this->configureFieldSelection([
            'selectable_fields' => ['id', 'name', 'email', 'slug', 'active'],
            'required_fields' => ['id'],
            'default_fields' => ['id', 'name', 'email']
        ]);
    }

    /**
     * Expose protected methods for testing by making them public
     */
    public function configureModelHooks(array $config): void
    {
        $this->initializeFilterServices();
        $this->hookService->registerFromConfig($config);
    }

    public function registerHook(string $hookType, string $hookName, callable $callback, array $options = []): void
    {
        $this->initializeFilterServices();
        $this->hookService->register($hookType, $hookName, $callback, $options);
    }

    public function configureAuditHooks(array $options = []): void
    {
        $this->initializeFilterServices();
        
        $auditFields = $options['fields'] ?? [];
        $auditUser = $options['track_user'] ?? true;
        $auditTable = $options['audit_table'] ?? 'audit_logs';
        
        // Before update hook to track changes
        $this->registerHook('beforeUpdate', 'trackChanges', function($model, $context) use ($auditFields, $auditUser, $auditTable) {
            $changes = $model->getDirty();
            
            // Filter to specific fields if configured
            if (!empty($auditFields)) {
                $changes = array_intersect_key($changes, array_flip($auditFields));
            }
            
            if (!empty($changes)) {
                $auditData = [
                    'model_type' => get_class($model),
                    'model_id' => $model->getKey(),
                    'changes' => $changes,
                    'original_values' => $model->getOriginal(),
                    'action' => 'update',
                    'created_at' => now(),
                ];
                
                if ($auditUser && auth()->check()) {
                    $auditData['user_id'] = auth()->id();
                }
                
                // Store in context for after hook
                $context->set('audit_data', $auditData);
            }
        }, ['priority' => 1]);
        
        // After update hook to save audit log
        $this->registerHook('afterUpdate', 'saveAuditLog', function($model, $context) use ($auditTable) {
            $auditData = $context->get('audit_data');
            if ($auditData) {
                \DB::table($auditTable)->insert($auditData);
            }
        }, ['priority' => 1]);
        
        // Store creation audit
        $this->registerHook('afterStore', 'auditCreation', function($model, $context) use ($auditUser, $auditTable) {
            $auditData = [
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'changes' => $model->getAttributes(),
                'original_values' => [],
                'action' => 'create',
                'created_at' => now(),
            ];
            
            if ($auditUser && auth()->check()) {
                $auditData['user_id'] = auth()->id();
            }
            
            \DB::table($auditTable)->insert($auditData);
        }, ['priority' => 1]);
        
        // Store deletion audit
        $this->registerHook('beforeDelete', 'auditDeletion', function($model, $context) use ($auditUser, $auditTable) {
            $auditData = [
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'changes' => [],
                'original_values' => $model->getAttributes(),
                'action' => 'delete',
                'created_at' => now(),
            ];
            
            if ($auditUser && auth()->check()) {
                $auditData['user_id'] = auth()->id();
            }
            
            \DB::table($auditTable)->insert($auditData);
            return true; // Allow deletion
        }, ['priority' => 1]);
    }

    public function configureValidationHooks(array $rules, array $options = []): void
    {
        $this->initializeFilterServices();
        
        $customMessages = $options['messages'] ?? [];
        $stopOnFailure = $options['stop_on_failure'] ?? true;
        
        // Before store validation
        $this->registerHook('beforeStore', 'validateBeforeStore', function($model, $context) use ($rules, $customMessages) {
            $validator = \Validator::make($model->getAttributes(), $rules, $customMessages);
            
            if ($validator->fails()) {
                throw new \Illuminate\Validation\ValidationException($validator);
            }
        }, ['priority' => 1, 'stopOnFailure' => $stopOnFailure]);
        
        // Before update validation
        $this->registerHook('beforeUpdate', 'validateBeforeUpdate', function($model, $context) use ($rules, $customMessages) {
            $data = $context->get('data', []);
            $validator = \Validator::make($data, $rules, $customMessages);
            
            if ($validator->fails()) {
                throw new \Illuminate\Validation\ValidationException($validator);
            }
        }, ['priority' => 1, 'stopOnFailure' => $stopOnFailure]);
    }

    public function configureNotificationHooks(array $config): void
    {
        $this->initializeFilterServices();
        
        // After store notifications
        if (isset($config['onCreate'])) {
            $this->registerHook('afterStore', 'notifyOnCreate', function($model, $context) use ($config) {
                $notificationConfig = $config['onCreate'];
                // Mock notification sending for testing
            }, ['priority' => $config['onCreate']['priority'] ?? 10]);
        }
    }

    public function configureCacheInvalidationHooks(array $cacheKeys, array $options = []): void
    {
        $this->initializeFilterServices();
        
        $priority = $options['priority'] ?? 5;
        
        // Invalidate cache after store
        $this->registerHook('afterStore', 'invalidateCacheOnStore', function($model, $context) use ($cacheKeys) {
            $this->invalidateCacheKeys($model, $cacheKeys);
        }, ['priority' => $priority]);
        
        // Invalidate cache after update
        $this->registerHook('afterUpdate', 'invalidateCacheOnUpdate', function($model, $context) use ($cacheKeys) {
            $this->invalidateCacheKeys($model, $cacheKeys);
        }, ['priority' => $priority]);
        
        // Invalidate cache after delete
        $this->registerHook('afterDelete', 'invalidateCacheOnDelete', function($model, $context) use ($cacheKeys) {
            $this->invalidateCacheKeys($model, $cacheKeys);
        }, ['priority' => $priority]);
    }

    public function configureSlugHooks(string $sourceField, string $slugField = 'slug', array $options = []): void
    {
        $this->initializeFilterServices();
        
        $separator = $options['separator'] ?? '-';
        $unique = $options['unique'] ?? true;
        $overwrite = $options['overwrite'] ?? false;
        
        // Generate slug before store
        $this->registerHook('beforeStore', 'generateSlugOnStore', function($model, $context) use ($sourceField, $slugField, $separator, $unique, $overwrite) {
            if (empty($model->$slugField) || $overwrite) {
                $sourceValue = $model->$sourceField;
                if (!empty($sourceValue)) {
                    $slug = \Str::slug($sourceValue, $separator);
                    
                    if ($unique) {
                        $slug = $this->makeSlugUnique($model, $slugField, $slug);
                    }
                    
                    $model->$slugField = $slug;
                }
            }
        }, ['priority' => 1]);
        
        // Update slug before update if source field changed
        $this->registerHook('beforeUpdate', 'updateSlugOnUpdate', function($model, $context) use ($sourceField, $slugField, $separator, $unique, $overwrite) {
            if ($model->isDirty($sourceField) && ($overwrite || empty($model->$slugField))) {
                $sourceValue = $model->$sourceField;
                if (!empty($sourceValue)) {
                    $slug = \Str::slug($sourceValue, $separator);
                    
                    if ($unique) {
                        $slug = $this->makeSlugUnique($model, $slugField, $slug);
                    }
                    
                    $model->$slugField = $slug;
                }
            }
        }, ['priority' => 1]);
    }

    public function configurePermissionHooks(array $permissions, array $options = []): void
    {
        $this->initializeFilterServices();
        
        $userField = $options['user_field'] ?? 'user_id';
        $throwOnFailure = $options['throw_on_failure'] ?? true;
        
        // Check permissions before store
        if (isset($permissions['create'])) {
            $this->registerHook('beforeStore', 'checkCreatePermission', function($model, $context) use ($permissions, $throwOnFailure) {
                if (!$this->checkPermission($permissions['create'], $model, 'create')) {
                    if ($throwOnFailure) {
                        throw new \Illuminate\Auth\Access\AuthorizationException('Unauthorized to create this resource');
                    }
                    return false;
                }
            }, ['priority' => 1, 'stopOnFailure' => true]);
        }
        
        // Check permissions before update
        if (isset($permissions['update'])) {
            $this->registerHook('beforeUpdate', 'checkUpdatePermission', function($model, $context) use ($permissions, $throwOnFailure) {
                if (!$this->checkPermission($permissions['update'], $model, 'update')) {
                    if ($throwOnFailure) {
                        throw new \Illuminate\Auth\Access\AuthorizationException('Unauthorized to update this resource');
                    }
                    return false;
                }
            }, ['priority' => 1, 'stopOnFailure' => true]);
        }
        
        // Check permissions before delete
        if (isset($permissions['delete'])) {
            $this->registerHook('beforeDelete', 'checkDeletePermission', function($model, $context) use ($permissions, $throwOnFailure) {
                if (!$this->checkPermission($permissions['delete'], $model, 'delete')) {
                    if ($throwOnFailure) {
                        throw new \Illuminate\Auth\Access\AuthorizationException('Unauthorized to delete this resource');
                    }
                    return false;
                }
                return true;
            }, ['priority' => 1, 'stopOnFailure' => true]);
        }
    }

    public function resolveCacheKeyPlaceholders(string $key, $model)
    {
        $replacements = [
            '{model_class}' => strtolower(class_basename(get_class($model))),
            '{model_id}' => $model->getKey(),
            '{user_id}' => auth()->id() ?? 'guest',
        ];
        
        // Add model attributes as placeholders
        foreach ($model->getAttributes() as $attribute => $value) {
            $replacements['{' . $attribute . '}'] = $value;
        }
        
        return str_replace(array_keys($replacements), array_values($replacements), $key);
    }

    public function makeSlugUnique($model, string $slugField, string $slug): string
    {
        $originalSlug = $slug;
        $counter = 1;
        
        $modelClass = get_class($model);
        
        while (true) {
            $query = $modelClass::where($slugField, $slug);
            
            // Exclude current model if updating
            if ($model->exists) {
                $query->where($model->getKeyName(), '!=', $model->getKey());
            }
            
            if (!$query->exists()) {
                break;
            }
            
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    public function invalidateCacheKeys($model, array $cacheKeys): void
    {
        foreach ($cacheKeys as $key) {
            // Replace placeholders in cache key
            $resolvedKey = $this->resolveCacheKeyPlaceholders($key, $model);
            
            if (is_array($resolvedKey)) {
                // Handle multiple keys (e.g., when using wildcards)
                foreach ($resolvedKey as $k) {
                    \Cache::forget($k);
                }
            } else {
                \Cache::forget($resolvedKey);
            }
        }
        
        // Also clear cache tags if using tagged cache
        $modelClass = get_class($model);
        $tags = [
            strtolower(class_basename($modelClass)),
            $modelClass . '_' . $model->getKey()
        ];
        
        try {
            \Cache::tags($tags)->flush();
        } catch (\Exception $e) {
            // Ignore if cache driver doesn't support tags
        }
    }

    public function resolveNotificationRecipients(array $recipients, $model, $context): array
    {
        $resolved = [];
        
        foreach ($recipients as $recipient) {
            if (is_string($recipient)) {
                // Handle string recipients (user IDs, emails, etc.)
                if (is_numeric($recipient)) {
                    $user = \App\Models\User::find($recipient);
                    if ($user) {
                        $resolved[] = $user;
                    }
                } elseif (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    // Handle email addresses
                    $user = \App\Models\User::where('email', $recipient)->first();
                    if ($user) {
                        $resolved[] = $user;
                    }
                } elseif ($recipient === 'owner' && method_exists($model, 'user')) {
                    // Handle model owner
                    $owner = $model->user;
                    if ($owner) {
                        $resolved[] = $owner;
                    }
                } elseif ($recipient === 'current_user' && auth()->check()) {
                    $resolved[] = auth()->user();
                }
            } elseif (is_callable($recipient)) {
                // Handle callable recipients
                $result = $recipient($model, $context);
                if ($result) {
                    $resolved = array_merge($resolved, is_array($result) ? $result : [$result]);
                }
            }
        }
        
        return $resolved;
    }

    public function checkPermission($permission, $model, string $action): bool
    {
        if (!auth()->check()) {
            return false;
        }
        
        $user = auth()->user();
        
        if (is_string($permission)) {
            // Simple permission string
            return $user->can($permission, $model);
        } elseif (is_array($permission)) {
            // Array of permissions (all must pass)
            foreach ($permission as $perm) {
                if (!$user->can($perm, $model)) {
                    return false;
                }
            }
            return true;
        } elseif (is_callable($permission)) {
            // Custom permission callback
            return $permission($user, $model, $action);
        }
        
        return false;
    }
}