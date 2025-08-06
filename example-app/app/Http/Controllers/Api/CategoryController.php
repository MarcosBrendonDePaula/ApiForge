<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use MarcosBrendon\ApiForge\Traits\HasAdvancedFilters;

class CategoryController extends Controller
{
    use HasAdvancedFilters;

    /**
     * Classe do modelo
     */
    protected function getModelClass(): string
    {
        return Category::class;
    }

    /**
     * Configuração dos filtros para categorias
     */
    protected function setupFilterConfiguration(): void
    {
        $this->configureFilters([
            // Filtros básicos
            'name' => [
                'type' => 'string',
                'operators' => ['eq', 'like', 'ne'],
                'searchable' => true,
                'sortable' => true,
                'description' => 'Nome da categoria',
                'example' => [
                    'like' => 'name=Electronics*',
                    'eq' => 'name=Smartphones'
                ]
            ],
            'slug' => [
                'type' => 'string',
                'operators' => ['eq', 'like'],
                'sortable' => true,
                'description' => 'Slug da categoria (URL amigável)',
                'example' => [
                    'eq' => 'slug=electronics',
                    'like' => 'slug=smart*'
                ]
            ],
            'description' => [
                'type' => 'text',
                'operators' => ['like'],
                'searchable' => true,
                'description' => 'Descrição da categoria',
                'example' => [
                    'like' => 'description=*device*'
                ]
            ],

            // Filtros de hierarquia
            'parent_id' => [
                'type' => 'integer',
                'operators' => ['eq', 'null', 'not_null', 'in'],
                'description' => 'ID da categoria pai (null = categoria raiz)',
                'example' => [
                    'null' => 'parent_id=null',
                    'eq' => 'parent_id=1',
                    'in' => 'parent_id=1,2,3'
                ]
            ],

            // Filtros de status
            'active' => [
                'type' => 'boolean',
                'operators' => ['eq'],
                'sortable' => true,
                'description' => 'Categoria ativa',
                'example' => [
                    'eq' => 'active=true'
                ]
            ],

            // Filtros de ordenação
            'sort_order' => [
                'type' => 'integer',
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'description' => 'Ordem de exibição',
                'example' => [
                    'lte' => 'sort_order=<=10'
                ]
            ],

            // Filtros de relacionamento (categoria pai)
            'parent.name' => [
                'type' => 'string',
                'operators' => ['eq', 'like'],
                'sortable' => true,
                'description' => 'Nome da categoria pai',
                'example' => [
                    'like' => 'parent.name=Electronics*'
                ]
            ],
            'parent.active' => [
                'type' => 'boolean',
                'operators' => ['eq'],
                'description' => 'Categoria pai ativa',
                'example' => [
                    'eq' => 'parent.active=true'
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
            ],

            // Filtros computados (precisam de withCount)
            'products_count' => [
                'type' => 'integer',
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte'],
                'sortable' => true,
                'description' => 'Número de produtos na categoria',
                'example' => [
                    'gte' => 'products_count=>=10',
                    'eq' => 'products_count=0'
                ]
            ]
        ]);

        // Configurar field selection
        $this->configureFieldSelection([
            'selectable_fields' => [
                'id', 'name', 'slug', 'description', 'parent_id', 'image', 
                'icon', 'sort_order', 'active', 'meta_title', 'meta_description',
                'created_at', 'updated_at',
                'parent.id', 'parent.name', 'parent.slug',
                'children.id', 'children.name', 'children.slug', 'children.active',
                'products_count'
            ],
            'required_fields' => ['id', 'name'],
            'default_fields' => [
                'id', 'name', 'slug', 'parent_id', 'active', 'sort_order'
            ],
            'field_aliases' => [
                'category_id' => 'id',
                'category_name' => 'name',
                'parent_name' => 'parent.name',
                'children_count' => 'children_count',
                'product_count' => 'products_count'
            ],
            'max_fields' => 20
        ]);
    }

    /**
     * Relacionamentos padrão
     */
    protected function getDefaultRelationships(): array
    {
        return ['parent:id,name,slug'];
    }

    /**
     * Scopes padrão - apenas categorias ativas
     */
    protected function applyDefaultScopes($query, Request $request): void
    {
        // Adicionar contagem de produtos se solicitado
        if ($request->has('products_count') || 
            str_contains($request->get('fields', ''), 'products_count') ||
            $request->get('with_products_count')) {
            $query->withCount('products');
        }

        // Adicionar contagem de filhos se solicitado
        if (str_contains($request->get('fields', ''), 'children') ||
            $request->get('with_children_count')) {
            $query->withCount('children');
        }

        // Por padrão, mostrar apenas categorias ativas
        if (!$request->has('active')) {
            $query->where('active', true);
        }
    }

    /**
     * Ordenação padrão por sort_order
     */
    protected function getDefaultSort(): array
    {
        return ['sort_order', 'asc'];
    }

    /**
     * Endpoint principal de categorias
     */
    public function index(Request $request): JsonResponse
    {
        return $this->indexWithFilters($request, [
            'cache' => true,
            'cache_ttl' => 3600, // 1 hora (categorias mudam pouco)
            'per_page' => 50
        ]);
    }

    /**
     * Árvore de categorias hierárquica
     */
    public function tree(Request $request): JsonResponse
    {
        $maxDepth = min(5, (int) $request->get('max_depth', 3));
        $withProducts = $request->boolean('with_products', false);

        // Buscar apenas categorias raiz
        $rootCategories = Category::with($this->buildTreeRelations($maxDepth, $withProducts))
            ->where('active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rootCategories,
            'meta' => [
                'max_depth' => $maxDepth,
                'with_products' => $withProducts,
                'count' => $rootCategories->count()
            ]
        ]);
    }

    /**
     * Construir relacionamentos para árvore hierárquica
     */
    private function buildTreeRelations(int $depth, bool $withProducts = false): array
    {
        $relations = [];
        
        for ($i = 1; $i <= $depth; $i++) {
            $relation = str_repeat('children.', $i - 1) . 'children';
            
            $relations[$relation] = function ($query) use ($withProducts) {
                $query->where('active', true)
                      ->orderBy('sort_order');
                
                if ($withProducts) {
                    $query->withCount('products');
                }
            };

            if ($withProducts) {
                $relations[str_repeat('children.', $i - 1) . 'products'] = function ($query) {
                    $query->where('status', 'active')
                          ->select('id', 'name', 'price', 'category_id');
                };
            }
        }

        return $relations;
    }

    /**
     * Categorias populares (com mais produtos)
     */
    public function popular(Request $request): JsonResponse
    {
        $limit = min(20, (int) $request->get('limit', 10));

        $categories = Category::where('active', true)
            ->withCount(['products' => function ($query) {
                $query->where('status', 'active');
            }])
            ->having('products_count', '>', 0)
            ->orderByDesc('products_count')
            ->orderBy('sort_order')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
            'meta' => [
                'count' => $categories->count(),
                'limit' => $limit
            ]
        ]);
    }

    /**
     * Busca rápida de categorias
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = min(20, (int) $request->get('limit', 10));
        $includeChildren = $request->boolean('include_children', false);

        if (empty($query)) {
            return response()->json([
                'success' => false,
                'message' => 'Query parameter "q" is required'
            ], 400);
        }

        $categories = Category::where('active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%")
                  ->orWhere('slug', 'LIKE', "%{$query}%");
            })
            ->when($includeChildren, function ($q) {
                $q->with('children:id,name,slug,parent_id,active');
            })
            ->withCount('products')
            ->select('id', 'name', 'slug', 'description', 'parent_id', 'sort_order')
            ->orderBy('sort_order')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
            'meta' => [
                'query' => $query,
                'count' => $categories->count(),
                'limit' => $limit,
                'include_children' => $includeChildren
            ]
        ]);
    }

    /**
     * Estatísticas de categorias
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_categories' => Category::count(),
            'active_categories' => Category::where('active', true)->count(),
            'root_categories' => Category::whereNull('parent_id')->count(),
            'categories_with_products' => Category::whereHas('products')->count(),
            'empty_categories' => Category::whereDoesntHave('products')->count(),
            'categories_by_level' => [
                'level_1' => Category::whereNull('parent_id')->count(),
                'level_2' => Category::whereNotNull('parent_id')
                    ->whereHas('parent', function ($q) {
                        $q->whereNull('parent_id');
                    })->count(),
                'level_3_plus' => Category::whereHas('parent.parent')->count(),
            ],
            'top_categories_by_products' => Category::withCount('products')
                ->orderByDesc('products_count')
                ->limit(10)
                ->get(['id', 'name', 'products_count'])
                ->pluck('products_count', 'name')
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'generated_at' => now()->toISOString()
        ]);
    }

    /**
     * Mostrar categoria específica
     */
    public function show(Request $request, $id): JsonResponse
    {
        $fields = $request->get('fields');
        $withChildren = $request->boolean('with_children', false);
        $withProducts = $request->boolean('with_products', false);

        $query = Category::query();

        // Configurar relacionamentos
        $with = ['parent'];
        if ($withChildren) {
            $with[] = 'children';
        }
        if ($withProducts) {
            $with[] = 'products';
        }
        $query->with($with);

        // Aplicar field selection se especificado
        if ($fields) {
            $this->initializeFilterServices();
            $this->applyFieldSelection($query, $request);
        }

        $category = $query->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }
}