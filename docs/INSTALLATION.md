# Installation Guide

## Requirements

- PHP 8.1 or higher
- Laravel 10.0 or higher
- Composer

## Installation

Install the package via Composer:

```bash
composer require marcosbrendon/laravel-apiforge
```

## Configuration

### 1. Publish Configuration (Optional)

```bash
php artisan vendor:publish --provider="MarcosBrendon\LaravelAdvancedApiFilters\LaravelAdvancedApiFiltersServiceProvider" --tag="config"
```

This will publish the configuration file to `config/apiforge.php`.

### 2. Add Middleware (Recommended)

Add the middleware to your API routes in `routes/api.php`:

```php
Route::group(['middleware' => ['api', 'apiforge']], function () {
    Route::apiResource('users', UserController::class);
});
```

### 3. Basic Usage

#### Option A: Extend BaseApiController

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use MarcosBrendon\LaravelAdvancedApiFilters\Http\Controllers\BaseApiController;

class UserController extends BaseApiController
{
    protected function getModelClass(): string
    {
        return User::class;
    }

    protected function setupFilterConfiguration(): void
    {
        $this->configureFilters([
            'name' => [
                'type' => 'string',
                'operators' => ['eq', 'like', 'ne'],
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
                'operators' => ['gte', 'lte', 'between'],
                'sortable' => true,
            ]
        ]);

        $this->configureFieldSelection([
            'selectable_fields' => ['id', 'name', 'email', 'created_at'],
            'default_fields' => ['id', 'name', 'email'],
            'max_fields' => 25
        ]);
    }

    protected function validateStoreData(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
        ]);
    }

    protected function validateUpdateData(Request $request, $resource): array
    {
        return $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $resource->id,
        ]);
    }
}
```

#### Option B: Use the Trait

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use MarcosBrendon\LaravelAdvancedApiFilters\Traits\HasAdvancedFilters;

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
            ]
        ]);
    }

    public function index(Request $request)
    {
        return $this->indexWithFilters($request);
    }
}
```

### 4. Configure Routes

```php
// routes/api.php
Route::group(['prefix' => 'api/v1', 'middleware' => ['api', 'apiforge']], function () {
    Route::group(['prefix' => 'users'], function () {
        // Metadata and examples (optional but recommended)
        Route::get('/metadata', [UserController::class, 'filterMetadata']);
        Route::get('/examples', [UserController::class, 'filterExamples']);
        
        // CRUD routes
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
    });
});
```

## Quick Test

After installation, test your API:

```bash
# Basic filtering
curl "http://your-app.test/api/v1/users?name=John&per_page=5"

# Field selection
curl "http://your-app.test/api/v1/users?fields=id,name,email"

# Advanced filtering
curl "http://your-app.test/api/v1/users?name=John*&created_at=>=2024-01-01"

# Get metadata
curl "http://your-app.test/api/v1/users/metadata"
```

## Next Steps

1. Read the [Configuration Guide](CONFIGURATION.md) for advanced options
2. Check out [Usage Examples](USAGE.md) for more complex scenarios
3. See [API Reference](API_REFERENCE.md) for complete documentation
4. View [Examples](../examples/) for complete controller implementations

## Troubleshooting

### Common Issues

1. **Middleware not working**: Make sure you've registered the middleware alias in your routes
2. **Filters not applying**: Check that your `setupFilterConfiguration()` method is being called
3. **Field selection errors**: Ensure the fields exist in your model and are in the `selectable_fields` array

### Debug Mode

Enable debug mode in your configuration:

```php
// config/apiforge.php
'debug' => [
    'enabled' => env('APP_DEBUG', false),
    'log_queries' => true,
    'log_filters' => true,
],
```

This will log SQL queries and filter applications to help with debugging.

## Support

- [GitHub Issues](https://github.com/marcosbrendon/laravel-apiforge/issues)
- [Documentation](../README.md)
- [Examples](../examples/)