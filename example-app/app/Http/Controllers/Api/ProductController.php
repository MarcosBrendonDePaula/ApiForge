<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use MarcosBrendon\ApiForge\Traits\HasAdvancedFilters;

class ProductController extends Controller
{
    use HasAdvancedFilters;

    /**
     * Classe do modelo
     */
    protected function getModelClass(): string
    {
        return Product::class;
    }

    /**
     * Configuração dos filtros para e-commerce
     */
    protected function setupFilterConfiguration(): void
    {
        $this->configureFilters([
            // Filtros básicos de produto
            'name' => [
                'type' => 'string',
                'operators' => ['eq', 'like', 'ne'],
                'searchable' => true,
                'sortable' => true,
                'description' => 'Nome do produto',
                'example' => [
                    'like' => 'name=iPhone*',
                    'eq' => 'name=iPhone 15 Pro'
                ]
            ],
            'description' => [
                'type' => 'text',
                'operators' => ['like'],
                'searchable' => true,
                'description' => 'Descrição do produto',
                'example' => [
                    'like' => 'description=*wireless*'
                ]
            ],
            'sku' => [
                'type' => 'string',
                'operators' => ['eq', 'like', 'in'],
                'sortable' => true,
                'description' => 'Código SKU do produto',
                'example' => [
                    'eq' => 'sku=IPH15PRO256',
                    'like' => 'sku=IPH*'
                ]
            ],

            // Filtros de preço
            'price' => [
                'type' => 'decimal',
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'description' => 'Preço do produto',
                'example' => [
                    'gte' => 'price=>=100',
                    'between' => 'price=100|500',
                    'lte' => 'price=<=1000'
                ]
            ],
            'sale_price' => [
                'type' => 'decimal',
                'operators' => ['null', 'not_null', 'gt', 'gte', 'lt', 'lte'],
                'sortable' => true,
                'description' => 'Preço promocional',
                'example' => [
                    'not_null' => 'sale_price=!null',
                    'lte' => 'sale_price=<=500'
                ]
            ],

            // Filtros de marca e categoria
            'brand' => [
                'type' => 'string',
                'operators' => ['eq', 'in', 'like'],
                'sortable' => true,
                'description' => 'Marca do produto',
                'example' => [
                    'eq' => 'brand=Apple',
                    'in' => 'brand=Apple,Samsung,Google'
                ]
            ],
            'category_id' => [
                'type' => 'integer',
                'operators' => ['eq', 'in'],
                'description' => 'ID da categoria',
                'example' => [
                    'eq' => 'category_id=1',
                    'in' => 'category_id=1,2,3'
                ]
            ],

            // Filtros de estoque
            'stock_quantity' => [
                'type' => 'integer',
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'description' => 'Quantidade em estoque',
                'example' => [
                    'gt' => 'stock_quantity=>0',
                    'lte' => 'stock_quantity=<=10'
                ]
            ],

            // Filtros de status e flags
            'status' => [
                'type' => 'enum',
                'values' => ['active', 'inactive', 'discontinued'],
                'operators' => ['eq', 'in', 'ne'],
                'sortable' => true,
                'description' => 'Status do produto',
                'example' => [
                    'eq' => 'status=active',
                    'ne' => 'status=!=discontinued'
                ]
            ],
            'featured' => [
                'type' => 'boolean',
                'operators' => ['eq'],
                'sortable' => true,
                'description' => 'Produto em destaque',
                'example' => [
                    'eq' => 'featured=true'
                ]
            ],

            // Filtros de avaliação
            'rating' => [
                'type' => 'decimal',
                'operators' => ['gte', 'lte', 'between'],
                'sortable' => true,
                'description' => 'Avaliação média (0-5)',
                'example' => [
                    'gte' => 'rating=>=4.0',
                    'between' => 'rating=4.0|5.0'
                ]
            ],
            'reviews_count' => [
                'type' => 'integer',
                'operators' => ['gte', 'lte'],
                'sortable' => true,
                'description' => 'Número de avaliações',
                'example' => [
                    'gte' => 'reviews_count=>=10'
                ]
            ],

            // Filtros de relacionamento (categoria)
            'category.name' => [
                'type' => 'string',
                'operators' => ['eq', 'like', 'in'],
                'sortable' => true,
                'description' => 'Nome da categoria',
                'example' => [
                    'eq' => 'category.name=Electronics',
                    'like' => 'category.name=*Phone*'
                ]
            ],
            'category.active' => [
                'type' => 'boolean',
                'operators' => ['eq'],
                'description' => 'Categoria ativa',
                'example' => [
                    'eq' => 'category.active=true'
                ]
            ],

            // Filtros de data
            'created_at' => [
                'type' => 'datetime',
                'operators' => ['gte', 'lte', 'between'],
                'sortable' => true,
                'description' => 'Data de criação',
                'example' => [
                    'gte' => 'created_at=>=2024-01-01'
                ]
            ]
        ]);

        // Configurar field selection otimizada para e-commerce
        $this->configureFieldSelection([
            'selectable_fields' => [
                'id', 'name', 'description', 'price', 'sale_price', 'sku', 
                'brand', 'stock_quantity', 'status', 'featured', 'rating', 
                'reviews_count', 'weight', 'created_at', 'updated_at',
                'category.id', 'category.name', 'category.slug',
                'images.url', 'images.alt_text', 'images.is_primary'
            ],
            'required_fields' => ['id', 'name', 'price'],
            'default_fields' => [
                'id', 'name', 'price', 'sale_price', 'brand', 
                'stock_quantity', 'status', 'featured', 'rating'
            ],
            'field_aliases' => [
                'product_id' => 'id',
                'product_name' => 'name',
                'product_price' => 'price',
                'category_name' => 'category.name',
                'primary_image' => 'images.url'
            ],
            'max_fields' => 25
        ]);
    }

    /**
     * Relacionamentos padrão para otimização
     */
    protected function getDefaultRelationships(): array
    {
        return ['category', 'images' => function ($query) {
            $query->orderBy('sort_order')->orderBy('is_primary', 'desc');
        }];
    }

    /**
     * Scopes padrão - apenas produtos ativos
     */
    protected function applyDefaultScopes($query, Request $request): void
    {
        // Por padrão, mostrar apenas produtos ativos
        if (!$request->has('status')) {
            $query->where('status', 'active');
        }
    }

    /**
     * Ordenação padrão por popularidade
     */
    protected function getDefaultSort(): array
    {
        return ['featured', 'desc'];
    }

    /**
     * Endpoint principal de produtos
     */
    public function index(Request $request): JsonResponse
    {
        return $this->indexWithFilters($request, [
            'cache' => true,
            'cache_ttl' => 3600, // 1 hora
            'per_page' => 12 // Típico para e-commerce
        ]);
    }

    /**
     * Busca rápida de produtos
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = min(20, (int) $request->get('limit', 8));
        $category = $request->get('category');

        if (empty($query)) {
            return response()->json([
                'success' => false,
                'message' => 'Query parameter "q" is required'
            ], 400);
        }

        $products = Product::where('status', 'active')
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%")
                  ->orWhere('brand', 'LIKE', "%{$query}%")
                  ->orWhere('sku', 'LIKE', "%{$query}%");
            })
            ->when($category, function ($q) use ($category) {
                $q->whereHas('category', function ($cat) use ($category) {
                    $cat->where('name', 'LIKE', "%{$category}%")
                        ->orWhere('slug', $category);
                });
            })
            ->with(['category:id,name,slug', 'images' => function ($q) {
                $q->where('is_primary', true)->select('product_id', 'url', 'alt_text');
            }])
            ->select('id', 'name', 'price', 'sale_price', 'brand', 'category_id', 'rating', 'stock_quantity')
            ->orderByDesc('featured')
            ->orderByDesc('rating')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products,
            'meta' => [
                'query' => $query,
                'category' => $category,
                'count' => $products->count(),
                'limit' => $limit
            ]
        ]);
    }

    /**
     * Produtos em destaque
     */
    public function featured(Request $request): JsonResponse
    {
        $limit = min(20, (int) $request->get('limit', 8));

        $products = Product::where('status', 'active')
            ->where('featured', true)
            ->with(['category:id,name,slug', 'images' => function ($q) {
                $q->where('is_primary', true)->select('product_id', 'url', 'alt_text');
            }])
            ->select('id', 'name', 'price', 'sale_price', 'brand', 'category_id', 'rating')
            ->orderByDesc('rating')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products,
            'meta' => [
                'count' => $products->count(),
                'limit' => $limit
            ]
        ]);
    }

    /**
     * Estatísticas de produtos
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_products' => Product::count(),
            'active_products' => Product::where('status', 'active')->count(),
            'featured_products' => Product::where('featured', true)->count(),
            'out_of_stock' => Product::where('stock_quantity', 0)->count(),
            'low_stock' => Product::whereColumn('stock_quantity', '<=', 'min_stock')->count(),
            'products_by_brand' => Product::where('status', 'active')
                ->selectRaw('brand, COUNT(*) as count')
                ->groupBy('brand')
                ->orderByDesc('count')
                ->limit(10)
                ->pluck('count', 'brand'),
            'products_by_category' => Product::where('status', 'active')
                ->join('categories', 'products.category_id', '=', 'categories.id')
                ->selectRaw('categories.name, COUNT(*) as count')
                ->groupBy('categories.name')
                ->orderByDesc('count')
                ->limit(10)
                ->pluck('count', 'categories.name'),
            'average_price' => Product::where('status', 'active')->avg('price'),
            'price_range' => [
                'min' => Product::where('status', 'active')->min('price'),
                'max' => Product::where('status', 'active')->max('price')
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'generated_at' => now()->toISOString()
        ]);
    }

    /**
     * Mostrar produto específico
     */
    public function show(Request $request, $id): JsonResponse
    {
        $fields = $request->get('fields');
        $query = Product::with(['category', 'images']);

        if ($fields) {
            $this->initializeFilterServices();
            $this->applyFieldSelection($query, $request);
        }

        $product = $query->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }
}