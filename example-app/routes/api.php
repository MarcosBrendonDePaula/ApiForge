<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\CategoryController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
| These routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'service' => 'ApiForge Example App'
    ]);
});

// API Documentation endpoint
Route::get('/docs', function () {
    return response()->json([
        'name' => 'ApiForge Example API',
        'version' => '1.0.0',
        'description' => 'Example API demonstrating ApiForge capabilities',
        'endpoints' => [
            'users' => '/api/users',
            'products' => '/api/products',
            'orders' => '/api/orders',
            'categories' => '/api/categories'
        ],
        'features' => [
            'Advanced filtering with 15+ operators',
            'Smart pagination with metadata',
            'Field selection for performance',
            'Relationship filtering',
            'Auto-documentation',
            'Caching support'
        ],
        'documentation' => [
            'users_metadata' => '/api/users/metadata',
            'users_examples' => '/api/users/examples',
            'products_metadata' => '/api/products/metadata',
            'github' => 'https://github.com/MarcosBrendonDePaula/ApiForge'
        ]
    ]);
});

/*
|--------------------------------------------------------------------------
| Users API Routes
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'users', 'middleware' => ['apiforge']], function () {
    // Static routes first (order matters!)
    Route::get('/metadata', [UserController::class, 'filterMetadata'])
        ->name('api.users.metadata');
    Route::get('/examples', [UserController::class, 'filterExamples'])
        ->name('api.users.examples');
    Route::get('/statistics', [UserController::class, 'statistics'])
        ->name('api.users.statistics');
    Route::get('/search', [UserController::class, 'quickSearch'])
        ->name('api.users.search');
    
    // CRUD routes
    Route::get('/', [UserController::class, 'index'])
        ->name('api.users.index');
    Route::get('/{id}', [UserController::class, 'show'])
        ->name('api.users.show');
});

/*
|--------------------------------------------------------------------------
| Products API Routes
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'products', 'middleware' => ['apiforge']], function () {
    // Static routes first
    Route::get('/metadata', [ProductController::class, 'filterMetadata'])
        ->name('api.products.metadata');
    Route::get('/examples', [ProductController::class, 'filterExamples'])
        ->name('api.products.examples');
    Route::get('/statistics', [ProductController::class, 'statistics'])
        ->name('api.products.statistics');
    Route::get('/search', [ProductController::class, 'search'])
        ->name('api.products.search');
    Route::get('/featured', [ProductController::class, 'featured'])
        ->name('api.products.featured');
    
    // CRUD routes
    Route::get('/', [ProductController::class, 'index'])
        ->name('api.products.index');
    Route::get('/{id}', [ProductController::class, 'show'])
        ->name('api.products.show');
});

/*
|--------------------------------------------------------------------------
| Orders API Routes
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'orders', 'middleware' => ['apiforge']], function () {
    // Static routes first
    Route::get('/metadata', [OrderController::class, 'filterMetadata'])
        ->name('api.orders.metadata');
    Route::get('/examples', [OrderController::class, 'filterExamples'])
        ->name('api.orders.examples');
    Route::get('/statistics', [OrderController::class, 'statistics'])
        ->name('api.orders.statistics');
    Route::get('/sales-report', [OrderController::class, 'salesReport'])
        ->name('api.orders.sales_report');
    
    // Status-specific routes
    Route::get('/status/{status}', [OrderController::class, 'byStatus'])
        ->name('api.orders.by_status')
        ->where('status', 'pending|processing|shipped|delivered|cancelled|refunded');
    
    // CRUD routes
    Route::get('/', [OrderController::class, 'index'])
        ->name('api.orders.index');
    Route::get('/{id}', [OrderController::class, 'show'])
        ->name('api.orders.show');
});

/*
|--------------------------------------------------------------------------
| Categories API Routes
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'categories', 'middleware' => ['apiforge']], function () {
    // Static routes first
    Route::get('/metadata', [CategoryController::class, 'filterMetadata'])
        ->name('api.categories.metadata');
    Route::get('/examples', [CategoryController::class, 'filterExamples'])
        ->name('api.categories.examples');
    Route::get('/statistics', [CategoryController::class, 'statistics'])
        ->name('api.categories.statistics');
    Route::get('/tree', [CategoryController::class, 'tree'])
        ->name('api.categories.tree');
    Route::get('/popular', [CategoryController::class, 'popular'])
        ->name('api.categories.popular');
    Route::get('/search', [CategoryController::class, 'search'])
        ->name('api.categories.search');
    
    // CRUD routes
    Route::get('/', [CategoryController::class, 'index'])
        ->name('api.categories.index');
    Route::get('/{id}', [CategoryController::class, 'show'])
        ->name('api.categories.show');
});

/*
|--------------------------------------------------------------------------
| Example Usage Routes (without middleware for comparison)
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'examples'], function () {
    
    // Manual filtering examples (without ApiForge middleware)
    Route::get('/manual/users', function (Request $request) {
        return response()->json([
            'message' => 'This endpoint demonstrates manual filtering without ApiForge middleware',
            'request_params' => $request->all(),
            'note' => 'Compare this with /api/users to see the difference'
        ]);
    });

    // Performance comparison
    Route::get('/performance', function () {
        return response()->json([
            'message' => 'Performance comparison examples',
            'examples' => [
                'without_field_selection' => '/api/products',
                'with_field_selection' => '/api/products?fields=id,name,price',
                'with_caching' => '/api/users (automatically cached)',
                'complex_relationships' => '/api/orders?fields=id,user.name,items.product.name'
            ],
            'tips' => [
                'Use field selection to reduce payload size',
                'Leverage automatic caching for better performance',
                'Use relationship filtering to avoid N+1 queries',
                'Monitor query counts in your application logs'
            ]
        ]);
    });

    // Filter examples by category
    Route::get('/filters', function () {
        return response()->json([
            'basic_filters' => [
                'equality' => '/api/users?name=John&status=active',
                'like_wildcard' => '/api/users?name=John*&email=*@gmail.com',
                'numeric_ranges' => '/api/products?price=100|500&rating=>=4.0',
                'date_ranges' => '/api/orders?created_at=2024-01-01|2024-12-31',
                'in_arrays' => '/api/products?brand=Apple,Samsung&status=active,featured'
            ],
            'advanced_filters' => [
                'relationships' => '/api/products?category.name=Electronics&category.active=true',
                'null_checks' => '/api/users?email_verified_at=!null&last_login_at=null',
                'complex_combinations' => '/api/orders?total=>=100&status=completed,shipped&user.name=John*'
            ],
            'performance_optimized' => [
                'field_selection' => '/api/users?fields=id,name,email,profile.city',
                'limited_relationships' => '/api/products?fields=id,name,price,category.name',
                'pagination' => '/api/users?per_page=50&page=2&sort_by=created_at'
            ]
        ]);
    });

    // Real-world scenarios
    Route::get('/scenarios', function () {
        return response()->json([
            'e_commerce' => [
                'product_search' => '/api/products?name=iPhone*&price=500|1500&in_stock=true',
                'category_browse' => '/api/products?category.name=Electronics&sort_by=price&sort_direction=asc',
                'featured_products' => '/api/products?featured=true&fields=id,name,price,rating',
                'customer_orders' => '/api/orders?user_id=123&status=completed,shipped&sort_by=created_at'
            ],
            'user_management' => [
                'active_users' => '/api/users?status=active&email_verified_at=!null',
                'recent_registrations' => '/api/users?created_at=>=2024-01-01&sort_by=created_at',
                'user_search' => '/api/users?search=john&fields=id,name,email,role',
                'admin_users' => '/api/users?role=admin,manager&status=active'
            ],
            'reporting' => [
                'sales_by_period' => '/api/orders/sales-report?date_from=2024-01-01&date_to=2024-12-31&group_by=month',
                'popular_categories' => '/api/categories/popular?limit=10',
                'order_statistics' => '/api/orders/statistics',
                'user_statistics' => '/api/users/statistics'
            ]
        ]);
    });
});

/*
|--------------------------------------------------------------------------
| Batch Operations Examples
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'batch'], function () {
    
    // Batch metadata for all endpoints
    Route::get('/metadata', function () {
        return response()->json([
            'users' => app(UserController::class)->filterMetadata()->getData(),
            'products' => app(ProductController::class)->filterMetadata()->getData(),
            'orders' => app(OrderController::class)->filterMetadata()->getData(),
            'categories' => app(CategoryController::class)->filterMetadata()->getData(),
        ]);
    });

    // Batch examples for all endpoints
    Route::get('/examples', function () {
        return response()->json([
            'users' => app(UserController::class)->filterExamples()->getData(),
            'products' => app(ProductController::class)->filterExamples()->getData(),
            'orders' => app(OrderController::class)->filterExamples()->getData(),
            'categories' => app(CategoryController::class)->filterExamples()->getData(),
        ]);
    });
});