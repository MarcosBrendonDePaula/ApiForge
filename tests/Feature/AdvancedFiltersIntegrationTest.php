<?php

namespace MarcosBrendon\ApiForge\Tests\Feature;

use Illuminate\Http\Request;
use MarcosBrendon\ApiForge\Tests\TestCase;
use MarcosBrendon\ApiForge\Tests\Fixtures\User;
use MarcosBrendon\ApiForge\Traits\HasAdvancedFilters;
use Illuminate\Database\Eloquent\Model;

class TestController
{
    use HasAdvancedFilters;

    protected function getModelClass(): string
    {
        return User::class;
    }

    protected function setupFilterConfiguration(): void
    {
        $this->configureFilters([
            'name' => [
                'type' => 'string',
                'operators' => ['eq', 'like', 'ne'],
                'searchable' => true,
                'sortable' => true
            ],
            'email' => [
                'type' => 'string',
                'operators' => ['eq', 'like'],
                'searchable' => true
            ],
            'age' => [
                'type' => 'integer',
                'operators' => ['eq', 'gte', 'lte', 'between'],
                'sortable' => true
            ],
            'status' => [
                'type' => 'enum',
                'operators' => ['eq', 'in'],
                'values' => ['active', 'inactive', 'pending']
            ],
            'created_at' => [
                'type' => 'datetime',
                'operators' => ['gte', 'lte', 'between'],
                'sortable' => true
            ]
        ]);

        $this->configureFieldSelection([
            'selectable_fields' => ['id', 'name', 'email', 'age', 'status', 'created_at'],
            'required_fields' => ['id'],
            'blocked_fields' => ['password'],
            'default_fields' => ['id', 'name', 'email'],
            'max_fields' => 10
        ]);
    }
}

class AdvancedFiltersIntegrationTest extends TestCase
{
    protected TestController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new TestController();
    }

    /** @test */
    public function it_handles_basic_filtering()
    {
        $request = Request::create('/test', 'GET', [
            'name' => 'John Doe',
            'status' => 'active'
        ]);

        $response = $this->controller->indexWithFilters($request);

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertArrayHasKey('filters', $data);
    }

    /** @test */
    public function it_handles_advanced_operators()
    {
        $request = Request::create('/test', 'GET', [
            'name' => 'John*',  // LIKE with wildcard
            'age' => '>=18',    // Greater than or equal
            'status' => 'active,pending'  // IN operator
        ]);

        $response = $this->controller->indexWithFilters($request);

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    /** @test */
    public function it_handles_field_selection()
    {
        $request = Request::create('/test', 'GET', [
            'fields' => 'id,name,email',
            'name' => 'John'
        ]);

        $response = $this->controller->indexWithFilters($request);

        $this->assertEquals(200, $response->getStatusCode());
        
        // Field selection would be applied to the actual query
        // In a real test with database, we would verify the selected fields
    }

    /** @test */
    public function it_handles_pagination()
    {
        $request = Request::create('/test', 'GET', [
            'page' => 2,
            'per_page' => 5,
            'sort_by' => 'name',
            'sort_direction' => 'desc'
        ]);

        $response = $this->controller->indexWithFilters($request);

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertEquals(2, $data['pagination']['current_page']);
        $this->assertEquals(5, $data['pagination']['per_page']);
    }

    /** @test */
    public function it_handles_search_functionality()
    {
        $request = Request::create('/test', 'GET', [
            'search' => 'John Doe'
        ]);

        $response = $this->controller->indexWithFilters($request);

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('filters', $data);
        $this->assertArrayHasKey('search', $data['filters']);
        $this->assertEquals('John Doe', $data['filters']['search']);
    }

    /** @test */
    public function it_handles_complex_json_filters()
    {
        $filters = [
            ['field' => 'name', 'operator' => 'like', 'value' => 'John', 'logic' => 'and'],
            ['field' => 'age', 'operator' => 'gte', 'value' => 18, 'logic' => 'and'],
            ['field' => 'status', 'operator' => 'in', 'value' => ['active', 'pending'], 'logic' => 'or']
        ];

        $request = Request::create('/test', 'GET', [
            'filters' => json_encode($filters)
        ]);

        $response = $this->controller->indexWithFilters($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_validates_required_filters()
    {
        // Configure a required filter
        $reflection = new \ReflectionClass($this->controller);
        
        // Initialize services first
        $initMethod = $reflection->getMethod('initializeFilterServices');
        $initMethod->setAccessible(true);
        $initMethod->invoke($this->controller);
        
        $method = $reflection->getMethod('configureFilters');
        $method->setAccessible(true);
        
        $method->invoke($this->controller, [
            'company_id' => [
                'type' => 'integer',
                'operators' => ['eq'],
                'required' => true
            ]
        ]);

        $request = Request::create('/test', 'GET', ['name' => 'John']);

        $response = $this->controller->indexWithFilters($request);

        $this->assertEquals(422, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('FILTER_VALIDATION_ERROR', $data['error']['code']);
    }

    /** @test */
    public function it_handles_invalid_operators()
    {
        config(['apiforge.validation.strict_mode' => true]);

        $request = Request::create('/test', 'GET', [
            'name' => 'John',
            'age' => '>100' // Invalid operator for this test
        ]);

        $response = $this->controller->indexWithFilters($request);

        // Should work because 'gt' is a valid operator, just different syntax
        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_handles_blocked_field_selection()
    {
        $request = Request::create('/test', 'GET', [
            'fields' => 'id,name,password' // password is blocked
        ]);

        $response = $this->controller->indexWithFilters($request);

        $this->assertEquals(200, $response->getStatusCode());
        
        // In strict mode, this might return an error
        // For now, it just ignores blocked fields
    }

    /** @test */
    public function it_handles_too_many_fields()
    {
        $manyFields = array_fill(0, 15, 'name'); // More than max_fields (10)
        $fieldsString = implode(',', $manyFields);

        $request = Request::create('/test', 'GET', [
            'fields' => $fieldsString
        ]);

        $response = $this->controller->indexWithFilters($request);

        // Should either limit fields or return error based on configuration
        $this->assertTrue(in_array($response->getStatusCode(), [200, 422]));
    }

    /** @test */
    public function it_handles_date_range_filters()
    {
        $request = Request::create('/test', 'GET', [
            'created_at' => '2024-01-01|2024-12-31' // Between dates
        ]);

        $response = $this->controller->indexWithFilters($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_handles_invalid_enum_values()
    {
        config(['apiforge.validation.strict_mode' => true]);

        $request = Request::create('/test', 'GET', [
            'status' => 'invalid_status' // Not in allowed enum values
        ]);

        $response = $this->controller->indexWithFilters($request);

        $this->assertEquals(422, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('FILTER_VALIDATION_ERROR', $data['error']['code']);
    }

    /** @test */
    public function it_provides_filter_metadata()
    {
        $response = $this->controller->filterMetadata();

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('enabled_filters', $data['data']);
        $this->assertArrayHasKey('filter_config', $data['data']);
        $this->assertArrayHasKey('available_operators', $data['data']);
    }

    /** @test */
    public function it_provides_filter_examples()
    {
        $response = $this->controller->filterExamples();

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('examples', $data['data']);
        $this->assertArrayHasKey('tips', $data['data']);
    }

    /** @test */
    public function it_handles_cache_when_enabled()
    {
        config(['apiforge.cache.enabled' => true]);

        $request = Request::create('/test', 'GET', [
            'name' => 'John',
            'cache' => true
        ]);

        // First request
        $response1 = $this->controller->indexWithFilters($request);
        $this->assertEquals(200, $response1->getStatusCode());

        // Second request should use cache
        $response2 = $this->controller->indexWithFilters($request);
        $this->assertEquals(200, $response2->getStatusCode());

        // Responses should be identical
        $this->assertEquals($response1->getContent(), $response2->getContent());
    }

    /** @test */
    public function it_handles_relationship_filters()
    {
        $withFilters = [
            'profile' => [
                ['field' => 'verified', 'operator' => 'eq', 'value' => true]
            ]
        ];

        $request = Request::create('/test', 'GET', [
            'with_filters' => json_encode($withFilters)
        ]);

        $response = $this->controller->indexWithFilters($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_handles_malicious_input_safely()
    {
        config(['apiforge.security.sanitize_input' => true]);

        $request = Request::create('/test', 'GET', [
            'name' => '<script>alert("xss")</script>',
            'email' => 'test@example.com; DROP TABLE users;'
        ]);

        $response = $this->controller->indexWithFilters($request);

        // Should not crash and should sanitize input
        $this->assertTrue(in_array($response->getStatusCode(), [200, 422]));
    }

    /** @test */
    public function it_logs_debug_information_when_enabled()
    {
        config(['apiforge.debug.enabled' => true]);

        $request = Request::create('/test', 'GET', [
            'name' => 'John',
            'invalid_filter' => 'value'
        ]);

        // Capture log output
        $logOutput = [];
        \Log::shouldReceive('warning')
            ->andReturnUsing(function($message, $context) use (&$logOutput) {
                $logOutput[] = ['message' => $message, 'context' => $context];
            });
        \Log::shouldReceive('error')
            ->andReturnUsing(function($message, $context) use (&$logOutput) {
                $logOutput[] = ['message' => $message, 'context' => $context];
            });
        \Log::shouldReceive('info')->andReturn(null);
        \Log::shouldReceive('debug')->andReturn(null);

        $response = $this->controller->indexWithFilters($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_respects_pagination_limits()
    {
        $request = Request::create('/test', 'GET', [
            'per_page' => 1000 // Above max limit
        ]);

        $response = $this->controller->indexWithFilters($request);

        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $maxPerPage = config('apiforge.pagination.max_per_page', 100);
        $this->assertLessThanOrEqual($maxPerPage, $data['pagination']['per_page']);
    }

    /** @test */
    public function it_handles_performance_optimization()
    {
        config(['apiforge.performance.query_optimization' => true]);

        $request = Request::create('/test', 'GET', [
            'fields' => 'id,name,profile.avatar,company.name',
            'name' => 'John'
        ]);

        $response = $this->controller->indexWithFilters($request);

        $this->assertEquals(200, $response->getStatusCode());
        
        // Performance optimization should not break functionality
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }
}