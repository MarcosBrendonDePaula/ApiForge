# ApiForge

[![Latest Version on Packagist](https://img.shields.io/packagist/v/marcosbrendon/apiforge.svg?style=flat-square)](https://packagist.org/packages/marcosbrendon/apiforge)
[![Total Downloads](https://img.shields.io/packagist/dt/marcosbrendon/apiforge.svg?style=flat-square)](https://packagist.org/packages/marcosbrendon/apiforge)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/MarcosBrendonDePaula/ApiForge/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/MarcosBrendonDePaula/ApiForge/actions)
[![PHP Version Require](https://img.shields.io/packagist/php-v/marcosbrendon/apiforge?style=flat-square)](https://packagist.org/packages/marcosbrendon/apiforge)
[![Laravel Version](https://img.shields.io/badge/Laravel-10%2B%20%7C%2011%2B%20%7C%2012%2B-red?style=flat-square&logo=laravel)](https://laravel.com)
[![GitHub License](https://img.shields.io/github/license/MarcosBrendonDePaula/ApiForge?style=flat-square)](https://github.com/MarcosBrendonDePaula/ApiForge/blob/main/LICENSE.md)

**Forge powerful APIs with advanced filtering, pagination, and field selection capabilities for Laravel applications.** Build sophisticated APIs with minimal configuration and maximum performance.

## ✨ Features

- 🔍 **Advanced Filtering** - 15+ operators (eq, like, gte, between, in, etc.)
- 📄 **Smart Pagination** - Automatic pagination with metadata
- 🎯 **Field Selection** - Optimize queries by selecting only needed fields
- 🔗 **Relationship Support** - Filter and select fields from relationships
- ✨ **Virtual Fields** - Computed fields that can be filtered, sorted, and selected
- 🪝 **Model Hooks** - Lifecycle callbacks for CRUD operations (beforeStore, afterStore, etc.)
- 🛡️ **Security First** - Built-in SQL injection protection and validation
- 📊 **Auto Documentation** - Generate filter metadata and examples automatically
- ⚡ **Performance Focused** - Query optimization and caching support
- 🎨 **Developer Friendly** - Simple configuration, extensive customization

## 🚀 Quick Start

### Installation

```bash
composer require marcosbrendon/apiforge
```

### Basic Usage

1. **Add the trait to your controller:**

```php
use MarcosBrendon\\ApiForge\\Traits\\HasAdvancedFilters;

class UserController extends Controller
{
    use HasAdvancedFilters;
    
    protected function getModelClass(): string
    {
        return User::class;
    }
    
    public function index(Request $request)
    {
        return $this->indexWithFilters($request);
    }
}
```

2. **Configure your filters:**

```php
protected function setupFilterConfiguration(): void
{
    $this->configureFilters([
        'name' => [
            'type' => 'string',
            'operators' => ['eq', 'like', 'ne'],
            'searchable' => true,
            'sortable' => true
        ],
        'email' => [
            'type' => 'string', 
            'operators' => ['eq', 'like'],
            'searchable' => true
        ],
        'created_at' => [
            'type' => 'datetime',
            'operators' => ['gte', 'lte', 'between'],
            'sortable' => true
        ]
    ]);
}
```

3. **Configure virtual fields and hooks:**

```php
protected function setupFilterConfiguration(): void
{
    // Configure regular filters
    $this->configureFilters([
        'name' => ['type' => 'string', 'operators' => ['eq', 'like']],
        'email' => ['type' => 'string', 'operators' => ['eq', 'like']]
    ]);
    
    // Configure virtual fields
    $this->configureVirtualFields([
        'full_name' => [
            'type' => 'string',
            'callback' => fn($user) => trim($user->first_name . ' ' . $user->last_name),
            'dependencies' => ['first_name', 'last_name'],
            'operators' => ['eq', 'like'],
            'searchable' => true,
            'sortable' => true
        ]
    ]);
    
    // Configure model hooks
    $this->configureModelHooks([
        'beforeStore' => [
            'generateSlug' => fn($model) => $model->slug = Str::slug($model->title)
        ],
        'afterStore' => [
            'sendNotification' => fn($model) => NotificationService::send($model)
        ]
    ]);
}
```

4. **Use the API:**

```bash
# Basic filtering
GET /api/users?name=John&email=*@gmail.com

# Virtual field filtering
GET /api/users?full_name=John*&fields=id,full_name

# Field selection  
GET /api/users?fields=id,name,email

# Pagination
GET /api/users?page=2&per_page=20

# Advanced filtering
GET /api/users?name=John*&created_at=>=2024-01-01&sort_by=name

# Relationship filtering
GET /api/users?fields=id,name,company.name&company.active=true
```

## 📖 Documentation

### Available Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `eq` | Equals | `name=John` |
| `ne` | Not equals | `name=!=John` |
| `like` | Contains (use `*` as wildcard) | `name=John*` |
| `not_like` | Does not contain | `name=!*John` |
| `gt` | Greater than | `age=>18` |
| `gte` | Greater than or equal | `age=>=18` |
| `lt` | Less than | `age=<65` |
| `lte` | Less than or equal | `age=<=65` |
| `in` | In array | `status=active,pending` |
| `not_in` | Not in array | `status=!=active,pending` |
| `between` | Between values | `age=18\\|65` |
| `null` | Is null | `deleted_at=null` |
| `not_null` | Is not null | `deleted_at=!null` |

### Field Selection

Optimize your API responses by selecting only the fields you need:

```bash
# Basic field selection
GET /api/users?fields=id,name,email

# Include relationships
GET /api/users?fields=id,name,company.name,company.city

# Use aliases
GET /api/users?fields=user_id,user_name,user_email
```

### Pagination

The package provides automatic pagination with comprehensive metadata:

```json
{
    "success": true,
    "data": [...],
    "pagination": {
        "current_page": 1,
        "per_page": 15,
        "total": 150,
        "last_page": 10,
        "from": 1,
        "to": 15,
        "has_more_pages": true,
        "prev_page_url": null,
        "next_page_url": "/api/users?page=2"
    },
    "filters": {
        "active": {
            "name": "John*",
            "status": "active"
        },
        "sorting": {
            "sort_by": "created_at",
            "sort_direction": "desc"
        }
    }
}
```

### Filter Configuration

Configure filters with detailed metadata:

```php
$this->configureFilters([
    'status' => [
        'type' => 'enum',
        'values' => ['active', 'inactive', 'pending'],
        'operators' => ['eq', 'in'],
        'description' => 'User account status',
        'example' => [
            'eq' => 'status=active',
            'in' => 'status=active,pending'
        ]
    ],
    'created_at' => [
        'type' => 'datetime',
        'operators' => ['gte', 'lte', 'between'],
        'format' => 'Y-m-d H:i:s',
        'sortable' => true,
        'description' => 'User registration date'
    ]
]);
```

### Middleware Configuration

Add the middleware to automatically validate and sanitize requests:

```php
// In your route group
Route::group(['middleware' => ['api', 'apiforge']], function () {
    Route::get('/users', [UserController::class, 'index']);
});
```

### Auto-Documentation

Get filter metadata and examples automatically:

```bash
# Get available filters and configuration
GET /api/users/metadata

# Get usage examples  
GET /api/users/examples
```

## 🌟 Virtual Fields

Virtual fields are computed fields that don't exist in your database but can be filtered, sorted, and selected like regular fields. They're calculated on-demand using custom callback functions.

### Basic Virtual Field

```php
protected function setupFilterConfiguration(): void
{
    $this->configureVirtualFields([
        'display_name' => [
            'type' => 'string',
            'callback' => function($user) {
                return $user->name ?: $user->email;
            },
            'dependencies' => ['name', 'email'],
            'operators' => ['eq', 'like'],
            'searchable' => true,
            'sortable' => true
        ]
    ]);
}
```

### Relationship-Based Virtual Fields

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
],

'total_spent' => [
    'type' => 'float',
    'callback' => function($user) {
        return $user->orders->sum('total');
    },
    'relationships' => ['orders'],
    'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
    'cacheable' => true,
    'cache_ttl' => 3600
]
```

### Complex Business Logic Virtual Fields

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
],

'age' => [
    'type' => 'integer',
    'callback' => function($user) {
        return $user->birth_date ? 
            Carbon::parse($user->birth_date)->age : null;
    },
    'dependencies' => ['birth_date'],
    'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
    'nullable' => true
]
```

### Virtual Field Configuration Options

| Option | Type | Description |
|--------|------|-------------|
| `type` | string | Field type (string, integer, float, boolean, enum, datetime) |
| `callback` | callable | Function to compute the field value |
| `dependencies` | array | Database fields required for computation |
| `relationships` | array | Eloquent relationships to eager load |
| `operators` | array | Allowed filter operators |
| `cacheable` | boolean | Enable caching for computed values |
| `cache_ttl` | integer | Cache time-to-live in seconds |
| `nullable` | boolean | Allow null values |
| `default_value` | mixed | Default value when computation fails |
| `searchable` | boolean | Include in search operations |
| `sortable` | boolean | Allow sorting by this field |

### Using Virtual Fields in API Calls

```bash
# Filter by virtual fields
GET /api/users?customer_tier=gold&age=>=25

# Sort by virtual fields
GET /api/users?sort_by=total_spent&sort_direction=desc

# Select virtual fields
GET /api/users?fields=id,name,customer_tier,total_spent

# Combine with regular fields
GET /api/users?name=John*&customer_tier=gold,platinum&fields=id,name,total_spent
```

## 🪝 Model Hooks

Model hooks provide lifecycle callbacks that execute custom logic during CRUD operations. They're perfect for audit logging, notifications, data validation, and business rule enforcement.

### Available Hook Types

- `beforeStore` - Before creating a new record
- `afterStore` - After successfully creating a record
- `beforeUpdate` - Before updating an existing record
- `afterUpdate` - After successfully updating a record
- `beforeDelete` - Before deleting a record (can prevent deletion)
- `afterDelete` - After successfully deleting a record

### Basic Hook Configuration

```php
protected function setupFilterConfiguration(): void
{
    $this->configureModelHooks([
        'beforeStore' => [
            'generateSlug' => function($model, $context) {
                if (empty($model->slug) && !empty($model->title)) {
                    $model->slug = Str::slug($model->title);
                }
            },
            'validateBusinessRules' => function($model, $context) {
                if ($model->type === 'premium' && !$model->user->isPremium()) {
                    throw new ValidationException('User must be premium');
                }
            }
        ],
        
        'afterStore' => [
            'sendNotification' => function($model, $context) {
                NotificationService::send($model->user, 'ItemCreated', $model);
            },
            'updateCache' => function($model, $context) {
                Cache::forget("user_items_{$model->user_id}");
            }
        ]
    ]);
}
```

### Advanced Hook Features

#### Priority-Based Execution

```php
'afterStore' => [
    'criticalNotification' => [
        'callback' => function($model, $context) {
            // Critical notification logic
        },
        'priority' => 1  // Higher priority (executes first)
    ],
    'regularNotification' => [
        'callback' => function($model, $context) {
            // Regular notification logic
        },
        'priority' => 10  // Lower priority (executes later)
    ]
]
```

#### Conditional Hook Execution

```php
'beforeStore' => [
    'validatePremiumFeatures' => [
        'callback' => function($model, $context) {
            // Validation logic for premium features
        },
        'conditions' => [
            'field' => 'type',
            'operator' => 'eq',
            'value' => 'premium'
        ]
    ]
]
```

#### Hooks That Can Prevent Operations

```php
'beforeDelete' => [
    'checkPermissions' => [
        'callback' => function($model, $context) {
            if (!auth()->user()->canDelete($model)) {
                throw new UnauthorizedException('Cannot delete this resource');
            }
            return true; // Allow deletion
        },
        'stopOnFailure' => true
    ],
    'checkDependencies' => function($model, $context) {
        if ($model->orders()->exists()) {
            throw new ValidationException('Cannot delete user with existing orders');
        }
        return true;
    }
]
```

### Hook Context

Hooks receive a context object with useful information:

```php
'afterUpdate' => [
    'trackChanges' => function($model, $context) {
        // Access request data
        $request = $context->request;
        
        // Access the operation type
        $operation = $context->operation; // 'store', 'update', 'delete'
        
        // Access additional data
        $changes = $context->get('changes', []);
        
        // Log the changes
        AuditLog::create([
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'changes' => $model->getDirty(),
            'user_id' => auth()->id(),
            'ip_address' => $request->ip()
        ]);
    }
]
```

### Common Hook Patterns

#### Audit Logging

```php
'beforeUpdate' => [
    'auditChanges' => function($model, $context) {
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
]
```

#### Automatic Timestamps and User Tracking

```php
'beforeStore' => [
    'setCreatedBy' => fn($model) => $model->created_by = auth()->id()
],
'beforeUpdate' => [
    'setUpdatedBy' => fn($model) => $model->updated_by = auth()->id()
]
```

#### Cache Management

```php
'afterStore' => [
    'clearCache' => fn($model) => Cache::tags(['users'])->flush()
],
'afterUpdate' => [
    'updateCache' => function($model, $context) {
        Cache::forget("user_{$model->id}");
        Cache::put("user_{$model->id}", $model, 3600);
    }
]
```

#### File Cleanup

```php
'afterDelete' => [
    'cleanupFiles' => function($model, $context) {
        if ($model->avatar) {
            Storage::delete($model->avatar);
        }
        if ($model->documents) {
            foreach ($model->documents as $doc) {
                Storage::delete($doc->path);
            }
        }
    }
]
```

## 🔧 Advanced Configuration

### Custom Base Controller

Extend the base controller for common functionality:

```php
use MarcosBrendon\\ApiForge\\Http\\Controllers\\BaseApiController;

class ApiController extends BaseApiController
{
    protected function getDefaultPerPage(): int
    {
        return 25;
    }
    
    protected function getMaxPerPage(): int  
    {
        return 500;
    }
}
```

### Custom Field Selection

Configure field selection with aliases and validation:

```php
protected function configureFieldSelection(): void
{
    $this->fieldSelection([
        'selectable_fields' => ['id', 'name', 'email', 'company.name'],
        'required_fields' => ['id'],
        'blocked_fields' => ['password', 'remember_token'],
        'default_fields' => ['id', 'name', 'email'],
        'field_aliases' => [
            'user_id' => 'id',
            'user_name' => 'name',
            'company_name' => 'company.name'
        ],
        'max_fields' => 50
    ]);
}
```

### Caching

Enable query caching for better performance:

```php
public function index(Request $request)
{
    return $this->indexWithFilters($request, [
        'cache' => true,
        'cache_ttl' => 3600, // 1 hour
        'cache_tags' => ['users', 'api']
    ]);
}
```

### Virtual Fields and Hooks Configuration

Configure advanced features in your controller:

```php
protected function setupFilterConfiguration(): void
{
    // Regular filters
    $this->configureFilters([
        'name' => ['type' => 'string', 'operators' => ['eq', 'like']],
        'status' => ['type' => 'enum', 'values' => ['active', 'inactive']]
    ]);
    
    // Virtual fields with caching
    $this->configureVirtualFields([
        'full_name' => [
            'type' => 'string',
            'callback' => fn($user) => trim($user->first_name . ' ' . $user->last_name),
            'dependencies' => ['first_name', 'last_name'],
            'operators' => ['eq', 'like'],
            'cacheable' => true,
            'cache_ttl' => 3600
        ],
        'customer_tier' => [
            'type' => 'enum',
            'values' => ['bronze', 'silver', 'gold', 'platinum'],
            'callback' => [$this, 'calculateCustomerTier'],
            'relationships' => ['orders'],
            'cacheable' => true,
            'cache_ttl' => 1800
        ]
    ]);
    
    // Model hooks with priorities
    $this->configureModelHooks([
        'beforeStore' => [
            'validateData' => [
                'callback' => [$this, 'validateBusinessRules'],
                'priority' => 1
            ],
            'generateSlug' => [
                'callback' => fn($model) => $model->slug = Str::slug($model->title),
                'priority' => 5
            ]
        ],
        'afterStore' => [
            'sendNotification' => fn($model) => NotificationService::send($model),
            'updateCache' => fn($model) => Cache::forget("users_list")
        ]
    ]);
}

private function calculateCustomerTier($user)
{
    $totalSpent = $user->orders->sum('total');
    if ($totalSpent >= 10000) return 'platinum';
    if ($totalSpent >= 5000) return 'gold';
    if ($totalSpent >= 1000) return 'silver';
    return 'bronze';
}

private function validateBusinessRules($model, $context)
{
    if ($model->type === 'premium' && !$model->user->isPremium()) {
        throw new ValidationException('User must be premium for this type');
    }
}
```

## ⚡ Performance Optimization

### Virtual Fields Performance

Virtual fields include several performance optimization features:

#### 1. Caching

```php
'expensive_calculation' => [
    'type' => 'float',
    'callback' => function($model) {
        // Expensive computation
        return $this->performComplexCalculation($model);
    },
    'cacheable' => true,
    'cache_ttl' => 3600, // Cache for 1 hour
    'relationships' => ['orders', 'payments']
]
```

#### 2. Dependency Optimization

```php
'user_summary' => [
    'type' => 'string',
    'callback' => function($model) {
        return "{$model->name} ({$model->email}) - {$model->orders_count} orders";
    },
    'dependencies' => ['name', 'email'], // Only load these fields
    'relationships' => ['orders'] // Eager load with count
]
```

#### 3. Batch Processing

Virtual fields are automatically computed in batches for better performance:

```php
// This will compute virtual fields for all users in a single batch
GET /api/users?fields=id,name,customer_tier&per_page=100
```

#### 4. Lazy Loading

Virtual fields are only computed when requested:

```php
// Virtual fields not computed (not requested)
GET /api/users?fields=id,name,email

// Virtual fields computed only for selected fields
GET /api/users?fields=id,name,customer_tier,total_spent
```

### Performance Configuration

Configure performance limits in your config file:

```php
// config/apiforge.php
'virtual_fields' => [
    'cache' => [
        'enabled' => true,
        'default_ttl' => 3600,
        'driver' => 'redis' // or 'database', 'file'
    ],
    'performance' => [
        'memory_limit' => '256M',
        'time_limit' => 30, // seconds
        'batch_size' => 100,
        'max_virtual_fields' => 20
    ]
]
```

### Hook Performance

Model hooks are designed for minimal performance impact:

#### 1. Conditional Execution

```php
'expensiveHook' => [
    'callback' => function($model, $context) {
        // Only run for specific conditions
        $this->performExpensiveOperation($model);
    },
    'conditions' => [
        'field' => 'type',
        'operator' => 'eq',
        'value' => 'premium'
    ]
]
```

#### 2. Async Processing

```php
'afterStore' => [
    'sendEmail' => function($model, $context) {
        // Queue for background processing
        SendWelcomeEmail::dispatch($model)->delay(now()->addMinutes(5));
    }
]
```

### General Performance Tips

1. **Use field selection** to reduce data transfer:
   ```bash
   GET /api/users?fields=id,name,email,customer_tier
   ```

2. **Enable caching** for expensive virtual fields:
   ```php
   'cacheable' => true,
   'cache_ttl' => 3600
   ```

3. **Optimize database queries** with proper indexing:
   ```php
   // Add indexes for fields used in virtual field dependencies
   Schema::table('users', function (Blueprint $table) {
       $table->index(['first_name', 'last_name']);
       $table->index('birth_date');
   });
   ```

4. **Use relationship counting** instead of loading full relationships:
   ```php
   'order_count' => [
       'callback' => function($user) {
           return $user->orders_count ?? $user->orders()->count();
       }
   ]
   ```

5. **Configure appropriate pagination**:
   ```php
   'pagination' => [
       'default_per_page' => 15,
       'max_per_page' => 100,
   ]
   ```

## 🧪 Testing

```bash
composer test
```

## 🚀 Production Ready

ApiForge is production-ready with:

- ✅ **11 comprehensive tests** covering all features
- ✅ **Security** - Built-in SQL injection protection
- ✅ **Performance** - Query optimization and caching
- ✅ **Compatibility** - PHP 8.1+ and Laravel 10/11/12+
- ✅ **CI/CD** - Automated testing with GitHub Actions
- ✅ **Documentation** - Complete API documentation

### Performance Tips

1. **Enable caching** for better performance:
   ```php
   // config/apiforge.php
   'cache' => [
       'enabled' => true,
       'ttl' => 3600,
   ],
   ```

2. **Use field selection** to reduce data transfer:
   ```bash
   GET /api/users?fields=id,name,email
   ```

3. **Configure appropriate pagination**:
   ```php
   'pagination' => [
       'default_per_page' => 15,
       'max_per_page' => 100,
   ],
   ```

## 📝 Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## 🤝 Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## 🔒 Security Vulnerabilities

Please review [our security policy](https://github.com/MarcosBrendonDePaula/ApiForge/security/policy) on how to report security vulnerabilities.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## 🙏 Credits

- [Marcos Brendon](https://github.com/MarcosBrendonDePaula)
- [All Contributors](https://github.com/MarcosBrendonDePaula/ApiForge/contributors)

## 💡 Why This Package?

This package was born from the need to create sophisticated APIs with advanced filtering capabilities while maintaining clean, maintainable code. It combines the power of Laravel's Eloquent ORM with a flexible, configuration-driven approach to API development.

### Key Differentiators:

- **More operators** than similar packages (15+ vs 5-8)
- **Virtual fields** - Computed fields that can be filtered and sorted like database fields
- **Model hooks** - Comprehensive lifecycle callbacks for CRUD operations
- **Built-in security** with automatic validation and sanitization  
- **Auto-documentation** generates API docs automatically
- **Performance focused** with query optimization, caching, and batch processing
- **Developer experience** with simple configuration and extensive examples
- **Production ready** with comprehensive testing and error handling

### Advanced Features:

- **Virtual Fields**: Create computed fields like `full_name`, `age`, `customer_tier` that can be filtered, sorted, and selected
- **Model Hooks**: Execute custom logic during CRUD operations with `beforeStore`, `afterStore`, `beforeUpdate`, `afterUpdate`, `beforeDelete`, `afterDelete`
- **Performance Optimization**: Built-in caching, batch processing, and query optimization for virtual fields
- **Business Logic Integration**: Perfect for audit logging, notifications, data validation, and complex business rules
- **Seamless Integration**: Virtual fields and hooks work with all existing ApiForge features

---

Made with ❤️ by [Marcos Brendon](https://github.com/MarcosBrendonDePaula)