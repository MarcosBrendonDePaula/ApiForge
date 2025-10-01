<?php

namespace MarcosBrendon\ApiForge\Traits;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use MarcosBrendon\ApiForge\Services\ApiFilterService;
use MarcosBrendon\ApiForge\Services\FilterConfigService;
use MarcosBrendon\ApiForge\Services\CacheService;
use MarcosBrendon\ApiForge\Services\QueryOptimizationService;
use MarcosBrendon\ApiForge\Services\ModelHookService;
use MarcosBrendon\ApiForge\Services\VirtualFieldService;
use MarcosBrendon\ApiForge\Http\Resources\PaginatedResource;
use MarcosBrendon\ApiForge\Exceptions\FilterValidationException;
use MarcosBrendon\ApiForge\Support\ExceptionHandler;

trait HasAdvancedFilters
{
    /**
     * Flag to prevent recursive initialization
     *
     * @var bool
     */
    protected bool $servicesInitialized = false;

    /**
     * Flag to prevent multiple configuration setups
     *
     * @var bool
     */
    protected bool $configurationSetup = false;

    /**
     * Serviço de filtros
     *
     * @var ApiFilterService|null
     */
    protected ?ApiFilterService $filterService = null;

    /**
     * Configuração avançada de filtros
     *
     * @var FilterConfigService|null
     */
    protected ?FilterConfigService $filterConfigService = null;

    /**
     * Serviço de cache
     *
     * @var CacheService|null
     */
    protected ?CacheService $cacheService = null;

    /**
     * Serviço de otimização de queries
     *
     * @var QueryOptimizationService|null
     */
    protected ?QueryOptimizationService $queryOptimizationService = null;

    /**
     * Serviço de hooks de modelo
     *
     * @var ModelHookService|null
     */
    protected ?ModelHookService $hookService = null;

    /**
     * Virtual field service instance
     *
     * @var VirtualFieldService|null
     */
    protected ?VirtualFieldService $virtualFieldService = null;

    /**
     * Inicializar serviços de filtro
     */
    protected function initializeFilterServices(): void
    {
        // Prevent recursive initialization
        if ($this->servicesInitialized) {
            return;
        }

        $this->servicesInitialized = true;

        if ($this->filterService === null) {
            $this->filterService = app(ApiFilterService::class);
        }

        if ($this->filterConfigService === null) {
            $this->filterConfigService = app(FilterConfigService::class);
        }

        if ($this->cacheService === null) {
            $this->cacheService = app(CacheService::class);
        }

        if ($this->queryOptimizationService === null) {
            $this->queryOptimizationService = app(QueryOptimizationService::class);
        }

        if ($this->hookService === null) {
            $this->hookService = app(ModelHookService::class);
        }

        if ($this->virtualFieldService === null) {
            $this->virtualFieldService = app(VirtualFieldService::class);
        }

        // Set up cross-service dependencies
        $this->filterConfigService->setVirtualFieldService($this->virtualFieldService);

        // Configurar filtros se o método existir (only once)
        if (method_exists($this, 'setupFilterConfiguration') && !$this->configurationSetup) {
            $this->configurationSetup = true;
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
        try {
            $this->initializeFilterServices();

            // Passar configuração de filtros para o middleware
            $request->attributes->set('filter_config', $this->filterConfigService->getFilterMetadata());

            // Validar filtros obrigatórios
            $missingFilters = $this->filterConfigService->validateRequiredFilters($request);
            if (!empty($missingFilters)) {
                $exception = FilterValidationException::requiredFilterMissing($missingFilters);
                return ExceptionHandler::handle($exception, $request);
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
        } catch (\MarcosBrendon\ApiForge\Exceptions\ApiForgeException $e) {
            return ExceptionHandler::handle($e, $request);
        } catch (\Throwable $e) {
            return ExceptionHandler::handleGeneric($e, $request);
        }

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

        // Aplicar cache avançado se configurado
        if (($options['cache'] ?? false) || config('apiforge.cache.enabled', false)) {
            $cacheKey = $this->cacheService->generateKey(
                $this->getModelClass(),
                $request->all(),
                $options
            );
            
            // Tentar recuperar do cache primeiro
            $cachedResponse = $this->cacheService->retrieve($cacheKey);
            
            if ($cachedResponse !== null) {
                return $cachedResponse;
            }
            
            // Gerar resposta se não estiver em cache
            $response = response()->json(
                (new PaginatedResource($result['data']))
                    ->withFilterMetadata($this->filterConfigService->getFilterMetadata())
                    ->additional($additionalData)
            );
            
            // Armazenar no cache com tags e metadados
            $cacheOptions = [
                'ttl' => $options['cache_ttl'] ?? config('apiforge.cache.default_ttl', 3600),
                'tags' => array_merge(
                    $options['cache_tags'] ?? [],
                    ['api_response']
                ),
                'model' => $this->getModelClass(),
                'query_params' => $request->all()
            ];
            
            $this->cacheService->store($cacheKey, $response, $cacheOptions);
            
            return $response;
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
        // Obter campos solicitados para otimização
        $fieldsParam = $request->get('fields');
        $requestedFields = $fieldsParam ? array_map('trim', explode(',', $fieldsParam)) : [];
        
        // Aplicar otimizações de query se habilitado
        if (config('apiforge.performance.query_optimization', true)) {
            $query = $this->queryOptimizationService->optimizeQuery($query, $requestedFields);
        }
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
        
        // Check if sorting by virtual field
        $isVirtualFieldSort = $sortBy && $this->filterConfigService->isVirtualField($sortBy);
        
        if ($sortBy && !$isVirtualFieldSort && in_array($sortBy, $sortableFields)) {
            // Regular field sorting at database level
            $query->orderBy($sortBy, $sortDirection);
        } elseif (!$isVirtualFieldSort) {
            // Default sorting for non-virtual fields
            if (method_exists($this, 'getDefaultSort')) {
                [$defaultSort, $defaultDirection] = $this->getDefaultSort();
                $query->orderBy($defaultSort, $defaultDirection);
            } else {
                $query->latest();
            }
        }
        // Virtual field sorting will be handled after data retrieval

        // Aplicar field selection
        $this->applyFieldSelection($query, $request);

        // Paginação otimizada
        $perPage = min(
            config('apiforge.pagination.max_per_page', 100),
            max(
                config('apiforge.pagination.min_per_page', 1),
                (int) $request->get('per_page', $defaultPerPage)
            )
        );
        
        $page = (int) $request->get('page', 1);
        
        // Aplicar otimização de paginação para grandes datasets
        if (config('apiforge.performance.optimize_pagination', true)) {
            $query = $this->queryOptimizationService->optimizePagination($query, $page, $perPage);
        }

        // Handle virtual field sorting differently
        $sortBy = $request->get('sort_by');
        $isVirtualFieldSort = $sortBy && $this->filterConfigService->isVirtualField($sortBy);
        
        if ($isVirtualFieldSort) {
            // For virtual field sorting, we need to get all records, compute virtual fields, sort, then paginate
            $paginated = $this->paginateWithVirtualFieldSorting($query, $request, $sortBy, $sortDirection, $perPage, $page);
        } else {
            // Regular pagination
            $paginated = $query->paginate($perPage);
            // Process virtual fields for selected models if any were requested
            $this->processVirtualFieldsForPaginatedResult($paginated, $request);
        }

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
        
        // Separar campos regulares, de relacionamento e virtuais
        $selectFields = [];
        $withFields = [];
        $virtualFields = [];
        
        foreach ($finalFields as $field) {
            if ($this->filterConfigService->isVirtualField($field)) {
                // Campo virtual
                $virtualFields[] = $field;
            } elseif (strpos($field, '.') !== false) {
                // Campo de relacionamento
                [$relation, $relationField] = explode('.', $field, 2);
                $withFields[$relation][] = $relationField;
            } else {
                // Campo direto
                $selectFields[] = $field;
            }
        }

        // Otimizar query para campos virtuais (carregar dependências)
        if (!empty($virtualFields) && $this->virtualFieldService) {
            $this->virtualFieldService->optimizeQueryForSelection($query, $virtualFields);
        }

        // Aplicar seleção de campos regulares
        if (!empty($selectFields)) {
            $query->select($selectFields);
        }

        // Aplicar seleção de campos de relacionamento
        foreach ($withFields as $relation => $fields) {
            $query->with([$relation => function ($q) use ($fields) {
                $q->select(array_merge(['id'], $fields)); // Sempre incluir ID para relacionamentos
            }]);
        }

        // Armazenar campos virtuais para processamento posterior
        if (!empty($virtualFields)) {
            // Store virtual fields in the query metadata for later processing
            $query->macro('getVirtualFields', function () use ($virtualFields) {
                return $virtualFields;
            });
        }

        // Log de campos inválidos se debug estiver ativo
        if (!empty($invalidFields) && config('apiforge.debug.enabled')) {
            logger()->debug('Invalid fields in field selection', ['invalid_fields' => $invalidFields]);
        }
    }

    /**
     * Paginate with virtual field sorting
     *
     * @param Builder $query
     * @param Request $request
     * @param string $sortBy
     * @param string $sortDirection
     * @param int $perPage
     * @param int $page
     * @return LengthAwarePaginator
     */
    protected function paginateWithVirtualFieldSorting(Builder $query, Request $request, string $sortBy, string $sortDirection, int $perPage, int $page)
    {
        // Check if virtual field is sortable
        if (!$this->virtualFieldService->isVirtualFieldSortable($sortBy)) {
            if (config('apiforge.virtual_fields.sort_fallback_enabled', true)) {
                // Fall back to regular pagination without virtual field sorting
                return $this->fallbackToRegularPagination($query, $request, $perPage);
            }
            throw new FilterValidationException("Virtual field '{$sortBy}' is not sortable");
        }

        // Get total count for pagination
        $total = $query->count();

        // Check if we can handle this many records
        $maxRecords = config('apiforge.virtual_fields.max_sort_records', 10000);
        if ($total > $maxRecords) {
            if (config('apiforge.virtual_fields.sort_fallback_enabled', true)) {
                // Fall back to regular pagination
                return $this->fallbackToRegularPagination($query, $request, $perPage);
            }
            throw new FilterValidationException(
                "Cannot sort by virtual field '{$sortBy}' - too many records ({$total}). Maximum allowed: {$maxRecords}"
            );
        }

        try {
            // Check cache first if enabled
            $cacheKey = null;
            if (config('apiforge.virtual_fields.sort_cache_enabled', true)) {
                $cacheKey = $this->generateVirtualFieldSortCacheKey($query, $sortBy, $sortDirection);
                $cached = cache()->get($cacheKey);
                if ($cached) {
                    return $this->createPaginatorFromCachedData($cached, $total, $perPage, $page);
                }
            }

            // Get all records efficiently
            $allModels = $this->getModelsForVirtualFieldSorting($query, $total);

            // Compute the virtual field for all models in batches
            $this->computeVirtualFieldInBatches($allModels, $sortBy);

            // Sort by the computed virtual field
            $sortedModels = $this->sortModelsByVirtualField($allModels, $sortBy, $sortDirection);

            // Cache the sorted result if enabled
            if ($cacheKey) {
                $cacheTtl = config('apiforge.virtual_fields.sort_cache_ttl', 1800);
                cache()->put($cacheKey, $sortedModels->pluck('id')->toArray(), $cacheTtl);
            }

            // Calculate pagination
            $offset = ($page - 1) * $perPage;
            $paginatedModels = $sortedModels->slice($offset, $perPage);

            // Process any additional virtual fields for the paginated subset
            $this->processVirtualFieldsForCollection($paginatedModels, $request);

            // Create paginator
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                $paginatedModels,
                $total,
                $perPage,
                $page,
                [
                    'path' => request()->url(),
                    'pageName' => 'page',
                ]
            );

            return $paginator;

        } catch (\Exception $e) {
            if (config('apiforge.virtual_fields.sort_fallback_enabled', true)) {
                // Log the error and fall back to regular pagination
                if (config('apiforge.debug.enabled')) {
                    logger()->warning('Virtual field sorting failed, falling back to regular pagination', [
                        'sort_field' => $sortBy,
                        'error' => $e->getMessage()
                    ]);
                }
                return $this->fallbackToRegularPagination($query, $request, $perPage);
            }
            throw $e;
        }
    }

    /**
     * Fall back to regular pagination when virtual field sorting fails
     */
    protected function fallbackToRegularPagination(Builder $query, Request $request, int $perPage)
    {
        // Apply default sorting
        if (method_exists($this, 'getDefaultSort')) {
            [$defaultSort, $defaultDirection] = $this->getDefaultSort();
            $query->orderBy($defaultSort, $defaultDirection);
        } else {
            $query->latest();
        }

        $paginated = $query->paginate($perPage);
        $this->processVirtualFieldsForPaginatedResult($paginated, $request);
        return $paginated;
    }

    /**
     * Get models for virtual field sorting with memory optimization
     */
    protected function getModelsForVirtualFieldSorting(Builder $query, int $total): Collection
    {
        $batchSize = config('apiforge.virtual_fields.batch_size', 100);
        
        if ($total <= $batchSize) {
            return $query->get();
        }

        // For larger datasets, use chunking to manage memory
        $allModels = collect();
        $query->chunk($batchSize, function ($models) use ($allModels) {
            $allModels = $allModels->concat($models);
        });

        return $allModels;
    }

    /**
     * Compute virtual field in batches for memory efficiency
     */
    protected function computeVirtualFieldInBatches(Collection $models, string $sortBy): void
    {
        $batchSize = config('apiforge.virtual_fields.batch_size', 100);
        
        if ($models->count() <= $batchSize) {
            $this->virtualFieldService->processSelectedFields($models, [$sortBy]);
            return;
        }

        // Process in batches
        $models->chunk($batchSize)->each(function ($batch) use ($sortBy) {
            $this->virtualFieldService->processSelectedFields($batch, [$sortBy]);
        });
    }

    /**
     * Sort models by virtual field value
     */
    protected function sortModelsByVirtualField(Collection $models, string $sortBy, string $sortDirection): Collection
    {
        $sortedModels = $models->sortBy(function ($model) use ($sortBy) {
            return $model->getAttribute($sortBy);
        }, SORT_REGULAR, $sortDirection === 'desc');

        return $sortedModels->values();
    }

    /**
     * Generate cache key for virtual field sorting
     */
    protected function generateVirtualFieldSortCacheKey(Builder $query, string $sortBy, string $sortDirection): string
    {
        $queryHash = md5($query->toSql() . serialize($query->getBindings()));
        return "virtual_field_sort:{$queryHash}:{$sortBy}:{$sortDirection}";
    }

    /**
     * Create paginator from cached sorted data
     */
    protected function createPaginatorFromCachedData(array $sortedIds, int $total, int $perPage, int $page)
    {
        // Calculate pagination
        $offset = ($page - 1) * $perPage;
        $paginatedIds = array_slice($sortedIds, $offset, $perPage);

        // Get the models for this page
        $modelClass = $this->getModelClass();
        $paginatedModels = $modelClass::whereIn('id', $paginatedIds)
            ->orderByRaw('FIELD(id, ' . implode(',', $paginatedIds) . ')')
            ->get();

        // Create paginator
        return new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedModels,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );
    }

    /**
     * Process virtual fields for paginated result
     *
     * @param LengthAwarePaginator $paginated
     * @param Request $request
     * @return void
     */
    protected function processVirtualFieldsForPaginatedResult($paginated, Request $request): void
    {
        $models = $paginated->getCollection();
        $this->processVirtualFieldsForCollection($models, $request);
    }

    /**
     * Process virtual fields for a collection
     *
     * @param Collection $models
     * @param Request $request
     * @return void
     */
    protected function processVirtualFieldsForCollection($models, Request $request): void
    {
        if (!$this->virtualFieldService || $models->isEmpty()) {
            return;
        }

        // Get requested virtual fields from field selection
        $fieldsParam = $request->get('fields');
        if (!$fieldsParam) {
            return;
        }

        $requestedFields = array_map('trim', explode(',', $fieldsParam));
        $virtualFields = [];

        foreach ($requestedFields as $field) {
            if ($this->filterConfigService->isVirtualField($field)) {
                $virtualFields[] = $field;
            }
        }

        if (empty($virtualFields)) {
            return;
        }

        // Process virtual fields for the collection
        $this->virtualFieldService->processSelectedFields($models, $virtualFields);
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
     * Helper para configurar virtual fields
     *
     * @param array $config
     * @return void
     */
    protected function configureVirtualFields(array $config): void
    {
        // Services should already be initialized, but check just in case
        if (!$this->servicesInitialized) {
            $this->initializeFilterServices();
        }
        
        $this->virtualFieldService->registerFromConfig($config);
        $this->filterConfigService->configureVirtualFields($config);
    }

    /**
     * Configure model hooks for CRUD operations
     *
     * @param array $config
     * @return void
     */
    protected function configureModelHooks(array $config): void
    {
        // Services should already be initialized, but check just in case
        if (!$this->servicesInitialized) {
            $this->initializeFilterServices();
        }
        
        $this->hookService->registerFromConfig($config);
    }

    /**
     * Register a single hook
     *
     * @param string $hookType
     * @param string $hookName
     * @param callable $callback
     * @param array $options
     * @return void
     */
    protected function registerHook(string $hookType, string $hookName, callable $callback, array $options = []): void
    {
        $this->initializeFilterServices();
        $this->hookService->register($hookType, $hookName, $callback, $options);
    }

    /**
     * Helper method to configure audit hooks
     *
     * @param array $options
     * @return void
     */
    protected function configureAuditHooks(array $options = []): void
    {
        $this->initializeFilterServices();
        
        $auditFields = $options['fields'] ?? [];
        $auditUser = $options['track_user'] ?? true;
        $auditTable = $options['audit_table'] ?? 'audit_logs';
        
        // Before update hook to track changes
        $this->registerHook('beforeUpdate', 'trackChanges', function($model, $context) use ($auditFields, $auditUser, $auditTable) {
            $changes = $model->getDirty();
            
            // Filter to specific fields if configured
            if (!empty($auditFields)) {
                $changes = array_intersect_key($changes, array_flip($auditFields));
            }
            
            if (!empty($changes)) {
                $auditData = [
                    'model_type' => get_class($model),
                    'model_id' => $model->getKey(),
                    'changes' => $changes,
                    'original_values' => $model->getOriginal(),
                    'action' => 'update',
                    'created_at' => now(),
                ];
                
                if ($auditUser && auth()->check()) {
                    $auditData['user_id'] = auth()->id();
                }
                
                // Store in context for after hook
                $context->set('audit_data', $auditData);
            }
        }, ['priority' => 1]);
        
        // After update hook to save audit log
        $this->registerHook('afterUpdate', 'saveAuditLog', function($model, $context) use ($auditTable) {
            $auditData = $context->get('audit_data');
            if ($auditData) {
                \DB::table($auditTable)->insert($auditData);
            }
        }, ['priority' => 1]);
        
        // Store creation audit
        $this->registerHook('afterStore', 'auditCreation', function($model, $context) use ($auditUser, $auditTable) {
            $auditData = [
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'changes' => $model->getAttributes(),
                'original_values' => [],
                'action' => 'create',
                'created_at' => now(),
            ];
            
            if ($auditUser && auth()->check()) {
                $auditData['user_id'] = auth()->id();
            }
            
            \DB::table($auditTable)->insert($auditData);
        }, ['priority' => 1]);
        
        // Store deletion audit
        $this->registerHook('beforeDelete', 'auditDeletion', function($model, $context) use ($auditUser, $auditTable) {
            $auditData = [
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'changes' => [],
                'original_values' => $model->getAttributes(),
                'action' => 'delete',
                'created_at' => now(),
            ];
            
            if ($auditUser && auth()->check()) {
                $auditData['user_id'] = auth()->id();
            }
            
            \DB::table($auditTable)->insert($auditData);
            return true; // Allow deletion
        }, ['priority' => 1]);
    }

    /**
     * Helper method to configure validation hooks
     *
     * @param array $rules
     * @param array $options
     * @return void
     */
    protected function configureValidationHooks(array $rules, array $options = []): void
    {
        $this->initializeFilterServices();
        
        $customMessages = $options['messages'] ?? [];
        $stopOnFailure = $options['stop_on_failure'] ?? true;
        
        // Before store validation
        $this->registerHook('beforeStore', 'validateBeforeStore', function($model, $context) use ($rules, $customMessages) {
            $validator = \Validator::make($model->getAttributes(), $rules, $customMessages);
            
            if ($validator->fails()) {
                throw new \Illuminate\Validation\ValidationException($validator);
            }
        }, ['priority' => 1, 'stopOnFailure' => $stopOnFailure]);
        
        // Before update validation
        $this->registerHook('beforeUpdate', 'validateBeforeUpdate', function($model, $context) use ($rules, $customMessages) {
            $data = $context->get('data', []);
            $validator = \Validator::make($data, $rules, $customMessages);
            
            if ($validator->fails()) {
                throw new \Illuminate\Validation\ValidationException($validator);
            }
        }, ['priority' => 1, 'stopOnFailure' => $stopOnFailure]);
    }

    /**
     * Helper method to configure notification hooks
     *
     * @param array $config
     * @return void
     */
    protected function configureNotificationHooks(array $config): void
    {
        $this->initializeFilterServices();
        
        // After store notifications
        if (isset($config['onCreate'])) {
            $this->registerHook('afterStore', 'notifyOnCreate', function($model, $context) use ($config) {
                $notificationConfig = $config['onCreate'];
                $this->sendNotification($model, $context, $notificationConfig, 'created');
            }, ['priority' => $config['onCreate']['priority'] ?? 10]);
        }
        
        // After update notifications
        if (isset($config['onUpdate'])) {
            $this->registerHook('afterUpdate', 'notifyOnUpdate', function($model, $context) use ($config) {
                $notificationConfig = $config['onUpdate'];
                
                // Check if specific fields changed
                if (isset($notificationConfig['watch_fields'])) {
                    $changes = $model->getDirty();
                    $watchFields = $notificationConfig['watch_fields'];
                    $hasWatchedChanges = !empty(array_intersect(array_keys($changes), $watchFields));
                    
                    if (!$hasWatchedChanges) {
                        return;
                    }
                }
                
                $this->sendNotification($model, $context, $notificationConfig, 'updated');
            }, ['priority' => $config['onUpdate']['priority'] ?? 10]);
        }
        
        // After delete notifications
        if (isset($config['onDelete'])) {
            $this->registerHook('afterDelete', 'notifyOnDelete', function($model, $context) use ($config) {
                $notificationConfig = $config['onDelete'];
                $this->sendNotification($model, $context, $notificationConfig, 'deleted');
            }, ['priority' => $config['onDelete']['priority'] ?? 10]);
        }
    }

    /**
     * Helper method to configure cache invalidation hooks
     *
     * @param array $cacheKeys
     * @param array $options
     * @return void
     */
    protected function configureCacheInvalidationHooks(array $cacheKeys, array $options = []): void
    {
        $this->initializeFilterServices();
        
        $priority = $options['priority'] ?? 5;
        
        // Invalidate cache after store
        $this->registerHook('afterStore', 'invalidateCacheOnStore', function($model, $context) use ($cacheKeys) {
            $this->invalidateCacheKeys($model, $cacheKeys);
        }, ['priority' => $priority]);
        
        // Invalidate cache after update
        $this->registerHook('afterUpdate', 'invalidateCacheOnUpdate', function($model, $context) use ($cacheKeys) {
            $this->invalidateCacheKeys($model, $cacheKeys);
        }, ['priority' => $priority]);
        
        // Invalidate cache after delete
        $this->registerHook('afterDelete', 'invalidateCacheOnDelete', function($model, $context) use ($cacheKeys) {
            $this->invalidateCacheKeys($model, $cacheKeys);
        }, ['priority' => $priority]);
    }

    /**
     * Helper method to configure slug generation hooks
     *
     * @param string $sourceField
     * @param string $slugField
     * @param array $options
     * @return void
     */
    protected function configureSlugHooks(string $sourceField, string $slugField = 'slug', array $options = []): void
    {
        $this->initializeFilterServices();
        
        $separator = $options['separator'] ?? '-';
        $unique = $options['unique'] ?? true;
        $overwrite = $options['overwrite'] ?? false;
        
        // Generate slug before store
        $this->registerHook('beforeStore', 'generateSlugOnStore', function($model, $context) use ($sourceField, $slugField, $separator, $unique, $overwrite) {
            if (empty($model->$slugField) || $overwrite) {
                $sourceValue = $model->$sourceField;
                if (!empty($sourceValue)) {
                    $slug = \Str::slug($sourceValue, $separator);
                    
                    if ($unique) {
                        $slug = $this->makeSlugUnique($model, $slugField, $slug);
                    }
                    
                    $model->$slugField = $slug;
                }
            }
        }, ['priority' => 1]);
        
        // Update slug before update if source field changed
        $this->registerHook('beforeUpdate', 'updateSlugOnUpdate', function($model, $context) use ($sourceField, $slugField, $separator, $unique, $overwrite) {
            if ($model->isDirty($sourceField) && ($overwrite || empty($model->$slugField))) {
                $sourceValue = $model->$sourceField;
                if (!empty($sourceValue)) {
                    $slug = \Str::slug($sourceValue, $separator);
                    
                    if ($unique) {
                        $slug = $this->makeSlugUnique($model, $slugField, $slug);
                    }
                    
                    $model->$slugField = $slug;
                }
            }
        }, ['priority' => 1]);
    }

    /**
     * Helper method to configure permission hooks
     *
     * @param array $permissions
     * @param array $options
     * @return void
     */
    protected function configurePermissionHooks(array $permissions, array $options = []): void
    {
        $this->initializeFilterServices();
        
        $userField = $options['user_field'] ?? 'user_id';
        $throwOnFailure = $options['throw_on_failure'] ?? true;
        
        // Check permissions before store
        if (isset($permissions['create'])) {
            $this->registerHook('beforeStore', 'checkCreatePermission', function($model, $context) use ($permissions, $throwOnFailure) {
                if (!$this->checkPermission($permissions['create'], $model, 'create')) {
                    if ($throwOnFailure) {
                        throw new \Illuminate\Auth\Access\AuthorizationException('Unauthorized to create this resource');
                    }
                    return false;
                }
            }, ['priority' => 1, 'stopOnFailure' => true]);
        }
        
        // Check permissions before update
        if (isset($permissions['update'])) {
            $this->registerHook('beforeUpdate', 'checkUpdatePermission', function($model, $context) use ($permissions, $throwOnFailure) {
                if (!$this->checkPermission($permissions['update'], $model, 'update')) {
                    if ($throwOnFailure) {
                        throw new \Illuminate\Auth\Access\AuthorizationException('Unauthorized to update this resource');
                    }
                    return false;
                }
            }, ['priority' => 1, 'stopOnFailure' => true]);
        }
        
        // Check permissions before delete
        if (isset($permissions['delete'])) {
            $this->registerHook('beforeDelete', 'checkDeletePermission', function($model, $context) use ($permissions, $throwOnFailure) {
                if (!$this->checkPermission($permissions['delete'], $model, 'delete')) {
                    if ($throwOnFailure) {
                        throw new \Illuminate\Auth\Access\AuthorizationException('Unauthorized to delete this resource');
                    }
                    return false;
                }
                return true;
            }, ['priority' => 1, 'stopOnFailure' => true]);
        }
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

    /**
     * Send notification helper method
     *
     * @param mixed $model
     * @param HookContext $context
     * @param array $config
     * @param string $action
     * @return void
     */
    protected function sendNotification($model, $context, array $config, string $action): void
    {
        $notificationClass = $config['notification'] ?? null;
        $recipients = $config['recipients'] ?? [];
        $channels = $config['channels'] ?? ['mail'];
        
        if (!$notificationClass || empty($recipients)) {
            return;
        }
        
        // Resolve recipients
        $resolvedRecipients = $this->resolveNotificationRecipients($recipients, $model, $context);
        
        // Create notification data
        $notificationData = [
            'model' => $model,
            'action' => $action,
            'user' => auth()->user(),
            'timestamp' => now(),
            'additional_data' => $config['data'] ?? []
        ];
        
        // Send notification
        foreach ($resolvedRecipients as $recipient) {
            $recipient->notify(new $notificationClass($notificationData));
        }
    }

    /**
     * Resolve notification recipients
     *
     * @param array $recipients
     * @param mixed $model
     * @param HookContext $context
     * @return array
     */
    protected function resolveNotificationRecipients(array $recipients, $model, $context): array
    {
        $resolved = [];
        
        foreach ($recipients as $recipient) {
            if (is_string($recipient)) {
                // Handle string recipients (user IDs, emails, etc.)
                if (is_numeric($recipient)) {
                    $user = \App\Models\User::find($recipient);
                    if ($user) {
                        $resolved[] = $user;
                    }
                } elseif (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    // Handle email addresses
                    $user = \App\Models\User::where('email', $recipient)->first();
                    if ($user) {
                        $resolved[] = $user;
                    }
                } elseif ($recipient === 'owner' && method_exists($model, 'user')) {
                    // Handle model owner
                    $owner = $model->user;
                    if ($owner) {
                        $resolved[] = $owner;
                    }
                } elseif ($recipient === 'current_user' && auth()->check()) {
                    $resolved[] = auth()->user();
                }
            } elseif (is_callable($recipient)) {
                // Handle callable recipients
                $result = $recipient($model, $context);
                if ($result) {
                    $resolved = array_merge($resolved, is_array($result) ? $result : [$result]);
                }
            }
        }
        
        return $resolved;
    }

    /**
     * Invalidate cache keys helper method
     *
     * @param mixed $model
     * @param array $cacheKeys
     * @return void
     */
    protected function invalidateCacheKeys($model, array $cacheKeys): void
    {
        foreach ($cacheKeys as $key) {
            // Replace placeholders in cache key
            $resolvedKey = $this->resolveCacheKeyPlaceholders($key, $model);
            
            if (is_array($resolvedKey)) {
                // Handle multiple keys (e.g., when using wildcards)
                foreach ($resolvedKey as $k) {
                    \Cache::forget($k);
                }
            } else {
                \Cache::forget($resolvedKey);
            }
        }
        
        // Also clear cache tags if using tagged cache
        $modelClass = get_class($model);
        $tags = [
            strtolower(class_basename($modelClass)),
            $modelClass . '_' . $model->getKey()
        ];
        
        try {
            \Cache::tags($tags)->flush();
        } catch (\Exception $e) {
            // Ignore if cache driver doesn't support tags
        }
    }

    /**
     * Resolve cache key placeholders
     *
     * @param string $key
     * @param mixed $model
     * @return string|array
     */
    protected function resolveCacheKeyPlaceholders(string $key, $model)
    {
        $replacements = [
            '{model_class}' => strtolower(class_basename(get_class($model))),
            '{model_id}' => $model->getKey(),
            '{user_id}' => auth()->id() ?? 'guest',
        ];
        
        // Add model attributes as placeholders
        foreach ($model->getAttributes() as $attribute => $value) {
            $replacements['{' . $attribute . '}'] = $value;
        }
        
        return str_replace(array_keys($replacements), array_values($replacements), $key);
    }

    /**
     * Make slug unique helper method
     *
     * @param mixed $model
     * @param string $slugField
     * @param string $slug
     * @return string
     */
    protected function makeSlugUnique($model, string $slugField, string $slug): string
    {
        $originalSlug = $slug;
        $counter = 1;
        
        $modelClass = get_class($model);
        
        while (true) {
            $query = $modelClass::where($slugField, $slug);
            
            // Exclude current model if updating
            if ($model->exists) {
                $query->where($model->getKeyName(), '!=', $model->getKey());
            }
            
            if (!$query->exists()) {
                break;
            }
            
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    /**
     * Check permission helper method
     *
     * @param string|array|callable $permission
     * @param mixed $model
     * @param string $action
     * @return bool
     */
    protected function checkPermission($permission, $model, string $action): bool
    {
        if (!auth()->check()) {
            return false;
        }
        
        $user = auth()->user();
        
        if (is_string($permission)) {
            // Simple permission string
            return $user->can($permission, $model);
        } elseif (is_array($permission)) {
            // Array of permissions (all must pass)
            foreach ($permission as $perm) {
                if (!$user->can($perm, $model)) {
                    return false;
                }
            }
            return true;
        } elseif (is_callable($permission)) {
            // Custom permission callback
            return $permission($user, $model, $action);
        }
        
        return false;
    }

    /**
     * Get hook service instance
     *
     * @return ModelHookService
     */
    public function getHookService(): ModelHookService
    {
        $this->initializeFilterServices();
        return $this->hookService;
    }

    /**
     * Get hooks metadata for debugging
     *
     * @return array
     */
    public function getHooksMetadata(): array
    {
        $this->initializeFilterServices();
        return $this->hookService->getMetadata();
    }

    /**
     * Clear all hooks or hooks for a specific type
     *
     * @param string|null $hookType
     * @return void
     */
    public function clearHooks(string $hookType = null): void
    {
        $this->initializeFilterServices();
        $this->hookService->clearHooks($hookType);
    }

    /**
     * Store a new resource with hooks
     *
     * @param Request $request
     * @param array $data
     * @return JsonResponse
     */
    public function storeWithHooks(Request $request, array $data): JsonResponse
    {
        try {
            $this->initializeFilterServices();
            
            $modelClass = $this->getModelClass();
            $model = new $modelClass();
            
            // Fill model with data
            $model->fill($data);
            
            // Create hook context
            $context = new \stdClass();
            $context->request = $request;
            $context->operation = 'store';
            
            // Execute beforeStore hooks
            $model = $this->hookService->execute('beforeStore', $model, $context);
            
            // Save the model
            $model->save();
            
            // Execute afterStore hooks
            $model = $this->hookService->execute('afterStore', $model, $context);
            
            return response()->json([
                'success' => true,
                'data' => $model,
                'message' => 'Resource created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => $e->getMessage(),
                    'type' => get_class($e)
                ]
            ], 500);
        }
    }

    /**
     * Update a resource with hooks
     *
     * @param Request $request
     * @param string $id
     * @param array $data
     * @return JsonResponse
     */
    public function updateWithHooks(Request $request, string $id, array $data): JsonResponse
    {
        try {
            $this->initializeFilterServices();
            
            $modelClass = $this->getModelClass();
            $model = $modelClass::findOrFail($id);
            
            // Store original model for hooks
            $originalModel = $model->replicate();
            
            // Fill model with new data
            $model->fill($data);
            
            // Create hook context
            $context = new \stdClass();
            $context->request = $request;
            $context->operation = 'update';
            $context->originalModel = $originalModel;
            
            // Execute beforeUpdate hooks
            $model = $this->hookService->execute('beforeUpdate', $model, $context);
            
            // Save the model
            $model->save();
            
            // Execute afterUpdate hooks
            $model = $this->hookService->execute('afterUpdate', $model, $context);
            
            return response()->json([
                'success' => true,
                'data' => $model,
                'message' => 'Resource updated successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => $e->getMessage(),
                    'type' => get_class($e)
                ]
            ], 500);
        }
    }

    /**
     * Delete a resource with hooks
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function destroyWithHooks(Request $request, string $id): JsonResponse
    {
        try {
            $this->initializeFilterServices();
            
            $modelClass = $this->getModelClass();
            $model = $modelClass::findOrFail($id);
            
            // Create hook context
            $context = new \stdClass();
            $context->request = $request;
            $context->operation = 'delete';
            
            // Execute beforeDelete hooks
            $this->hookService->execute('beforeDelete', $model, $context);
            
            // Delete the model
            $model->delete();
            
            // Execute afterDelete hooks
            $this->hookService->execute('afterDelete', $model, $context);
            
            return response()->json([
                'success' => true,
                'message' => 'Resource deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => $e->getMessage(),
                    'type' => get_class($e)
                ]
            ], 500);
        }
    }

    /**
     * Show a resource with filters
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function showWithFilters(Request $request, string $id): JsonResponse
    {
        try {
            $this->initializeFilterServices();
            
            $modelClass = $this->getModelClass();
            $query = $modelClass::where('id', $id);
            
            // Apply field selection
            $this->applyFieldSelection($query, $request);
            
            $model = $query->firstOrFail();
            
            // Process virtual fields if any were requested
            $this->processVirtualFieldsForCollection(collect([$model]), $request);
            
            return response()->json([
                'success' => true,
                'data' => $model
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => $e->getMessage(),
                    'type' => get_class($e)
                ]
            ], 404);
        }
    }
}