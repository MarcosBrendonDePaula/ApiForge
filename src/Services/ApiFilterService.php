<?php

namespace MarcosBrendon\ApiForge\Services;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use MarcosBrendon\ApiForge\Exceptions\FilterValidationException;

class ApiFilterService
{
    /**
     * Configuração de filtros disponíveis
     *
     * @var array
     */
    protected array $filterConfig = [];

    /**
     * Operadores válidos para filtros
     *
     * @var array
     */
    protected array $validOperators = [
        'eq' => '=',               // igual
        'ne' => '!=',              // diferente
        'gt' => '>',               // maior que
        'gte' => '>=',             // maior ou igual
        'lt' => '<',               // menor que
        'lte' => '<=',             // menor ou igual
        'like' => 'LIKE',          // contém
        'not_like' => 'NOT LIKE',  // não contém
        'in' => 'IN',              // está em
        'not_in' => 'NOT IN',      // não está em
        'null' => 'IS NULL',       // é nulo
        'not_null' => 'IS NOT NULL', // não é nulo
        'between' => 'BETWEEN',    // entre valores
        'not_between' => 'NOT BETWEEN', // não entre valores
        'starts_with' => 'LIKE',   // começa com
        'ends_with' => 'LIKE',     // termina com
    ];

    /**
     * Tipos de campo suportados
     *
     * @var array
     */
    protected array $fieldTypes = [
        'string', 'integer', 'float', 'boolean', 'date', 'datetime', 'json', 'enum'
    ];

    /**
     * Configura os filtros disponíveis
     *
     * @param array $config
     * @return self
     */
    public function configure(array $config): self
    {
        $this->filterConfig = $config;
        return $this;
    }

    /**
     * Aplica filtros avançados à query
     *
     * @param Builder $query
     * @param Request $request
     * @return Builder
     */
    public function applyAdvancedFilters(Builder $query, Request $request): Builder
    {
        // Filtros simples via query parameters
        $this->applySimpleFilters($query, $request);

        // Filtros complexos via JSON
        if ($request->has('filters')) {
            $this->applyComplexFilters($query, $request->get('filters'));
        }

        // Filtros de relacionamento
        if ($request->has('with_filters')) {
            $this->applyRelationshipFilters($query, $request->get('with_filters'));
        }

        return $query;
    }

    /**
     * Aplica filtros simples baseados nos parâmetros da query
     *
     * @param Builder $query
     * @param Request $request
     * @return void
     */
    protected function applySimpleFilters(Builder $query, Request $request): void
    {
        foreach ($this->filterConfig as $field => $config) {
            $value = $request->get($field);
            
            if ($value === null || $value === '') {
                continue;
            }

            $this->applyFieldFilter($query, $field, $value, $config);
        }
    }

    /**
     * Aplica filtros complexos baseados em JSON
     *
     * @param Builder $query
     * @param string|array $filters
     * @return void
     */
    protected function applyComplexFilters(Builder $query, $filters): void
    {
        if (is_string($filters)) {
            $filters = json_decode($filters, true);
        }

        if (!is_array($filters)) {
            return;
        }

        foreach ($filters as $filter) {
            $this->applyComplexFilter($query, $filter);
        }
    }

    /**
     * Aplica um filtro complexo individual
     *
     * @param Builder $query
     * @param array $filter
     * @return void
     */
    protected function applyComplexFilter(Builder $query, array $filter): void
    {
        $field = $filter['field'] ?? null;
        $operator = $filter['operator'] ?? 'eq';
        $value = $filter['value'] ?? null;
        $logic = $filter['logic'] ?? 'and'; // and, or

        if (!$field || !isset($this->validOperators[$operator])) {
            return;
        }

        $method = $logic === 'or' ? 'orWhere' : 'where';
        $this->applyOperatorFilter($query, $field, $operator, $value, $method);
    }

    /**
     * Aplica filtro com operador específico
     *
     * @param Builder $query
     * @param string $field
     * @param string $operator
     * @param mixed $value
     * @param string $method
     * @return void
     */
    protected function applyOperatorFilter(Builder $query, string $field, string $operator, $value, string $method = 'where'): void
    {
        // Sanitizar valor antes de aplicar à query
        $sanitizedValue = $this->sanitizeValue($value);
        
        // Se valor foi bloqueado, não aplicar filtro
        if ($sanitizedValue === null && $value !== null) {
            return;
        }
        
        switch ($operator) {
            case 'in':
            case 'not_in':
                $values = is_array($sanitizedValue) ? $sanitizedValue : explode(',', $sanitizedValue);
                // Sanitizar cada valor individualmente
                $sanitizedValues = array_filter(array_map(function($value) {
                    return $this->sanitizeValueForArray($value);
                }, $values), function($v) {
                    return $v !== null && $v !== '';
                });
                
                if (!empty($sanitizedValues)) {
                    $inMethod = $method . ($operator === 'in' ? 'In' : 'NotIn');
                    $query->{$inMethod}($field, $sanitizedValues);
                }
                break;

            case 'null':
                $query->{$method . 'Null'}($field);
                break;

            case 'not_null':
                $query->{$method . 'NotNull'}($field);
                break;

            case 'between':
            case 'not_between':
                $values = is_array($sanitizedValue) ? $sanitizedValue : explode('|', $sanitizedValue);
                if (count($values) === 2) {
                    // Sanitizar ambos os valores
                    $sanitizedBetweenValues = [
                        $this->sanitizeValue($values[0]),
                        $this->sanitizeValue($values[1])
                    ];
                    
                    // Verificar se ambos os valores são válidos
                    if ($sanitizedBetweenValues[0] !== null && $sanitizedBetweenValues[1] !== null) {
                        $betweenMethod = $method . ($operator === 'between' ? 'Between' : 'NotBetween');
                        $query->{$betweenMethod}($field, $sanitizedBetweenValues);
                    }
                }
                break;

            case 'like':
            case 'not_like':
                // Usar sanitização contextual para preservar wildcards
                $likeValue = $this->sanitizeValueWithContext($value, 'like');
                if ($likeValue === null && $value !== null) {
                    return;
                }
                
                // Suporte a wildcards com *
                if (strpos($likeValue, '*') !== false) {
                    $likeValue = str_replace('*', '%', $likeValue);
                } else {
                    $likeValue = "%{$likeValue}%";
                }
                $query->{$method}($field, $this->validOperators[$operator], $likeValue);
                break;

            case 'starts_with':
                $query->{$method}($field, 'LIKE', "{$sanitizedValue}%");
                break;

            case 'ends_with':
                $query->{$method}($field, 'LIKE', "%{$sanitizedValue}");
                break;

            default:
                // Para operadores padrão (eq, ne, gt, etc.), usar sanitização rigorosa
                $sanitizedValue = $this->sanitizeValue($value);
                if ($sanitizedValue === null && $value !== null) {
                    if (config('apiforge.debug.enabled')) {
                        logger()->warning('Filter value blocked by sanitization', [
                            'field' => $field,
                            'original_value' => $value
                        ]);
                    }
                    return; // Block malicious values completely
                }
                
                $sqlOperator = $this->validOperators[$operator];
                $query->{$method}($field, $sqlOperator, $sanitizedValue);
                break;
        }
    }

    /**
     * Aplica filtros em relacionamentos
     *
     * @param Builder $query
     * @param string|array $withFilters
     * @return void
     */
    protected function applyRelationshipFilters(Builder $query, $withFilters): void
    {
        if (is_string($withFilters)) {
            $withFilters = json_decode($withFilters, true);
        }

        if (!is_array($withFilters)) {
            return;
        }

        foreach ($withFilters as $relation => $filters) {
            $query->whereHas($relation, function ($subQuery) use ($filters) {
                if (is_array($filters)) {
                    foreach ($filters as $filter) {
                        $this->applyComplexFilter($subQuery, $filter);
                    }
                }
            });
        }
    }

    /**
     * Aplica filtro para um campo específico
     *
     * @param Builder $query
     * @param string $field
     * @param mixed $value
     * @param array $config
     * @return void
     */
    protected function applyFieldFilter(Builder $query, string $field, $value, array $config): void
    {
        $type = $config['type'] ?? 'string';
        $operator = $this->detectOperator($value, $config);

        // Para operadores IN, processar valores individuais ANTES de qualquer sanitização geral
        if (in_array($operator, ['in', 'not_in'])) {
            $values = is_array($value) ? $value : explode(',', $value);
            $sanitizedValues = array_filter(array_map(function($v) {
                return $this->sanitizeValueForArray(trim($v));
            }, $values), function($v) {
                return $v !== null && $v !== '';
            });
            
            if (!empty($sanitizedValues)) {
                $inMethod = 'where' . ($operator === 'in' ? 'In' : 'NotIn');
                $query->{$inMethod}($field, $sanitizedValues);
            }
            return;
        }

        // Processar valor baseado no operador detectado
        $processedValue = $this->processValueForOperator($value, $operator, $type);

        // Validar e converter valor baseado no tipo
        $processedValue = $this->castValue($processedValue, $type);
        
        // Validação adicional por tipo (pular para operadores que têm formatos especiais)
        if (!in_array($operator, ['between', 'not_between', 'in', 'not_in']) && !$this->isValidValueForType($processedValue, $type, $config)) {
            if (config('apiforge.validation.strict_mode', false)) {
                throw FilterValidationException::invalidFieldType($field, $type, $processedValue);
            }
            
            if (config('apiforge.debug.enabled')) {
                logger()->warning('Invalid value for field type', [
                    'field' => $field,
                    'value' => $processedValue,
                    'type' => $type
                ]);
            }
            return;
        }

        switch ($type) {
            case 'date':
            case 'datetime':
                $this->applyDateFilter($query, $field, $processedValue, $operator);
                break;
                
            case 'enum':
                $allowedValues = $config['values'] ?? [];
                if (in_array($processedValue, $allowedValues)) {
                    $this->applyOperatorFilter($query, $field, $operator, $processedValue);
                } elseif (config('apiforge.validation.strict_mode', false)) {
                    throw FilterValidationException::enumValueNotAllowed($field, $processedValue, $allowedValues);
                }
                break;
                
            case 'json':
                $this->applyJsonFilter($query, $field, $value, $config); // Use original value, not processed
                break;
                
            default:
                $this->applyOperatorFilter($query, $field, $operator, $processedValue);
                break;
        }
    }

    /**
     * Detecta operador baseado no valor
     *
     * @param mixed $value
     * @param array $config
     * @return string
     */
    protected function detectOperator($value, array $config): string
    {
        if (!is_string($value)) {
            return $config['operator'] ?? 'eq';
        }

        // Detectar operadores por prefixo
        $operators = [
            '>=' => 'gte',
            '<=' => 'lte',
            '!=' => 'ne',
            '>' => 'gt',
            '<' => 'lt',
            '=' => 'eq',
        ];

        foreach ($operators as $prefix => $operator) {
            if (str_starts_with($value, $prefix)) {
                return $operator;
            }
        }

        // Detectar wildcards
        if (strpos($value, '*') !== false) {
            return 'like';
        }

        // Detectar múltiplos valores (IN)
        if (strpos($value, ',') !== false) {
            return 'in';
        }

        // Detectar range (BETWEEN)
        if (strpos($value, '|') !== false) {
            return 'between';
        }

        return $config['operator'] ?? 'eq';
    }

    /**
     * Processa valor para operador específico
     *
     * @param mixed $value
     * @param string $operator
     * @param string $type
     * @return mixed
     */
    protected function processValueForOperator($value, string $operator, string $type)
    {
        if (!is_string($value)) {
            return $value;
        }

        // Remover prefixos de operador
        $prefixes = ['>=', '<=', '!=', '>', '<', '='];
        foreach ($prefixes as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return substr($value, strlen($prefix));
            }
        }

        return $value;
    }

    /**
     * Aplica filtro de data
     *
     * @param Builder $query
     * @param string $field
     * @param mixed $value
     * @param string $operator
     * @return void
     */
    protected function applyDateFilter(Builder $query, string $field, $value, string $operator): void
    {
        try {
            if ($operator === 'between' && (is_array($value) || strpos($value, '|') !== false)) {
                // Range de datas
                $dates = is_array($value) ? $value : explode('|', $value);
                if (count($dates) === 2) {
                    $from = Carbon::parse($dates[0])->startOfDay();
                    $to = Carbon::parse($dates[1])->endOfDay();
                    $query->whereBetween($field, [$from, $to]);
                }
            } else {
                // Data única
                $date = Carbon::parse($value);
                
                switch ($operator) {
                    case 'eq':
                        $query->whereDate($field, $date->toDateString());
                        break;
                    case 'gte':
                        $query->where($field, '>=', $date->startOfDay());
                        break;
                    case 'lte':
                        $query->where($field, '<=', $date->endOfDay());
                        break;
                    case 'gt':
                        $query->where($field, '>', $date->endOfDay());
                        break;
                    case 'lt':
                        $query->where($field, '<', $date->startOfDay());
                        break;
                    default:
                        $sqlOperator = $this->validOperators[$operator] ?? '=';
                        $query->where($field, $sqlOperator, $date);
                        break;
                }
            }
        } catch (\Exception $e) {
            // Ignorar datas inválidas
            if (config('apiforge.debug.enabled')) {
                logger()->warning('Invalid date filter', [
                    'field' => $field,
                    'value' => $value,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Aplica filtro JSON
     *
     * @param Builder $query
     * @param string $field
     * @param mixed $value
     * @param array $config
     * @return void
     */
    protected function applyJsonFilter(Builder $query, string $field, $value, array $config): void
    {
        // Sanitizar valor JSON específicamente
        $sanitizedValue = $this->sanitizeValueWithContext($value, 'json');
        
        if ($sanitizedValue === null && $value !== null) {
            if (config('apiforge.debug.enabled')) {
                logger()->warning('JSON filter value blocked by sanitization', [
                    'field' => $field,
                    'original_value' => $value
                ]);
            }
            return;
        }
        
        $path = $config['path'] ?? null;
        
        if ($path) {
            // Sanitizar também o path para evitar injection
            $sanitizedPath = $this->sanitizeValue($path);
            if ($sanitizedPath) {
                $query->where("{$field}->{$sanitizedPath}", $sanitizedValue);
            }
        } else {
            $query->whereJsonContains($field, $sanitizedValue);
        }
    }

    /**
     * Converte valor para o tipo apropriado
     *
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    protected function castValue($value, string $type)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        switch ($type) {
            case 'integer':
                return is_numeric($value) ? (int) $value : $value;
                
            case 'float':
                return is_numeric($value) ? (float) $value : $value;
                
            case 'boolean':
                if (is_bool($value)) return $value;
                if (is_string($value)) {
                    $lower = strtolower($value);
                    if (in_array($lower, ['true', '1', 'yes', 'on'])) return true;
                    if (in_array($lower, ['false', '0', 'no', 'off'])) return false;
                }
                return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                
            case 'json':
                return is_string($value) ? json_decode($value, true) : $value;
                
            default:
                return config('apiforge.security.strip_tags') ? strip_tags($value) : $value;
        }
    }

    /**
     * Obtém configuração de filtros padrão para um modelo
     *
     * @param string $modelClass
     * @return array
     */
    public static function getDefaultConfig(string $modelClass): array
    {
        // Pode ser expandido para detectar automaticamente campos do modelo
        return [
            'id' => ['type' => 'integer', 'operator' => 'eq'],
            'created_at' => ['type' => 'datetime', 'operator' => 'gte'],
            'updated_at' => ['type' => 'datetime', 'operator' => 'gte'],
        ];
    }

    /**
     * Valida se um filtro é permitido
     *
     * @param string $field
     * @param string $operator
     * @return bool
     */
    public function isValidFilter(string $field, string $operator = 'eq'): bool
    {
        return isset($this->filterConfig[$field]) && 
               isset($this->validOperators[$operator]);
    }

    /**
     * Obtém metadados dos filtros disponíveis
     *
     * @return array
     */
    public function getFilterMetadata(): array
    {
        return [
            'available_fields' => array_keys($this->filterConfig),
            'available_operators' => array_keys($this->validOperators),
            'field_types' => $this->fieldTypes,
            'filter_config' => $this->filterConfig,
            'operator_descriptions' => config('apiforge.filters.available_operators', [])
        ];
    }

    /**
     * Sanitiza valor de entrada
     *
     * @param mixed $value
     * @return mixed
     */
    protected function sanitizeValue($value)
    {
        if (!config('apiforge.security.sanitize_input')) {
            return $value;
        }

        if (is_string($value)) {
            // Verificar palavras bloqueadas ANTES de strip_tags
            $blockedKeywords = config('apiforge.security.blocked_keywords', []);
            foreach ($blockedKeywords as $keyword) {
                if (stripos($value, $keyword) !== false) {
                    return null;
                }
            }

            // Aplicar strip_tags se configurado
            if (config('apiforge.security.strip_tags')) {
                $value = strip_tags($value);
            }

            // Verificar palavras bloqueadas DEPOIS de strip_tags também (caso outras tags tenham exposto conteúdo malicioso)
            foreach ($blockedKeywords as $keyword) {
                if (stripos($value, $keyword) !== false) {
                    return null;
                }
            }

            // Limitar tamanho da query
            $maxLength = config('apiforge.security.max_query_length', 2000);
            if (strlen($value) > $maxLength) {
                $value = substr($value, 0, $maxLength);
            }
        }

        return $value;
    }

    /**
     * Valida se um valor é apropriado para um tipo específico
     *
     * @param mixed $value
     * @param string $type
     * @param array $config
     * @return bool
     */
    protected function isValidValueForType($value, string $type, array $config): bool
    {
        if ($value === null) {
            return true; // Null é sempre válido
        }

        switch ($type) {
            case 'integer':
                return is_numeric($value) && is_int($value + 0);
                
            case 'float':
                return is_numeric($value);
                
            case 'boolean':
                return is_bool($value) || in_array(strtolower((string)$value), ['true', 'false', '1', '0', 'yes', 'no', 'on', 'off']);
                
            case 'date':
            case 'datetime':
                try {
                    if (is_string($value)) {
                        \Carbon\Carbon::parse($value);
                        return true;
                    }
                    return false;
                } catch (\Exception $e) {
                    return false;
                }
                
            case 'enum':
                $allowedValues = $config['values'] ?? [];
                return in_array($value, $allowedValues);
                
            case 'json':
                if (is_string($value)) {
                    json_decode($value);
                    return json_last_error() === JSON_ERROR_NONE;
                }
                return is_array($value) || is_object($value);
                
            case 'string':
            default:
                return is_string($value) || is_numeric($value);
        }
    }

    /**
     * Sanitiza valor melhorado com validação de contexto
     *
     * @param mixed $value
     * @param string $context
     * @return mixed
     */
    protected function sanitizeValueWithContext($value, string $context = 'filter')
    {
        if (!config('apiforge.security.sanitize_input')) {
            return $value;
        }

        if (is_array($value)) {
            return array_map(function($item) use ($context) {
                return $this->sanitizeValueWithContext($item, $context);
            }, $value);
        }

        if (!is_string($value)) {
            return $value;
        }

        // Contextos específicos de sanitização
        switch ($context) {
            case 'like':
                // Para LIKE, preservar wildcards mas sanitizar o resto
                $wildcardPreserved = str_replace(['*', '%'], ['__WILDCARD__', '__PERCENT__'], $value);
                $sanitized = $this->sanitizeValueForLike($wildcardPreserved);
                if ($sanitized === null) {
                    return null;
                }
                return str_replace(['__WILDCARD__', '__PERCENT__'], ['*', '%'], $sanitized);
                
            case 'json':
                // Para JSON, permitir caracteres especiais necessários
                return $this->sanitizeJsonValue($value);
                
            default:
                return $this->sanitizeValue($value);
        }
    }

    /**
     * Sanitização específica para valores em arrays (IN filter)
     *
     * @param mixed $value
     * @return mixed|null
     */
    protected function sanitizeValueForArray($value)
    {
        if (!config('apiforge.security.sanitize_input')) {
            return $value;
        }

        if (!is_string($value)) {
            return $value;
        }

        // Verificar e remover palavras bloqueadas ANTES de strip_tags
        $blockedKeywords = config('apiforge.security.blocked_keywords', []);
        foreach ($blockedKeywords as $keyword) {
            if (stripos($value, $keyword) !== false) {
                return null; // Block the entire value if it contains blocked keywords
            }
        }

        // Aplicar strip_tags se configurado
        if (config('apiforge.security.strip_tags')) {
            $value = strip_tags($value);
        }

        // Limitar tamanho da query
        $maxLength = config('apiforge.security.max_query_length', 2000);
        if (strlen($value) > $maxLength) {
            $value = substr($value, 0, $maxLength);
        }

        return $value;
    }

    /**
     * Sanitização específica para valores LIKE
     *
     * @param string $value
     * @return string|null
     */
    protected function sanitizeValueForLike(string $value)
    {
        if (!config('apiforge.security.sanitize_input')) {
            return $value;
        }

        // Aplicar strip_tags se configurado
        if (config('apiforge.security.strip_tags')) {
            $value = strip_tags($value);
        }

        // Remover palavras bloqueadas
        $blockedKeywords = config('apiforge.security.blocked_keywords', []);
        foreach ($blockedKeywords as $keyword) {
            $value = str_ireplace($keyword, '', $value);
        }

        // Limitar tamanho da query
        $maxLength = config('apiforge.security.max_query_length', 2000);
        if (strlen($value) > $maxLength) {
            $value = substr($value, 0, $maxLength);
        }

        return $value;
    }

    /**
     * Sanitização específica para valores JSON
     *
     * @param string $value
     * @return string|null
     */
    protected function sanitizeJsonValue(string $value)
    {
        if (!config('apiforge.security.sanitize_input')) {
            return $value;
        }

        // Verificar palavras bloqueadas
        $blockedKeywords = config('apiforge.security.blocked_keywords', []);
        foreach ($blockedKeywords as $keyword) {
            if (stripos($value, $keyword) !== false) {
                return null;
            }
        }

        // Limitar tamanho
        $maxLength = config('apiforge.security.max_query_length', 2000);
        if (strlen($value) > $maxLength) {
            $value = substr($value, 0, $maxLength);
        }

        // Validar que é JSON válido
        json_decode($value);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $value;
    }
}