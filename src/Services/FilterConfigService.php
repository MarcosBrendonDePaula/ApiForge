<?php

namespace MarcosBrendon\ApiForge\Services;

use Illuminate\Http\Request;

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
        $metadata = [
            'enabled_filters' => array_keys($this->enabledFilters),
            'filter_config' => $this->filterConfig,
            'available_operators' => self::$operatorConfig,
            'field_selection' => $this->fieldSelectionConfig,
            'searchable_fields' => $this->getSearchableFields(),
            'sortable_fields' => $this->getSortableFields(),
        ];

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
        ];

        return $metadata;
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
}