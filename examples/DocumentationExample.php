<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\Request;
use MarcosBrendon\ApiForge\Traits\HasAdvancedFilters;
use App\Http\Controllers\Controller;

/**
 * Example controller demonstrating comprehensive ApiForge documentation generation
 * 
 * This controller showcases all features that will be documented by the AI:
 * - Complex filter configurations
 * - Field selection with relationships
 * - Validation rules
 * - Custom scopes and relationships
 * - Multiple data types and operators
 */
class ProductController extends Controller
{
    use HasAdvancedFilters;

    /**
     * Get the model class for this controller
     *
     * @return string
     */
    protected function getModelClass(): string
    {
        return Product::class;
    }

    /**
     * Setup comprehensive filter configuration
     * This demonstrates all features that will be documented
     *
     * @return void
     */
    protected function setupFilterConfiguration(): void
    {
        // Configure extensive filters for documentation example
        $this->configureFilters([
            // String filters with multiple operators
            'name' => [
                'type' => 'string',
                'operators' => ['eq', 'like', 'ne', 'starts_with', 'ends_with'],
                'description' => 'Product name with flexible text matching',
                'searchable' => true,
                'sortable' => true,
                'example' => [
                    'eq' => 'name=iPhone 15',
                    'like' => 'name=*iPhone*',
                    'starts_with' => 'name=iPhone*',
                    'ne' => 'name=!=Samsung'
                ]
            ],
            
            'sku' => [
                'type' => 'string',
                'operators' => ['eq', 'like', 'in'],
                'description' => 'Stock Keeping Unit - unique product identifier',
                'searchable' => true,
                'sortable' => true,
                'validation' => ['regex:/^[A-Z0-9-]+$/'],
                'example' => [
                    'eq' => 'sku=IPHONE-15-PRO-256',
                    'like' => 'sku=IPHONE*',
                    'in' => 'sku=SKU001,SKU002,SKU003'
                ]
            ],

            // Numeric filters
            'price' => [
                'type' => 'float',
                'operators' => ['eq', 'gte', 'lte', 'gt', 'lt', 'between'],
                'description' => 'Product price in USD',
                'sortable' => true,
                'min' => 0,
                'max' => 99999.99,
                'example' => [
                    'gte' => 'price=>=100.00',
                    'between' => 'price=50.00|500.00',
                    'lt' => 'price=<1000'
                ]
            ],

            'stock_quantity' => [
                'type' => 'integer',
                'operators' => ['eq', 'gte', 'lte', 'gt', 'lt', 'between'],
                'description' => 'Available stock quantity',
                'sortable' => true,
                'min' => 0,
                'example' => [
                    'gte' => 'stock_quantity=>=10',
                    'eq' => 'stock_quantity=0',
                    'between' => 'stock_quantity=1|100'
                ]
            ],

            // Boolean filters
            'is_active' => [
                'type' => 'boolean',
                'operators' => ['eq'],
                'description' => 'Whether the product is active and available for sale',
                'example' => [
                    'eq' => 'is_active=true'
                ]
            ],

            'is_featured' => [
                'type' => 'boolean',
                'operators' => ['eq'],
                'description' => 'Featured products displayed prominently',
                'example' => [
                    'eq' => 'is_featured=true'
                ]
            ],

            // Enum filters
            'status' => [
                'type' => 'enum',
                'values' => ['draft', 'published', 'archived', 'out_of_stock'],
                'operators' => ['eq', 'ne', 'in', 'not_in'],
                'description' => 'Product publication status',
                'sortable' => true,
                'example' => [
                    'eq' => 'status=published',
                    'in' => 'status=published,draft',
                    'ne' => 'status=!=archived'
                ]
            ],

            'category' => [
                'type' => 'enum',
                'values' => ['electronics', 'clothing', 'books', 'home', 'sports', 'automotive'],
                'operators' => ['eq', 'in', 'ne'],
                'description' => 'Product category classification',
                'sortable' => true,
                'example' => [
                    'eq' => 'category=electronics',
                    'in' => 'category=electronics,clothing'
                ]
            ],

            // Date filters
            'created_at' => [
                'type' => 'datetime',
                'operators' => ['eq', 'gte', 'lte', 'gt', 'lt', 'between'],
                'description' => 'Product creation date and time',
                'sortable' => true,
                'format' => 'Y-m-d H:i:s',
                'example' => [
                    'gte' => 'created_at=>=2024-01-01',
                    'between' => 'created_at=2024-01-01|2024-12-31',
                    'eq' => 'created_at=2024-01-15'
                ]
            ],

            'last_sold_at' => [
                'type' => 'datetime',
                'operators' => ['null', 'not_null', 'gte', 'lte'],
                'description' => 'Date when product was last sold',
                'sortable' => true,
                'example' => [
                    'null' => 'last_sold_at=null',
                    'gte' => 'last_sold_at=>=2024-01-01'
                ]
            ],

            // Relationship filters
            'brand_name' => [
                'type' => 'string',
                'operators' => ['eq', 'like', 'in'],
                'description' => 'Filter by brand name (relationship field)',
                'relationship' => 'brand',
                'searchable' => true,
                'example' => [
                    'eq' => 'brand_name=Apple',
                    'like' => 'brand_name=*Samsung*',
                    'in' => 'brand_name=Apple,Samsung,Google'
                ]
            ],

            'supplier_country' => [
                'type' => 'string',
                'operators' => ['eq', 'in'],
                'description' => 'Filter by supplier country',
                'relationship' => 'supplier',
                'example' => [
                    'eq' => 'supplier_country=USA',
                    'in' => 'supplier_country=USA,China,Germany'
                ]
            ],

            // JSON field filters
            'specifications' => [
                'type' => 'json',
                'operators' => ['null', 'not_null'],
                'description' => 'Product specifications stored as JSON',
                'path' => 'weight', // JSON path for nested filtering
                'example' => [
                    'not_null' => 'specifications=!null'
                ]
            ],

            // Complex validation examples
            'weight' => [
                'type' => 'float',
                'operators' => ['gte', 'lte', 'between'],
                'description' => 'Product weight in kilograms',
                'min' => 0.001,
                'max' => 1000,
                'validation' => ['numeric', 'min:0.001', 'max:1000'],
                'example' => [
                    'gte' => 'weight=>=0.5',
                    'between' => 'weight=0.1|10.0'
                ]
            ],

            // Required filters example
            'tenant_id' => [
                'type' => 'integer',
                'operators' => ['eq'],
                'required' => true,
                'description' => 'Tenant ID - required for multi-tenant filtering',
                'example' => [
                    'eq' => 'tenant_id=1'
                ]
            ]
        ]);

        // Configure comprehensive field selection
        $this->configureFieldSelection([
            'selectable_fields' => [
                // Basic product fields
                'id', 'name', 'sku', 'description', 'price', 'stock_quantity',
                'is_active', 'is_featured', 'status', 'category', 'weight',
                'created_at', 'updated_at', 'last_sold_at',
                
                // Relationship fields
                'brand.id', 'brand.name', 'brand.logo_url',
                'supplier.id', 'supplier.name', 'supplier.country',
                'reviews.rating', 'reviews.comment', 'reviews.created_at',
                'variants.id', 'variants.size', 'variants.color', 'variants.price',
                
                // Computed/accessor fields
                'formatted_price', 'is_in_stock', 'average_rating', 'review_count'
            ],
            
            'required_fields' => ['id', 'name', 'price'],
            
            'blocked_fields' => [
                'internal_cost', 'profit_margin', 'supplier_cost',
                'deleted_at', 'internal_notes'
            ],
            
            'default_fields' => [
                'id', 'name', 'sku', 'price', 'stock_quantity', 
                'is_active', 'status', 'created_at'
            ],
            
            'field_aliases' => [
                'product_id' => 'id',
                'product_name' => 'name',
                'product_price' => 'price',
                'stock' => 'stock_quantity',
                'active' => 'is_active',
                'featured' => 'is_featured',
                'brand_name' => 'brand.name',
                'supplier_name' => 'supplier.name'
            ],
            
            'max_fields' => 30
        ]);
    }

    /**
     * Get default relationships to load
     * This will be documented in the API spec
     *
     * @return array
     */
    protected function getDefaultRelationships(): array
    {
        return [
            'brand:id,name,logo_url',
            'supplier:id,name,country',
            'category:id,name,slug'
        ];
    }

    /**
     * Apply default scopes to the query
     * This demonstrates business logic that will be documented
     *
     * @param mixed $query
     * @param Request $request
     * @return void
     */
    protected function applyDefaultScopes($query, Request $request): void
    {
        // Only show active products by default
        if (!$request->has('is_active') && !$request->has('status')) {
            $query->where('is_active', true);
        }

        // Multi-tenant scoping
        if (auth()->check() && auth()->user()->tenant_id) {
            $query->where('tenant_id', auth()->user()->tenant_id);
        }

        // Hide deleted products unless specifically requested
        if (!$request->has('include_deleted')) {
            $query->whereNull('deleted_at');
        }
    }

    /**
     * Get default sort configuration
     *
     * @return array [field, direction]
     */
    protected function getDefaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    /**
     * Main endpoint that will be documented
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        return $this->indexWithFilters($request);
    }

    /**
     * Show single product endpoint
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, int $id)
    {
        $this->initializeFilterServices();
        
        $query = $this->buildBaseQuery($request);
        $product = $query->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    /**
     * Validation rules for product creation
     * This will be included in documentation
     *
     * @param Request $request
     * @return array
     */
    protected function validateStoreData(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:products,sku|regex:/^[A-Z0-9-]+$/',
            'description' => 'nullable|string|max:2000',
            'price' => 'required|numeric|min:0|max:99999.99',
            'stock_quantity' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'status' => 'required|in:draft,published,archived,out_of_stock',
            'category' => 'required|in:electronics,clothing,books,home,sports,automotive',
            'weight' => 'nullable|numeric|min:0.001|max:1000',
            'brand_id' => 'required|exists:brands,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'specifications' => 'nullable|array',
            'specifications.dimensions' => 'nullable|array',
            'specifications.features' => 'nullable|array',
        ]);
    }

    /**
     * Validation rules for product updates
     *
     * @param Request $request
     * @param mixed $product
     * @return array
     */
    protected function validateUpdateData(Request $request, $product): array
    {
        return $request->validate([
            'name' => 'sometimes|string|max:255',
            'sku' => 'sometimes|string|unique:products,sku,' . $product->id . '|regex:/^[A-Z0-9-]+$/',
            'description' => 'sometimes|nullable|string|max:2000',
            'price' => 'sometimes|numeric|min:0|max:99999.99',
            'stock_quantity' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'status' => 'sometimes|in:draft,published,archived,out_of_stock',
            'category' => 'sometimes|in:electronics,clothing,books,home,sports,automotive',
            'weight' => 'sometimes|nullable|numeric|min:0.001|max:1000',
            'brand_id' => 'sometimes|exists:brands,id',
            'supplier_id' => 'sometimes|exists:suppliers,id',
            'specifications' => 'sometimes|nullable|array',
        ]);
    }

    /**
     * Transform data before storing
     *
     * @param array $data
     * @param Request $request
     * @return array
     */
    protected function transformStoreData(array $data, Request $request): array
    {
        // Set default values
        $data['is_active'] = $data['is_active'] ?? true;
        $data['is_featured'] = $data['is_featured'] ?? false;
        
        // Add tenant ID for multi-tenant setup
        if (auth()->check() && auth()->user()->tenant_id) {
            $data['tenant_id'] = auth()->user()->tenant_id;
        }

        // Generate SKU if not provided
        if (empty($data['sku'])) {
            $data['sku'] = $this->generateSku($data['name'], $data['category']);
        }

        // Process specifications JSON
        if (isset($data['specifications']) && is_array($data['specifications'])) {
            $data['specifications'] = json_encode($data['specifications']);
        }

        return $data;
    }

    /**
     * Get custom statistics for the API
     * This demonstrates additional endpoints that can be documented
     *
     * @param mixed $query
     * @param Request $request
     * @return array
     */
    protected function getCustomStatistics($query, Request $request): array
    {
        $baseQuery = clone $query;

        return [
            'total_products' => (clone $baseQuery)->count(),
            'active_products' => (clone $baseQuery)->where('is_active', true)->count(),
            'featured_products' => (clone $baseQuery)->where('is_featured', true)->count(),
            'out_of_stock' => (clone $baseQuery)->where('stock_quantity', 0)->count(),
            'low_stock' => (clone $baseQuery)->where('stock_quantity', '<=', 10)->where('stock_quantity', '>', 0)->count(),
            'categories' => (clone $baseQuery)->select('category')->groupBy('category')->pluck('category'),
            'price_range' => [
                'min' => (clone $baseQuery)->min('price'),
                'max' => (clone $baseQuery)->max('price'),
                'avg' => round((clone $baseQuery)->avg('price'), 2)
            ],
            'recent_products' => (clone $baseQuery)->where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }

    /**
     * Generate automatic SKU
     * Supporting method for data transformation
     *
     * @param string $name
     * @param string $category
     * @return string
     */
    protected function generateSku(string $name, string $category): string
    {
        $namePrefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 8));
        $categoryPrefix = strtoupper(substr($category, 0, 4));
        $timestamp = now()->format('Ymd');
        
        return $categoryPrefix . '-' . $namePrefix . '-' . $timestamp;
    }

    /**
     * Additional endpoint for bulk operations
     * This will also be documented
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'products' => 'required|array',
            'products.*.id' => 'required|exists:products,id',
            'products.*.price' => 'sometimes|numeric|min:0',
            'products.*.stock_quantity' => 'sometimes|integer|min:0',
            'products.*.is_active' => 'sometimes|boolean'
        ]);

        // Process bulk updates...
        
        return response()->json([
            'success' => true,
            'message' => 'Bulk update completed',
            'updated_count' => count($validated['products'])
        ]);
    }

    /**
     * Search endpoint with different logic
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        $query = $request->validate(['q' => 'required|string|min:2']);
        
        $this->initializeFilterServices();
        
        $products = $this->getModelClass()::where('name', 'LIKE', "%{$query['q']}%")
            ->orWhere('sku', 'LIKE', "%{$query['q']}%")
            ->orWhere('description', 'LIKE', "%{$query['q']}%")
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products,
            'query' => $query['q'],
            'count' => $products->count()
        ]);
    }
}

/*
 * To generate documentation for this controller, run:
 * 
 * php artisan apiforge:docs "App\Http\Controllers\Api\ProductController" --format=json
 * 
 * This will create comprehensive OpenAPI 3.0 documentation including:
 * 
 * 1. All filter parameters with examples
 * 2. Field selection capabilities
 * 3. Pagination parameters
 * 4. Sorting options
 * 5. Response schemas
 * 6. Error responses
 * 7. Validation rules
 * 8. Multiple endpoints (index, show, search, bulkUpdate)
 * 9. Relationship documentation
 * 10. Business logic explanations generated by AI
 * 
 * The AI will analyze all the configuration and generate professional
 * descriptions, examples, and documentation automatically.
 */