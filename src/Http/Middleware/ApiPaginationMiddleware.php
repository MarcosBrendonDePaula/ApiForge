<?php

namespace MarcosBrendon\ApiForge\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ApiPaginationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Sanitizar e validar parâmetros de paginação
        $this->sanitizePaginationParams($request);
        
        // Validar filtros
        $this->validateFilters($request);
        
        // Validar field selection
        $this->validateFieldSelection($request);
        
        // Definir configurações padrão
        $this->setDefaultConfig($request);

        return $next($request);
    }

    /**
     * Sanitiza e valida parâmetros de paginação
     *
     * @param Request $request
     * @return void
     */
    protected function sanitizePaginationParams(Request $request): void
    {
        // Validar página
        $page = $request->get('page', 1);
        if (!is_numeric($page) || $page < 1) {
            $request->merge(['page' => 1]);
        } else {
            $request->merge(['page' => (int) $page]);
        }

        // Validar itens por página
        $perPage = $request->get('per_page', config('apiforge.pagination.default_per_page', 15));
        $minPerPage = config('apiforge.pagination.min_per_page', 1);
        $maxPerPage = config('apiforge.pagination.max_per_page', 100);
        
        if (!is_numeric($perPage) || $perPage < $minPerPage) {
            $perPage = config('apiforge.pagination.default_per_page', 15);
        } elseif ($perPage > $maxPerPage) {
            $perPage = $maxPerPage;
        }
        $request->merge(['per_page' => (int) $perPage]);

        // Validar direção de ordenação
        $sortDirection = strtolower($request->get('sort_direction', 'asc'));
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }
        $request->merge(['sort_direction' => $sortDirection]);

        // Sanitizar campo de busca
        $search = $request->get('search');
        if ($search) {
            $search = $this->sanitizeString($search);
            $request->merge(['search' => $search]);
        }
    }

    /**
     * Valida filtros da requisição
     *
     * @param Request $request
     * @return void
     */
    protected function validateFilters(Request $request): void
    {
        // Validar filtros de data
        $this->validateDateFilters($request);
        
        // Validar filtros JSON se presentes
        if ($request->has('filters')) {
            $this->validateJsonFilters($request);
        }

        // Validar filtros permitidos usando configuração do controller
        $this->validateAllowedFilters($request);

        // Sanitizar valores de filtro
        $this->sanitizeFilterValues($request);
    }

    /**
     * Valida se apenas filtros permitidos estão sendo usados
     *
     * @param Request $request
     * @return void
     */
    protected function validateAllowedFilters(Request $request): void
    {
        // Obter configuração de filtros do controller se disponível
        $filterConfig = $request->attributes->get('filter_config', []);
        
        if (empty($filterConfig)) {
            return; // Se não há configuração, permite todos os filtros
        }

        $allowedFilters = array_keys($filterConfig);
        $excludeKeys = [
            'page', 'per_page', 'sort_by', 'sort_direction', 'search', 
            'filters', 'with_filters', 'date_from', 'date_to', 'date_field', 
            'period', 'fields', 'empresa_id', 'cache'
        ];
        
        foreach ($request->all() as $key => $value) {
            if (in_array($key, $excludeKeys)) {
                continue;
            }

            // Se o filtro não está permitido, remover da requisição
            if (!in_array($key, $allowedFilters)) {
                $request->request->remove($key);
                
                // Log ou armazenar filtros inválidos para debugging
                $invalidFilters = $request->attributes->get('invalid_filters', []);
                $invalidFilters[] = $key;
                $request->attributes->set('invalid_filters', $invalidFilters);
            }
        }
    }

    /**
     * Valida filtros de data
     *
     * @param Request $request
     * @return void
     */
    protected function validateDateFilters(Request $request): void
    {
        $dateFields = ['date_from', 'date_to'];
        
        foreach ($dateFields as $field) {
            $value = $request->get($field);
            if ($value && !$this->isValidDate($value)) {
                $request->request->remove($field);
            }
        }

        // Validar período predefinido
        $period = $request->get('period');
        $validPeriods = [
            'today', 'yesterday', 'this_week', 'last_week',
            'this_month', 'last_month', 'this_year'
        ];
        
        if ($period && !in_array($period, $validPeriods)) {
            $request->request->remove('period');
        }
    }

    /**
     * Valida filtros JSON
     *
     * @param Request $request
     * @return void
     */
    protected function validateJsonFilters(Request $request): void
    {
        $filters = $request->get('filters');
        
        if (is_string($filters)) {
            $decodedFilters = json_decode($filters, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $request->request->remove('filters');
                return;
            }
            $filters = $decodedFilters;
        }

        if (!is_array($filters)) {
            $request->request->remove('filters');
            return;
        }

        // Validar estrutura de cada filtro
        $validFilters = [];
        foreach ($filters as $filter) {
            if ($this->isValidFilterStructure($filter)) {
                $validFilters[] = $filter;
            }
        }

        $request->merge(['filters' => $validFilters]);
    }

    /**
     * Verifica se a estrutura do filtro é válida
     *
     * @param mixed $filter
     * @return bool
     */
    protected function isValidFilterStructure($filter): bool
    {
        if (!is_array($filter)) {
            return false;
        }

        // Campos obrigatórios
        if (!isset($filter['field']) || !isset($filter['value'])) {
            return false;
        }

        // Validar operador se presente
        if (isset($filter['operator'])) {
            $validOperators = array_keys(config('apiforge.filters.available_operators', []));
            
            if (!in_array($filter['operator'], $validOperators)) {
                return false;
            }
        }

        // Validar lógica se presente
        if (isset($filter['logic']) && !in_array($filter['logic'], ['and', 'or'])) {
            return false;
        }

        return true;
    }

    /**
     * Sanitiza valores de filtro
     *
     * @param Request $request
     * @return void
     */
    protected function sanitizeFilterValues(Request $request): void
    {
        $excludeKeys = [
            'page', 'per_page', 'sort_by', 'sort_direction', 'search', 
            'filters', 'with_filters', 'fields', 'empresa_id', 'cache'
        ];
        
        foreach ($request->all() as $key => $value) {
            if (in_array($key, $excludeKeys)) {
                continue;
            }

            // Sanitizar strings
            if (is_string($value)) {
                $sanitized = $this->sanitizeString($value);
                $request->merge([$key => $sanitized]);
            }
        }
    }

    /**
     * Define configurações padrão
     *
     * @param Request $request
     * @return void
     */
    protected function setDefaultConfig(Request $request): void
    {
        // Adicionar cabeçalhos para indicar capacidades de paginação
        $request->headers->set('X-Pagination-Enabled', 'true');
        $request->headers->set('X-Max-Per-Page', config('apiforge.pagination.max_per_page', 100));
        
        // Definir configurações de cache se necessário
        if ($request->get('cache', false)) {
            $cacheKey = $this->generateCacheKey($request);
            $request->attributes->set('cache_key', $cacheKey);
        }
    }

    /**
     * Verifica se uma data é válida
     *
     * @param string $date
     * @return bool
     */
    protected function isValidDate(string $date): bool
    {
        try {
            $parsed = Carbon::parse($date);
            return $parsed->isValid();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Gera chave de cache baseada nos parâmetros da requisição
     *
     * @param Request $request
     * @return string
     */
    protected function generateCacheKey(Request $request): string
    {
        $params = $request->except(['_token', 'cache']);
        ksort($params);
        
        $prefix = config('apiforge.cache.key_prefix', 'api_filters_');
        return $prefix . md5(json_encode($params) . $request->url());
    }

    /**
     * Validar parâmetros de field selection
     *
     * @param Request $request
     * @return void
     */
    protected function validateFieldSelection(Request $request): void
    {
        $fieldsParam = $request->get('fields');
        
        if (empty($fieldsParam)) {
            return; // Não há field selection para validar
        }

        // Sanitizar o parâmetro fields
        $this->sanitizeFieldsParam($request, $fieldsParam);
        
        // Validar estrutura básica
        $this->validateFieldsStructure($request);
        
        // Validar limite de campos
        $this->validateFieldsLimit($request);
        
        // Validar caracteres permitidos
        $this->validateFieldsCharacters($request);
    }

    /**
     * Sanitizar parâmetro fields
     *
     * @param Request $request
     * @param string $fieldsParam
     * @return void
     */
    protected function sanitizeFieldsParam(Request $request, string $fieldsParam): void
    {
        // Remover espaços em branco extras
        $fieldsParam = trim($fieldsParam);
        
        // Remover vírgulas duplicadas
        $fieldsParam = preg_replace('/,+/', ',', $fieldsParam);
        
        // Remover vírgula inicial ou final
        $fieldsParam = trim($fieldsParam, ',');
        
        // Aplicar sanitização básica
        $fieldsParam = $this->sanitizeString($fieldsParam, false);
        
        $request->merge(['fields' => $fieldsParam]);
    }

    /**
     * Validar estrutura do parâmetro fields
     *
     * @param Request $request
     * @return void
     */
    protected function validateFieldsStructure(Request $request): void
    {
        $fieldsParam = $request->get('fields');
        
        if (empty($fieldsParam)) {
            return;
        }

        // Verificar se não contém apenas vírgulas ou espaços
        if (preg_match('/^[,\s]*$/', $fieldsParam)) {
            $request->request->remove('fields');
            $this->addValidationError($request, 'fields', 'Parâmetro fields não pode estar vazio');
            return;
        }

        // Verificar caracteres inválidos básicos
        if (preg_match('/[<>\'\"\\\\]/', $fieldsParam)) {
            $request->request->remove('fields');
            $this->addValidationError($request, 'fields', 'Parâmetro fields contém caracteres inválidos');
            return;
        }
    }

    /**
     * Validar limite de campos solicitados
     *
     * @param Request $request
     * @return void
     */
    protected function validateFieldsLimit(Request $request): void
    {
        $fieldsParam = $request->get('fields');
        
        if (empty($fieldsParam)) {
            return;
        }

        $fields = explode(',', $fieldsParam);
        $fieldCount = count($fields);
        $maxFields = config('apiforge.field_selection.max_fields', 50);

        if ($fieldCount > $maxFields) {
            $request->request->remove('fields');
            $this->addValidationError($request, 'fields', "Máximo de {$maxFields} campos permitidos. Solicitados: {$fieldCount}");
        }
    }

    /**
     * Validar caracteres permitidos nos campos
     *
     * @param Request $request
     * @return void
     */
    protected function validateFieldsCharacters(Request $request): void
    {
        $fieldsParam = $request->get('fields');
        
        if (empty($fieldsParam)) {
            return;
        }

        $fields = array_map('trim', explode(',', $fieldsParam));
        $invalidFields = [];
        $maxDepth = config('apiforge.field_selection.max_relationship_depth', 3);

        foreach ($fields as $field) {
            // Permitir apenas letras, números, underscore e ponto (para relacionamentos)
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $field)) {
                $invalidFields[] = $field;
                continue;
            }
            
            // Validar estrutura de relacionamento (não pode começar ou terminar com ponto)
            if (str_starts_with($field, '.') || str_ends_with($field, '.')) {
                $invalidFields[] = $field;
                continue;
            }
            
            // Validar pontos consecutivos
            if (str_contains($field, '..')) {
                $invalidFields[] = $field;
                continue;
            }

            // Validar profundidade máxima de relacionamentos
            $depth = substr_count($field, '.');
            if ($depth > $maxDepth) {
                $invalidFields[] = $field;
                continue;
            }
        }

        if (!empty($invalidFields)) {
            $request->request->remove('fields');
            $this->addValidationError($request, 'fields', 'Campos inválidos: ' . implode(', ', $invalidFields));
        }
    }

    /**
     * Sanitizar string
     *
     * @param string $value
     * @param bool $stripTags
     * @return string
     */
    protected function sanitizeString(string $value, bool $stripTags = true): string
    {
        // Trim espaços
        $value = trim($value);

        // Strip tags se configurado
        if ($stripTags && config('apiforge.security.strip_tags', true)) {
            $value = strip_tags($value);
        }

        // Verificar palavras bloqueadas
        if (config('apiforge.security.sanitize_input', true)) {
            $blockedKeywords = config('apiforge.security.blocked_keywords', []);
            foreach ($blockedKeywords as $keyword) {
                if (stripos($value, $keyword) !== false) {
                    return '';
                }
            }
        }

        // Limitar tamanho
        $maxLength = config('apiforge.security.max_query_length', 2000);
        if (strlen($value) > $maxLength) {
            $value = substr($value, 0, $maxLength);
        }

        return $value;
    }

    /**
     * Adicionar erro de validação aos atributos da requisição
     *
     * @param Request $request
     * @param string $field
     * @param string $message
     * @return void
     */
    protected function addValidationError(Request $request, string $field, string $message): void
    {
        $errors = $request->attributes->get('validation_errors', []);
        
        if (!isset($errors[$field])) {
            $errors[$field] = [];
        }
        
        $errors[$field][] = $message;
        $request->attributes->set('validation_errors', $errors);
    }

    /**
     * Verificar se há erros de validação na requisição
     *
     * @param Request $request
     * @return bool
     */
    protected function hasValidationErrors(Request $request): bool
    {
        $errors = $request->attributes->get('validation_errors', []);
        return !empty($errors);
    }

    /**
     * Obter erros de validação da requisição
     *
     * @param Request $request
     * @return array
     */
    protected function getValidationErrors(Request $request): array
    {
        return $request->attributes->get('validation_errors', []);
    }
}