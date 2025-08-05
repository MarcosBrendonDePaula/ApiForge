<?php

namespace MarcosBrendon\ApiForge\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
        return $this->indexWithFilters($request);
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
        $query = $modelClass::query();

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

        return response()->json([
            'success' => true,
            'data' => $resource,
            'metadata' => [
                'timestamp' => now()->format('c'),
                'api_version' => 'v2'
            ]
        ]);
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

        // Validar dados
        $validatedData = $this->validateStoreData($request);

        // Aplicar transformações se necessário
        if (method_exists($this, 'transformStoreData')) {
            $validatedData = $this->transformStoreData($validatedData, $request);
        }

        $modelClass = $this->getModelClass();
        $resource = $modelClass::create($validatedData);

        // Carregar relacionamentos se necessário
        if (method_exists($this, 'getDefaultRelationships')) {
            $relationships = $this->getDefaultRelationships();
            if (!empty($relationships)) {
                $resource->load($relationships);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $resource,
            'message' => 'Recurso criado com sucesso'
        ], 201);
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

        // Validar dados
        $validatedData = $this->validateUpdateData($request, $resource);

        // Aplicar transformações se necessário
        if (method_exists($this, 'transformUpdateData')) {
            $validatedData = $this->transformUpdateData($validatedData, $request, $resource);
        }

        $resource->update($validatedData);

        // Carregar relacionamentos se necessário
        if (method_exists($this, 'getDefaultRelationships')) {
            $relationships = $this->getDefaultRelationships();
            if (!empty($relationships)) {
                $resource->load($relationships);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $resource,
            'message' => 'Recurso atualizado com sucesso'
        ]);
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

        // Aplicar soft delete se disponível, senão delete permanente
        if (method_exists($resource, 'delete')) {
            $resource->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Recurso removido com sucesso'
        ]);
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
}