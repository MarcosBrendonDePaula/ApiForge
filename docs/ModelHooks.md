# Model Hooks Documentation

## Overview

Model Hooks provide a powerful way to execute custom logic at specific points during CRUD operations and API requests. They allow you to implement cross-cutting concerns like authorization, validation, auditing, caching, and notifications in a clean, organized manner.

## Available Hook Types

### Authorization Hooks

#### `beforeAuthorization`
Executed before checking if the user is authorized to perform an action.

**Context Data:**
- `action`: The action being performed (store, update, delete, show, index)

**Return Value:**
- `true`: Allow the action to proceed
- `false`: Deny authorization (returns 403 response)

#### `afterAuthorization`
Executed after authorization check is complete.

**Context Data:**
- `action`: The action being performed
- `authorized`: Whether authorization was granted

### Validation Hooks

#### `beforeValidation`
Executed before request data validation.

**Context Data:**
- Request data array

**Return Value:**
- Modified data array (optional)

#### `afterValidation`
Executed after successful validation.

**Context Data:**
- Validated data array

### Transformation Hooks

#### `beforeTransform`
Executed before applying data transformations.

**Context Data:**
- Data to be transformed

**Return Value:**
- Modified data array (optional)

#### `afterTransform`
Executed after data transformations are applied.

**Context Data:**
- Transformed data array

### CRUD Hooks

#### `beforeStore`
Executed before creating a new model instance.

**Context Data:**
- Model instance (not yet saved)
- Request object

#### `afterStore`
Executed after successfully creating a model instance.

**Context Data:**
- Created model instance
- Request object

#### `beforeUpdate`
Executed before updating a model instance.

**Context Data:**
- `original`: Original model data
- `updated`: New data to be applied
- `changes`: Array of changed fields

#### `afterUpdate`
Executed after successfully updating a model instance.

**Context Data:**
- `original`: Original model data
- `updated`: Updated model data
- `changes`: Array of actual changes made

#### `beforeDelete`
Executed before deleting a model instance.

**Context Data:**
- Model instance to be deleted
- Request object

**Return Value:**
- `true`: Allow deletion to proceed
- `false`: Prevent deletion (returns 422 response)

#### `afterDelete`
Executed after successfully deleting a model instance.

**Context Data:**
- Deleted model data
- Request object

### Query Hooks

#### `beforeQuery`
Executed before running database queries (index, show operations).

**Context Data:**
- `query`: Query builder instance (for modification)

#### `afterQuery`
Executed after database queries are completed.

**Context Data:**
- `results`: Query results

### Audit Hooks

#### `beforeAudit`
Executed before logging audit information.

**Context Data:**
- `changes`: Array of changes being audited
- `action`: Action being audited

#### `afterAudit`
Executed after audit logging is complete.

**Context Data:**
- Audit log data that was recorded

### Cache Hooks

#### `beforeCache`
Executed before cache operations.

**Context Data:**
- `query_params`: Request parameters for cache key generation

**Return Value:**
- Cache configuration data (optional)

#### `afterCache`
Executed after cache operations.

**Context Data:**
- `cached`: Whether data was cached
- `result_count`: Number of results processed

### Notification Hooks

#### `beforeNotification`
Executed before sending notifications.

**Context Data:**
- `action`: Action that triggered the notification
- `model`: Model instance related to the notification

**Return Value:**
- Modified notification data (optional)

#### `afterNotification`
Executed after notifications are sent.

**Context Data:**
- Notification result data

### Response Hooks

#### `beforeResponse`
Executed before sending the API response.

**Context Data:**
- Response data array

**Return Value:**
- Modified response data (optional)

#### `afterResponse`
Executed after the API response is sent.

**Context Data:**
- Final response data

## Hook Configuration Options

When registering hooks, you can specify additional options:

```php
$this->registerHook('beforeStore', 'myHook', $callback, [
    'priority' => 10,           // Lower numbers execute first (default: 10)
    'stopOnFailure' => true,    // Stop execution if this hook fails (default: false)
    'conditions' => [           // Conditional execution (default: [])
        'field' => 'type',
        'operator' => 'eq',
        'value' => 'premium'
    ],
    'description' => 'My custom hook description'
]);
```

### Priority System

Hooks with lower priority numbers execute first:
- Priority 1: Critical hooks (authorization, validation)
- Priority 5: Data transformation hooks
- Priority 10: Business logic hooks (default)
- Priority 15: Notification hooks
- Priority 20: Cache and cleanup hooks

### Conditional Execution

Hooks can be configured to execute only when certain conditions are met:

```php
'conditions' => [
    'field' => 'status',        // Model field to check
    'operator' => 'eq',         // Comparison operator
    'value' => 'active'         // Value to compare against
]
```

Supported operators:
- `eq`: Equal
- `ne`: Not equal
- `gt`: Greater than
- `gte`: Greater than or equal
- `lt`: Less than
- `lte`: Less than or equal
- `in`: In array
- `not_in`: Not in array

## Hook Context

The `HookContext` object provides access to:

```php
$context->model;        // The model instance
$context->request;      // The HTTP request
$context->data;         // Hook-specific data
$context->operation;    // The operation being performed

// Helper methods
$context->get('key', $default);    // Get context data
$context->set('key', $value);      // Set context data
$context->has('key');              // Check if key exists
```

## Error Handling

### Exception Handling

Hooks can throw exceptions to stop execution:

```php
'beforeStore' => [
    'validateBusinessRules' => function($model, $context) {
        if ($model->type === 'premium' && !$model->user->isPremium()) {
            throw new ValidationException('User must be premium for this type');
        }
    }
]
```

### Graceful Failures

Use `stopOnFailure` to control whether hook failures stop execution:

```php
'afterStore' => [
    'sendEmail' => [
        'callback' => function($model, $context) {
            // This might fail, but shouldn't stop the operation
            MailService::send($model->email, 'welcome');
        },
        'stopOnFailure' => false  // Continue even if email fails
    ]
]
```

## Best Practices

### 1. Use Appropriate Hook Types

Choose the right hook for your use case:
- **Authorization**: Use `beforeAuthorization`
- **Data validation**: Use `beforeValidation` and `afterValidation`
- **Data transformation**: Use `beforeTransform` and `afterTransform`
- **Business logic**: Use CRUD hooks (`beforeStore`, `afterUpdate`, etc.)
- **Logging/Auditing**: Use audit hooks
- **Notifications**: Use notification hooks
- **Performance**: Use cache and query hooks

### 2. Set Proper Priorities

Order hooks by importance:
```php
'beforeStore' => [
    'authorize' => ['callback' => $authCallback, 'priority' => 1],
    'validate' => ['callback' => $validateCallback, 'priority' => 5],
    'transform' => ['callback' => $transformCallback, 'priority' => 10],
    'notify' => ['callback' => $notifyCallback, 'priority' => 15]
]
```

### 3. Handle Failures Gracefully

Use `stopOnFailure` appropriately:
```php
// Critical hooks should stop on failure
'beforeStore' => [
    'authorize' => [
        'callback' => $authCallback,
        'stopOnFailure' => true
    ]
],

// Optional hooks should not stop on failure
'afterStore' => [
    'sendEmail' => [
        'callback' => $emailCallback,
        'stopOnFailure' => false
    ]
]
```

### 4. Use Conditional Execution

Avoid unnecessary hook execution:
```php
'afterStore' => [
    'sendPremiumWelcome' => [
        'callback' => $premiumWelcomeCallback,
        'conditions' => [
            'field' => 'type',
            'operator' => 'eq',
            'value' => 'premium'
        ]
    ]
]
```

### 5. Keep Hooks Focused

Each hook should have a single responsibility:
```php
// Good: Focused hooks
'afterStore' => [
    'sendWelcomeEmail' => $sendEmailCallback,
    'createUserProfile' => $createProfileCallback,
    'logUserCreation' => $logCallback
],

// Bad: One hook doing everything
'afterStore' => [
    'doEverything' => function($model, $context) {
        // Send email
        // Create profile
        // Log creation
        // Update cache
        // etc...
    }
]
```

## Performance Considerations

### 1. Minimize Database Queries

Use eager loading in query hooks:
```php
'beforeQuery' => [
    'eagerLoad' => function($model, $context) {
        $query = $context->data['query'];
        $query->with(['profile', 'roles', 'permissions']);
    }
]
```

### 2. Use Caching Effectively

Implement smart caching:
```php
'beforeCache' => [
    'generateCacheKey' => function($model, $context) {
        $params = $context->data['query_params'];
        return ['cache_key' => 'users_' . md5(serialize($params))];
    }
],

'afterStore' => [
    'invalidateCache' => function($model, $context) {
        Cache::tags(['users'])->flush();
    }
]
```

### 3. Defer Heavy Operations

Use queues for heavy operations:
```php
'afterStore' => [
    'processImage' => [
        'callback' => function($model, $context) {
            // Queue heavy image processing instead of doing it synchronously
            ProcessUserImageJob::dispatch($model);
        },
        'stopOnFailure' => false
    ]
]
```

## Testing Hooks

### Unit Testing

Test hooks in isolation:
```php
public function test_before_store_hook_sets_defaults()
{
    $user = new User();
    $request = new Request();
    $context = new HookContext($user, $request);
    
    $hook = new SetDefaultsHook();
    $hook->execute($context);
    
    $this->assertEquals('user', $user->role);
}
```

### Integration Testing

Test hooks with the full controller:
```php
public function test_user_creation_with_hooks()
{
    $response = $this->postJson('/api/users', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123'
    ]);
    
    $response->assertStatus(201);
    
    // Verify hook effects
    $user = User::where('email', 'john@example.com')->first();
    $this->assertEquals('user', $user->role); // Default set by hook
    $this->assertNotNull($user->username); // Generated by hook
}
```

## Hook Configuration Methods

ApiForge provides several convenience methods to configure common hook patterns quickly and consistently.

### Basic Hook Configuration

#### `configureModelHooks(array $config)`

Configure hooks using a comprehensive array structure:

```php
protected function setupFilterConfiguration(): void
{
    $this->configureModelHooks([
        'beforeStore' => [
            'validateData' => [
                'callback' => function($model, $context) {
                    // Validation logic
                },
                'priority' => 1,
                'stopOnFailure' => true,
                'description' => 'Validate model data'
            ]
        ],
        'afterStore' => [
            'sendNotification' => [
                'callback' => function($model, $context) {
                    // Notification logic
                },
                'priority' => 10
            ]
        ]
    ]);
}
```

#### `registerHook(string $hookType, string $hookName, callable $callback, array $options = [])`

Register individual hooks:

```php
$this->registerHook('beforeStore', 'generateSlug', function($model, $context) {
    if (empty($model->slug) && !empty($model->title)) {
        $model->slug = Str::slug($model->title);
    }
}, ['priority' => 1]);
```

### Convenience Configuration Methods

#### `configureAuditHooks(array $options = [])`

Automatically configure comprehensive audit logging:

```php
$this->configureAuditHooks([
    'fields' => ['name', 'email', 'status'],  // Fields to track (empty = all)
    'track_user' => true,                     // Track who made changes
    'audit_table' => 'audit_logs'             // Table to store audit logs
]);
```

**Generated Hooks:**
- `beforeUpdate`: Tracks field changes
- `afterUpdate`: Saves audit log for updates
- `afterStore`: Logs creation
- `beforeDelete`: Logs deletion

#### `configureValidationHooks(array $rules, array $options = [])`

Configure validation hooks with Laravel validation rules:

```php
$this->configureValidationHooks([
    'name' => 'required|string|max:255',
    'email' => 'required|email|unique:users,email',
    'age' => 'integer|min:18'
], [
    'stop_on_failure' => true,
    'messages' => [
        'email.unique' => 'This email is already registered',
        'age.min' => 'Must be at least 18 years old'
    ]
]);
```

**Generated Hooks:**
- `beforeStore`: Validates data before creation
- `beforeUpdate`: Validates data before updates

#### `configureNotificationHooks(array $config)`

Configure notification hooks for CRUD operations:

```php
$this->configureNotificationHooks([
    'onCreate' => [
        'notification' => \App\Notifications\UserCreated::class,
        'recipients' => ['admin', 'current_user'],
        'channels' => ['mail', 'database'],
        'priority' => 10
    ],
    'onUpdate' => [
        'notification' => \App\Notifications\UserUpdated::class,
        'recipients' => ['owner', 'admin'],
        'channels' => ['database'],
        'watch_fields' => ['status', 'role'],  // Only notify on these changes
        'priority' => 10
    ],
    'onDelete' => [
        'notification' => \App\Notifications\UserDeleted::class,
        'recipients' => ['admin'],
        'channels' => ['mail'],
        'priority' => 5
    ]
]);
```

**Recipient Types:**
- `'admin'`: Users with admin role
- `'current_user'`: Currently authenticated user
- `'owner'`: Model owner (requires `user()` relationship)
- `'manager'`: Custom manager resolution
- `function($model, $context) { ... }`: Custom callback
- `123`: Specific user ID
- `'user@example.com'`: Specific email address

#### `configureCacheInvalidationHooks(array $cacheKeys, array $options = [])`

Configure automatic cache invalidation:

```php
$this->configureCacheInvalidationHooks([
    'user_list',
    'user_stats',
    'user_permissions_{model_id}',
    'user_profile_{model_id}',
    'category_users_{category_id}'
], ['priority' => 20]);
```

**Cache Key Placeholders:**
- `{model_id}`: Model's primary key
- `{model_class}`: Lowercase model class name
- `{user_id}`: Current user ID
- `{field_name}`: Any model attribute

**Generated Hooks:**
- `afterStore`: Invalidates cache after creation
- `afterUpdate`: Invalidates cache after updates
- `afterDelete`: Invalidates cache after deletion

#### `configureSlugHooks(string $sourceField, string $slugField = 'slug', array $options = [])`

Configure automatic slug generation:

```php
$this->configureSlugHooks('title', 'slug', [
    'unique' => true,           // Ensure slug uniqueness
    'overwrite' => false,       // Don't overwrite existing slugs
    'separator' => '-'          // Slug separator
]);
```

**Generated Hooks:**
- `beforeStore`: Generates slug on creation
- `beforeUpdate`: Updates slug when source field changes

#### `configurePermissionHooks(array $permissions, array $options = [])`

Configure permission checking hooks:

```php
$this->configurePermissionHooks([
    'create' => 'create-users',
    'update' => function($user, $model, $action) {
        // Custom permission logic
        return $user->id === $model->id || $user->can('update-users');
    },
    'delete' => ['delete-users', 'admin-access']  // Multiple permissions (all required)
], [
    'throw_on_failure' => true  // Throw exception vs return false
]);
```

**Permission Types:**
- `string`: Simple permission name
- `array`: Multiple permissions (all must pass)
- `callable`: Custom permission callback

**Generated Hooks:**
- `beforeStore`: Checks create permissions
- `beforeUpdate`: Checks update permissions
- `beforeDelete`: Checks delete permissions

## Advanced Hook Patterns

### Conditional Hook Execution

Execute hooks only when specific conditions are met:

```php
$this->configureModelHooks([
    'afterStore' => [
        'sendPremiumWelcome' => [
            'callback' => function($model, $context) {
                // Send premium welcome email
            },
            'conditions' => [
                'field' => 'subscription_type',
                'operator' => 'eq',
                'value' => 'premium'
            ]
        ]
    ]
]);
```

### Hook Chaining and Dependencies

Use context to pass data between hooks:

```php
$this->configureModelHooks([
    'beforeUpdate' => [
        'trackChanges' => [
            'callback' => function($model, $context) {
                $changes = $model->getDirty();
                $context->set('tracked_changes', $changes);
            },
            'priority' => 1
        ]
    ],
    'afterUpdate' => [
        'logChanges' => [
            'callback' => function($model, $context) {
                $changes = $context->get('tracked_changes', []);
                if (!empty($changes)) {
                    AuditLog::create([
                        'model_id' => $model->id,
                        'changes' => $changes
                    ]);
                }
            },
            'priority' => 5
        ]
    ]
]);
```

### Error Handling Patterns

#### Graceful Degradation

```php
$this->configureModelHooks([
    'afterStore' => [
        'sendWelcomeEmail' => [
            'callback' => function($model, $context) {
                try {
                    Mail::to($model->email)->send(new WelcomeEmail($model));
                } catch (\Exception $e) {
                    Log::warning('Failed to send welcome email', [
                        'user_id' => $model->id,
                        'error' => $e->getMessage()
                    ]);
                    // Don't fail the entire operation
                }
            },
            'stopOnFailure' => false
        ]
    ]
]);
```

#### Critical Validation

```php
$this->configureModelHooks([
    'beforeStore' => [
        'validateCriticalRules' => [
            'callback' => function($model, $context) {
                if ($model->type === 'admin' && !auth()->user()->isSuperAdmin()) {
                    throw new AuthorizationException('Only super admins can create admin users');
                }
            },
            'priority' => 1,
            'stopOnFailure' => true
        ]
    ]
]);
```

### Performance Optimization Patterns

#### Batch Operations

```php
$this->configureModelHooks([
    'afterStore' => [
        'updateStatistics' => [
            'callback' => function($model, $context) {
                // Defer statistics update to avoid N+1 queries
                dispatch(new UpdateUserStatisticsJob($model->id))->delay(now()->addMinutes(5));
            },
            'priority' => 20
        ]
    ]
]);
```

#### Conditional Heavy Operations

```php
$this->configureModelHooks([
    'afterUpdate' => [
        'reindexSearch' => [
            'callback' => function($model, $context) {
                // Only reindex if searchable fields changed
                $searchableFields = ['name', 'email', 'bio'];
                $changes = array_keys($model->getDirty());
                
                if (array_intersect($searchableFields, $changes)) {
                    SearchIndexJob::dispatch($model);
                }
            },
            'priority' => 15
        ]
    ]
]);
```

## Hook Debugging and Monitoring

### Getting Hook Metadata

```php
// Get all hooks metadata
$metadata = $this->getHooksMetadata();

// Get specific hook service
$hookService = $this->getHookService();
$hooks = $hookService->getHooks('beforeStore');
```

### Clearing Hooks

```php
// Clear all hooks
$this->clearHooks();

// Clear specific hook type
$this->clearHooks('beforeStore');
```

### Hook Execution Logging

Enable hook execution logging in configuration:

```php
// config/apiforge.php
'hooks' => [
    'log_execution' => env('APIFORGE_LOG_HOOKS', false),
    'throw_on_failure' => env('APIFORGE_THROW_ON_HOOK_FAILURE', true)
]
```

## Migration Guide

### From Basic Hooks

If you're upgrading from basic CRUD hooks:

```php
// Old way
protected function afterStore($model, $request)
{
    // Custom logic here
}

// New way
protected function setupFilterConfiguration(): void
{
    $this->configureModelHooks([
        'afterStore' => [
            'customLogic' => function($model, $context) {
                // Custom logic here
            }
        ]
    ]);
}
```

### Adding New Hook Types

To add new hook types to existing controllers:

```php
protected function setupFilterConfiguration(): void
{
    // Keep existing configuration
    parent::setupFilterConfiguration();
    
    // Add new hooks
    $this->configureModelHooks([
        'beforeAuthorization' => [
            'checkPermissions' => $this->checkPermissionsCallback()
        ],
        'afterAudit' => [
            'logAudit' => $this->logAuditCallback()
        ]
    ]);
}
```

### Using Convenience Methods

Replace manual hook configuration with convenience methods:

```php
// Old way
$this->configureModelHooks([
    'beforeUpdate' => [
        'trackChanges' => function($model, $context) {
            // Manual audit logic
        }
    ],
    'afterUpdate' => [
        'saveAudit' => function($model, $context) {
            // Manual audit saving
        }
    ]
]);

// New way
$this->configureAuditHooks([
    'fields' => ['name', 'email', 'status'],
    'track_user' => true
]);
```