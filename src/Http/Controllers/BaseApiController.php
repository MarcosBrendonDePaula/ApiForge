<?php

namespace MarcosBrendon\ApiForge\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MarcosBrendon\ApiForge\Traits\HasAdvancedFilters;

abstract class BaseApiController extends Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests, HasAdvancedFilters;

    /**
     * Lista recursos com filtros avançados
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $this->initializeFilterServices();
        
        $modelClass = $this->getModelClass();
        $tempModel = new $modelClass();

        try {
            // Executar hooks beforeAuthorization
            if ($this->hookService && $this->hookService->hasHook('beforeAuthorization')) {
                $authorized = $this->hookService->executeBeforeAuthorization($tempModel, $request, 'index');
                if (!$authorized) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Não autorizado para listar estes recursos'
                    ], 403);
                }
            }

            // Executar hooks beforeQuery
            if ($this->hookService && $this->hookService->hasHook('beforeQuery')) {
                $this->hookService->executeBeforeQuery($tempModel, $request, null);
            }

            // Executar hooks beforeCache
            $cacheData = [];
            if ($this->hookService && $this->hookService->hasHook('beforeCache')) {
                $cacheData = $this->hookService->executeBeforeCache($tempModel, $request, [
                    'query_params' => $request->all()
                ]);
            }

            $result = $this->indexWithFilters($request);
            $responseData = json_decode($result->getContent(), true);

            // Executar hooks afterQuery
            if ($this->hookService && $this->hookService->hasHook('afterQuery')) {
                $this->hookService->executeAfterQuery($tempModel, $request, $responseData);
            }

            // Executar hooks afterCache
            if ($this->hookService && $this->hookService->hasHook('afterCache')) {
                $this->hookService->executeAfterCache($tempModel, $request, [
                    'cached' => !empty($cacheData),
                    'result_count' => isset($responseData['data']) ? count($responseData['data']) : 0
                ]);
            }

            // Executar hooks beforeResponse
            if ($this->hookService && $this->hookService->hasHook('beforeResponse')) {
                $responseData = $this->hookService->executeBeforeResponse($tempModel, $request, $responseData);
            }

            // Executar hooks afterResponse
            if ($this->hookService && $this->hookService->hasHook('afterResponse')) {
                $this->hookService->executeAfterResponse($tempModel, $request, $responseData);
            }

            return response()->json($responseData, $result->getStatusCode());

        } catch (\Exception $e) {
            Log::error('Error in index method with hooks', [
                'exception' => $e->getMessage(),
                'model_class' => $modelClass
            ]);

            // Fallback to original method if hooks fail
            return $this->indexWithFilters($request);
        }
    }

    /**
     * Exibe um recurso específico
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $this->initializeFilterServices();

        $modelClass = $this->getModelClass();
        
        try {
            // Executar hooks beforeAuthorization
            $tempModel = new $modelClass();
            if ($this->hookService && $this->hookService->hasHook('beforeAuthorization')) {
                $authorized = $this->hookService->executeBeforeAuthorization($tempModel, $request, 'show');
                if (!$authorized) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Não autorizado para visualizar este recurso'
                    ], 403);
                }
            }

            $query = $modelClass::query();

            // Executar hooks beforeQuery
            if ($this->hookService && $this->hookService->hasHook('beforeQuery')) {
                $this->hookService->executeBeforeQuery($tempModel, $request, $query);
            }

            // Aplicar relacionamentos padrão
            if (method_exists($this, 'getDefaultRelationships')) {
                $relationships = $this->getDefaultRelationships();
                if (!empty($relationships)) {
                    $query->with($relationships);
                }
            }

            // Aplicar field selection se solicitado
            $this->applyFieldSelection($query, $request);

            $resource = $query->findOrFail($id);

            // Executar hooks afterQuery
            if ($this->hookService && $this->hookService->hasHook('afterQuery')) {
                $this->hookService->executeAfterQuery($resource, $request, $resource);
            }

            // Preparar dados de resposta
            $responseData = [
                'success' => true,
                'data' => $resource,
                'metadata' => [
                    'timestamp' => now()->format('c'),
                    'api_version' => 'v2'
                ]
            ];

            // Executar hooks beforeResponse
            if ($this->hookService && $this->hookService->hasHook('beforeResponse')) {
                $responseData = $this->hookService->executeBeforeResponse($resource, $request, $responseData);
            }

            // Executar hooks afterResponse
            if ($this->hookService && $this->hookService->hasHook('afterResponse')) {
                $this->hookService->executeAfterResponse($resource, $request, $responseData);
            }

            return response()->json($responseData);

        } catch (\Exception $e) {
            Log::error('Error in show method with hooks', [
                'exception' => $e->getMessage(),
                'model_class' => $modelClass,
                'model_id' => $id
            ]);

            // Fallback to original logic if hooks fail
            $query = $modelClass::query();
            
            if (method_exists($this, 'getDefaultRelationships')) {
                $relationships = $this->getDefaultRelationships();
                if (!empty($relationships)) {
                    $query->with($relationships);
                }
            }

            $this->applyFieldSelection($query, $request);
            $resource = $query->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $resource,
                'metadata' => [
                    'timestamp' => now()->format('c'),
                    'api_version' => 'v2'
                ]
            ]);
        }
    }

    /**
     * Cria um novo recurso
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $this->initializeFilterServices();

        $modelClass = $this->getModelClass();
        $tempModel = new $modelClass();

        try {
            DB::beginTransaction();

            // Executar hooks beforeAuthorization
            if ($this->hookService && $this->hookService->hasHook('beforeAuthorization')) {
                $authorized = $this->hookService->executeBeforeAuthorization($tempModel, $request, 'store');
                if (!$authorized) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Não autorizado para criar este recurso'
                    ], 403);
                }
            }

            // Executar hooks beforeValidation
            $requestData = $request->all();
            if ($this->hookService && $this->hookService->hasHook('beforeValidation')) {
                $requestData = $this->hookService->executeBeforeValidation($tempModel, $request, $requestData);
            }

            // Validar dados
            $validatedData = $this->validateStoreData($request);

            // Executar hooks afterValidation
            if ($this->hookService && $this->hookService->hasHook('afterValidation')) {
                $this->hookService->executeAfterValidation($tempModel, $request, $validatedData);
            }

            // Executar hooks beforeTransform
            if ($this->hookService && $this->hookService->hasHook('beforeTransform')) {
                $validatedData = $this->hookService->executeBeforeTransform($tempModel, $request, $validatedData);
            }

            // Aplicar transformações se necessário
            if (method_exists($this, 'transformStoreData')) {
                $validatedData = $this->transformStoreData($validatedData, $request);
            }

            // Executar hooks afterTransform
            if ($this->hookService && $this->hookService->hasHook('afterTransform')) {
                $this->hookService->executeAfterTransform($tempModel, $request, $validatedData);
            }

            // Criar instância temporária do modelo para hooks beforeStore
            $resource = new $modelClass($validatedData);

            // Executar hooks beforeStore
            if ($this->hookService && $this->hookService->hasHook('beforeStore')) {
                $this->hookService->executeBeforeStore($resource, $request);
            }

            // Criar o recurso no banco de dados
            $resource = $modelClass::create($validatedData);

            // Executar hooks beforeAudit
            if ($this->hookService && $this->hookService->hasHook('beforeAudit')) {
                $this->hookService->executeBeforeAudit($resource, $request, $validatedData);
            }

            // Executar hooks afterStore
            if ($this->hookService && $this->hookService->hasHook('afterStore')) {
                $this->hookService->executeAfterStore($resource, $request);
            }

            // Executar hooks afterAudit
            if ($this->hookService && $this->hookService->hasHook('afterAudit')) {
                $this->hookService->executeAfterAudit($resource, $request, [
                    'action' => 'store',
                    'model_id' => $resource->getKey(),
                    'data' => $validatedData
                ]);
            }

            // Carregar relacionamentos se necessário
            if (method_exists($this, 'getDefaultRelationships')) {
                $relationships = $this->getDefaultRelationships();
                if (!empty($relationships)) {
                    $resource->load($relationships);
                }
            }

            // Preparar dados de resposta
            $responseData = [
                'success' => true,
                'data' => $resource,
                'message' => 'Recurso criado com sucesso'
            ];

            // Executar hooks beforeResponse
            if ($this->hookService && $this->hookService->hasHook('beforeResponse')) {
                $responseData = $this->hookService->executeBeforeResponse($resource, $request, $responseData);
            }

            // Executar hooks beforeNotification
            if ($this->hookService && $this->hookService->hasHook('beforeNotification')) {
                $notificationData = $this->hookService->executeBeforeNotification($resource, $request, [
                    'action' => 'created',
                    'model' => $resource
                ]);
                
                // Aqui você pode implementar o envio de notificações
                // NotificationService::send($notificationData);
            }

            DB::commit();

            // Executar hooks afterResponse
            if ($this->hookService && $this->hookService->hasHook('afterResponse')) {
                $this->hookService->executeAfterResponse($resource, $request, $responseData);
            }

            return response()->json($responseData, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log the error
            Log::error('Error creating resource in store method', [
                'exception' => $e->getMessage(),
                'model_class' => $modelClass,
                'data' => $validatedData ?? $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar recurso: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza um recurso existente
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->initializeFilterServices();

        $modelClass = $this->getModelClass();
        $resource = $modelClass::findOrFail($id);

        try {
            DB::beginTransaction();

            // Executar hooks beforeAuthorization
            if ($this->hookService && $this->hookService->hasHook('beforeAuthorization')) {
                $authorized = $this->hookService->executeBeforeAuthorization($resource, $request, 'update');
                if (!$authorized) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Não autorizado para atualizar este recurso'
                    ], 403);
                }
            }

            // Executar hooks beforeValidation
            $requestData = $request->all();
            if ($this->hookService && $this->hookService->hasHook('beforeValidation')) {
                $requestData = $this->hookService->executeBeforeValidation($resource, $request, $requestData);
            }

            // Validar dados
            $validatedData = $this->validateUpdateData($request, $resource);

            // Executar hooks afterValidation
            if ($this->hookService && $this->hookService->hasHook('afterValidation')) {
                $this->hookService->executeAfterValidation($resource, $request, $validatedData);
            }

            // Executar hooks beforeTransform
            if ($this->hookService && $this->hookService->hasHook('beforeTransform')) {
                $validatedData = $this->hookService->executeBeforeTransform($resource, $request, $validatedData);
            }

            // Aplicar transformações se necessário
            if (method_exists($this, 'transformUpdateData')) {
                $validatedData = $this->transformUpdateData($validatedData, $request, $resource);
            }

            // Executar hooks afterTransform
            if ($this->hookService && $this->hookService->hasHook('afterTransform')) {
                $this->hookService->executeAfterTransform($resource, $request, $validatedData);
            }

            // Capturar dados originais para o contexto
            $originalData = $resource->getOriginal();

            // Executar hooks beforeUpdate
            if ($this->hookService && $this->hookService->hasHook('beforeUpdate')) {
                $hookData = [
                    'original' => $originalData,
                    'updated' => $validatedData,
                    'changes' => array_diff_assoc($validatedData, $originalData)
                ];
                $this->hookService->executeBeforeUpdate($resource, $request, $hookData);
            }

            // Executar hooks beforeAudit
            if ($this->hookService && $this->hookService->hasHook('beforeAudit')) {
                $changes = array_diff_assoc($validatedData, $originalData);
                $this->hookService->executeBeforeAudit($resource, $request, $changes);
            }

            // Atualizar o recurso
            $resource->update($validatedData);

            // Executar hooks afterUpdate
            if ($this->hookService && $this->hookService->hasHook('afterUpdate')) {
                $hookData = [
                    'original' => $originalData,
                    'updated' => $resource->toArray(),
                    'changes' => $resource->getChanges()
                ];
                $this->hookService->executeAfterUpdate($resource, $request, $hookData);
            }

            // Executar hooks afterAudit
            if ($this->hookService && $this->hookService->hasHook('afterAudit')) {
                $this->hookService->executeAfterAudit($resource, $request, [
                    'action' => 'update',
                    'model_id' => $resource->getKey(),
                    'original' => $originalData,
                    'changes' => $resource->getChanges()
                ]);
            }

            // Carregar relacionamentos se necessário
            if (method_exists($this, 'getDefaultRelationships')) {
                $relationships = $this->getDefaultRelationships();
                if (!empty($relationships)) {
                    $resource->load($relationships);
                }
            }

            // Preparar dados de resposta
            $responseData = [
                'success' => true,
                'data' => $resource,
                'message' => 'Recurso atualizado com sucesso'
            ];

            // Executar hooks beforeResponse
            if ($this->hookService && $this->hookService->hasHook('beforeResponse')) {
                $responseData = $this->hookService->executeBeforeResponse($resource, $request, $responseData);
            }

            // Executar hooks beforeNotification
            if ($this->hookService && $this->hookService->hasHook('beforeNotification')) {
                $notificationData = $this->hookService->executeBeforeNotification($resource, $request, [
                    'action' => 'updated',
                    'model' => $resource,
                    'changes' => $resource->getChanges()
                ]);
                
                // Aqui você pode implementar o envio de notificações
                // NotificationService::send($notificationData);
            }

            DB::commit();

            // Executar hooks afterResponse
            if ($this->hookService && $this->hookService->hasHook('afterResponse')) {
                $this->hookService->executeAfterResponse($resource, $request, $responseData);
            }

            return response()->json($responseData);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log the error
            Log::error('Error updating resource in update method', [
                'exception' => $e->getMessage(),
                'model_class' => $modelClass,
                'model_id' => $id,
                'data' => $validatedData ?? $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar recurso: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove um recurso
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->initializeFilterServices();

        $modelClass = $this->getModelClass();
        $resource = $modelClass::findOrFail($id);

        try {
            DB::beginTransaction();

            // Executar hooks beforeAuthorization
            if ($this->hookService && $this->hookService->hasHook('beforeAuthorization')) {
                $authorized = $this->hookService->executeBeforeAuthorization($resource, $request, 'delete');
                if (!$authorized) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Não autorizado para remover este recurso'
                    ], 403);
                }
            }

            // Verificar se pode ser deletado
            if (method_exists($this, 'canDelete')) {
                $canDelete = $this->canDelete($resource, $request);
                if (!$canDelete) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Este recurso não pode ser removido'
                    ], 422);
                }
            }

            // Executar hooks beforeDelete
            if ($this->hookService && $this->hookService->hasHook('beforeDelete')) {
                $canDelete = $this->hookService->executeBeforeDelete($resource, $request);
                if (!$canDelete) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'A remoção foi impedida por regras de negócio'
                    ], 422);
                }
            }

            // Executar hooks beforeAudit
            if ($this->hookService && $this->hookService->hasHook('beforeAudit')) {
                $this->hookService->executeBeforeAudit($resource, $request, [
                    'action' => 'delete',
                    'resource_data' => $resource->toArray()
                ]);
            }

            // Capturar dados do recurso antes da exclusão para hooks afterDelete
            $resourceData = $resource->toArray();

            // Aplicar soft delete se disponível, senão delete permanente
            if (method_exists($resource, 'delete')) {
                $resource->delete();
            }

            // Executar hooks afterDelete
            if ($this->hookService && $this->hookService->hasHook('afterDelete')) {
                // Criar uma cópia do modelo com os dados originais para o hook
                $deletedResource = new $modelClass($resourceData);
                $deletedResource->setAttribute($resource->getKeyName(), $id);
                $this->hookService->executeAfterDelete($deletedResource, $request);
            }

            // Executar hooks afterAudit
            if ($this->hookService && $this->hookService->hasHook('afterAudit')) {
                $this->hookService->executeAfterAudit($resource, $request, [
                    'action' => 'delete',
                    'model_id' => $id,
                    'deleted_data' => $resourceData
                ]);
            }

            // Preparar dados de resposta
            $responseData = [
                'success' => true,
                'message' => 'Recurso removido com sucesso'
            ];

            // Executar hooks beforeResponse
            if ($this->hookService && $this->hookService->hasHook('beforeResponse')) {
                $responseData = $this->hookService->executeBeforeResponse($resource, $request, $responseData);
            }

            // Executar hooks beforeNotification
            if ($this->hookService && $this->hookService->hasHook('beforeNotification')) {
                $notificationData = $this->hookService->executeBeforeNotification($resource, $request, [
                    'action' => 'deleted',
                    'model_data' => $resourceData
                ]);
                
                // Aqui você pode implementar o envio de notificações
                // NotificationService::send($notificationData);
            }

            DB::commit();

            // Executar hooks afterResponse
            if ($this->hookService && $this->hookService->hasHook('afterResponse')) {
                $this->hookService->executeAfterResponse($resource, $request, $responseData);
            }

            return response()->json($responseData);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log the error
            Log::error('Error deleting resource in destroy method', [
                'exception' => $e->getMessage(),
                'model_class' => $modelClass,
                'model_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao remover recurso: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter estatísticas do recurso
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        $this->initializeFilterServices();

        $modelClass = $this->getModelClass();
        $query = $modelClass::query();

        // Aplicar scopes padrão se disponível
        if (method_exists($this, 'applyDefaultScopes')) {
            $this->applyDefaultScopes($query, $request);
        }

        $stats = [
            'total' => $query->count(),
        ];

        // Adicionar estatísticas customizadas se o método existir
        if (method_exists($this, 'getCustomStatistics')) {
            $customStats = $this->getCustomStatistics($query, $request);
            $stats = array_merge($stats, $customStats);
        }

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Busca rápida
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function quickSearch(Request $request): JsonResponse
    {
        $this->initializeFilterServices();

        $searchTerm = $request->get('q');
        $limit = min(50, max(1, (int) $request->get('limit', 10)));

        if (empty($searchTerm)) {
            return response()->json([
                'success' => false,
                'message' => 'Termo de busca é obrigatório'
            ], 400);
        }

        $searchableFields = $this->filterConfigService->getSearchableFields();
        if (empty($searchableFields)) {
            return response()->json([
                'success' => false,
                'message' => 'Busca não configurada para este recurso'
            ], 400);
        }

        $modelClass = $this->getModelClass();
        $query = $modelClass::query();

        // Aplicar scopes padrão se disponível
        if (method_exists($this, 'applyDefaultScopes')) {
            $this->applyDefaultScopes($query, $request);
        }

        // Aplicar busca nos campos configurados
        $query->where(function ($q) use ($searchableFields, $searchTerm) {
            foreach ($searchableFields as $field) {
                $q->orWhere($field, 'LIKE', "%{$searchTerm}%");
            }
        });

        // Aplicar field selection para busca rápida
        if (method_exists($this, 'getQuickSearchFields')) {
            $fields = $this->getQuickSearchFields();
            $query->select($fields);
        }

        $results = $query->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $results
        ]);
    }

    /**
     * Obter configuração padrão per_page
     *
     * @return int
     */
    protected function getDefaultPerPage(): int
    {
        return config('apiforge.pagination.default_per_page', 15);
    }

    /**
     * Obter configuração máxima per_page
     *
     * @return int
     */
    protected function getMaxPerPage(): int
    {
        return config('apiforge.pagination.max_per_page', 100);
    }

    /**
     * Obter ordenação padrão
     *
     * @return array [field, direction]
     */
    protected function getDefaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    /**
     * Validar dados para criação (deve ser implementado pela classe filha)
     *
     * @param Request $request
     * @return array
     */
    abstract protected function validateStoreData(Request $request): array;

    /**
     * Validar dados para atualização (deve ser implementado pela classe filha)
     *
     * @param Request $request
     * @param mixed $resource
     * @return array
     */
    abstract protected function validateUpdateData(Request $request, $resource): array;

    /**
     * Implementação padrão para aplicar scopes
     *
     * @param mixed $query
     * @param Request $request
     * @return void
     */
    protected function applyDefaultScopes($query, Request $request): void
    {
        // Implementação padrão vazia - pode ser sobrescrita pelas classes filhas
    }

    /**
     * Obter relacionamentos padrão
     *
     * @return array
     */
    protected function getDefaultRelationships(): array
    {
        return [];
    }

    /**
     * Obter campos para busca rápida
     *
     * @return array
     */
    protected function getQuickSearchFields(): array
    {
        return ['id'];
    }

    /**
     * Configurar hooks do modelo
     *
     * @param array $hooks
     * @return void
     */
    protected function configureModelHooks(array $hooks): void
    {
        if (!$this->hookService) {
            return;
        }

        foreach ($hooks as $hookType => $hookDefinitions) {
            if (is_array($hookDefinitions)) {
                foreach ($hookDefinitions as $hookName => $hookConfig) {
                    if (is_callable($hookConfig)) {
                        // Hook simples com apenas callback
                        $this->hookService->register($hookType, $hookName, $hookConfig);
                    } elseif (is_array($hookConfig) && isset($hookConfig['callback'])) {
                        // Hook com configurações avançadas
                        $options = [
                            'priority' => $hookConfig['priority'] ?? 10,
                            'stopOnFailure' => $hookConfig['stopOnFailure'] ?? false,
                            'conditions' => $hookConfig['conditions'] ?? [],
                            'description' => $hookConfig['description'] ?? ''
                        ];
                        $this->hookService->register($hookType, $hookName, $hookConfig['callback'], $options);
                    }
                }
            }
        }
    }

    /**
     * Registrar um hook específico
     *
     * @param string $hookType
     * @param string $hookName
     * @param callable $callback
     * @param array $options
     * @return void
     */
    protected function registerHook(string $hookType, string $hookName, callable $callback, array $options = []): void
    {
        if ($this->hookService) {
            $this->hookService->register($hookType, $hookName, $callback, $options);
        }
    }

    /**
     * Verificar se um hook existe
     *
     * @param string $hookType
     * @return bool
     */
    protected function hasHook(string $hookType): bool
    {
        return $this->hookService && $this->hookService->hasHook($hookType);
    }

    /**
     * Obter todos os hooks registrados
     *
     * @return array
     */
    protected function getRegisteredHooks(): array
    {
        return $this->hookService ? $this->hookService->getHooks() : [];
    }
}