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