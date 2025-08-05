# Quick Start Guide

Get up and running with ApiForge in under 5 minutes!

## 1. Install the Package

```bash
composer require marcosbrendon/laravel-apiforge
```

## 2. Create Your Controller

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use MarcosBrendon\ApiForge\Traits\HasAdvancedFilters;
use App\Http\Controllers\Controller;

class UserController extends Controller
{
    use HasAdvancedFilters;

    protected function getModelClass(): string
    {
        return User::class;
    }

    protected function setupFilterConfiguration(): void
    {
        $this->configureFilters([
            'name' => [
                'type' => 'string',
                'operators' => ['eq', 'like'],
                'searchable' => true,
                'sortable' => true,
            ],
            'email' => [
                'type' => 'string', 
                'operators' => ['eq', 'like'],
                'searchable' => true,
            ],
            'created_at' => [
                'type' => 'datetime',
                'operators' => ['gte', 'lte'],
                'sortable' => true,
            ]
        ]);

        $this->configureFieldSelection([
            'selectable_fields' => ['id', 'name', 'email', 'created_at'],
            'default_fields' => ['id', 'name', 'email'],
        ]);
    }

    public function index(Request $request)
    {
        return $this->indexWithFilters($request);
    }
}
```

## 3. Add Routes

```php
// routes/api.php
Route::middleware(['api', 'apiforge'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
});
```

## 4. Test Your API

```bash
# Basic usage
GET /api/users

# Filter by name
GET /api/users?name=John

# Wildcard search
GET /api/users?name=John*

# Select specific fields
GET /api/users?fields=id,name,email

# Pagination
GET /api/users?page=2&per_page=10

# Sorting
GET /api/users?sort_by=created_at&sort_direction=desc

# Combined
GET /api/users?name=John*&fields=id,name&sort_by=created_at&per_page=5
```

## 5. Response Format

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com"
        }
    ],
    "pagination": {
        "current_page": 1,
        "per_page": 15,
        "total": 25,
        "last_page": 2,
        "has_more_pages": true,
        "next_page_url": "/api/users?page=2"
    },
    "filters": {
        "active": {
            "name": "John*"
        },
        "sorting": {
            "sort_by": "created_at",
            "sort_direction": "desc"
        }
    }
}
```

## 6. Available Operators

| Operator | Usage | Example |
|----------|--------|---------|
| `eq` | Equals | `name=John` |
| `ne` | Not equals | `name=!=John` |
| `like` | Contains (with *) | `name=John*` |
| `gt` | Greater than | `age=>18` |
| `gte` | Greater than or equal | `age=>=18` |
| `lt` | Less than | `age=<65` |
| `lte` | Less than or equal | `age=<=65` |
| `in` | In array | `status=active,pending` |
| `between` | Between values | `age=18\|65` |
| `null` | Is null | `deleted_at=null` |

## 7. Get API Documentation

Your API automatically provides documentation:

```bash
# Get available filters and configuration
GET /api/users/metadata

# Get usage examples
GET /api/users/examples
```

## What's Next?

- Read the [Complete Documentation](../README.md)
- Explore [Advanced Examples](../examples/)
- Learn about [Configuration Options](CONFIGURATION.md)
- Check out [Security Features](SECURITY.md)

That's it! You now have a powerful, filterable API with pagination and field selection. 🚀