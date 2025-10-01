<?php

namespace MarcosBrendon\ApiForge\Observers;

use Illuminate\Database\Eloquent\Model;
use MarcosBrendon\ApiForge\Services\CacheService;

class CacheInvalidationObserver
{
    /**
     * Serviço de cache
     */
    protected CacheService $cacheService;

    /**
     * Constructor
     */
    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Handle the Model "created" event.
     */
    public function created(Model $model): void
    {
        $this->invalidateModelCache($model, 'created');
    }

    /**
     * Handle the Model "updated" event.
     */
    public function updated(Model $model): void
    {
        $this->invalidateModelCache($model, 'updated');
    }

    /**
     * Handle the Model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        $this->invalidateModelCache($model, 'deleted');
    }

    /**
     * Handle the Model "restored" event.
     */
    public function restored(Model $model): void
    {
        $this->invalidateModelCache($model, 'restored');
    }

    /**
     * Handle the Model "forceDeleted" event.
     */
    public function forceDeleted(Model $model): void
    {
        $this->invalidateModelCache($model, 'forceDeleted');
    }

    /**
     * Invalidar cache do modelo e relacionados
     *
     * @param Model $model
     * @param string $operation
     * @return void
     */
    protected function invalidateModelCache(Model $model, string $operation): void
    {
        // Verificar se invalidação automática está habilitada
        if (!config('apiforge.cache.enabled', false)) {
            return;
        }

        $modelClass = get_class($model);
        
        // Invalidar cache do modelo principal
        $this->cacheService->invalidateByModel($modelClass);

        // Invalidar cache de modelos relacionados
        $this->invalidateRelatedModels($model);

        // Invalidar por tabela
        if (method_exists($model, 'getTable')) {
            $this->cacheService->handleTableChange($model->getTable(), $operation);
        }

        // Log da invalidação se debug estiver ativo
        if (config('apiforge.debug.enabled')) {
            logger()->info('Model cache invalidated via observer', [
                'model' => $modelClass,
                'id' => $model->getKey(),
                'operation' => $operation,
                'table' => $model->getTable() ?? 'unknown'
            ]);
        }
    }

    /**
     * Invalidar cache de modelos relacionados
     *
     * @param Model $model
     * @return void
     */
    protected function invalidateRelatedModels(Model $model): void
    {
        // Obter relacionamentos que devem invalidar cache
        $relationsToInvalidate = $this->getRelationsToInvalidate($model);

        foreach ($relationsToInvalidate as $relation) {
            try {
                if (method_exists($model, $relation)) {
                    $relationInstance = $model->$relation();
                    $relatedModel = $relationInstance->getRelated();
                    
                    if ($relatedModel) {
                        $this->cacheService->invalidateByModel(get_class($relatedModel));
                    }
                }
            } catch (\Exception $e) {
                if (config('apiforge.debug.enabled')) {
                    logger()->warning('Failed to invalidate related model cache', [
                        'model' => get_class($model),
                        'relation' => $relation,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Obter lista de relacionamentos que devem invalidar cache
     *
     * @param Model $model
     * @return array
     */
    protected function getRelationsToInvalidate(Model $model): array
    {
        // Primeiro, verificar se o modelo define relacionamentos para invalidação
        if (method_exists($model, 'getCacheInvalidationRelations')) {
            return $model->getCacheInvalidationRelations();
        }

        // Configuração padrão baseada no tipo de modelo
        $modelClass = get_class($model);
        $relations = [];

        // Mapeamento comum de relacionamentos para invalidação
        $commonRelations = [
            'App\\Models\\User' => ['profile', 'company', 'roles', 'permissions'],
            'App\\Models\\Post' => ['user', 'category', 'tags', 'comments'],
            'App\\Models\\Order' => ['user', 'items', 'payments'],
            'App\\Models\\Product' => ['category', 'tags', 'reviews'],
        ];

        if (isset($commonRelations[$modelClass])) {
            $relations = $commonRelations[$modelClass];
        }

        // Permitir configuração customizada via config
        $customRelations = config("apiforge.cache.model_relations.{$modelClass}", []);
        $relations = array_merge($relations, $customRelations);

        return array_unique($relations);
    }
}