<?php

namespace MarcosBrendon\ApiForge\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class QueryOptimizationService
{
    /**
     * Cache de relacionamentos já carregados
     */
    protected array $loadedRelationships = [];

    /**
     * Cache de estrutura de relacionamentos
     */
    protected array $relationshipStructure = [];

    /**
     * Contador de queries para debugging
     */
    protected int $queryCount = 0;

    /**
     * Otimizar query com eager loading inteligente
     */
    public function optimizeQuery(Builder $query, array $requestedFields = []): Builder
    {
        $this->resetCounters();
        
        // Analisar campos solicitados para determinar relacionamentos
        $relationships = $this->extractRelationships($requestedFields);
        
        // Otimizar relacionamentos
        if (!empty($relationships)) {
            $query = $this->optimizeRelationships($query, $relationships);
        }
        
        // Aplicar otimizações de query
        $query = $this->applyQueryOptimizations($query);
        
        return $query;
    }

    /**
     * Extrair relacionamentos dos campos solicitados
     */
    protected function extractRelationships(array $fields): array
    {
        $relationships = [];
        
        foreach ($fields as $field) {
            if (strpos($field, '.') !== false) {
                $parts = explode('.', $field);
                $currentPath = '';
                
                for ($i = 0; $i < count($parts) - 1; $i++) {
                    $currentPath = $currentPath ? $currentPath . '.' . $parts[$i] : $parts[$i];
                    
                    if (!isset($relationships[$currentPath])) {
                        $relationships[$currentPath] = [
                            'fields' => [],
                            'depth' => $i + 1,
                            'parent' => $i > 0 ? implode('.', array_slice($parts, 0, $i)) : null
                        ];
                    }
                }
                
                // Adicionar campo final ao relacionamento
                $relationPath = implode('.', array_slice($parts, 0, -1));
                $fieldName = end($parts);
                
                if (isset($relationships[$relationPath])) {
                    $relationships[$relationPath]['fields'][] = $fieldName;
                }
            }
        }
        
        return $relationships;
    }

    /**
     * Otimizar carregamento de relacionamentos
     */
    protected function optimizeRelationships(Builder $query, array $relationships): Builder
    {
        // Agrupar relacionamentos por profundidade para carregamento eficiente
        $relationshipsByDepth = $this->groupRelationshipsByDepth($relationships);
        
        // Construir array de relacionamentos para eager loading
        $eagerLoad = $this->buildEagerLoadArray($relationships);
        
        if (!empty($eagerLoad)) {
            $query->with($eagerLoad);
        }
        
        return $query;
    }

    /**
     * Agrupar relacionamentos por profundidade
     */
    protected function groupRelationshipsByDepth(array $relationships): array
    {
        $grouped = [];
        
        foreach ($relationships as $path => $config) {
            $depth = $config['depth'];
            
            if (!isset($grouped[$depth])) {
                $grouped[$depth] = [];
            }
            
            $grouped[$depth][$path] = $config;
        }
        
        ksort($grouped);
        return $grouped;
    }

    /**
     * Construir array para eager loading
     */
    protected function buildEagerLoadArray(array $relationships): array
    {
        $eagerLoad = [];
        
        foreach ($relationships as $path => $config) {
            $fields = $config['fields'];
            
            if (!empty($fields)) {
                // Sempre incluir ID para relacionamentos
                if (!in_array('id', $fields)) {
                    array_unshift($fields, 'id');
                }
                
                // Adicionar chaves estrangeiras necessárias
                $fields = $this->addRequiredKeys($path, $fields);
                
                $eagerLoad[$path] = function ($query) use ($fields) {
                    $query->select($fields);
                };
            } else {
                $eagerLoad[] = $path;
            }
        }
        
        return $eagerLoad;
    }

    /**
     * Adicionar chaves necessárias para relacionamentos
     */
    protected function addRequiredKeys(string $relationshipPath, array $fields): array
    {
        // Lógica para determinar chaves necessárias baseada no tipo de relacionamento
        // Por exemplo, para belongsTo, precisamos da foreign key
        // Para hasMany, precisamos da primary key
        
        $additionalKeys = $this->inferRequiredKeys($relationshipPath);
        
        return array_unique(array_merge($fields, $additionalKeys));
    }

    /**
     * Inferir chaves necessárias baseadas no relacionamento
     */
    protected function inferRequiredKeys(string $relationshipPath): array
    {
        // Implementação básica - pode ser expandida
        $commonKeys = ['id', 'created_at', 'updated_at'];
        
        // Adicionar chaves específicas baseadas em convenções
        $parts = explode('.', $relationshipPath);
        $lastRelation = end($parts);
        
        // Se for um relacionamento que termina com _id, incluir
        if (Str::endsWith($lastRelation, '_id')) {
            $commonKeys[] = $lastRelation;
        }
        
        // Adicionar foreign keys comuns
        $commonKeys[] = Str::singular($lastRelation) . '_id';
        
        return $commonKeys;
    }

    /**
     * Aplicar otimizações gerais de query
     */
    protected function applyQueryOptimizations(Builder $query): Builder
    {
        // Otimização 1: Usar índices quando possível
        $query = $this->optimizeIndexUsage($query);
        
        // Otimização 2: Evitar SELECT *
        $query = $this->optimizeSelectClauses($query);
        
        // Otimização 3: Otimizar ORDER BY
        $query = $this->optimizeOrderBy($query);
        
        return $query;
    }

    /**
     * Otimizar uso de índices
     */
    protected function optimizeIndexUsage(Builder $query): Builder
    {
        // Analisar condições WHERE e sugerir uso de índices
        $wheres = $query->getQuery()->wheres;
        
        foreach ($wheres as $where) {
            if (isset($where['column']) && $this->shouldHaveIndex($where['column'])) {
                // Log sugestão de índice se debug estiver ativo
                if (config('apiforge.debug.enabled')) {
                    logger()->info('Consider adding index for column: ' . $where['column']);
                }
            }
        }
        
        return $query;
    }

    /**
     * Verificar se coluna deveria ter índice
     */
    protected function shouldHaveIndex(string $column): bool
    {
        // Colunas que geralmente se beneficiam de índices
        $indexCandidates = [
            'id', 'email', 'status', 'type', 'category_id', 'user_id',
            'created_at', 'updated_at', 'deleted_at'
        ];
        
        return in_array($column, $indexCandidates) || 
               Str::endsWith($column, '_id') ||
               Str::endsWith($column, '_at');
    }

    /**
     * Otimizar cláusulas SELECT
     */
    protected function optimizeSelectClauses(Builder $query): Builder
    {
        $columns = $query->getQuery()->columns;
        
        // Se não há colunas específicas selecionadas, pode ser otimizado
        if (empty($columns) || in_array('*', $columns)) {
            if (config('apiforge.debug.enabled')) {
                logger()->info('Query using SELECT * - consider specifying columns');
            }
        }
        
        return $query;
    }

    /**
     * Otimizar ORDER BY
     */
    protected function optimizeOrderBy(Builder $query): Builder
    {
        $orders = $query->getQuery()->orders;
        
        if (!empty($orders)) {
            foreach ($orders as $order) {
                if (isset($order['column']) && $this->shouldHaveIndex($order['column'])) {
                    // Sugerir índice para colunas de ordenação
                    if (config('apiforge.debug.enabled')) {
                        logger()->info('Consider adding index for ORDER BY column: ' . $order['column']);
                    }
                }
            }
        }
        
        return $query;
    }

    /**
     * Analisar performance da query
     */
    public function analyzeQueryPerformance(Builder $query): array
    {
        $startTime = microtime(true);
        $startQueries = $this->getQueryCount();
        
        // Executar uma pequena amostra para análise
        $sample = $query->limit(1)->get();
        
        $endTime = microtime(true);
        $endQueries = $this->getQueryCount();
        
        return [
            'execution_time' => ($endTime - $startTime) * 1000, // ms
            'query_count' => $endQueries - $startQueries,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'recommendations' => $this->generateRecommendations($query),
        ];
    }

    /**
     * Gerar recomendações de otimização
     */
    protected function generateRecommendations(Builder $query): array
    {
        $recommendations = [];
        
        // Verificar se há muitos JOINs
        $joins = $query->getQuery()->joins ?? [];
        if (count($joins) > 3) {
            $recommendations[] = 'Consider breaking down complex joins into separate queries';
        }
        
        // Verificar uso de LIKE
        $wheres = $query->getQuery()->wheres;
        foreach ($wheres as $where) {
            if (isset($where['operator']) && $where['operator'] === 'like') {
                if (isset($where['value']) && Str::startsWith($where['value'], '%')) {
                    $recommendations[] = 'LIKE queries starting with % cannot use indexes efficiently';
                }
            }
        }
        
        // Verificar paginação
        $limit = $query->getQuery()->limit;
        $offset = $query->getQuery()->offset;
        
        if ($offset > 1000) {
            $recommendations[] = 'Large offset values can be slow - consider cursor-based pagination';
        }
        
        if ($limit > 100) {
            $recommendations[] = 'Large page sizes can impact performance - consider smaller pages';
        }
        
        return $recommendations;
    }

    /**
     * Otimizar paginação para grandes datasets
     */
    public function optimizePagination(Builder $query, int $page, int $perPage): Builder
    {
        // Para páginas muito profundas, usar cursor-based pagination
        if ($page > 100) {
            return $this->applyCursorPagination($query, $page, $perPage);
        }
        
        // Para paginação normal, otimizar OFFSET
        return $this->optimizeOffset($query, $page, $perPage);
    }

    /**
     * Aplicar cursor-based pagination
     */
    protected function applyCursorPagination(Builder $query, int $page, int $perPage): Builder
    {
        // Implementação básica de cursor pagination
        // Assume que há uma coluna 'id' ou 'created_at' para cursor
        
        $orderBy = $query->getQuery()->orders[0] ?? ['column' => 'id', 'direction' => 'asc'];
        $cursorColumn = $orderBy['column'];
        $direction = $orderBy['direction'];
        
        if ($page > 1) {
            // Calcular valor do cursor baseado na página
            $offset = ($page - 1) * $perPage;
            $cursorValue = $query->clone()->offset($offset - 1)->limit(1)->value($cursorColumn);
            
            if ($cursorValue) {
                $operator = $direction === 'asc' ? '>' : '<';
                $query->where($cursorColumn, $operator, $cursorValue);
            }
        }
        
        return $query->limit($perPage);
    }

    /**
     * Otimizar OFFSET para paginação tradicional
     */
    protected function optimizeOffset(Builder $query, int $page, int $perPage): Builder
    {
        $offset = ($page - 1) * $perPage;
        
        // Para offsets grandes, usar subquery para melhor performance
        if ($offset > 500) {
            $primaryKey = $query->getModel()->getKeyName();
            $table = $query->getModel()->getTable();
            
            $subQuery = $query->clone()
                ->select($primaryKey)
                ->offset($offset)
                ->limit($perPage);
            
            return $query->getModel()
                ->newQuery()
                ->whereIn($primaryKey, $subQuery)
                ->orderBy($primaryKey);
        }
        
        return $query->offset($offset)->limit($perPage);
    }

    /**
     * Resetar contadores
     */
    protected function resetCounters(): void
    {
        $this->queryCount = 0;
        $this->loadedRelationships = [];
    }

    /**
     * Obter contagem de queries
     */
    protected function getQueryCount(): int
    {
        return \DB::getQueryLog() ? count(\DB::getQueryLog()) : 0;
    }

    /**
     * Detectar N+1 queries
     */
    public function detectNPlusOne(callable $callback): array
    {
        \DB::enableQueryLog();
        $startQueries = count(\DB::getQueryLog());
        
        $result = $callback();
        
        $endQueries = count(\DB::getQueryLog());
        $queryCount = $endQueries - $startQueries;
        
        $queries = array_slice(\DB::getQueryLog(), $startQueries);
        
        // Analisar padrões de N+1
        $nPlusOneDetected = $this->analyzeForNPlusOne($queries);
        
        return [
            'query_count' => $queryCount,
            'n_plus_one_detected' => $nPlusOneDetected,
            'queries' => $queries,
            'result' => $result
        ];
    }

    /**
     * Analisar queries para detectar padrões N+1
     */
    protected function analyzeForNPlusOne(array $queries): bool
    {
        // Procurar por padrões repetitivos que indicam N+1
        $queryPatterns = [];
        
        foreach ($queries as $query) {
            $sql = $query['query'];
            
            // Normalizar query removendo valores específicos
            $pattern = preg_replace('/\d+/', '?', $sql);
            $pattern = preg_replace('/\'[^\']*\'/', '?', $pattern);
            
            if (!isset($queryPatterns[$pattern])) {
                $queryPatterns[$pattern] = 0;
            }
            
            $queryPatterns[$pattern]++;
        }
        
        // Se algum padrão se repete muitas vezes, provavelmente é N+1
        foreach ($queryPatterns as $pattern => $count) {
            if ($count > 5 && strpos($pattern, 'where') !== false) {
                return true;
            }
        }
        
        return false;
    }
}