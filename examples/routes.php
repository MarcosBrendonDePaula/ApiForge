<?php

/**
 * Example routes configuration for ApiForge
 * 
 * Add these routes to your routes/api.php file
 */

use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserWithTraitController;

// Group with the ApiForge middleware
Route::group(['prefix' => 'api/v1', 'middleware' => ['api', 'apiforge']], function () {
    
    // Users API with full BaseApiController functionality
    Route::group(['prefix' => 'users'], function () {
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
        Route::post('/', [UserController::class, 'store'])
            ->name('api.users.store');
        Route::get('/{id}', [UserController::class, 'show'])
            ->name('api.users.show');
        Route::put('/{id}', [UserController::class, 'update'])
            ->name('api.users.update');
        Route::delete('/{id}', [UserController::class, 'destroy'])
            ->name('api.users.destroy');
    });
    
    // Alternative users API using only the trait
    Route::group(['prefix' => 'users-simple'], function () {
        Route::get('/metadata', [UserWithTraitController::class, 'metadata']);
        Route::get('/examples', [UserWithTraitController::class, 'examples']);
        Route::get('/search', [UserWithTraitController::class, 'search']);
        Route::get('/', [UserWithTraitController::class, 'index']);
    });
});

// Example usage without middleware (manual parameter handling)
Route::group(['prefix' => 'api/v1/manual'], function () {
    Route::get('/users', [UserController::class, 'index']);
});

/**
 * URL Examples:
 * 
 * Basic filtering:
 * GET /api/v1/users?name=John&active=true
 * 
 * Advanced operators:
 * GET /api/v1/users?name=John*&created_at=>=2024-01-01
 * 
 * Field selection:
 * GET /api/v1/users?fields=id,name,email
 * 
 * Pagination:
 * GET /api/v1/users?page=2&per_page=20
 * 
 * Sorting:
 * GET /api/v1/users?sort_by=name&sort_direction=asc
 * 
 * Combined:
 * GET /api/v1/users?name=John*&active=true&fields=id,name,email&sort_by=created_at&page=1&per_page=10
 * 
 * Multiple values:
 * GET /api/v1/users?role=admin,moderator
 * 
 * Date ranges:
 * GET /api/v1/users?created_at=2024-01-01|2024-12-31
 * 
 * Search:
 * GET /api/v1/users?search=John
 * 
 * Quick search:
 * GET /api/v1/users/search?q=john&limit=5
 * 
 * Metadata:
 * GET /api/v1/users/metadata
 * 
 * Examples:
 * GET /api/v1/users/examples
 * 
 * Statistics:
 * GET /api/v1/users/statistics
 */