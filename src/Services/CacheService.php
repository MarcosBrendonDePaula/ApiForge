<?php

namespace MarcosBrendon\ApiForge\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CacheService
{
    /**
     * Prefix padrão para chaves de cache
     */
    protected string $keyPrefix;

    /**
     * TTL padrão para cache
     */
    protected int $defaultTtl;

    /**
     * Tags padrão para cache
     */
    protected array $defaultTags;

    /**
     * Store de cache configurado
     */
    protected string $cacheStore;

    /**
     * Tabelas monitoradas para invalidação automática
     */
    protected array $monitoredTables = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->keyPrefix = config('apiforge.cache.key_prefix', 'api_filters_');
        $this->defaultTtl = config('apiforge.cache.default_ttl', 3600);
        $this->defaultTags = config('apiforge.cache.tags', ['api', 'filters']);
        $this->cacheStore = config('apiforge.cache.store') ?: config('cache.default');
    }

    /**
     * Gerar chave de cache baseada em parâmetros
     *
     * @param string $model
     * @param array $params
     * @param array $additionalContext
     * @return string
     */
    public function generateKey(string $model, array $params = [], array $additionalContext = []): string
    {
        // Normalizar parâmetros para chave consistente
        $normalizedParams = $this->normalizeParams($params);
        
        $keyData = [
            'model' => $model,
            'params' => $normalizedParams,
            'context' => $additionalContext,
            'version' => $this->getCacheVersion($model)
        ];

        return $this->keyPrefix . md5(json_encode($keyData));
    }

    /**
     * Armazenar dados no cache com tags e TTL inteligente
     *
     * @param string $key
     * @param mixed $data
     * @param array $options
     * @return mixed
     */
    public function store(string $key, $data, array $options = [])
    {
        $ttl = $options['ttl'] ?? $this->defaultTtl;
        $tags = array_merge($this->defaultTags, $options['tags'] ?? []);
        $model = $options['model'] ?? null;

        // Adicionar tag do modelo se fornecido
        if ($model) {
            $tags[] = $this->getModelTag($model);
        }

        // Adicionar metadados para gerenciamento
        $cacheData = [
            'data' => $data,
            'metadata' => [
                'created_at' => now(),
                'expires_at' => now()->addSeconds($ttl),
                'model' => $model,
                'tags' => $tags,
                'size' => $this->calculateDataSize($data),
                'query_params' => $options['query_params'] ?? [],
            ]
        ];

        // Usar tags se o driver suportar
        if ($this->supportsTags()) {
            return Cache::store($this->cacheStore)
                ->tags($tags)
                ->put($key, $cacheData, $ttl);
        }

        // Fallback sem tags
        $result = Cache::store($this->cacheStore)->put($key, $cacheData, $ttl);
        
        // Registrar chave para invalidação manual
        $this->registerKeyForModel($key, $model, $tags);
        
        return $result;
    }

    /**
     * Recuperar dados do cache
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function retrieve(string $key, $default = null)
    {
        $cacheData = Cache::store($this->cacheStore)->get($key);
        
        if (!$cacheData) {
            return $default;
        }

        // Verificar se cache está expirado
        if (isset($cacheData['metadata']['expires_at'])) {
            $expiresAt = Carbon::parse($cacheData['metadata']['expires_at']);
            if ($expiresAt->isPast()) {
                $this->forget($key);
                return $default;
            }
        }

        // Registrar hit para estatísticas
        $this->recordCacheHit($key, $cacheData['metadata'] ?? []);

        return $cacheData['data'] ?? $cacheData;
    }

    /**
     * Remover chave específica do cache
     *
     * @param string $key
     * @return bool
     */
    public function forget(string $key): bool
    {
        $this->unregisterKey($key);
        return Cache::store($this->cacheStore)->forget($key);
    }

    /**
     * Invalidar cache por modelo
     *
     * @param string $model
     * @return bool
     */
    public function invalidateByModel(string $model): bool
    {
        $modelTag = $this->getModelTag($model);
        
        if ($this->supportsTags()) {
            Cache::store($this->cacheStore)->tags([$modelTag])->flush();
        } else {
            // Invalidação manual para drivers que não suportam tags
            $this->invalidateKeysForModel($model);
        }

        // Incrementar versão do cache para invalidar chaves futuras
        $this->incrementCacheVersion($model);

        if (config('apiforge.debug.enabled')) {
            logger()->info('Cache invalidated for model', ['model' => $model]);
        }

        return true;
    }

    /**
     * Invalidar cache por tags
     *
     * @param array $tags
     * @return bool
     */
    public function invalidateByTags(array $tags): bool
    {
        if ($this->supportsTags()) {
            Cache::store($this->cacheStore)->tags($tags)->flush();
            
            if (config('apiforge.debug.enabled')) {
                logger()->info('Cache invalidated by tags', ['tags' => $tags]);
            }
            
            return true;
        }

        // Para drivers sem suporte a tags, invalidar tudo
        return $this->flush();
    }

    /**
     * Limpar todo o cache da aplicação
     *
     * @return bool
     */
    public function flush(): bool
    {
        // Limpar registros de chaves
        Cache::store($this->cacheStore)->forget($this->keyPrefix . 'key_registry');
        
        if ($this->supportsTags()) {
            return Cache::store($this->cacheStore)->tags($this->defaultTags)->flush();
        }

        // Limpar cache completo se não há suporte a tags
        return Cache::store($this->cacheStore)->flush();
    }

    /**
     * Garbage collection - remover chaves expiradas
     *
     * @return int Número de chaves removidas
     */
    public function garbageCollect(): int
    {
        $removed = 0;
        
        if (!$this->supportsTags()) {
            $keyRegistry = $this->getKeyRegistry();
            
            foreach ($keyRegistry as $key => $metadata) {
                if (isset($metadata['expires_at'])) {
                    $expiresAt = Carbon::parse($metadata['expires_at']);
                    if ($expiresAt->isPast()) {
                        $this->forget($key);
                        $removed++;
                    }
                }
            }
        }

        return $removed;
    }

    /**
     * Obter estatísticas do cache
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $stats = [
            'total_keys' => 0,
            'total_size' => 0,
            'expired_keys' => 0,
            'hit_rate' => 0,
            'models' => [],
            'oldest_entry' => null,
            'newest_entry' => null,
        ];

        if (!$this->supportsTags()) {
            $keyRegistry = $this->getKeyRegistry();
            $hits = Cache::store($this->cacheStore)->get($this->keyPrefix . 'hits', []);
            $misses = Cache::store($this->cacheStore)->get($this->keyPrefix . 'misses', 0);
            
            $stats['total_keys'] = count($keyRegistry);
            $totalHits = array_sum($hits);
            
            if ($totalHits + $misses > 0) {
                $stats['hit_rate'] = round(($totalHits / ($totalHits + $misses)) * 100, 2);
            }

            foreach ($keyRegistry as $key => $metadata) {
                if (isset($metadata['size'])) {
                    $stats['total_size'] += $metadata['size'];
                }
                
                if (isset($metadata['expires_at'])) {
                    $expiresAt = Carbon::parse($metadata['expires_at']);
                    if ($expiresAt->isPast()) {
                        $stats['expired_keys']++;
                    }
                }

                if (isset($metadata['model'])) {
                    $model = $metadata['model'];
                    if (!isset($stats['models'][$model])) {
                        $stats['models'][$model] = 0;
                    }
                    $stats['models'][$model]++;
                }

                if (isset($metadata['created_at'])) {
                    $createdAt = Carbon::parse($metadata['created_at']);
                    if (!$stats['oldest_entry'] || $createdAt->lt(Carbon::parse($stats['oldest_entry']))) {
                        $stats['oldest_entry'] = $createdAt->toISOString();
                    }
                    if (!$stats['newest_entry'] || $createdAt->gt(Carbon::parse($stats['newest_entry']))) {
                        $stats['newest_entry'] = $createdAt->toISOString();
                    }
                }
            }
        }

        return $stats;
    }

    /**
     * Configurar invalidação automática para tabelas
     *
     * @param array $tables
     * @return void
     */
    public function monitorTables(array $tables): void
    {
        $this->monitoredTables = array_merge($this->monitoredTables, $tables);
    }

    /**
     * Callback para invalidação automática quando dados mudam
     *
     * @param string $table
     * @param string $operation
     * @return void
     */
    public function handleTableChange(string $table, string $operation = 'update'): void
    {
        if (!in_array($table, $this->monitoredTables)) {
            return;
        }

        // Mapear tabela para modelo
        $model = $this->tableToModel($table);
        
        if ($model) {
            $this->invalidateByModel($model);
            
            if (config('apiforge.debug.enabled')) {
                logger()->info('Auto-invalidation triggered', [
                    'table' => $table,
                    'model' => $model,
                    'operation' => $operation
                ]);
            }
        }
    }

    /**
     * Verificar se o driver de cache suporta tags
     *
     * @return bool
     */
    protected function supportsTags(): bool
    {
        $driver = config("cache.stores.{$this->cacheStore}.driver");
        return in_array($driver, ['redis', 'memcached']);
    }

    /**
     * Normalizar parâmetros para chave consistente
     *
     * @param array $params
     * @return array
     */
    protected function normalizeParams(array $params): array
    {
        // Remover parâmetros que não afetam o resultado
        $excludeKeys = ['_token', 'cache', 'debug'];
        $normalized = array_diff_key($params, array_flip($excludeKeys));
        
        // Ordenar chaves
        ksort($normalized);
        
        // Normalizar valores
        array_walk_recursive($normalized, function(&$value) {
            if (is_string($value)) {
                $value = trim($value);
            }
        });

        return $normalized;
    }

    /**
     * Obter tag para modelo
     *
     * @param string $model
     * @return string
     */
    protected function getModelTag(string $model): string
    {
        return 'model_' . strtolower(class_basename($model));
    }

    /**
     * Registrar chave para invalidação manual
     *
     * @param string $key
     * @param string|null $model
     * @param array $tags
     * @return void
     */
    protected function registerKeyForModel(string $key, ?string $model, array $tags): void
    {
        if (!$model) return;

        $registry = $this->getKeyRegistry();
        $registry[$key] = [
            'model' => $model,
            'tags' => $tags,
            'created_at' => now()->toISOString(),
            'expires_at' => now()->addSeconds($this->defaultTtl)->toISOString(),
        ];

        Cache::store($this->cacheStore)->put(
            $this->keyPrefix . 'key_registry',
            $registry,
            $this->defaultTtl * 24 // Registry vive mais tempo
        );
    }

    /**
     * Obter registro de chaves
     *
     * @return array
     */
    protected function getKeyRegistry(): array
    {
        return Cache::store($this->cacheStore)->get($this->keyPrefix . 'key_registry', []);
    }

    /**
     * Invalidar chaves por modelo (fallback)
     *
     * @param string $model
     * @return void
     */
    protected function invalidateKeysForModel(string $model): void
    {
        $registry = $this->getKeyRegistry();
        $keysToRemove = [];

        foreach ($registry as $key => $metadata) {
            if (isset($metadata['model']) && $metadata['model'] === $model) {
                Cache::store($this->cacheStore)->forget($key);
                $keysToRemove[] = $key;
            }
        }

        // Atualizar registry
        foreach ($keysToRemove as $key) {
            unset($registry[$key]);
        }

        Cache::store($this->cacheStore)->put(
            $this->keyPrefix . 'key_registry',
            $registry,
            $this->defaultTtl * 24
        );
    }

    /**
     * Remover chave do registro
     *
     * @param string $key
     * @return void
     */
    protected function unregisterKey(string $key): void
    {
        $registry = $this->getKeyRegistry();
        unset($registry[$key]);
        
        Cache::store($this->cacheStore)->put(
            $this->keyPrefix . 'key_registry',
            $registry,
            $this->defaultTtl * 24
        );
    }

    /**
     * Obter versão do cache para modelo
     *
     * @param string $model
     * @return string
     */
    protected function getCacheVersion(string $model): string
    {
        $versionKey = $this->keyPrefix . 'version_' . md5($model);
        return Cache::store($this->cacheStore)->get($versionKey, '1');
    }

    /**
     * Incrementar versão do cache para modelo
     *
     * @param string $model
     * @return void
     */
    protected function incrementCacheVersion(string $model): void
    {
        $versionKey = $this->keyPrefix . 'version_' . md5($model);
        $currentVersion = (int) $this->getCacheVersion($model);
        
        Cache::store($this->cacheStore)->forever($versionKey, (string) ($currentVersion + 1));
    }

    /**
     * Mapear nome da tabela para classe do modelo
     *
     * @param string $table
     * @return string|null
     */
    protected function tableToModel(string $table): ?string
    {
        // Mapeamento básico - pode ser expandido
        $mapping = [
            'users' => 'App\\Models\\User',
            // Adicionar mais mapeamentos conforme necessário
        ];

        return $mapping[$table] ?? null;
    }

    /**
     * Calcular tamanho dos dados para estatísticas
     *
     * @param mixed $data
     * @return int
     */
    protected function calculateDataSize($data): int
    {
        return strlen(serialize($data));
    }

    /**
     * Registrar cache hit para estatísticas
     *
     * @param string $key
     * @param array $metadata
     * @return void
     */
    protected function recordCacheHit(string $key, array $metadata): void
    {
        if (!config('apiforge.debug.enabled')) {
            return;
        }

        $hits = Cache::store($this->cacheStore)->get($this->keyPrefix . 'hits', []);
        $hits[$key] = ($hits[$key] ?? 0) + 1;
        
        Cache::store($this->cacheStore)->put($this->keyPrefix . 'hits', $hits, $this->defaultTtl * 24);
    }
}