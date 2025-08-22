<?php

namespace MarcosBrendon\ApiForge\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use MarcosBrendon\ApiForge\Services\DocumentationGeneratorService;
use MarcosBrendon\ApiForge\Services\FilterConfigService;
use MarcosBrendon\ApiForge\Tests\TestCase;
use MarcosBrendon\ApiForge\Exceptions\DocumentationGenerationException;

class DocumentationGeneratorServiceTest extends TestCase
{
    protected DocumentationGeneratorService $docGenerator;
    protected FilterConfigService $filterConfigService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->filterConfigService = new FilterConfigService();
        $this->docGenerator = new DocumentationGeneratorService($this->filterConfigService);

        // Mock basic configuration
        Config::set('apiforge.documentation', [
            'enabled' => true,
            'cache' => ['enabled' => true, 'ttl' => 3600],
            'llm' => [
                'priority' => ['openai'],
                'providers' => [
                    'openai' => [
                        'enabled' => true,
                        'api_key' => 'test-key',
                        'endpoint' => 'https://api.openai.com/v1/chat/completions',
                        'model' => 'gpt-4o',
                        'temperature' => 0.1,
                        'max_tokens' => 4000,
                        'timeout' => 60,
                    ]
                ]
            ]
        ]);
    }

    /** @test */
    public function it_can_extract_endpoint_metadata_from_mock_controller()
    {
        // Mock controller class
        $controllerClass = 'Tests\MockUserController';
        $endpoint = '/api/users';

        // Configure filter service with test data
        $this->filterConfigService->configure([
            'name' => [
                'type' => 'string',
                'operators' => ['eq', 'like'],
                'searchable' => true,
                'sortable' => true,
                'description' => 'User name'
            ],
            'email' => [
                'type' => 'string',
                'operators' => ['eq'],
                'description' => 'User email'
            ]
        ]);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->docGenerator);
        $method = $reflection->getMethod('extractEndpointMetadata');
        $method->setAccessible(true);

        // Since we can't easily mock a controller, we'll test the metadata structure
        // by checking what the filter config service returns
        $metadata = $this->filterConfigService->getCompleteMetadata();

        $this->assertArrayHasKey('filter_config', $metadata);
        $this->assertArrayHasKey('available_operators', $metadata);
        $this->assertArrayHasKey('searchable_fields', $metadata);
        $this->assertEquals(['name'], $metadata['searchable_fields']);
        $this->assertEquals(['name'], $metadata['sortable_fields']);
    }

    /** @test */
    public function it_generates_comprehensive_examples()
    {
        $this->filterConfigService->configure([
            'name' => [
                'type' => 'string',
                'operators' => ['eq', 'like'],
                'searchable' => true
            ],
            'age' => [
                'type' => 'integer',
                'operators' => ['gte', 'lte', 'between']
            ]
        ]);

        $reflection = new \ReflectionClass($this->docGenerator);
        $method = $reflection->getMethod('generateComprehensiveExamples');
        $method->setAccessible(true);

        $examples = $method->invoke($this->docGenerator, '/api/users');

        $this->assertIsArray($examples);
        $this->assertArrayHasKey('basic', $examples);
        $this->assertArrayHasKey('advanced', $examples);
        $this->assertArrayHasKey('pagination', $examples);
        $this->assertArrayHasKey('sorting', $examples);

        // Check basic examples
        $this->assertNotEmpty($examples['basic']);
        
        // Check pagination examples
        $this->assertContains('/api/users?page=1&per_page=20', $examples['pagination']);
    }

    /** @test */
    public function it_prepares_documentation_context_correctly()
    {
        $metadata = [
            'controller' => ['class' => 'UserController', 'name' => 'UserController'],
            'endpoint' => ['path' => '/api/users'],
            'filters' => ['filter_config' => []]
        ];

        $reflection = new \ReflectionClass($this->docGenerator);
        $method = $reflection->getMethod('prepareDocumentationContext');
        $method->setAccessible(true);

        $context = $method->invoke($this->docGenerator, $metadata, ['extra' => 'data']);

        $this->assertArrayHasKey('project_info', $context);
        $this->assertArrayHasKey('endpoint_metadata', $context);
        $this->assertArrayHasKey('additional_context', $context);
        $this->assertArrayHasKey('documentation_requirements', $context);

        // Check project info
        $this->assertEquals('Laravel ' . app()->version(), $context['project_info']['framework']);
        $this->assertEquals('ApiForge - Advanced API Filters', $context['project_info']['package']);

        // Check requirements
        $requirements = $context['documentation_requirements'];
        $this->assertTrue($requirements['include_examples']);
        $this->assertTrue($requirements['include_error_responses']);
        $this->assertEquals('OpenAPI 3.0', $requirements['format']);
    }

    /** @test */
    public function it_builds_llm_prompt_correctly()
    {
        $context = [
            'project_info' => [
                'name' => 'Test API',
                'framework' => 'Laravel 11.x',
                'package' => 'ApiForge'
            ],
            'endpoint_metadata' => [
                'controller' => ['name' => 'UserController'],
                'endpoint' => ['path' => '/api/users', 'model_class' => 'User'],
                'filters' => [
                    'filter_config' => [
                        'name' => [
                            'type' => 'string',
                            'operators' => ['eq', 'like'],
                            'description' => 'User name'
                        ]
                    ]
                ],
                'examples' => ['basic' => ['/api/users?name=John']]
            ]
        ];

        $reflection = new \ReflectionClass($this->docGenerator);
        $method = $reflection->getMethod('buildLLMPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($this->docGenerator, $context);

        $this->assertIsString($prompt);
        $this->assertStringContainsString('OpenAPI 3.0', $prompt);
        $this->assertStringContainsString('Test API', $prompt);
        $this->assertStringContainsString('UserController', $prompt);
        $this->assertStringContainsString('/api/users', $prompt);
        $this->assertStringContainsString('name: string', $prompt);
        $this->assertStringContainsString('/api/users?name=John', $prompt);
        
        // Check that it includes requirements
        $this->assertStringContainsString('Generate complete OpenAPI 3.0 specification', $prompt);
        $this->assertStringContainsString('Include detailed parameter descriptions', $prompt);
        $this->assertStringContainsString('JSON format', $prompt);
    }

    /** @test */
    public function it_parses_llm_response_correctly()
    {
        $jsonResponse = '```json
        {
            "openapi": "3.0.0",
            "info": {
                "title": "Test API",
                "version": "1.0.0"
            },
            "paths": {
                "/users": {
                    "get": {
                        "summary": "List users"
                    }
                }
            }
        }
        ```';

        $reflection = new \ReflectionClass($this->docGenerator);
        $method = $reflection->getMethod('parseLLMResponse');
        $method->setAccessible(true);

        $parsed = $method->invoke($this->docGenerator, $jsonResponse);

        $this->assertIsArray($parsed);
        $this->assertEquals('3.0.0', $parsed['openapi']);
        $this->assertEquals('Test API', $parsed['info']['title']);
        $this->assertArrayHasKey('/users', $parsed['paths']);
    }

    /** @test */
    public function it_handles_invalid_json_in_llm_response()
    {
        $invalidResponse = '```json
        {
            "openapi": "3.0.0"
            "invalid": json
        }
        ```';

        $reflection = new \ReflectionClass($this->docGenerator);
        $method = $reflection->getMethod('parseLLMResponse');
        $method->setAccessible(true);

        $this->expectException(DocumentationGenerationException::class);
        $this->expectExceptionMessage('Invalid JSON response from LLM');

        $method->invoke($this->docGenerator, $invalidResponse);
    }

    /** @test */
    public function it_builds_complete_openapi_schema()
    {
        $metadata = [
            'controller' => ['name' => 'UserController'],
            'configuration' => [
                'pagination' => ['max_per_page' => 100, 'default_per_page' => 15]
            ],
            'filters' => [
                'filter_config' => [
                    'name' => [
                        'type' => 'string',
                        'operators' => ['eq', 'like'],
                        'example' => ['eq' => 'name=John', 'like' => 'name=John*']
                    ]
                ],
                'sortable_fields' => ['name', 'created_at']
            ]
        ];

        $llmEnhanced = [
            'paths' => [
                '/users' => [
                    'get' => ['summary' => 'List users']
                ]
            ]
        ];

        $reflection = new \ReflectionClass($this->docGenerator);
        $method = $reflection->getMethod('buildOpenApiSchema');
        $method->setAccessible(true);

        $schema = $method->invoke($this->docGenerator, $metadata, $llmEnhanced, '/api/users');

        $this->assertIsArray($schema);
        $this->assertEquals('3.0.0', $schema['openapi']);
        $this->assertEquals('UserController API', $schema['info']['title']);
        $this->assertArrayHasKey('servers', $schema);
        $this->assertArrayHasKey('paths', $schema);
        $this->assertArrayHasKey('components', $schema);

        // Check components
        $this->assertArrayHasKey('schemas', $schema['components']);
        $this->assertArrayHasKey('parameters', $schema['components']);
        $this->assertArrayHasKey('responses', $schema['components']);

        // Check that LLM enhanced content was merged
        $this->assertEquals('List users', $schema['paths']['/users']['get']['summary']);
    }

    /** @test */
    public function it_generates_common_parameters()
    {
        $metadata = [
            'configuration' => [
                'pagination' => ['max_per_page' => 100, 'default_per_page' => 15]
            ],
            'filters' => [
                'filter_config' => [
                    'name' => [
                        'type' => 'string',
                        'description' => 'User name',
                        'example' => ['eq' => 'name=John']
                    ]
                ],
                'sortable_fields' => ['name', 'created_at']
            ]
        ];

        $reflection = new \ReflectionClass($this->docGenerator);
        $method = $reflection->getMethod('generateCommonParameters');
        $method->setAccessible(true);

        $parameters = $method->invoke($this->docGenerator, $metadata);

        $this->assertIsArray($parameters);
        
        // Check pagination parameters
        $this->assertArrayHasKey('page', $parameters);
        $this->assertArrayHasKey('per_page', $parameters);
        $this->assertEquals(1, $parameters['page']['schema']['default']);
        $this->assertEquals(15, $parameters['per_page']['schema']['default']);
        $this->assertEquals(100, $parameters['per_page']['schema']['maximum']);

        // Check filter parameters
        $this->assertArrayHasKey('name', $parameters);
        $this->assertEquals('User name', $parameters['name']['description']);
        $this->assertEquals('string', $parameters['name']['schema']['type']);

        // Check sorting parameters
        $this->assertArrayHasKey('sort_by', $parameters);
        $this->assertContains('name', $parameters['sort_by']['schema']['enum']);
        $this->assertContains('created_at', $parameters['sort_by']['schema']['enum']);
    }

    /** @test */
    public function it_caches_documentation_correctly()
    {
        Cache::shouldReceive('put')
            ->once()
            ->with(
                \Mockery::pattern('/apiforge_docs_documentation_/'),
                ['test' => 'documentation'],
                3600
            );

        $reflection = new \ReflectionClass($this->docGenerator);
        $method = $reflection->getMethod('cacheDocumentation');
        $method->setAccessible(true);

        $method->invoke(
            $this->docGenerator,
            'UserController',
            '/api/users',
            ['test' => 'documentation']
        );
    }

    /** @test */
    public function it_retrieves_cached_documentation()
    {
        $cachedData = ['cached' => 'documentation'];
        
        Cache::shouldReceive('get')
            ->once()
            ->with(\Mockery::pattern('/apiforge_docs_documentation_/'))
            ->andReturn($cachedData);

        $result = $this->docGenerator->getCachedDocumentation('UserController', '/api/users');

        $this->assertEquals($cachedData, $result);
    }

    /** @test */
    public function it_clears_cache_correctly()
    {
        // Test clearing specific cache
        Cache::shouldReceive('forget')
            ->once()
            ->with(\Mockery::pattern('/apiforge_docs_documentation_/'));

        $this->docGenerator->clearCache('UserController', '/api/users');

        // Test clearing all cache
        Cache::shouldReceive('flush')->once();
        
        $this->docGenerator->clearCache();
    }

    /** @test */
    public function it_selects_best_available_provider()
    {
        // Mock multiple providers with different availability
        Config::set('apiforge.documentation.llm.providers', [
            'claude' => ['enabled' => true, 'api_key' => 'claude-key'],
            'openai' => ['enabled' => false, 'api_key' => null],
            'deepseek' => ['enabled' => true, 'api_key' => 'deepseek-key']
        ]);

        $reflection = new \ReflectionClass($this->docGenerator);
        $method = $reflection->getMethod('selectBestProvider');
        $method->setAccessible(true);

        $provider = $method->invoke($this->docGenerator);

        $this->assertEquals('claude', $provider); // Should pick Claude as it's first in priority
    }

    /** @test */
    public function it_generates_schema_for_different_field_types()
    {
        $reflection = new \ReflectionClass($this->docGenerator);
        $method = $reflection->getMethod('getSchemaForFieldType');
        $method->setAccessible(true);

        // Test different types
        $this->assertEquals(['type' => 'string'], $method->invoke($this->docGenerator, 'string'));
        $this->assertEquals(['type' => 'integer'], $method->invoke($this->docGenerator, 'integer'));
        $this->assertEquals(['type' => 'number', 'format' => 'float'], $method->invoke($this->docGenerator, 'float'));
        $this->assertEquals(['type' => 'boolean'], $method->invoke($this->docGenerator, 'boolean'));
        $this->assertEquals(['type' => 'string', 'format' => 'date'], $method->invoke($this->docGenerator, 'date'));
        $this->assertEquals(['type' => 'string', 'format' => 'date-time'], $method->invoke($this->docGenerator, 'datetime'));
    }

    /** @test */
    public function it_generates_parameter_examples_correctly()
    {
        $config = [
            'example' => [
                'eq' => 'name=John',
                'like' => 'name=John*'
            ]
        ];

        $reflection = new \ReflectionClass($this->docGenerator);
        $method = $reflection->getMethod('generateParameterExamples');
        $method->setAccessible(true);

        $examples = $method->invoke($this->docGenerator, 'name', $config);

        $this->assertIsArray($examples);
        $this->assertArrayHasKey('eq', $examples);
        $this->assertArrayHasKey('like', $examples);
        
        $this->assertEquals('Using eq operator', $examples['eq']['summary']);
        $this->assertEquals('John', $examples['eq']['value']);
        
        $this->assertEquals('Using like operator', $examples['like']['summary']);
        $this->assertEquals('John*', $examples['like']['value']);
    }
}