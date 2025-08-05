<?php

namespace MarcosBrendon\ApiForge\Traits;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use MarcosBrendon\ApiForge\Services\ApiFilterService;
use MarcosBrendon\ApiForge\Services\FilterConfigService;
use MarcosBrendon\ApiForge\Http\Resources\PaginatedResource;

trait HasAdvancedFilters
{
    /**
     * Serviço de filtros
     *
     * @var ApiFilterService
     */
    protected ApiFilterService $filterService;

    /**
     * Configuração avançada de filtros
     *
     * @var FilterConfigService
     */
    protected FilterConfigService $filterConfigService;

    /**
     * Inicializar serviços de filtro
     */
    protected function initializeFilterServices(): void
    {
        if (!isset($this->filterService)) {
            $this->filterService = app(ApiFilterService::class);
        }

        if (!isset($this->filterConfigService)) {
            $this->filterConfigService = app(FilterConfigService::class);
        }

        // Configurar filtros se o método existir
        if (method_exists($this, 'setupFilterConfiguration')) {
            $this->setupFilterConfiguration();
        }
    }

    /**
     * Endpoint index com filtros avançados
     *
     * @param Request $request
     * @param array $options
     * @return JsonResponse
     */
    public function indexWithFilters(Request $request, array $options = []): JsonResponse
    {
        $this->initializeFilterServices();

        // Passar configuração de filtros para o middleware
        $request->attributes->set('filter_config', $this->filterConfigService->getFilterMetadata());

        // Validar filtros obrigatórios
        $missingFilters = $this->filterConfigService->validateRequiredFilters($request);
        if (!empty($missingFilters)) {
            return response()->json([
                'success' => false,
                'message' => 'Filtros obrigatórios ausentes',
                'missing_filters' => $missingFilters
            ], 400);
        }

        // Criar query base
        $query = $this->buildBaseQuery($request, $options);

        // Aplicar filtros avançados
        $this->filterService->applyAdvancedFilters($query, $request);

        // Obter campos pesquisáveis e ordenáveis da configuração
        $searchableFields = $this->filterConfigService->getSearchableFields();
        $sortableFields = $this->filterConfigService->getSortableFields();

        // Aplicar paginação com field selection automático
        $result = $this->paginateQueryWithAutoFieldSelection(
            $query,
            $request,
            $searchableFields,
            $sortableFields,
            $options['per_page'] ?? config('apiforge.pagination.default_per_page', 15)
        );

        // Adicionar informações sobre filtros inválidos se houver
        $additionalData = [
            'pagination' => $result['pagination'],
            'filters' => $result['filters'],
            'sorting' => $result['sorting']
        ];

        $invalidFilters = $request->attributes->get('invalid_filters', []);
        if (!empty($invalidFilters)) {
            $additionalData['warnings'] = [
                'invalid_filters' => $invalidFilters,
                'message' => 'Alguns filtros foram ignorados por não serem permitidos'
            ];
        }

        // Aplicar cache se configurado
        if ($options['cache'] ?? false) {
            $cacheKey = $this->generateCacheKey($request, $options);
            $cacheTtl = $options['cache_ttl'] ?? config('apiforge.cache.default_ttl', 3600);
            
            return cache()->remember($cacheKey, $cacheTtl, function () use ($result, $additionalData) {
                return response()->json(
                    (new PaginatedResource($result['data']))
                        ->withFilterMetadata($this->filterConfigService->getFilterMetadata())
                        ->additional($additionalData)
                );
            });
        }

        // Retornar resposta usando Resource
        return response()->json(
            (new PaginatedResource($result['data']))
                ->withFilterMetadata($this->filterConfigService->getFilterMetadata())
                ->additional($additionalData)
        );
    }

    /**
     * Construir query base
     *
     * @param Request $request
     * @param array $options
     * @return Builder
     */
    protected function buildBaseQuery(Request $request, array $options = []): Builder
    {
        $modelClass = $this->getModelClass();
        $query = $modelClass::query();

        // Aplicar relacionamentos padrão
        if (method_exists($this, 'getDefaultRelationships')) {
            $relationships = $this->getDefaultRelationships();
            if (!empty($relationships)) {
                $query->with($relationships);
            }
        }

        // Aplicar scopes padrão
        if (method_exists($this, 'applyDefaultScopes')) {
            $this->applyDefaultScopes($query, $request);
        }

        return $query;
    }

    /**
     * Paginação com field selection automático
     */
    protected function paginateQueryWithAutoFieldSelection(
        Builder $query,
        Request $request,
        array $searchableFields = [],
        array $sortableFields = [],
        int $defaultPerPage = 15
    ): array {
        // Aplicar busca geral
        $searchTerm = $request->get('search');
        if ($searchTerm && !empty($searchableFields)) {
            $query->where(function ($q) use ($searchableFields, $searchTerm) {
                foreach ($searchableFields as $field) {
                    $q->orWhere($field, 'LIKE', "%{$searchTerm}%");
                }
            });
        }

        // Aplicar ordenação
        $sortBy = $request->get('sort_by');
        $sortDirection = $request->get('sort_direction', 'asc');
        
        if ($sortBy && in_array($sortBy, $sortableFields)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            // Ordenação padrão
            if (method_exists($this, 'getDefaultSort')) {
                [$defaultSort, $defaultDirection] = $this->getDefaultSort();
                $query->orderBy($defaultSort, $defaultDirection);
            } else {
                $query->latest();
            }
        }

        // Aplicar field selection
        $this->applyFieldSelection($query, $request);

        // Paginação
        $perPage = min(
            config('apiforge.pagination.max_per_page', 100),
            max(
                config('apiforge.pagination.min_per_page', 1),
                (int) $request->get('per_page', $defaultPerPage)
            )
        );

        $paginated = $query->paginate($perPage);

        return [
            'data' => $paginated,
            'pagination' => $this->formatPaginationData($paginated),
            'filters' => $this->getActiveFilters($request),
            'sorting' => [
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection
            ]
        ];
    }

    /**
     * Aplicar field selection à query
     *
     * @param Builder $query
     * @param Request $request
     * @return void
     */
    protected function applyFieldSelection(Builder $query, Request $request): void
    {
        $fieldsParam = $request->get('fields');
        
        if (!$fieldsParam) {
            // Usar campos padrão se configurados
            $defaultFields = $this->filterConfigService->getDefaultFields();
            if (!empty($defaultFields)) {
                $query->select($defaultFields);
            }
            return;
        }

        // Processar campos solicitados
        $requestedFields = array_map('trim', explode(',', $fieldsParam));
        
        // Resolver aliases e validar campos
        [$validFields, $invalidFields] = $this->filterConfigService->validateFieldSelection($requestedFields);
        
        // Aplicar limites
        $finalFields = $this->filterConfigService->applyFieldLimits($validFields);
        
        // Separar campos de relacionamento
        $selectFields = [];
        $withFields = [];
        
        foreach ($finalFields as $field) {
            if (strpos($field, '.') !== false) {
                // Campo de relacionamento
                [$relation, $relationField] = explode('.', $field, 2);
                $withFields[$relation][] = $relationField;
            } else {
                // Campo direto
                $selectFields[] = $field;
            }
        }

        // Aplicar seleção de campos
        if (!empty($selectFields)) {
            $query->select($selectFields);
        }

        // Aplicar seleção de campos de relacionamento
        foreach ($withFields as $relation => $fields) {
            $query->with([$relation => function ($q) use ($fields) {
                $q->select(array_merge(['id'], $fields)); // Sempre incluir ID para relacionamentos
            }]);
        }

        // Log de campos inválidos se debug estiver ativo
        if (!empty($invalidFields) && config('apiforge.debug.enabled')) {
            logger()->debug('Invalid fields in field selection', ['invalid_fields' => $invalidFields]);
        }
    }

    /**
     * Formatar dados de paginação
     *
     * @param LengthAwarePaginator $paginated
     * @return array
     */
    protected function formatPaginationData(LengthAwarePaginator $paginated): array
    {
        return [
            'current_page' => $paginated->currentPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
            'last_page' => $paginated->lastPage(),
            'from' => $paginated->firstItem(),
            'to' => $paginated->lastItem(),
            'has_more_pages' => $paginated->hasMorePages(),
            'prev_page_url' => $paginated->previousPageUrl(),
            'next_page_url' => $paginated->nextPageUrl(),
        ];
    }

    /**
     * Obter filtros ativos da requisição
     *
     * @param Request $request
     * @return array
     */
    protected function getActiveFilters(Request $request): array
    {
        $active = [];
        $excludeKeys = [
            'page', 'per_page', 'sort_by', 'sort_direction', 
            'search', 'filters', 'with_filters', 'fields',
            'date_from', 'date_to', 'date_field', 'period',
            'empresa_id', 'cache'
        ];

        foreach ($request->all() as $key => $value) {
            if (!in_array($key, $excludeKeys) && $value !== null && $value !== '') {
                $active[$key] = $value;
            }
        }

        return [
            'active' => $active,
            'search' => $request->get('search'),
        ];
    }

    /**
     * Gerar chave de cache
     *
     * @param Request $request
     * @param array $options
     * @return string
     */
    protected function generateCacheKey(Request $request, array $options = []): string
    {
        $params = $request->except(['_token']);
        ksort($params);
        
        $keyData = [
            'model' => $this->getModelClass(),
            'params' => $params,
            'url' => $request->url(),
            'options' => $options
        ];
        
        $prefix = config('apiforge.cache.key_prefix', 'api_filters_');
        return $prefix . md5(json_encode($keyData));
    }

    /**
     * Configurar filtros (deve ser implementado pela classe que usa o trait)
     *
     * @return void
     */
    abstract protected function setupFilterConfiguration(): void;

    /**
     * Obter classe do modelo (deve ser implementado pela classe que usa o trait)
     *
     * @return string
     */
    abstract protected function getModelClass(): string;

    /**
     * Helper para configurar filtros facilmente
     *
     * @param array $config
     * @return void
     */
    protected function configureFilters(array $config): void
    {
        $this->filterConfigService->configure($config);
        $this->filterService->configure($config);
    }

    /**
     * Helper para configurar field selection
     *
     * @param array $config
     * @return void
     */
    protected function configureFieldSelection(array $config): void
    {
        $this->filterConfigService->configureFieldSelection($config);
    }

    /**
     * Endpoint para metadados dos filtros
     *
     * @return JsonResponse
     */
    public function filterMetadata(): JsonResponse
    {
        $this->initializeFilterServices();

        return response()->json([
            'success' => true,
            'data' => $this->filterConfigService->getCompleteMetadata()
        ]);
    }

    /**
     * Endpoint para exemplos de filtros
     *
     * @return JsonResponse
     */
    public function filterExamples(): JsonResponse
    {
        $this->initializeFilterServices();

        $examples = $this->generateFilterExamples();
        $tips = $this->getFilterTips();

        return response()->json([
            'success' => true,
            'data' => [
                'examples' => $examples,
                'tips' => $tips,
                'help_url' => '/metadata'
            ]
        ]);
    }

    /**
     * Gerar exemplos de filtros
     *
     * @return array
     */
    protected function generateFilterExamples(): array
    {
        $metadata = $this->filterConfigService->getFilterMetadata();
        $examples = [
            'basic_usage' => [],
            'advanced_operators' => [],
            'field_selection' => [],
            'pagination' => [],
            'sorting' => [],
            'combined_filters' => []
        ];

        $endpoint = '/' . strtolower(class_basename($this->getModelClass()));

        // Exemplos básicos
        foreach (array_slice($metadata, 0, 3) as $field => $config) {
            $examples['basic_usage'][] = "GET {$endpoint}?{$field}=valor";
        }

        // Exemplos com operadores avançados
        foreach ($metadata as $field => $config) {
            if (in_array('like', $config['operators'] ?? [])) {
                $examples['advanced_operators'][] = "GET {$endpoint}?{$field}=João*";
                break;
            }
        }

        // Field selection
        $selectableFields = $this->filterConfigService->getFieldSelectionConfig()['selectable_fields'] ?? [];
        if (!empty($selectableFields)) {
            $fields = implode(',', array_slice($selectableFields, 0, 3));
            $examples['field_selection'][] = "GET {$endpoint}?fields={$fields}";
        }

        // Paginação
        $examples['pagination'] = [
            "GET {$endpoint}?page=2&per_page=20",
            "GET {$endpoint}?per_page=50"
        ];

        // Ordenação
        $sortableFields = $this->filterConfigService->getSortableFields();
        if (!empty($sortableFields)) {
            $field = $sortableFields[0];
            $examples['sorting'][] = "GET {$endpoint}?sort_by={$field}&sort_direction=asc";
        }

        return $examples;
    }

    /**
     * Obter dicas de uso
     *
     * @return array
     */
    protected function getFilterTips(): array
    {
        return [
            'Use * como wildcard em filtros de texto (João* = começa com João)',
            'Separe múltiplos valores com vírgula (id=1,2,3)',
            'Use | para intervalos de data/números (data=2024-01-01|2024-12-31)',
            'Combine filtros para refinar a busca',
            'Use search= para busca geral em todos os campos pesquisáveis',
            'Use fields= para selecionar apenas campos específicos (otimiza performance)',
            'Relacionamentos: fields=id,nome,empresa.nome',
            'Máximo de ' . config('apiforge.pagination.max_per_page', 100) . ' itens por página',
            'Use /metadata para ver todos os filtros disponíveis'
        ];
    }
}