<?php

namespace MarcosBrendon\ApiForge\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use MarcosBrendon\ApiForge\Services\FilterConfigService;
use MarcosBrendon\ApiForge\Exceptions\DocumentationGenerationException;

class DocumentationGeneratorService
{
    protected FilterConfigService $filterConfigService;
    protected array $config;
    protected array $llmClients = [];

    public function __construct(FilterConfigService $filterConfigService)
    {
        $this->filterConfigService = $filterConfigService;
        $this->config = config('apiforge.documentation', []);
        $this->initializeLLMClients();
    }

    /**
     * Initialize available LLM clients based on configuration
     */
    protected function initializeLLMClients(): void
    {
        $providers = $this->config['llm']['providers'] ?? [];

        foreach ($providers as $provider => $config) {
            if ($config['enabled'] ?? false) {
                $this->llmClients[$provider] = $config;
            }
        }
    }

    /**
     * Generate complete OpenAPI documentation for an endpoint
     *
     * @param string $controllerClass
     * @param string $endpoint
     * @param array $additionalData
     * @return array
     * @throws DocumentationGenerationException
     */
    public function generateOpenApiDocumentation(
        string $controllerClass,
        string $endpoint,
        array $additionalData = []
    ): array {
        try {
            // Extract metadata from the controller
            $metadata = $this->extractEndpointMetadata($controllerClass, $endpoint);
            
            // Prepare context for LLM
            $context = $this->prepareDocumentationContext($metadata, $additionalData);
            
            // Generate enhanced documentation using LLM
            $llmEnhanced = $this->generateLLMEnhancedDocumentation($context);
            
            // Build OpenAPI schema
            $openApiDoc = $this->buildOpenApiSchema($metadata, $llmEnhanced, $endpoint);
            
            // Cache the generated documentation
            $this->cacheDocumentation($controllerClass, $endpoint, $openApiDoc);
            
            return $openApiDoc;

        } catch (\Exception $e) {
            Log::error('Documentation generation failed', [
                'controller' => $controllerClass,
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
            
            throw new DocumentationGenerationException(
                "Failed to generate documentation: {$e->getMessage()}"
            );
        }
    }

    /**
     * Extract comprehensive metadata from controller and ApiForge configuration
     *
     * @param string $controllerClass
     * @param string $endpoint
     * @return array
     */
    protected function extractEndpointMetadata(string $controllerClass, string $endpoint): array
    {
        // Get controller instance to extract configuration
        $controller = app($controllerClass);
        
        // Initialize filter services if the controller uses HasAdvancedFilters trait
        if (method_exists($controller, 'initializeFilterServices')) {
            $controller->initializeFilterServices();
        }

        // Extract comprehensive metadata
        $metadata = [
            'controller' => [
                'class' => $controllerClass,
                'name' => class_basename($controllerClass),
                'namespace' => (new \ReflectionClass($controllerClass))->getNamespaceName(),
            ],
            'endpoint' => [
                'path' => $endpoint,
                'methods' => $this->detectHttpMethods($controller),
                'model_class' => method_exists($controller, 'getModelClass') 
                    ? $controller->getModelClass() 
                    : null,
            ],
            'filters' => $this->filterConfigService->getCompleteMetadata(),
            'configuration' => [
                'pagination' => config('apiforge.pagination'),
                'field_selection' => config('apiforge.field_selection'),
                'security' => config('apiforge.security'),
                'cache' => config('apiforge.cache'),
            ],
            'examples' => $this->generateComprehensiveExamples($endpoint),
            'relationships' => $this->extractRelationshipInfo($controller),
            'validation' => $this->extractValidationRules($controller),
        ];

        return $metadata;
    }

    /**
     * Detect HTTP methods supported by the controller
     *
     * @param object $controller
     * @return array
     */
    protected function detectHttpMethods(object $controller): array
    {
        $methods = [];
        $reflection = new \ReflectionClass($controller);
        
        // Common REST methods
        $restMethods = [
            'index' => 'GET',
            'show' => 'GET', 
            'store' => 'POST',
            'update' => 'PUT',
            'destroy' => 'DELETE',
            'indexWithFilters' => 'GET'
        ];

        foreach ($restMethods as $method => $httpMethod) {
            if ($reflection->hasMethod($method)) {
                $methods[$httpMethod][] = $method;
            }
        }

        return $methods;
    }

    /**
     * Generate comprehensive examples for the endpoint
     *
     * @param string $endpoint
     * @return array
     */
    protected function generateComprehensiveExamples(string $endpoint): array
    {
        $filterMetadata = $this->filterConfigService->getFilterMetadata();
        $examples = [
            'basic' => [],
            'advanced' => [],
            'field_selection' => [],
            'pagination' => [],
            'sorting' => [],
            'complex_queries' => []
        ];

        // Basic examples
        foreach (array_slice($filterMetadata, 0, 3) as $field => $config) {
            $examples['basic'][] = "{$endpoint}?{$field}=" . $this->getExampleValue($config);
        }

        // Advanced operator examples
        foreach ($filterMetadata as $field => $config) {
            if (!empty($config['operators'])) {
                foreach ($config['operators'] as $operator) {
                    if (in_array($operator, ['like', 'gte', 'between', 'in'])) {
                        $examples['advanced'][] = $this->generateOperatorExample($endpoint, $field, $operator, $config);
                    }
                }
            }
        }

        // Field selection examples
        $selectableFields = $this->filterConfigService->getFieldSelectionConfig()['selectable_fields'] ?? [];
        if (!empty($selectableFields)) {
            $examples['field_selection'][] = $endpoint . '?fields=' . implode(',', array_slice($selectableFields, 0, 4));
            $examples['field_selection'][] = $endpoint . '?fields=id,name,created_at';
        }

        // Pagination examples
        $examples['pagination'] = [
            $endpoint . '?page=1&per_page=20',
            $endpoint . '?page=2&per_page=50'
        ];

        // Sorting examples
        $sortableFields = $this->filterConfigService->getSortableFields();
        if (!empty($sortableFields)) {
            $examples['sorting'][] = $endpoint . '?sort_by=' . $sortableFields[0] . '&sort_direction=desc';
        }

        // Complex query examples
        $examples['complex_queries'][] = $endpoint . '?name=John*&age=>=18&fields=id,name,email&sort_by=created_at&per_page=25';

        return $examples;
    }

    /**
     * Generate example value for a field configuration
     *
     * @param array $config
     * @return string
     */
    protected function getExampleValue(array $config): string
    {
        switch ($config['type']) {
            case 'string':
                return 'example';
            case 'integer':
                return '100';
            case 'float':
                return '99.99';
            case 'boolean':
                return 'true';
            case 'date':
            case 'datetime':
                return '2024-01-01';
            case 'enum':
                $values = $config['values'] ?? ['active', 'inactive'];
                return is_array($values) ? $values[0] : 'active';
            default:
                return 'value';
        }
    }

    /**
     * Generate operator-specific example
     *
     * @param string $endpoint
     * @param string $field
     * @param string $operator
     * @param array $config
     * @return string
     */
    protected function generateOperatorExample(string $endpoint, string $field, string $operator, array $config): string
    {
        $examples = $config['example'][$operator] ?? '';
        return $endpoint . '?' . $examples;
    }

    /**
     * Extract relationship information from controller
     *
     * @param object $controller
     * @return array
     */
    protected function extractRelationshipInfo(object $controller): array
    {
        $relationships = [];
        
        if (method_exists($controller, 'getDefaultRelationships')) {
            $defaultRels = $controller->getDefaultRelationships();
            foreach ($defaultRels as $rel) {
                $relationships[] = [
                    'name' => is_string($rel) ? $rel : (is_array($rel) ? key($rel) : 'unknown'),
                    'type' => 'default',
                    'fields' => is_array($rel) ? $rel : []
                ];
            }
        }

        return $relationships;
    }

    /**
     * Extract validation rules from controller
     *
     * @param object $controller
     * @return array
     */
    protected function extractValidationRules(object $controller): array
    {
        $validation = [];
        
        $methods = ['validateStoreData', 'validateUpdateData', 'getValidationRules'];
        foreach ($methods as $method) {
            if (method_exists($controller, $method)) {
                $validation[$method] = 'available';
            }
        }

        return $validation;
    }

    /**
     * Prepare comprehensive context for LLM processing
     *
     * @param array $metadata
     * @param array $additionalData
     * @return array
     */
    protected function prepareDocumentationContext(array $metadata, array $additionalData = []): array
    {
        return [
            'project_info' => [
                'name' => config('app.name', 'API'),
                'description' => 'Laravel API with advanced filtering capabilities',
                'version' => '1.0.0',
                'framework' => 'Laravel ' . app()->version(),
                'package' => 'ApiForge - Advanced API Filters'
            ],
            'endpoint_metadata' => $metadata,
            'additional_context' => $additionalData,
            'documentation_requirements' => [
                'format' => 'OpenAPI 3.0',
                'include_examples' => true,
                'include_error_responses' => true,
                'detailed_descriptions' => true,
                'parameter_validation' => true,
                'response_schemas' => true
            ]
        ];
    }

    /**
     * Generate enhanced documentation using LLM
     *
     * @param array $context
     * @return array
     * @throws DocumentationGenerationException
     */
    protected function generateLLMEnhancedDocumentation(array $context): array
    {
        $cacheKey = $this->getCacheKey('llm_enhanced', $context);
        
        return Cache::remember($cacheKey, $this->config['cache']['ttl'] ?? 3600, function () use ($context) {
            $provider = $this->selectBestProvider();
            
            if (!$provider) {
                throw new DocumentationGenerationException('No LLM provider available');
            }

            $prompt = $this->buildLLMPrompt($context);
            $response = $this->callLLMProvider($provider, $prompt);
            
            return $this->parseLLMResponse($response);
        });
    }

    /**
     * Select the best available LLM provider based on configuration and availability
     *
     * @return string|null
     */
    protected function selectBestProvider(): ?string
    {
        $priorities = $this->config['llm']['priority'] ?? ['claude', 'openai', 'deepseek'];
        
        foreach ($priorities as $provider) {
            if (isset($this->llmClients[$provider]) && $this->isProviderAvailable($provider)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Check if a provider is available and properly configured
     *
     * @param string $provider
     * @return bool
     */
    protected function isProviderAvailable(string $provider): bool
    {
        $config = $this->llmClients[$provider] ?? null;
        
        if (!$config || empty($config['api_key'])) {
            return false;
        }

        // You could add health check here
        return true;
    }

    /**
     * Build comprehensive prompt for LLM
     *
     * @param array $context
     * @return string
     */
    protected function buildLLMPrompt(array $context): string
    {
        $prompt = "You are an expert technical writer specializing in API documentation. Generate comprehensive, professional OpenAPI 3.0 documentation.\n\n";
        
        $prompt .= "**PROJECT CONTEXT:**\n";
        $prompt .= "- Name: {$context['project_info']['name']}\n";
        $prompt .= "- Framework: {$context['project_info']['framework']}\n";
        $prompt .= "- Package: {$context['project_info']['package']}\n\n";

        $prompt .= "**ENDPOINT INFORMATION:**\n";
        $prompt .= "- Controller: {$context['endpoint_metadata']['controller']['name']}\n";
        $prompt .= "- Path: {$context['endpoint_metadata']['endpoint']['path']}\n";
        $prompt .= "- Model: " . ($context['endpoint_metadata']['endpoint']['model_class'] ?? 'Unknown') . "\n\n";

        $prompt .= "**AVAILABLE FILTERS:**\n";
        foreach ($context['endpoint_metadata']['filters']['filter_config'] as $field => $config) {
            $prompt .= "- {$field}: {$config['type']} (operators: " . implode(', ', $config['operators']) . ")\n";
            if (!empty($config['description'])) {
                $prompt .= "  Description: {$config['description']}\n";
            }
        }

        $prompt .= "\n**EXAMPLES:**\n";
        foreach ($context['endpoint_metadata']['examples']['basic'] as $example) {
            $prompt .= "- {$example}\n";
        }

        $prompt .= "\n**REQUIREMENTS:**\n";
        $prompt .= "1. Generate complete OpenAPI 3.0 specification\n";
        $prompt .= "2. Include detailed parameter descriptions\n";
        $prompt .= "3. Add comprehensive examples for all filter types\n";
        $prompt .= "4. Document response schemas with proper types\n";
        $prompt .= "5. Include error response documentation\n";
        $prompt .= "6. Add security requirements if applicable\n";
        $prompt .= "7. Use professional, clear descriptions\n";
        $prompt .= "8. Include pagination parameters and responses\n";
        $prompt .= "9. Document field selection capabilities\n";
        $prompt .= "10. Add sorting and search parameters\n\n";

        $prompt .= "Generate ONLY the OpenAPI documentation in JSON format. Focus on the paths, parameters, responses, and components sections.";

        return $prompt;
    }

    /**
     * Call the selected LLM provider
     *
     * @param string $provider
     * @param string $prompt
     * @return string
     * @throws DocumentationGenerationException
     */
    protected function callLLMProvider(string $provider, string $prompt): string
    {
        $config = $this->llmClients[$provider];
        
        try {
            switch ($provider) {
                case 'openai':
                    return $this->callOpenAI($config, $prompt);
                case 'claude':
                    return $this->callClaude($config, $prompt);
                case 'deepseek':
                    return $this->callDeepSeek($config, $prompt);
                default:
                    throw new DocumentationGenerationException("Unsupported provider: {$provider}");
            }
        } catch (\Exception $e) {
            Log::error("LLM provider {$provider} failed", [
                'error' => $e->getMessage(),
                'prompt_length' => strlen($prompt)
            ]);
            
            throw new DocumentationGenerationException("Provider {$provider} failed: {$e->getMessage()}");
        }
    }

    /**
     * Call OpenAI API
     *
     * @param array $config
     * @param string $prompt
     * @return string
     */
    protected function callOpenAI(array $config, string $prompt): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['api_key'],
            'Content-Type' => 'application/json',
        ])->timeout($config['timeout'] ?? 60)->post($config['endpoint'], [
            'model' => $config['model'] ?? 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert API documentation generator. Generate only valid OpenAPI 3.0 JSON.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => $config['temperature'] ?? 0.1,
            'max_tokens' => $config['max_tokens'] ?? 4000,
        ]);

        if (!$response->successful()) {
            throw new \Exception("OpenAI API error: " . $response->body());
        }

        return $response->json()['choices'][0]['message']['content'];
    }

    /**
     * Call Claude API (Anthropic)
     *
     * @param array $config
     * @param string $prompt
     * @return string
     */
    protected function callClaude(array $config, string $prompt): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $config['api_key'],
            'Content-Type' => 'application/json',
            'anthropic-version' => $config['version'] ?? '2023-06-01',
        ])->timeout($config['timeout'] ?? 60)->post($config['endpoint'], [
            'model' => $config['model'] ?? 'claude-3-sonnet-20240229',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => $config['max_tokens'] ?? 4000,
            'temperature' => $config['temperature'] ?? 0.1,
        ]);

        if (!$response->successful()) {
            throw new \Exception("Claude API error: " . $response->body());
        }

        return $response->json()['content'][0]['text'];
    }

    /**
     * Call DeepSeek API
     *
     * @param array $config
     * @param string $prompt
     * @return string
     */
    protected function callDeepSeek(array $config, string $prompt): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['api_key'],
            'Content-Type' => 'application/json',
        ])->timeout($config['timeout'] ?? 60)->post($config['endpoint'], [
            'model' => $config['model'] ?? 'deepseek-chat',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert API documentation generator. Generate only valid OpenAPI 3.0 JSON.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => $config['temperature'] ?? 0.1,
            'max_tokens' => $config['max_tokens'] ?? 4000,
        ]);

        if (!$response->successful()) {
            throw new \Exception("DeepSeek API error: " . $response->body());
        }

        return $response->json()['choices'][0]['message']['content'];
    }

    /**
     * Parse LLM response and extract OpenAPI documentation
     *
     * @param string $response
     * @return array
     * @throws DocumentationGenerationException
     */
    protected function parseLLMResponse(string $response): array
    {
        // Extract JSON from response (remove markdown code blocks if present)
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*$/', '', $response);
        $response = trim($response);

        $parsed = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new DocumentationGenerationException('Invalid JSON response from LLM: ' . json_last_error_msg());
        }

        return $parsed;
    }

    /**
     * Build complete OpenAPI schema
     *
     * @param array $metadata
     * @param array $llmEnhanced
     * @param string $endpoint
     * @return array
     */
    protected function buildOpenApiSchema(array $metadata, array $llmEnhanced, string $endpoint): array
    {
        $baseSchema = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => $metadata['controller']['name'] . ' API',
                'version' => '1.0.0',
                'description' => 'Advanced API with filtering, pagination, and field selection capabilities',
                'contact' => [
                    'name' => 'API Support',
                    'url' => config('app.url')
                ]
            ],
            'servers' => [
                [
                    'url' => config('app.url') . '/api',
                    'description' => 'Main API Server'
                ]
            ],
            'paths' => [],
            'components' => [
                'schemas' => $this->generateSchemas($metadata),
                'parameters' => $this->generateCommonParameters($metadata),
                'responses' => $this->generateCommonResponses($metadata)
            ]
        ];

        // Merge with LLM enhanced content
        if (!empty($llmEnhanced['paths'])) {
            $baseSchema['paths'] = $llmEnhanced['paths'];
        }

        if (!empty($llmEnhanced['components'])) {
            $baseSchema['components'] = array_merge_recursive($baseSchema['components'], $llmEnhanced['components']);
        }

        return $baseSchema;
    }

    /**
     * Generate common schemas for the API
     *
     * @param array $metadata
     * @return array
     */
    protected function generateSchemas(array $metadata): array
    {
        return [
            'PaginatedResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean'],
                    'data' => ['type' => 'array', 'items' => ['type' => 'object']],
                    'pagination' => [
                        'type' => 'object',
                        'properties' => [
                            'current_page' => ['type' => 'integer'],
                            'per_page' => ['type' => 'integer'],
                            'total' => ['type' => 'integer'],
                            'last_page' => ['type' => 'integer'],
                            'has_more_pages' => ['type' => 'boolean'],
                            'next_page_url' => ['type' => 'string', 'nullable' => true],
                            'prev_page_url' => ['type' => 'string', 'nullable' => true]
                        ]
                    ],
                    'filters' => [
                        'type' => 'object',
                        'properties' => [
                            'active' => ['type' => 'object'],
                            'sorting' => ['type' => 'object']
                        ]
                    ]
                ]
            ],
            'ErrorResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => false],
                    'message' => ['type' => 'string'],
                    'errors' => ['type' => 'object', 'nullable' => true]
                ]
            ]
        ];
    }

    /**
     * Generate common parameters for the API
     *
     * @param array $metadata
     * @return array
     */
    protected function generateCommonParameters(array $metadata): array
    {
        $parameters = [
            'page' => [
                'name' => 'page',
                'in' => 'query',
                'description' => 'Page number for pagination',
                'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1]
            ],
            'per_page' => [
                'name' => 'per_page',
                'in' => 'query',
                'description' => 'Number of items per page',
                'schema' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => $metadata['configuration']['pagination']['max_per_page'] ?? 100,
                    'default' => $metadata['configuration']['pagination']['default_per_page'] ?? 15
                ]
            ],
            'fields' => [
                'name' => 'fields',
                'in' => 'query',
                'description' => 'Comma-separated list of fields to include in response',
                'schema' => ['type' => 'string'],
                'example' => 'id,name,email'
            ],
            'sort_by' => [
                'name' => 'sort_by',
                'in' => 'query',
                'description' => 'Field to sort by',
                'schema' => [
                    'type' => 'string',
                    'enum' => $metadata['filters']['sortable_fields'] ?? []
                ]
            ],
            'sort_direction' => [
                'name' => 'sort_direction',
                'in' => 'query',
                'description' => 'Sort direction',
                'schema' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'asc']
            ]
        ];

        // Add filter parameters
        foreach ($metadata['filters']['filter_config'] as $field => $config) {
            $parameters[$field] = [
                'name' => $field,
                'in' => 'query',
                'description' => $config['description'] ?? "Filter by {$field}",
                'schema' => $this->getSchemaForFieldType($config['type']),
                'examples' => $this->generateParameterExamples($field, $config)
            ];
        }

        return $parameters;
    }

    /**
     * Generate common responses for the API
     *
     * @param array $metadata
     * @return array
     */
    protected function generateCommonResponses(array $metadata): array
    {
        return [
            '200' => [
                'description' => 'Successful response',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/PaginatedResponse']
                    ]
                ]
            ],
            '400' => [
                'description' => 'Bad Request',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse']
                    ]
                ]
            ],
            '422' => [
                'description' => 'Validation Error',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse']
                    ]
                ]
            ],
            '500' => [
                'description' => 'Internal Server Error',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse']
                    ]
                ]
            ]
        ];
    }

    /**
     * Get OpenAPI schema for field type
     *
     * @param string $type
     * @return array
     */
    protected function getSchemaForFieldType(string $type): array
    {
        switch ($type) {
            case 'integer':
                return ['type' => 'integer'];
            case 'float':
                return ['type' => 'number', 'format' => 'float'];
            case 'boolean':
                return ['type' => 'boolean'];
            case 'date':
                return ['type' => 'string', 'format' => 'date'];
            case 'datetime':
                return ['type' => 'string', 'format' => 'date-time'];
            case 'enum':
                return ['type' => 'string']; // Could be enhanced with enum values
            default:
                return ['type' => 'string'];
        }
    }

    /**
     * Generate parameter examples
     *
     * @param string $field
     * @param array $config
     * @return array
     */
    protected function generateParameterExamples(string $field, array $config): array
    {
        $examples = [];
        
        foreach ($config['example'] ?? [] as $operator => $example) {
            $examples[$operator] = [
                'summary' => "Using {$operator} operator",
                'value' => str_replace("{$field}=", '', $example)
            ];
        }

        return $examples;
    }

    /**
     * Cache the generated documentation
     *
     * @param string $controllerClass
     * @param string $endpoint
     * @param array $documentation
     * @return void
     */
    protected function cacheDocumentation(string $controllerClass, string $endpoint, array $documentation): void
    {
        $cacheKey = $this->getCacheKey('documentation', compact('controllerClass', 'endpoint'));
        $ttl = $this->config['cache']['ttl'] ?? 3600;
        
        Cache::put($cacheKey, $documentation, $ttl);
    }

    /**
     * Generate cache key
     *
     * @param string $type
     * @param mixed $data
     * @return string
     */
    protected function getCacheKey(string $type, $data): string
    {
        return 'apiforge_docs_' . $type . '_' . md5(serialize($data));
    }

    /**
     * Get cached documentation if available
     *
     * @param string $controllerClass
     * @param string $endpoint
     * @return array|null
     */
    public function getCachedDocumentation(string $controllerClass, string $endpoint): ?array
    {
        $cacheKey = $this->getCacheKey('documentation', compact('controllerClass', 'endpoint'));
        return Cache::get($cacheKey);
    }

    /**
     * Clear documentation cache
     *
     * @param string|null $controllerClass
     * @param string|null $endpoint
     * @return void
     */
    public function clearCache(?string $controllerClass = null, ?string $endpoint = null): void
    {
        if ($controllerClass && $endpoint) {
            $cacheKey = $this->getCacheKey('documentation', compact('controllerClass', 'endpoint'));
            Cache::forget($cacheKey);
        } else {
            // Clear all documentation cache
            Cache::flush(); // This is aggressive, you might want to use tags instead
        }
    }
}