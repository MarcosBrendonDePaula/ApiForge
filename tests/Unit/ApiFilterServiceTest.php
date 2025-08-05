<?php

namespace MarcosBrendon\ApiForge\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use MarcosBrendon\ApiForge\Services\ApiFilterService;
use MarcosBrendon\ApiForge\Tests\TestCase;
use MarcosBrendon\ApiForge\Tests\Fixtures\User;

class ApiFilterServiceTest extends TestCase
{
    protected ApiFilterService $filterService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filterService = new ApiFilterService();
    }

    /** @test */
    public function it_can_configure_filters()
    {
        $config = [
            'name' => ['type' => 'string', 'operator' => 'like'],
            'email' => ['type' => 'string', 'operator' => 'eq'],
        ];

        $result = $this->filterService->configure($config);

        $this->assertInstanceOf(ApiFilterService::class, $result);
        $this->assertEquals(['name', 'email'], $this->filterService->getFilterMetadata()['available_fields']);
    }

    /** @test */
    public function it_can_detect_operators_from_values()
    {
        $this->filterService->configure([
            'age' => ['type' => 'integer', 'operators' => ['gte', 'lte', 'eq']]
        ]);

        $request = Request::create('/test', 'GET', ['age' => '>=18']);
        $query = User::query();

        $this->filterService->applyAdvancedFilters($query, $request);

        // Check that the query was modified
        $sql = $query->toSql();
        $this->assertStringContainsString('>=', $sql);
    }

    /** @test */
    public function it_handles_like_filters_with_wildcards()
    {
        $this->filterService->configure([
            'name' => ['type' => 'string', 'operators' => ['like']]
        ]);

        $request = Request::create('/test', 'GET', ['name' => 'John*']);
        $query = User::query();

        $this->filterService->applyAdvancedFilters($query, $request);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('LIKE', $sql);
        $this->assertEquals('John%', $bindings[0]);
    }

    /** @test */
    public function it_handles_in_filters()
    {
        $this->filterService->configure([
            'status' => ['type' => 'string', 'operators' => ['in']]
        ]);

        $request = Request::create('/test', 'GET', ['status' => 'active,pending']);
        $query = User::query();

        $this->filterService->applyAdvancedFilters($query, $request);

        $sql = $query->toSql();
        $this->assertStringContainsString('in', strtolower($sql));
    }

    /** @test */
    public function it_handles_between_filters()
    {
        $this->filterService->configure([
            'age' => ['type' => 'integer', 'operators' => ['between']]
        ]);

        $request = Request::create('/test', 'GET', ['age' => '18|65']);
        $query = User::query();

        $this->filterService->applyAdvancedFilters($query, $request);

        $sql = $query->toSql();
        $this->assertStringContainsString('between', strtolower($sql));
    }

    /** @test */
    public function it_handles_date_filters()
    {
        $this->filterService->configure([
            'created_at' => ['type' => 'datetime', 'operators' => ['gte']]
        ]);

        $request = Request::create('/test', 'GET', ['created_at' => '>=2024-01-01']);
        $query = User::query();

        $this->filterService->applyAdvancedFilters($query, $request);

        $sql = $query->toSql();
        $this->assertStringContainsString('>=', $sql);
    }

    /** @test */
    public function it_handles_complex_json_filters()
    {
        $this->filterService->configure([
            'name' => ['type' => 'string', 'operators' => ['like']],
            'age' => ['type' => 'integer', 'operators' => ['gte']]
        ]);

        $filters = [
            ['field' => 'name', 'operator' => 'like', 'value' => 'John', 'logic' => 'and'],
            ['field' => 'age', 'operator' => 'gte', 'value' => 18, 'logic' => 'and']
        ];

        $request = Request::create('/test', 'GET', ['filters' => json_encode($filters)]);
        $query = User::query();

        $this->filterService->applyAdvancedFilters($query, $request);

        $sql = $query->toSql();
        $this->assertStringContainsString('LIKE', $sql);
        $this->assertStringContainsString('>=', $sql);
    }

    /** @test */
    public function it_validates_filter_permissions()
    {
        $this->filterService->configure([
            'name' => ['type' => 'string', 'operators' => ['eq']]
        ]);

        $this->assertTrue($this->filterService->isValidFilter('name', 'eq'));
        $this->assertFalse($this->filterService->isValidFilter('email', 'eq'));
        $this->assertFalse($this->filterService->isValidFilter('name', 'invalid_operator'));
    }

    /** @test */
    public function it_casts_values_correctly()
    {
        $this->filterService->configure([
            'active' => ['type' => 'boolean', 'operators' => ['eq']],
            'age' => ['type' => 'integer', 'operators' => ['eq']],
            'price' => ['type' => 'float', 'operators' => ['eq']]
        ]);

        $request = Request::create('/test', 'GET', [
            'active' => 'true',
            'age' => '25',
            'price' => '19.99'
        ]);
        $query = User::query();

        $this->filterService->applyAdvancedFilters($query, $request);

        $bindings = $query->getBindings();
        
        // Check that values are properly cast
        $this->assertContains(true, $bindings); // boolean true
        $this->assertContains(25, $bindings);   // integer 25
        $this->assertContains(19.99, $bindings); // float 19.99
    }

    /** @test */
    public function it_ignores_invalid_dates()
    {
        $this->filterService->configure([
            'created_at' => ['type' => 'datetime', 'operators' => ['gte']]
        ]);

        $request = Request::create('/test', 'GET', ['created_at' => '>=invalid-date']);
        $query = User::query();

        // Should not throw exception and should not add any where clauses for invalid dates
        $this->filterService->applyAdvancedFilters($query, $request);

        // Query should remain unchanged
        $this->assertStringNotContainsString('created_at', $query->toSql());
    }

    /** @test */
    public function it_returns_filter_metadata()
    {
        $config = [
            'name' => ['type' => 'string', 'operators' => ['eq', 'like']],
            'age' => ['type' => 'integer', 'operators' => ['gte', 'lte']]
        ];

        $this->filterService->configure($config);
        $metadata = $this->filterService->getFilterMetadata();

        $this->assertArrayHasKey('available_fields', $metadata);
        $this->assertArrayHasKey('available_operators', $metadata);
        $this->assertArrayHasKey('filter_config', $metadata);

        $this->assertEquals(['name', 'age'], $metadata['available_fields']);
        $this->assertContains('eq', $metadata['available_operators']);
        $this->assertContains('like', $metadata['available_operators']);
    }
}