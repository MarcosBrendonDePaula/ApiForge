# ApiForge

[![Latest Version on Packagist](https://img.shields.io/packagist/v/marcosbrendon/apiforge.svg?style=flat-square)](https://packagist.org/packages/marcosbrendon/apiforge)
[![Total Downloads](https://img.shields.io/packagist/dt/marcosbrendon/apiforge.svg?style=flat-square)](https://packagist.org/packages/marcosbrendon/apiforge)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/MarcosBrendonDePaula/ApiForge/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/MarcosBrendonDePaula/ApiForge/actions)
[![PHP Version Require](https://img.shields.io/packagist/php-v/marcosbrendon/apiforge?style=flat-square)](https://packagist.org/packages/marcosbrendon/apiforge)
[![Laravel Version](https://img.shields.io/badge/Laravel-10%2B%20%7C%2011%2B-red?style=flat-square&logo=laravel)](https://laravel.com)
[![GitHub License](https://img.shields.io/github/license/MarcosBrendonDePaula/ApiForge?style=flat-square)](https://github.com/MarcosBrendonDePaula/ApiForge/blob/main/LICENSE.md)

**Forge powerful APIs with advanced filtering, pagination, and field selection capabilities for Laravel applications.** Build sophisticated APIs with minimal configuration and maximum performance.

## ✨ Features

- 🔍 **Advanced Filtering** - 15+ operators (eq, like, gte, between, in, etc.)
- 📄 **Smart Pagination** - Automatic pagination with metadata
- 🎯 **Field Selection** - Optimize queries by selecting only needed fields
- 🔗 **Relationship Support** - Filter and select fields from relationships
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

3. **Use the API:**

```bash
# Basic filtering
GET /api/users?name=John&email=*@gmail.com

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

## 🧪 Testing

```bash
composer test
```

## 🚀 Production Ready

ApiForge is production-ready with:

- ✅ **11 comprehensive tests** covering all features
- ✅ **Security** - Built-in SQL injection protection
- ✅ **Performance** - Query optimization and caching
- ✅ **Compatibility** - PHP 8.1+ and Laravel 10+
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
- **Built-in security** with automatic validation and sanitization  
- **Auto-documentation** generates API docs automatically
- **Performance focused** with query optimization and caching
- **Developer experience** with simple configuration and extensive examples
- **Production ready** with comprehensive testing and error handling

---

Made with ❤️ by [Marcos Brendon](https://github.com/MarcosBrendonDePaula)