<?php

namespace MarcosBrendon\ApiForge\Services;

use Illuminate\Http\Request;
use MarcosBrendon\ApiForge\Services\VirtualFieldService;

class FilterConfigService
{
    /**
     * Configuração completa de filtros
     *
     * @var array
     */
    protected array $filterConfig = [];

    /**
     * Filtros que estão habilitados
     *
     * @var array
     */
    protected array $enabledFilters = [];

    /**
     * Virtual field service instance
     *
     * @var VirtualFieldService|null
     */
    protected ?VirtualFieldService $virtualFieldService = null;

    /**
     * Configuração de seleção de campos
     *
     * @var array
     */
    protected array $fieldSelectionConfig = [
        'selectable_fields' => [],
        'required_fields' => ['id'],
        'blocked_fields' => [],
        'default_fields' => [],
        'field_aliases' => [],
        'max_fields' => 50,
        'allow_all_fields' => false
    ];

    /**
     * Operadores disponíveis com suas configurações
     *
     * @var array
     */
    protected static array $operatorConfig = [
        'eq' => [
            'sql' => '=',
            'name' => 'Igual',
            'description' => 'Valor exato',
            'example' => 'campo=valor',
            'supports' => ['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'enum']
        ],
        'ne' => [
            'sql' => '!=',
            'name' => 'Diferente',
            'description' => 'Diferente do valor',
            'example' => 'campo=!=valor',
            'supports' => ['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'enum']
        ],
        'gt' => [
            'sql' => '>',
            'name' => 'Maior que',
            'description' => 'Maior que o valor',
            'example' => 'campo=>10',
            'supports' => ['integer', 'float', 'date', 'datetime']
        ],
        'gte' => [
            'sql' => '>=',
            'name' => 'Maior ou igual',
            'description' => 'Maior ou igual ao valor',
            'example' => 'campo=>=10',
            'supports' => ['integer', 'float', 'date', 'datetime']
        ],
        'lt' => [
            'sql' => '<',
            'name' => 'Menor que',
            'description' => 'Menor que o valor',
            'example' => 'campo=<10',
            'supports' => ['integer', 'float', 'date', 'datetime']
        ],
        'lte' => [
            'sql' => '<=',
            'name' => 'Menor ou igual',
            'description' => 'Menor ou igual ao valor',
            'example' => 'campo=<=10',
            'supports' => ['integer', 'float', 'date', 'datetime']
        ],
        'like' => [
            'sql' => 'LIKE',
            'name' => 'Contém',
            'description' => 'Contém o texto (use * como wildcard)',
            'example' => 'campo=João* ou campo=*Silva ou campo=*João*',
            'supports' => ['string']
        ],
        'not_like' => [
            'sql' => 'NOT LIKE',
            'name' => 'Não contém',
            'description' => 'Não contém o texto',
            'example' => 'campo=!=*João*',
            'supports' => ['string']
        ],
        'in' => [
            'sql' => 'IN',
            'name' => 'Está em',
            'description' => 'Valor está na lista (separado por vírgula)',
            'example' => 'campo=1,2,3 ou campo=ativo,inativo',
            'supports' => ['string', 'integer', 'float', 'enum']
        ],
        'not_in' => [
            'sql' => 'NOT IN',
            'name' => 'Não está em',
            'description' => 'Valor não está na lista',
            'example' => 'campo=!=1,2,3',
            'supports' => ['string', 'integer', 'float', 'enum']
        ],
        'null' => [
            'sql' => 'IS NULL',
            'name' => 'É nulo',
            'description' => 'Campo é nulo/vazio',
            'example' => 'campo=null',
            'supports' => ['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'json']
        ],
        'not_null' => [
            'sql' => 'IS NOT NULL',
            'name' => 'Não é nulo',
            'description' => 'Campo não é nulo/vazio',
            'example' => 'campo=!=null',
            'supports' => ['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'json']
        ],
        'between' => [
            'sql' => 'BETWEEN',
            'name' => 'Entre valores',
            'description' => 'Valor entre dois números ou datas',
            'example' => 'campo=10|20 ou campo=2024-01-01|2024-12-31',
            'supports' => ['integer', 'float', 'date', 'datetime']
        ],
        'not_between' => [
            'sql' => 'NOT BETWEEN',
            'name' => 'Não entre valores',
            'description' => 'Valor não está entre dois números ou datas',
            'example' => 'campo=!=10|20',
            'supports' => ['integer', 'float', 'date', 'datetime']
        ],
        'starts_with' => [
            'sql' => 'LIKE',
            'name' => 'Começa com',
            'description' => 'Texto que começa com o valor',
            'example' => 'campo=João*',
            'supports' => ['string']
        ],
        'ends_with' => [
            'sql' => 'LIKE',
            'name' => 'Termina com',
            'description' => 'Texto que termina com o valor',
            'example' => 'campo=*Silva',
            'supports' => ['string']
        ]
    ];

    /**
     * Configurar filtros disponíveis
     *
     * @param array $config
     * @return self
     */
    public function configure(array $config): self
    {
        $this->filterConfig = [];
        $this->enabledFilters = [];

        foreach ($config as $field => $settings) {
            $this->addFilter($field, $settings);
        }

        return $this;
    }

    /**
     * Adicionar um filtro individual
     *
     * @param string $field
     * @param array $settings
     * @return self
     */
    public function addFilter(string $field, array $settings): self
    {
        // Configuração padrão
        $defaultConfig = [
            'type' => 'string',
            'operators' => ['eq'],
            'required' => false,
            'description' => null,
            'example' => [],
            'values' => null, // Para enums
            'min' => null,
            'max' => null,
            'format' => null, // Para datas
            'relationship' => null, // Para campos de relacionamento
            'searchable' => false, // Se pode ser pesquisado no search geral
            'sortable' => false, // Se pode ser usado na ordenação
            'validation' => [], // Regras de validação customizadas
        ];

        $config = array_merge($defaultConfig, $settings);

        // Validar operadores suportados para o tipo
        $validOperators = [];
        foreach ($config['operators'] as $operator) {
            if (isset(self::$operatorConfig[$operator])) {
                $operatorInfo = self::$operatorConfig[$operator];
                if (in_array($config['type'], $operatorInfo['supports'])) {
                    $validOperators[] = $operator;
                }
            }
        }

        $config['operators'] = $validOperators;

        // Gerar exemplos automáticos se não fornecidos
        if (empty($config['example'])) {
            $config['example'] = $this->generateExamples($field, $config);
        }

        $this->filterConfig[$field] = $config;
        $this->enabledFilters[$field] = true;

        return $this;
    }

    /**
     * Configurar seleção de campos
     *
     * @param array $config
     * @return self
     */
    public function configureFieldSelection(array $config): self
    {
        $this->fieldSelectionConfig = array_merge($this->fieldSelectionConfig, $config);
        return $this;
    }

    /**
     * Set the virtual field service
     *
     * @param VirtualFieldService $service
     * @return self
     */
    public function setVirtualFieldService(VirtualFieldService $service): self
    {
        $this->virtualFieldService = $service;
        return $this;
    }

    /**
     * Configure virtual fields
     *
     * @param array $config
     * @return self
     */
    public function configureVirtualFields(array $config): self
    {
        if (!$this->virtualFieldService) {
            throw new \RuntimeException('Virtual field service must be set before configuring virtual fields');
        }

        // Register virtual fields with the service
        $this->virtualFieldService->registerFromConfig($config);

        // Add virtual fields to filter configuration
        foreach ($config as $fieldName => $fieldConfig) {
            $this->addVirtualFieldFilter($fieldName, $fieldConfig);
        }

        return $this;
    }

    /**
     * Add a virtual field to the filter configuration
     *
     * @param string $fieldName
     * @param array $fieldConfig
     * @return void
     */
    protected function addVirtualFieldFilter(string $fieldName, array $fieldConfig): void
    {
        // Create filter configuration for the virtual field
        $filterConfig = [
            'type' => $fieldConfig['type'],
            'operators' => $fieldConfig['operators'] ?? $this->getDefaultOperatorsForType($fieldConfig['type']),
            'required' => false,
            'description' => $fieldConfig['description'] ?? "Virtual field: {$fieldName}",
            'example' => [],
            'values' => $fieldConfig['values'] ?? null,
            'min' => $fieldConfig['min'] ?? null,
            'max' => $fieldConfig['max'] ?? null,
            'format' => $fieldConfig['format'] ?? null,
            'relationship' => null, // Virtual fields don't have direct relationships
            'searchable' => $fieldConfig['searchable'] ?? true,
            'sortable' => $fieldConfig['sortable'] ?? true,
            'validation' => $fieldConfig['validation'] ?? [],
            'virtual' => true, // Mark as virtual field
            'dependencies' => $fieldConfig['dependencies'] ?? [],
            'relationships' => $fieldConfig['relationships'] ?? [],
            'cacheable' => $fieldConfig['cacheable'] ?? false,
            'cache_ttl' => $fieldConfig['cache_ttl'] ?? 3600,
            'default_value' => $fieldConfig['default_value'] ?? null,
            'nullable' => $fieldConfig['nullable'] ?? true
        ];

        // Validate operators for the field type
        $validOperators = [];
        foreach ($filterConfig['operators'] as $operator) {
            if (isset(self::$operatorConfig[$operator])) {
                $operatorInfo = self::$operatorConfig[$operator];
                if (in_array($filterConfig['type'], $operatorInfo['supports'])) {
                    $validOperators[] = $operator;
                }
            }
        }
        $filterConfig['operators'] = $validOperators;

        // Generate examples
        if (empty($filterConfig['example'])) {
            $filterConfig['example'] = $this->generateExamples($fieldName, $filterConfig);
        }

        $this->filterConfig[$fieldName] = $filterConfig;
        $this->enabledFilters[$fieldName] = true;
    }

    /**
     * Get default operators for a field type
     *
     * @param string $type
     * @return array
     */
    protected function getDefaultOperatorsForType(string $type): array
    {
        switch ($type) {
            case 'string':
                return ['eq', 'ne', 'like', 'not_like', 'in', 'not_in', 'null', 'not_null', 'starts_with', 'ends_with'];
            case 'integer':
            case 'float':
                return ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in', 'null', 'not_null', 'between', 'not_between'];
            case 'boolean':
                return ['eq', 'ne', 'null', 'not_null'];
            case 'date':
            case 'datetime':
                return ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'null', 'not_null', 'between', 'not_between'];
            case 'enum':
                return ['eq', 'ne', 'in', 'not_in', 'null', 'not_null'];
            case 'json':
                return ['eq', 'ne', 'null', 'not_null'];
            default:
                return ['eq', 'ne', 'null', 'not_null'];
        }
    }

    /**
     * Check if a field is a virtual field
     *
     * @param string $field
     * @return bool
     */
    public function isVirtualField(string $field): bool
    {
        return isset($this->filterConfig[$field]) && 
               ($this->filterConfig[$field]['virtual'] ?? false);
    }

    /**
     * Get virtual field configuration
     *
     * @param string $field
     * @return array|null
     */
    public function getVirtualFieldConfig(string $field): ?array
    {
        if (!$this->isVirtualField($field)) {
            return null;
        }

        return $this->filterConfig[$field];
    }

    /**
     * Get all virtual fields
     *
     * @return array
     */
    public function getVirtualFields(): array
    {
        return array_filter($this->filterConfig, function ($config) {
            return $config['virtual'] ?? false;
        });
    }

    /**
     * Gerar exemplos automáticos para um campo
     *
     * @param string $field
     * @param array $config
     * @return array
     */
    protected function generateExamples(string $field, array $config): array
    {
        $examples = [];
        $type = $config['type'];

        foreach ($config['operators'] as $operator) {
            if (!isset(self::$operatorConfig[$operator])) {
                continue;
            }

            $operatorInfo = self::$operatorConfig[$operator];
            
            switch ($type) {
                case 'string':
                    $examples[$operator] = str_replace('campo', $field, $operatorInfo['example']);
                    break;
                    
                case 'integer':
                case 'float':
                    $examples[$operator] = str_replace(['campo', 'valor'], [$field, '100'], $operatorInfo['example']);
                    break;
                    
                case 'boolean':
                    $examples[$operator] = str_replace(['campo', 'valor'], [$field, 'true'], $operatorInfo['example']);
                    break;
                    
                case 'date':
                case 'datetime':
                    $examples[$operator] = str_replace(['campo', 'valor'], [$field, '2024-01-01'], $operatorInfo['example']);
                    break;
                    
                case 'enum':
                    $values = $config['values'] ?? ['ativo', 'inativo'];
                    $value = is_array($values) ? $values[0] : 'ativo';
                    $examples[$operator] = str_replace(['campo', 'valor'], [$field, $value], $operatorInfo['example']);
                    break;
                    
                default:
                    $examples[$operator] = str_replace('campo', $field, $operatorInfo['example']);
                    break;
            }
        }

        return $examples;
    }

    /**
     * Validar filtros obrigatórios
     *
     * @param Request $request
     * @return array
     */
    public function validateRequiredFilters(Request $request): array
    {
        $missing = [];
        
        foreach ($this->filterConfig as $field => $config) {
            if ($config['required'] && !$request->has($field)) {
                $missing[] = $field;
            }
        }
        
        return $missing;
    }

    /**
     * Obter campos pesquisáveis
     *
     * @return array
     */
    public function getSearchableFields(): array
    {
        $searchable = [];
        
        foreach ($this->filterConfig as $field => $config) {
            if ($config['searchable']) {
                $searchable[] = $field;
            }
        }
        
        return $searchable;
    }

    /**
     * Get searchable virtual fields
     *
     * @return array
     */
    public function getSearchableVirtualFields(): array
    {
        $searchable = [];
        
        foreach ($this->filterConfig as $field => $config) {
            if (($config['virtual'] ?? false) && $config['searchable']) {
                $searchable[] = $field;
            }
        }
        
        return $searchable;
    }

    /**
     * Obter campos ordenáveis
     *
     * @return array
     */
    public function getSortableFields(): array
    {
        $sortable = [];
        
        foreach ($this->filterConfig as $field => $config) {
            if ($config['sortable']) {
                $sortable[] = $field;
            }
        }
        
        return $sortable;
    }

    /**
     * Get sortable virtual fields
     *
     * @return array
     */
    public function getSortableVirtualFields(): array
    {
        $sortable = [];
        
        foreach ($this->filterConfig as $field => $config) {
            if (($config['virtual'] ?? false) && $config['sortable']) {
                $sortable[] = $field;
            }
        }
        
        return $sortable;
    }

    /**
     * Obter metadados dos filtros
     *
     * @return array
     */
    public function getFilterMetadata(): array
    {
        return $this->filterConfig;
    }

    /**
     * Obter metadados completos incluindo operadores
     *
     * @return array
     */
    public function getCompleteMetadata(): array
    {
        // Separate regular and virtual fields
        $regularFields = [];
        $virtualFields = [];
        
        foreach ($this->filterConfig as $field => $config) {
            if ($config['virtual'] ?? false) {
                $virtualFields[$field] = $config;
            } else {
                $regularFields[$field] = $config;
            }
        }

        $metadata = [
            'enabled_filters' => array_keys($this->enabledFilters),
            'filter_config' => $regularFields,
            'virtual_field_config' => $virtualFields,
            'available_operators' => self::$operatorConfig,
            'field_selection' => $this->fieldSelectionConfig,
            'searchable_fields' => $this->getSearchableFields(),
            'sortable_fields' => $this->getSortableFields(),
            'virtual_fields' => [
                'searchable' => $this->getSearchableVirtualFields(),
                'sortable' => $this->getSortableVirtualFields(),
                'total_count' => count($virtualFields),
                'by_type' => $this->getVirtualFieldsByType()
            ]
        ];

        // Add virtual field service metadata if available
        if ($this->virtualFieldService) {
            $metadata['virtual_field_service'] = $this->virtualFieldService->getStatistics();
        }

        // Adicionar guia de uso
        $metadata['usage_guide'] = [
            'simple_filters' => 'Use ?campo=valor para filtros simples',
            'operators' => 'Use operadores como >=, <=, !=, * para wildcards',
            'multiple_values' => 'Separe múltiplos valores com vírgula: campo=1,2,3',
            'ranges' => 'Use | para intervalos: campo=10|20 ou data=2024-01-01|2024-12-31',
            'wildcards' => 'Use * como wildcard: nome=João* (começa com João)',
            'relationships' => 'Acesse relacionamentos: fields=id,nome,empresa.nome',
            'pagination' => 'Use page=1&per_page=20 para paginação',
            'field_selection' => 'Use fields=id,nome,email para selecionar campos específicos',
            'sorting' => 'Use sort_by=nome&sort_direction=asc para ordenação',
            'virtual_fields' => 'Campos virtuais são computados dinamicamente e podem ser filtrados como campos normais'
        ];

        return $metadata;
    }

    /**
     * Get virtual fields grouped by type
     *
     * @return array
     */
    protected function getVirtualFieldsByType(): array
    {
        $byType = [];
        
        foreach ($this->filterConfig as $field => $config) {
            if ($config['virtual'] ?? false) {
                $type = $config['type'];
                if (!isset($byType[$type])) {
                    $byType[$type] = [];
                }
                $byType[$type][] = $field;
            }
        }
        
        return $byType;
    }

    /**
     * Obter configuração de field selection
     *
     * @return array
     */
    public function getFieldSelectionConfig(): array
    {
        return $this->fieldSelectionConfig;
    }

    /**
     * Verificar se um campo pode ser selecionado
     *
     * @param string $field
     * @return bool
     */
    public function isFieldSelectable(string $field): bool
    {
        $config = $this->fieldSelectionConfig;
        
        // Verificar se está na lista de bloqueados
        if (in_array($field, $config['blocked_fields'])) {
            return false;
        }

        // Virtual fields are selectable if they are configured
        if ($this->isVirtualField($field)) {
            return true;
        }

        // Se allow_all_fields for true, permitir qualquer campo não bloqueado
        if ($config['allow_all_fields']) {
            return true;
        }

        // Verificar se está na lista de campos selecionáveis
        return in_array($field, $config['selectable_fields']);
    }

    /**
     * Resolver alias de campo
     *
     * @param string $field
     * @return string
     */
    public function resolveFieldAlias(string $field): string
    {
        $aliases = $this->fieldSelectionConfig['field_aliases'] ?? [];
        return $aliases[$field] ?? $field;
    }

    /**
     * Validar seleção de campos
     *
     * @param array $fields
     * @return array [valid_fields, invalid_fields]
     */
    public function validateFieldSelection(array $fields): array
    {
        $valid = [];
        $invalid = [];
        
        foreach ($fields as $field) {
            // Resolver alias
            $resolvedField = $this->resolveFieldAlias($field);
            
            if ($this->isFieldSelectable($resolvedField)) {
                $valid[] = $resolvedField;
            } else {
                $invalid[] = $field;
            }
        }
        
        return [$valid, $invalid];
    }

    /**
     * Obter campos padrão para seleção
     *
     * @return array
     */
    public function getDefaultFields(): array
    {
        $defaults = $this->fieldSelectionConfig['default_fields'];
        $required = $this->fieldSelectionConfig['required_fields'];
        
        return array_unique(array_merge($required, $defaults));
    }

    /**
     * Aplicar limites de seleção de campos
     *
     * @param array $fields
     * @return array
     */
    public function applyFieldLimits(array $fields): array
    {
        $maxFields = $this->fieldSelectionConfig['max_fields'];
        
        if (count($fields) > $maxFields) {
            $fields = array_slice($fields, 0, $maxFields);
        }
        
        // Garantir que campos obrigatórios estejam incluídos
        $required = $this->fieldSelectionConfig['required_fields'];
        foreach ($required as $requiredField) {
            if (!in_array($requiredField, $fields)) {
                array_unshift($fields, $requiredField);
            }
        }
        
        return array_unique($fields);
    }

    /**
     * Verificar se um operador é válido para um tipo de campo
     *
     * @param string $operator
     * @param string $fieldType
     * @return bool
     */
    public function isOperatorValidForType(string $operator, string $fieldType): bool
    {
        if (!isset(self::$operatorConfig[$operator])) {
            return false;
        }
        
        return in_array($fieldType, self::$operatorConfig[$operator]['supports']);
    }

    /**
     * Obter operadores válidos para um tipo de campo
     *
     * @param string $fieldType
     * @return array
     */
    public function getOperatorsForType(string $fieldType): array
    {
        $validOperators = [];
        
        foreach (self::$operatorConfig as $operator => $config) {
            if (in_array($fieldType, $config['supports'])) {
                $validOperators[] = $operator;
            }
        }
        
        return $validOperators;
    }

    /**
     * Validate virtual field filter request
     *
     * @param string $field
     * @param string $operator
     * @param mixed $value
     * @return array [valid, errors]
     */
    public function validateVirtualFieldFilter(string $field, string $operator, $value): array
    {
        $errors = [];

        // Check if field exists
        if (!$this->isVirtualField($field)) {
            $errors[] = "Virtual field '{$field}' is not configured";
            return [false, $errors];
        }

        $config = $this->getVirtualFieldConfig($field);

        // Check if operator is supported
        if (!in_array($operator, $config['operators'])) {
            $errors[] = "Operator '{$operator}' is not supported for virtual field '{$field}'";
        }

        // Validate value based on field type
        $typeValidation = $this->validateValueForType($value, $config['type'], $config);
        if (!$typeValidation[0]) {
            $errors = array_merge($errors, $typeValidation[1]);
        }

        return [empty($errors), $errors];
    }

    /**
     * Validate value for a specific field type
     *
     * @param mixed $value
     * @param string $type
     * @param array $config
     * @return array [valid, errors]
     */
    protected function validateValueForType($value, string $type, array $config): array
    {
        $errors = [];

        if ($value === null || $value === '') {
            return [true, []]; // Null/empty values are generally valid
        }

        switch ($type) {
            case 'integer':
                if (!is_numeric($value) || !is_int($value + 0)) {
                    $errors[] = "Value must be an integer";
                }
                break;

            case 'float':
                if (!is_numeric($value)) {
                    $errors[] = "Value must be a number";
                }
                break;

            case 'boolean':
                $validBooleans = ['true', 'false', '1', '0', 'yes', 'no', 'on', 'off'];
                if (!is_bool($value) && !in_array(strtolower((string)$value), $validBooleans)) {
                    $errors[] = "Value must be a boolean";
                }
                break;

            case 'date':
            case 'datetime':
                try {
                    if (is_string($value)) {
                        \Carbon\Carbon::parse($value);
                    } else {
                        $errors[] = "Value must be a valid date string";
                    }
                } catch (\Exception $e) {
                    $errors[] = "Value must be a valid date";
                }
                break;

            case 'enum':
                $allowedValues = $config['values'] ?? [];
                if (!empty($allowedValues) && !in_array($value, $allowedValues)) {
                    $errors[] = "Value must be one of: " . implode(', ', $allowedValues);
                }
                break;

            case 'json':
                if (is_string($value)) {
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $errors[] = "Value must be valid JSON";
                    }
                } elseif (!is_array($value) && !is_object($value)) {
                    $errors[] = "Value must be valid JSON";
                }
                break;
        }

        return [empty($errors), $errors];
    }

    /**
     * Get virtual field dependencies for query optimization
     *
     * @param array $virtualFields
     * @return array
     */
    public function getVirtualFieldDependencies(array $virtualFields): array
    {
        if (!$this->virtualFieldService) {
            return ['fields' => [], 'relationships' => []];
        }

        return $this->virtualFieldService->getRegistry()->getAllDependencies($virtualFields);
    }
}