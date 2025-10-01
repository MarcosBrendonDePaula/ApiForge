<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\Request;
use MarcosBrendon\ApiForge\Http\Controllers\BaseApiController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Performance Optimization Examples
 * 
 * This demonstrates various performance optimization techniques
 * for virtual fields and hooks in high-traffic scenarios.
 */
class PerformanceOptimizationExamples extends BaseApiController
{
    protected function getModelClass(): string
    {
        return Product::class;
    }

    protected function setupFilterConfiguration(): void
    {
        // Configure regular filters
        $this->configureFilters([
            'name' => [
                'type' => 'string',
                'operators' => ['eq', 'like', 'ne'],
                'searchable' => true,
                'sortable' => true
            ],
            'category_id' => [
                'type' => 'integer',
                'operators' => ['eq', 'in', 'ne'],
                'description' => 'Product category'
            ],
            'price' => [
                'type' => 'float',
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true
            ],
            'active' => [
                'type' => 'boolean',
                'operators' => ['eq']
            ]
        ]);

        // Configure performance-optimized virtual fields
        $this->configureVirtualFields([
            // Example 1: Cached expensive calculation
            'popularity_score' => [
                'type' => 'float',
                'callback' => function($product) {
                    // This would be expensive to calculate every time
                    return Cache::remember(
                        "product_popularity_{$product->id}",
                        3600, // 1 hour cache
                        function() use ($product) {
                            // Complex calculation involving multiple tables
                            $views = $product->views()->count();
                            $orders = $product->orderItems()->count();
                            $reviews = $product->reviews()->count();
                            $avgRating = $product->reviews()->avg('rating') ?? 0;
                            
                            // Weighted popularity score
                            return round(
                                ($views * 0.1) + 
                                ($orders * 2.0) + 
                                ($reviews * 1.5) + 
                                ($avgRating * 10),
                                2
                            );
                        }
                    );
                },
                'relationships' => [], // No relationships needed due to caching
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'cacheable' => true,
                'cache_ttl' => 3600,
                'description' => 'Product popularity score based on views, orders, and reviews'
            ],

            // Example 2: Optimized with database aggregates
            'total_sales' => [
                'type' => 'integer',
                'callback' => function($product) {
                    // Use existing aggregate column if available, fallback to calculation
                    return $product->total_sales ?? 
                           $product->orderItems()->sum('quantity');
                },
                'relationships' => [], // Avoid loading relationships when aggregate exists
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'cacheable' => true,
                'cache_ttl' => 1800, // 30 minutes
                'description' => 'Total units sold'
            ],

            // Example 3: Batch-optimized calculation
            'revenue_generated' => [
                'type' => 'float',
                'callback' => function($product) {
                    // Use pre-calculated value if available
                    if (isset($product->revenue_generated)) {
                        return $product->revenue_generated;
                    }
                    
                    // Fallback to calculation (will be batch-processed)
                    return $product->orderItems()
                        ->join('orders', 'order_items.order_id', '=', 'orders.id')
                        ->where('orders.status', '!=', 'cancelled')
                        ->sum(DB::raw('order_items.quantity * order_items.price'));
                },
                'relationships' => [], // Handled by raw query
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'cacheable' => true,
                'cache_ttl' => 3600,
                'description' => 'Total revenue generated by product'
            ],

            // Example 4: Lightweight calculation with dependencies
            'profit_margin' => [
                'type' => 'float',
                'callback' => function($product) {
                    if ($product->price <= 0) return 0;
                    return round((($product->price - $product->cost) / $product->price) * 100, 2);
                },
                'dependencies' => ['price', 'cost'], // Only load these fields
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'description' => 'Profit margin percentage'
            ],

            // Example 5: Conditional expensive calculation
            'detailed_analytics' => [
                'type' => 'array',
                'callback' => function($product) {
                    // Only calculate if specifically requested
                    if (!request()->has('include_analytics')) {
                        return null;
                    }
                    
                    return Cache::remember(
                        "product_analytics_{$product->id}",
                        1800,
                        function() use ($product) {
                            return [
                                'monthly_sales' => $this->getMonthlyProductSales($product),
                                'customer_segments' => $this->getCustomerSegments($product),
                                'seasonal_trends' => $this->getSeasonalTrends($product),
                                'competitor_analysis' => $this->getCompetitorAnalysis($product)
                            ];
                        }
                    );
                },
                'relationships' => [],
                'operators' => ['null', 'not_null'],
                'nullable' => true,
                'cacheable' => true,
                'cache_ttl' => 1800,
                'description' => 'Detailed product analytics (only when requested)'
            ],

            // Example 6: Memory-efficient relationship counting
            'review_count' => [
                'type' => 'integer',
                'callback' => function($product) {
                    // Use withCount() result if available
                    return $product->reviews_count ?? $product->reviews()->count();
                },
                'relationships' => [], // Handled by withCount()
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'description' => 'Number of customer reviews'
            ],

            // Example 7: Optimized enum calculation
            'stock_status' => [
                'type' => 'enum',
                'values' => ['out_of_stock', 'low_stock', 'in_stock', 'overstocked'],
                'callback' => function($product) {
                    $stock = $product->stock_quantity;
                    $lowThreshold = $product->low_stock_threshold ?? 10;
                    $highThreshold = $product->high_stock_threshold ?? 1000;
                    
                    if ($stock <= 0) return 'out_of_stock';
                    if ($stock <= $lowThreshold) return 'low_stock';
                    if ($stock >= $highThreshold) return 'overstocked';
                    return 'in_stock';
                },
                'dependencies' => ['stock_quantity', 'low_stock_threshold', 'high_stock_threshold'],
                'operators' => ['eq', 'in', 'ne'],
                'description' => 'Current stock status'
            ],

            // Example 8: Time-based caching with different TTLs
            'trending_score' => [
                'type' => 'float',
                'callback' => function($product) {
                    $cacheKey = "trending_score_{$product->id}";
                    $hour = now()->hour;
                    
                    // Different cache TTL based on time of day
                    $ttl = ($hour >= 9 && $hour <= 17) ? 300 : 1800; // 5 min during business hours, 30 min otherwise
                    
                    return Cache::remember($cacheKey, $ttl, function() use ($product) {
                        // Calculate trending score based on recent activity
                        $recentViews = $product->views()
                            ->where('created_at', '>=', now()->subHours(24))
                            ->count();
                        
                        $recentOrders = $product->orderItems()
                            ->whereHas('order', function($q) {
                                $q->where('created_at', '>=', now()->subHours(24));
                            })
                            ->sum('quantity');
                        
                        return round(($recentViews * 0.1) + ($recentOrders * 2.0), 2);
                    });
                },
                'relationships' => [],
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'cacheable' => true,
                'cache_ttl' => 300, // Will be overridden by callback logic
                'description' => 'Trending score based on recent activity'
            ]
        ]);

        // Configure performance-optimized hooks
        $this->configureModelHooks([
            'beforeStore' => [
                // Lightweight validation
                'validateBasicData' => [
                    'callback' => function($product, $context) {
                        if (empty($product->name) || empty($product->price)) {
                            throw new \Exception('Name and price are required');
                        }
                    },
                    'priority' => 1,
                    'description' => 'Basic validation without database queries'
                ],

                // Batch-friendly slug generation
                'generateSlug' => [
                    'callback' => function($product, $context) {
                        if (empty($product->slug)) {
                            $baseSlug = \Str::slug($product->name);
                            
                            // Check for duplicates efficiently
                            $existingCount = Product::where('slug', 'like', $baseSlug . '%')->count();
                            $product->slug = $existingCount > 0 ? $baseSlug . '-' . ($existingCount + 1) : $baseSlug;
                        }
                    },
                    'priority' => 2,
                    'description' => 'Generate unique slug efficiently'
                ]
            ],

            'afterStore' => [
                // Async cache warming
                'warmCache' => [
                    'callback' => function($product, $context) {
                        // Queue cache warming for expensive calculations
                        \App\Jobs\WarmProductCache::dispatch($product)->delay(now()->addMinutes(1));
                    },
                    'priority' => 10,
                    'description' => 'Queue cache warming for virtual fields'
                ],

                // Batch update aggregates
                'updateAggregates' => [
                    'callback' => function($product, $context) {
                        // Queue aggregate updates to avoid blocking
                        \App\Jobs\UpdateCategoryAggregates::dispatch($product->category_id)
                            ->delay(now()->addMinutes(5));
                    },
                    'priority' => 15,
                    'description' => 'Queue aggregate updates'
                ]
            ],

            'afterUpdate' => [
                // Selective cache invalidation
                'invalidateCache' => [
                    'callback' => function($product, $context) {
                        $changedFields = array_keys($product->getDirty());
                        
                        // Only invalidate relevant caches
                        if (array_intersect($changedFields, ['price', 'cost'])) {
                            Cache::forget("product_popularity_{$product->id}");
                        }
                        
                        if (array_intersect($changedFields, ['stock_quantity'])) {
                            Cache::forget("product_analytics_{$product->id}");
                        }
                        
                        // Always invalidate trending score on any change
                        Cache::forget("trending_score_{$product->id}");
                    },
                    'priority' => 5,
                    'description' => 'Selective cache invalidation based on changed fields'
                ]
            ]
        ]);

        // Configure field selection for performance
        $this->configureFieldSelection([
            'selectable_fields' => [
                'id', 'name', 'slug', 'price', 'cost', 'stock_quantity', 'active',
                'category.name', 'brand.name',
                // Virtual fields
                'popularity_score', 'total_sales', 'revenue_generated', 'profit_margin',
                'detailed_analytics', 'review_count', 'stock_status', 'trending_score'
            ],
            'default_fields' => [
                'id', 'name', 'price', 'stock_status', 'popularity_score'
            ],
            'blocked_fields' => ['cost'], // Hide sensitive cost data
            'max_fields' => 15 // Limit to prevent performance issues
        ]);
    }

    /**
     * Override to add performance optimizations
     */
    protected function getFilteredQuery(Request $request)
    {
        $query = parent::getFilteredQuery($request);
        
        // Add performance optimizations
        $this->optimizeQueryForVirtualFields($query, $request);
        
        return $query;
    }

    /**
     * Optimize query based on requested virtual fields
     */
    private function optimizeQueryForVirtualFields($query, Request $request): void
    {
        $requestedFields = $this->getRequestedFields($request);
        
        // Add withCount() for relationship counting virtual fields
        if (in_array('review_count', $requestedFields)) {
            $query->withCount('reviews');
        }
        
        // Add select() to limit loaded columns
        $databaseFields = array_intersect($requestedFields, [
            'id', 'name', 'slug', 'price', 'cost', 'stock_quantity', 
            'low_stock_threshold', 'high_stock_threshold', 'active', 'category_id'
        ]);
        
        if (!empty($databaseFields)) {
            $query->select($databaseFields);
        }
        
        // Eager load relationships only when needed
        $relationships = [];
        if (array_intersect($requestedFields, ['category.name'])) {
            $relationships[] = 'category:id,name';
        }
        if (array_intersect($requestedFields, ['brand.name'])) {
            $relationships[] = 'brand:id,name';
        }
        
        if (!empty($relationships)) {
            $query->with($relationships);
        }
    }

    /**
     * Get requested fields from request
     */
    private function getRequestedFields(Request $request): array
    {
        $fields = $request->get('fields');
        if (!$fields) {
            return $this->getDefaultFields();
        }
        
        return explode(',', $fields);
    }

    /**
     * Batch process virtual fields for better performance
     */
    public function indexWithBatchProcessing(Request $request)
    {
        $query = $this->getFilteredQuery($request);
        $products = $query->get();
        
        // Batch process expensive virtual fields
        $this->batchProcessVirtualFields($products, $this->getRequestedFields($request));
        
        return response()->json([
            'success' => true,
            'data' => $products,
            'meta' => $this->getPaginationMeta($request, $query)
        ]);
    }

    /**
     * Batch process virtual fields to minimize database queries
     */
    private function batchProcessVirtualFields($products, array $requestedFields): void
    {
        $productIds = $products->pluck('id')->toArray();
        
        // Batch load data for revenue_generated if needed
        if (in_array('revenue_generated', $requestedFields)) {
            $revenues = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->whereIn('order_items.product_id', $productIds)
                ->where('orders.status', '!=', 'cancelled')
                ->select('order_items.product_id', DB::raw('SUM(order_items.quantity * order_items.price) as revenue'))
                ->groupBy('order_items.product_id')
                ->pluck('revenue', 'product_id');
            
            foreach ($products as $product) {
                $product->revenue_generated = $revenues[$product->id] ?? 0;
            }
        }
        
        // Batch load data for total_sales if needed
        if (in_array('total_sales', $requestedFields)) {
            $sales = DB::table('order_items')
                ->whereIn('product_id', $productIds)
                ->select('product_id', DB::raw('SUM(quantity) as total_sales'))
                ->groupBy('product_id')
                ->pluck('total_sales', 'product_id');
            
            foreach ($products as $product) {
                $product->total_sales = $sales[$product->id] ?? 0;
            }
        }
    }

    /**
     * Example of cached aggregation endpoint
     */
    public function categoryAnalytics(Request $request)
    {
        $categoryId = $request->get('category_id');
        $cacheKey = "category_analytics_{$categoryId}";
        
        $analytics = Cache::remember($cacheKey, 1800, function() use ($categoryId) {
            $query = Product::where('category_id', $categoryId);
            
            return [
                'total_products' => $query->count(),
                'active_products' => $query->where('active', true)->count(),
                'average_price' => $query->avg('price'),
                'total_revenue' => $query->join('order_items', 'products.id', '=', 'order_items.product_id')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->where('orders.status', '!=', 'cancelled')
                    ->sum(DB::raw('order_items.quantity * order_items.price')),
                'low_stock_count' => $query->where('stock_quantity', '<=', DB::raw('low_stock_threshold'))->count(),
                'out_of_stock_count' => $query->where('stock_quantity', '<=', 0)->count()
            ];
        });
        
        return response()->json([
            'success' => true,
            'category_id' => $categoryId,
            'analytics' => $analytics,
            'cached_at' => now()->toISOString()
        ]);
    }

    /**
     * Performance monitoring endpoint
     */
    public function performanceMetrics(Request $request)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        // Execute the filtered query
        $result = $this->indexWithFilters($request);
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $metrics = [
            'execution_time_ms' => round(($endTime - $startTime) * 1000, 2),
            'memory_usage_mb' => round(($endMemory - $startMemory) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage() / 1024 / 1024, 2),
            'cache_hits' => $this->getCacheHitCount(),
            'database_queries' => $this->getDatabaseQueryCount()
        ];
        
        // Add metrics to response
        $response = $result->getData(true);
        $response['performance_metrics'] = $metrics;
        
        return response()->json($response);
    }

    // Helper methods for analytics calculations
    private function getMonthlyProductSales($product): array
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('order_items.product_id', $product->id)
            ->where('orders.created_at', '>=', now()->subMonths(12))
            ->select(
                DB::raw('YEAR(orders.created_at) as year'),
                DB::raw('MONTH(orders.created_at) as month'),
                DB::raw('SUM(order_items.quantity) as quantity'),
                DB::raw('SUM(order_items.quantity * order_items.price) as revenue')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->toArray();
    }

    private function getCustomerSegments($product): array
    {
        // Simplified customer segmentation
        return [
            'new_customers' => 25,
            'returning_customers' => 60,
            'vip_customers' => 15
        ];
    }

    private function getSeasonalTrends($product): array
    {
        // Simplified seasonal trends
        return [
            'spring' => 1.2,
            'summer' => 0.8,
            'fall' => 1.1,
            'winter' => 1.3
        ];
    }

    private function getCompetitorAnalysis($product): array
    {
        // Simplified competitor analysis
        return [
            'market_position' => 'strong',
            'price_competitiveness' => 0.95,
            'feature_comparison' => 'above_average'
        ];
    }

    private function getCacheHitCount(): int
    {
        // This would integrate with your cache monitoring system
        return rand(5, 15);
    }

    private function getDatabaseQueryCount(): int
    {
        // This would integrate with your query monitoring system
        return rand(3, 8);
    }
}