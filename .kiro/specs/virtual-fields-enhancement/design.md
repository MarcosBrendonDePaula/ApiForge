# Virtual Fields & Model Hooks Enhancement Design Document

## Overview

This enhancement extends ApiForge with two powerful features:

1. **Virtual Fields**: Computed fields that don't exist in the database but can be filtered, sorted, and selected like regular fields
2. **Model Hooks**: Lifecycle callbacks (beforeStore, afterStore, beforeDelete, afterDelete, etc.) that execute custom logic during CRUD operations

Both features integrate seamlessly with existing ApiForge functionality while providing developers with powerful tools for custom business logic and data presentation.

## Architecture

### Core Components

```
VirtualFieldService
├── VirtualFieldRegistry (manages field definitions)
├── VirtualFieldProcessor (handles computation)
├── VirtualFieldCache (performance optimization)
└── VirtualFieldValidator (configuration validation)

ModelHookService
├── HookRegistry (manages hook definitions)
├── HookExecutor (executes hooks at appropriate times)
├── HookValidator (validates hook configurations)
└── HookContext (provides context data to hooks)

FilterConfigService (Enhanced)
├── Virtual field configuration support
├── Dependency resolution
└── Hook integration

ApiFilterService (Enhanced)
├── Virtual field filtering logic
├── Query optimization for virtual fields
└── Hook execution during filtering
```

### Integration Points

- **HasAdvancedFilters Trait**: Extended to support virtual fields and model hooks
- **FilterConfigService**: Enhanced to handle virtual field metadata and hook configurations
- **ApiFilterService**: Modified to process virtual field filters and execute hooks
- **BaseApiController**: Updated with virtual field and hook helper methods
- **CRUD Operations**: All create, update, delete operations enhanced with hook execution

## Components and Interfaces

### VirtualFieldService

```php
class VirtualFieldService
{
    public function register(string $field, array $config): void
    public function compute(string $field, $model, array $context = []): mixed
    public function computeBatch(string $field, Collection $models): array
    public function getDependencies(string $field): array
    public function isVirtualField(string $field): bool
    public function getVirtualFields(): array
}
```

### ModelHookService

```php
class ModelHookService
{
    public function register(string $hook, callable $callback): void
    public function execute(string $hook, $model, array $context = []): mixed
    public function hasHook(string $hook): bool
    public function getHooks(): array
    public function executeBeforeStore($model, Request $request): void
    public function executeAfterStore($model, Request $request): void
    public function executeBeforeUpdate($model, Request $request, array $data): void
    public function executeAfterUpdate($model, Request $request, array $data): void
    public function executeBeforeDelete($model, Request $request): bool
    public function executeAfterDelete($model, Request $request): void
}
```

### VirtualFieldRegistry

```php
class VirtualFieldRegistry
{
    public function add(string $field, VirtualFieldDefinition $definition): void
    public function get(string $field): ?VirtualFieldDefinition
    public function has(string $field): bool
    public function all(): array
    public function getByType(string $type): array
}
```

### VirtualFieldDefinition

```php
class VirtualFieldDefinition
{
    public string $name;
    public string $type;
    public callable $callback;
    public array $dependencies;
    public array $operators;
    public array $relationships;
    public bool $cacheable;
    public int $cacheTtl;
    public mixed $defaultValue;
    public bool $nullable;
}
```

### ModelHookDefinition

```php
class ModelHookDefinition
{
    public string $name;
    public callable $callback;
    public int $priority;
    public bool $stopOnFailure;
    public array $conditions;
    public string $description;
}
```

### HookContext

```php
class HookContext
{
    public $model;
    public Request $request;
    public array $data;
    public string $operation;
    public array $metadata;
    
    public function __construct($model, Request $request, array $data = [], string $operation = '')
    public function get(string $key, $default = null): mixed
    public function set(string $key, $value): void
    public function has(string $key): bool
}
```

### VirtualFieldProcessor

```php
class VirtualFieldProcessor
{
    public function processForFiltering(Builder $query, string $field, mixed $value, string $operator): void
    public function processForSelection(Collection $models, array $virtualFields): Collection
    public function processForSorting(Builder $query, string $field, string $direction): void
    public function optimizeQuery(Builder $query, array $virtualFields): Builder
}
```

## Data Models

### Virtual Field Configuration Structure

```php
[
    'full_name' => [
        'type' => 'string',
        'callback' => function($model) {
            return trim($model->first_name . ' ' . $model->last_name);
        },
        'dependencies' => ['first_name', 'last_name'],
        'operators' => ['eq', 'like', 'ne'],
        'cacheable' => true,
        'cache_ttl' => 3600,
        'description' => 'User full name',
        'sortable' => true,
        'searchable' => true
    ],
    
    'total_orders_value' => [
        'type' => 'float',
        'callback' => function($model) {
            return $model->orders->sum('total');
        },
        'dependencies' => [],
        'relationships' => ['orders'],
        'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
        'cacheable' => true,
        'cache_ttl' => 1800,
        'default_value' => 0.0,
        'description' => 'Total value of all user orders'
    ],
    
    'age' => [
        'type' => 'integer',
        'callback' => function($model) {
            return $model->birth_date ? 
                Carbon::parse($model->birth_date)->age : null;
        },
        'dependencies' => ['birth_date'],
        'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
        'nullable' => true,
        'description' => 'Calculated age from birth date'
    ]
]
```

### Enhanced Filter Configuration

```php
// In controller setupFilterConfiguration()
$this->configureVirtualFields([
    'full_name' => [
        'type' => 'string',
        'callback' => [$this, 'calculateFullName'],
        'dependencies' => ['first_name', 'last_name'],
        'operators' => ['eq', 'like', 'ne'],
        'searchable' => true,
        'sortable' => true
    ]
]);
```

## Error Handling

### Exception Hierarchy

```php
VirtualFieldException (base)
├── VirtualFieldConfigurationException
├── VirtualFieldComputationException  
├── VirtualFieldDependencyException
└── VirtualFieldCacheException
```

### Error Scenarios

1. **Configuration Errors**: Invalid callback, missing dependencies, unsupported operators
2. **Computation Errors**: Callback exceptions, missing relationship data, type conversion failures
3. **Performance Errors**: Memory limits exceeded, computation timeouts
4. **Cache Errors**: Cache storage failures, serialization issues

### Error Handling Strategy

- **Development Mode**: Throw exceptions immediately for quick debugging
- **Production Mode**: Log errors and return default values or exclude records
- **Graceful Degradation**: Continue processing other fields when one virtual field fails
- **User Feedback**: Provide meaningful error messages for invalid virtual field usage

## Testing Strategy

### Unit Tests

- **VirtualFieldService**: Field registration, computation, dependency resolution
- **VirtualFieldProcessor**: Filtering logic, sorting, field selection
- **VirtualFieldCache**: Cache operations, TTL handling, invalidation
- **Configuration Validation**: Invalid configurations, edge cases

### Integration Tests

- **Controller Integration**: Virtual fields with existing ApiForge features
- **Database Integration**: Query optimization, relationship loading
- **Performance Tests**: Large datasets, complex computations, memory usage
- **Cache Integration**: Redis/database cache backends

### Feature Tests

- **API Endpoints**: Complete request/response cycles with virtual fields
- **Filter Combinations**: Virtual + regular fields, complex expressions
- **Field Selection**: Mixed virtual and database fields
- **Pagination**: Virtual field filtering with pagination

### Performance Tests

- **Benchmark Tests**: Virtual field computation performance
- **Memory Tests**: Large dataset processing
- **Cache Effectiveness**: Hit rates, performance improvements
- **Query Optimization**: N+1 prevention, efficient joins

### Advanced Hook Examples

```php
// Conditional hooks
'beforeStore' => [
    'validatePremiumFeatures' => [
        'callback' => function($model, $context) {
            // Validation logic
        },
        'conditions' => [
            'field' => 'type',
            'operator' => 'eq',
            'value' => 'premium'
        ]
    ]
],

// Priority-based hooks (lower number = higher priority)
'afterStore' => [
    'criticalNotification' => [
        'callback' => function($model, $context) {
            // Critical notification
        },
        'priority' => 1
    ],
    'regularNotification' => [
        'callback' => function($model, $context) {
            // Regular notification
        },
        'priority' => 10
    ]
],

// Hooks that can stop execution
'beforeDelete' => [
    'checkPermissions' => [
        'callback' => function($model, $context) {
            if (!auth()->user()->canDelete($model)) {
                throw new UnauthorizedException('Cannot delete this resource');
            }
            return true;
        },
        'stopOnFailure' => true
    ]
]
```

## Implementation Phases

### Phase 1: Model Hooks Infrastructure
- ModelHookService and registry implementation
- Basic hook definition and registration
- Hook execution in BaseApiController CRUD operations
- beforeStore, afterStore, beforeUpdate, afterUpdate hooks

### Phase 2: Advanced Hook Features
- beforeDelete, afterDelete hooks with validation
- Hook priorities and conditional execution
- Hook context and metadata passing
- Error handling and rollback mechanisms

### Phase 3: Virtual Fields Core
- VirtualFieldService and registry implementation
- Basic virtual field definition and registration
- Simple computation without caching

### Phase 4: Virtual Fields Integration
- Extend ApiFilterService for virtual field filtering
- Implement virtual field operators
- Query optimization for virtual field filters

### Phase 5: Field Selection & Sorting
- Virtual field selection in API responses
- Sorting by virtual fields
- Dependency resolution and eager loading

### Phase 6: Performance Optimization
- Caching layer for virtual fields
- Batch processing for multiple records
- Memory and time limit handling
- Hook performance monitoring

## Configuration Examples

### Model Hooks Configuration

```php
protected function setupFilterConfiguration(): void
{
    $this->configureFilters([
        // Regular fields...
    ]);
    
    // Configure model lifecycle hooks
    $this->configureModelHooks([
        'beforeStore' => [
            'generateSlug' => function($model, $context) {
                if (empty($model->slug) && !empty($model->title)) {
                    $model->slug = Str::slug($model->title);
                }
            },
            'validateBusinessRules' => function($model, $context) {
                if ($model->type === 'premium' && !$model->user->isPremium()) {
                    throw new ValidationException('User must be premium for this type');
                }
            }
        ],
        
        'afterStore' => [
            'sendNotification' => function($model, $context) {
                // Send notification after creating
                NotificationService::send($model->user, 'ItemCreated', $model);
            },
            'updateCache' => function($model, $context) {
                Cache::forget("user_items_{$model->user_id}");
            }
        ],
        
        'beforeUpdate' => [
            'trackChanges' => function($model, $context) {
                $changes = $model->getDirty();
                if (!empty($changes)) {
                    AuditLog::create([
                        'model_type' => get_class($model),
                        'model_id' => $model->id,
                        'changes' => $changes,
                        'user_id' => auth()->id()
                    ]);
                }
            }
        ],
        
        'afterUpdate' => [
            'syncRelatedData' => function($model, $context) {
                if ($model->wasChanged('status')) {
                    $model->relatedItems()->update(['parent_status' => $model->status]);
                }
            }
        ],
        
        'beforeDelete' => [
            'checkDependencies' => function($model, $context) {
                if ($model->orders()->exists()) {
                    throw new ValidationException('Cannot delete user with existing orders');
                }
                return true; // Allow deletion
            }
        ],
        
        'afterDelete' => [
            'cleanupFiles' => function($model, $context) {
                if ($model->avatar) {
                    Storage::delete($model->avatar);
                }
            },
            'notifyAdmins' => function($model, $context) {
                AdminNotification::send('User deleted: ' . $model->email);
            }
        ]
    ]);
}
```

### Basic Virtual Field

```php
protected function setupFilterConfiguration(): void
{
    $this->configureFilters([
        // Regular fields...
    ]);
    
    $this->configureVirtualFields([
        'display_name' => [
            'type' => 'string',
            'callback' => function($user) {
                return $user->name ?: $user->email;
            },
            'dependencies' => ['name', 'email'],
            'operators' => ['eq', 'like'],
            'searchable' => true
        ]
    ]);
}
```

### Relationship-Based Virtual Field

```php
'order_count' => [
    'type' => 'integer',
    'callback' => function($user) {
        return $user->orders_count ?? $user->orders->count();
    },
    'relationships' => ['orders'],
    'operators' => ['eq', 'gt', 'gte', 'lt', 'lte'],
    'cacheable' => true,
    'cache_ttl' => 1800
]
```

### Complex Business Logic Virtual Field

```php
'customer_tier' => [
    'type' => 'enum',
    'values' => ['bronze', 'silver', 'gold', 'platinum'],
    'callback' => function($user) {
        $totalSpent = $user->orders->sum('total');
        if ($totalSpent >= 10000) return 'platinum';
        if ($totalSpent >= 5000) return 'gold';
        if ($totalSpent >= 1000) return 'silver';
        return 'bronze';
    },
    'relationships' => ['orders'],
    'operators' => ['eq', 'in', 'ne'],
    'cacheable' => true,
    'cache_ttl' => 3600
]
```

## Performance Considerations

### Caching Strategy
- **Field-level caching**: Cache individual virtual field values
- **Model-level caching**: Cache all virtual fields for a model instance
- **Query-level caching**: Cache filtered results including virtual fields
- **Invalidation**: Smart cache invalidation based on dependencies

### Query Optimization
- **Dependency analysis**: Only load required database fields
- **Relationship optimization**: Eager load relationships used by virtual fields
- **Batch processing**: Compute virtual fields for multiple records efficiently
- **Lazy evaluation**: Only compute virtual fields when actually needed

### Memory Management
- **Streaming processing**: Handle large datasets without loading all into memory
- **Garbage collection**: Clean up computed values after processing
- **Memory limits**: Configurable limits with graceful degradation
- **Chunked processing**: Process large result sets in chunks