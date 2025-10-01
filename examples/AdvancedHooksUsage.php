<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\Request;
use MarcosBrendon\ApiForge\Http\Controllers\BaseApiController;

/**
 * Advanced Model Hooks Usage Examples
 * 
 * This file demonstrates comprehensive usage of model hooks in ApiForge,
 * showing various patterns and best practices for implementing business logic
 * through hooks.
 */
class ProductController extends BaseApiController
{
    /**
     * Get the model class for this controller
     */
    protected function getModelClass(): string
    {
        return Product::class;
    }

    /**
     * Setup filter configuration and model hooks
     */
    protected function setupFilterConfiguration(): void
    {
        // Basic filter configuration
        $this->configureFilters([
            'name' => [
                'type' => 'string',
                'operators' => ['eq', 'like', 'ne'],
                'searchable' => true,
                'sortable' => true
            ],
            'price' => [
                'type' => 'float',
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true
            ],
            'category_id' => [
                'type' => 'integer',
                'operators' => ['eq', 'in', 'ne']
            ],
            'active' => [
                'type' => 'boolean',
                'operators' => ['eq']
            ]
        ]);

        // ========================================
        // COMPREHENSIVE HOOK CONFIGURATION
        // ========================================

        $this->configureModelHooks([
            // ========================================
            // BEFORE STORE HOOKS
            // ========================================
            'beforeStore' => [
                // Data validation and transformation
                'validateProductData' => [
                    'callback' => function($model, $context) {
                        // Custom business validation
                        if ($model->price <= 0) {
                            throw new \InvalidArgumentException('Product price must be greater than 0');
                        }
                        
                        if (empty($model->sku)) {
                            $model->sku = $this->generateSKU($model);
                        }
                    },
                    'priority' => 1,
                    'stopOnFailure' => true,
                    'description' => 'Validate product data and generate SKU'
                ],

                // Check inventory limits
                'checkInventoryLimits' => [
                    'callback' => function($model, $context) {
                        $category = \App\Models\Category::find($model->category_id);
                        if ($category && $category->max_products > 0) {
                            $currentCount = Product::where('category_id', $model->category_id)->count();
                            if ($currentCount >= $category->max_products) {
                                throw new \Exception("Category has reached maximum product limit ({$category->max_products})");
                            }
                        }
                    },
                    'priority' => 2,
                    'conditions' => [
                        'field' => 'category_id',
                        'operator' => 'not_null'
                    ],
                    'description' => 'Check category product limits'
                ],

                // Set default values
                'setDefaults' => [
                    'callback' => function($model, $context) {
                        $model->active = $model->active ?? true;
                        $model->featured = $model->featured ?? false;
                        $model->sort_order = $model->sort_order ?? 0;
                        
                        // Set created_by
                        if (auth()->check()) {
                            $model->created_by = auth()->id();
                        }
                    },
                    'priority' => 5,
                    'description' => 'Set default values for new products'
                ]
            ],

            // ========================================
            // AFTER STORE HOOKS
            // ========================================
            'afterStore' => [
                // Create initial inventory record
                'createInventoryRecord' => [
                    'callback' => function($model, $context) {
                        \App\Models\Inventory::create([
                            'product_id' => $model->id,
                            'quantity' => $context->get('initial_quantity', 0),
                            'reserved_quantity' => 0,
                            'location' => 'main_warehouse'
                        ]);
                    },
                    'priority' => 1,
                    'description' => 'Create initial inventory record'
                ],

                // Generate product images
                'processProductImages' => [
                    'callback' => function($model, $context) {
                        $images = $context->get('images', []);
                        foreach ($images as $image) {
                            $model->images()->create([
                                'url' => $image['url'],
                                'alt_text' => $image['alt_text'] ?? $model->name,
                                'sort_order' => $image['sort_order'] ?? 0
                            ]);
                        }
                    },
                    'priority' => 5,
                    'description' => 'Process and store product images'
                ],

                // Update category statistics
                'updateCategoryStats' => [
                    'callback' => function($model, $context) {
                        if ($model->category_id) {
                            $category = $model->category;
                            $category->increment('products_count');
                            $category->touch(); // Update updated_at
                        }
                    },
                    'priority' => 10,
                    'description' => 'Update category product count'
                ],

                // Send notifications
                'notifyStakeholders' => [
                    'callback' => function($model, $context) {
                        // Notify inventory managers
                        $managers = \App\Models\User::where('role', 'inventory_manager')->get();
                        foreach ($managers as $manager) {
                            $manager->notify(new \App\Notifications\ProductCreated($model));
                        }

                        // Notify category managers
                        if ($model->category && $model->category->manager) {
                            $model->category->manager->notify(
                                new \App\Notifications\ProductAddedToCategory($model)
                            );
                        }
                    },
                    'priority' => 15,
                    'description' => 'Notify relevant stakeholders'
                ],

                // Index for search
                'indexForSearch' => [
                    'callback' => function($model, $context) {
                        // Add to search index (Elasticsearch, Algolia, etc.)
                        if (config('services.search.enabled')) {
                            \App\Services\SearchService::index($model);
                        }
                    },
                    'priority' => 20,
                    'description' => 'Add product to search index'
                ]
            ],

            // ========================================
            // BEFORE UPDATE HOOKS
            // ========================================
            'beforeUpdate' => [
                // Track price changes
                'trackPriceChanges' => [
                    'callback' => function($model, $context) {
                        if ($model->isDirty('price')) {
                            $oldPrice = $model->getOriginal('price');
                            $newPrice = $model->price;
                            
                            // Log price change
                            \App\Models\PriceHistory::create([
                                'product_id' => $model->id,
                                'old_price' => $oldPrice,
                                'new_price' => $newPrice,
                                'changed_by' => auth()->id(),
                                'reason' => $context->get('price_change_reason', 'Manual update')
                            ]);

                            // Store in context for after hook
                            $context->set('price_changed', true);
                            $context->set('old_price', $oldPrice);
                            $context->set('new_price', $newPrice);
                        }
                    },
                    'priority' => 1,
                    'description' => 'Track and log price changes'
                ],

                // Validate status changes
                'validateStatusChange' => [
                    'callback' => function($model, $context) {
                        if ($model->isDirty('active')) {
                            // Check if product can be deactivated
                            if (!$model->active && $model->getOriginal('active')) {
                                $activeOrders = $model->orderItems()
                                    ->whereHas('order', function($q) {
                                        $q->whereIn('status', ['pending', 'processing']);
                                    })->exists();

                                if ($activeOrders) {
                                    throw new \Exception('Cannot deactivate product with pending orders');
                                }
                            }
                        }
                    },
                    'priority' => 2,
                    'stopOnFailure' => true,
                    'description' => 'Validate product status changes'
                ],

                // Update modified timestamp and user
                'trackModification' => [
                    'callback' => function($model, $context) {
                        if (auth()->check()) {
                            $model->updated_by = auth()->id();
                        }
                    },
                    'priority' => 10,
                    'description' => 'Track who modified the product'
                ]
            ],

            // ========================================
            // AFTER UPDATE HOOKS
            // ========================================
            'afterUpdate' => [
                // Update search index
                'updateSearchIndex' => [
                    'callback' => function($model, $context) {
                        if (config('services.search.enabled')) {
                            \App\Services\SearchService::update($model);
                        }
                    },
                    'priority' => 1,
                    'description' => 'Update product in search index'
                ],

                // Handle category changes
                'handleCategoryChange' => [
                    'callback' => function($model, $context) {
                        if ($model->wasChanged('category_id')) {
                            $oldCategoryId = $model->getOriginal('category_id');
                            $newCategoryId = $model->category_id;

                            // Update old category count
                            if ($oldCategoryId) {
                                \App\Models\Category::where('id', $oldCategoryId)
                                    ->decrement('products_count');
                            }

                            // Update new category count
                            if ($newCategoryId) {
                                \App\Models\Category::where('id', $newCategoryId)
                                    ->increment('products_count');
                            }
                        }
                    },
                    'priority' => 5,
                    'description' => 'Handle category change statistics'
                ],

                // Send price change notifications
                'notifyPriceChange' => [
                    'callback' => function($model, $context) {
                        if ($context->get('price_changed')) {
                            $oldPrice = $context->get('old_price');
                            $newPrice = $context->get('new_price');
                            $changePercent = (($newPrice - $oldPrice) / $oldPrice) * 100;

                            // Notify if significant price change (>10%)
                            if (abs($changePercent) > 10) {
                                $managers = \App\Models\User::where('role', 'pricing_manager')->get();
                                foreach ($managers as $manager) {
                                    $manager->notify(new \App\Notifications\SignificantPriceChange(
                                        $model, $oldPrice, $newPrice, $changePercent
                                    ));
                                }
                            }
                        }
                    },
                    'priority' => 10,
                    'description' => 'Notify significant price changes'
                ],

                // Clear related caches
                'clearCaches' => [
                    'callback' => function($model, $context) {
                        $cacheKeys = [
                            "product_{$model->id}",
                            "product_category_{$model->category_id}",
                            'featured_products',
                            'product_search_*'
                        ];

                        foreach ($cacheKeys as $key) {
                            if (str_contains($key, '*')) {
                                // Clear pattern-based cache keys
                                \Cache::flush(); // Or use more specific pattern clearing
                            } else {
                                \Cache::forget($key);
                            }
                        }
                    },
                    'priority' => 15,
                    'description' => 'Clear product-related caches'
                ]
            ],

            // ========================================
            // BEFORE DELETE HOOKS
            // ========================================
            'beforeDelete' => [
                // Check for dependencies
                'checkOrderDependencies' => [
                    'callback' => function($model, $context) {
                        $orderCount = $model->orderItems()->count();
                        if ($orderCount > 0) {
                            throw new \Exception("Cannot delete product with {$orderCount} order(s)");
                        }
                        return true;
                    },
                    'priority' => 1,
                    'stopOnFailure' => true,
                    'description' => 'Check for order dependencies'
                ],

                'checkInventoryDependencies' => [
                    'callback' => function($model, $context) {
                        $inventory = $model->inventory;
                        if ($inventory && $inventory->quantity > 0) {
                            throw new \Exception('Cannot delete product with remaining inventory');
                        }
                        return true;
                    },
                    'priority' => 2,
                    'stopOnFailure' => true,
                    'description' => 'Check inventory before deletion'
                ],

                // Archive product data
                'archiveProductData' => [
                    'callback' => function($model, $context) {
                        \App\Models\ArchivedProduct::create([
                            'original_id' => $model->id,
                            'name' => $model->name,
                            'sku' => $model->sku,
                            'price' => $model->price,
                            'category_id' => $model->category_id,
                            'data' => $model->toArray(),
                            'deleted_by' => auth()->id(),
                            'deleted_at' => now()
                        ]);
                    },
                    'priority' => 5,
                    'description' => 'Archive product data before deletion'
                ]
            ],

            // ========================================
            // AFTER DELETE HOOKS
            // ========================================
            'afterDelete' => [
                // Clean up related data
                'cleanupRelatedData' => [
                    'callback' => function($model, $context) {
                        // Delete inventory records
                        $model->inventory()->delete();
                        
                        // Delete images
                        foreach ($model->images as $image) {
                            \Storage::delete($image->url);
                            $image->delete();
                        }
                        
                        // Delete price history
                        $model->priceHistory()->delete();
                    },
                    'priority' => 1,
                    'description' => 'Clean up related product data'
                ],

                // Update category statistics
                'updateCategoryStatsAfterDelete' => [
                    'callback' => function($model, $context) {
                        if ($model->category_id) {
                            \App\Models\Category::where('id', $model->category_id)
                                ->decrement('products_count');
                        }
                    },
                    'priority' => 5,
                    'description' => 'Update category statistics after deletion'
                ],

                // Remove from search index
                'removeFromSearchIndex' => [
                    'callback' => function($model, $context) {
                        if (config('services.search.enabled')) {
                            \App\Services\SearchService::delete($model->id);
                        }
                    },
                    'priority' => 10,
                    'description' => 'Remove product from search index'
                ],

                // Send deletion notifications
                'notifyDeletion' => [
                    'callback' => function($model, $context) {
                        $managers = \App\Models\User::whereIn('role', ['inventory_manager', 'product_manager'])->get();
                        foreach ($managers as $manager) {
                            $manager->notify(new \App\Notifications\ProductDeleted($model));
                        }
                    },
                    'priority' => 15,
                    'description' => 'Notify stakeholders of product deletion'
                ]
            ]
        ]);

        // ========================================
        // CONVENIENCE METHOD CONFIGURATIONS
        // ========================================

        // Configure comprehensive audit logging
        $this->configureAuditHooks([
            'fields' => ['name', 'sku', 'price', 'category_id', 'active'],
            'track_user' => true,
            'audit_table' => 'product_audit_logs'
        ]);

        // Configure notification system
        $this->configureNotificationHooks([
            'onCreate' => [
                'notification' => \App\Notifications\ProductCreated::class,
                'recipients' => ['inventory_manager', 'category_manager'],
                'channels' => ['mail', 'database'],
                'priority' => 10
            ],
            'onUpdate' => [
                'notification' => \App\Notifications\ProductUpdated::class,
                'recipients' => ['inventory_manager'],
                'channels' => ['database'],
                'watch_fields' => ['price', 'active', 'category_id'],
                'priority' => 10
            ],
            'onDelete' => [
                'notification' => \App\Notifications\ProductDeleted::class,
                'recipients' => ['product_manager', 'inventory_manager'],
                'channels' => ['mail', 'database'],
                'priority' => 5
            ]
        ]);

        // Configure cache invalidation
        $this->configureCacheInvalidationHooks([
            'product_{model_id}',
            'products_category_{category_id}',
            'featured_products',
            'product_search',
            'category_products_{category_id}'
        ], ['priority' => 20]);

        // Configure automatic slug generation
        $this->configureSlugHooks('name', 'slug', [
            'unique' => true,
            'overwrite' => false,
            'separator' => '-'
        ]);

        // Configure permission checks
        $this->configurePermissionHooks([
            'create' => 'create-products',
            'update' => function($user, $model, $action) {
                // Product managers can update any product
                // Category managers can only update products in their categories
                if ($user->can('update-any-product')) {
                    return true;
                }
                
                if ($user->can('update-category-products')) {
                    $managedCategories = $user->managedCategories->pluck('id')->toArray();
                    return in_array($model->category_id, $managedCategories);
                }
                
                return false;
            },
            'delete' => ['delete-products', 'admin-access']
        ], [
            'throw_on_failure' => true
        ]);

        // Configure validation hooks
        $this->configureValidationHooks([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:products,sku',
            'price' => 'required|numeric|min:0.01',
            'category_id' => 'required|exists:categories,id'
        ], [
            'stop_on_failure' => true,
            'messages' => [
                'sku.unique' => 'This SKU is already in use by another product',
                'price.min' => 'Product price must be greater than 0'
            ]
        ]);
    }

    /**
     * Generate unique SKU for product
     */
    private function generateSKU($product): string
    {
        $prefix = $product->category ? strtoupper(substr($product->category->name, 0, 3)) : 'PRD';
        $timestamp = now()->format('ymd');
        $random = strtoupper(\Str::random(4));
        
        return "{$prefix}-{$timestamp}-{$random}";
    }

    /**
     * Get default relationships to load
     */
    protected function getDefaultRelationships(): array
    {
        return ['category:id,name', 'inventory:id,product_id,quantity'];
    }

    /**
     * Apply default scopes
     */
    protected function applyDefaultScopes($query, Request $request): void
    {
        // Only show active products by default
        if (!$request->has('active')) {
            $query->where('active', true);
        }
    }
}