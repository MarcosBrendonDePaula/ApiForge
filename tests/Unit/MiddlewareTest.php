<?php

namespace MarcosBrendon\ApiForge\Tests\Unit;

use Illuminate\Http\Request;
use MarcosBrendon\ApiForge\Http\Middleware\ApiPaginationMiddleware;
use MarcosBrendon\ApiForge\Tests\TestCase;

class MiddlewareTest extends TestCase
{
    protected ApiPaginationMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new ApiPaginationMiddleware();
    }

    /** @test */
    public function it_sanitizes_pagination_parameters()
    {
        $request = Request::create('/test', 'GET', [
            'page' => '-1',
            'per_page' => '1000',
            'sort_direction' => 'invalid'
        ]);

        $this->middleware->handle($request, function ($req) {
            return $req;
        });

        $this->assertEquals(1, $request->get('page'));
        $this->assertEquals(100, $request->get('per_page')); // Max limit
        $this->assertEquals('asc', $request->get('sort_direction'));
    }

    /** @test */
    public function it_validates_date_filters()
    {
        $request = Request::create('/test', 'GET', [
            'date_from' => 'invalid-date',
            'date_to' => '2024-01-01',
            'period' => 'invalid_period'
        ]);

        $this->middleware->handle($request, function ($req) {
            return $req;
        });

        $this->assertNull($request->get('date_from')); // Invalid date removed
        $this->assertEquals('2024-01-01', $request->get('date_to')); // Valid date kept
        $this->assertNull($request->get('period')); // Invalid period removed
    }

    /** @test */
    public function it_validates_json_filters()
    {
        $validFilters = [
            ['field' => 'name', 'operator' => 'eq', 'value' => 'John'],
            ['field' => 'age', 'operator' => 'gte', 'value' => 18]
        ];

        $invalidFilters = [
            ['field' => 'name'], // Missing value
            ['operator' => 'eq', 'value' => 'test'], // Missing field
            ['field' => 'status', 'operator' => 'invalid_op', 'value' => 'active'] // Invalid operator
        ];

        $request = Request::create('/test', 'GET', [
            'filters' => json_encode(array_merge($validFilters, $invalidFilters))
        ]);

        $this->middleware->handle($request, function ($req) {
            return $req;
        });

        $processedFilters = $request->get('filters');
        
        $this->assertCount(2, $processedFilters); // Only valid filters remain
        $this->assertEquals('name', $processedFilters[0]['field']);
        $this->assertEquals('age', $processedFilters[1]['field']);
    }

    /** @test */
    public function it_removes_invalid_json_filters()
    {
        $request = Request::create('/test', 'GET', [
            'filters' => 'invalid-json'
        ]);

        $this->middleware->handle($request, function ($req) {
            return $req;
        });

        $this->assertNull($request->get('filters'));
    }

    /** @test */
    public function it_validates_field_selection()
    {
        $request = Request::create('/test', 'GET', [
            'fields' => 'id,name,<script>alert(1)</script>,company.name'
        ]);

        $this->middleware->handle($request, function ($req) {
            return $req;
        });

        // Malicious content should be removed/sanitized
        $fields = $request->get('fields');
        $this->assertIsString($fields);
        $this->assertStringNotContainsString('<script>', $fields);
    }

    /** @test */
    public function it_validates_field_count_limits()
    {
        config(['apiforge.field_selection.max_fields' => 3]);

        $manyFields = array_fill(0, 10, 'field');
        $request = Request::create('/test', 'GET', [
            'fields' => implode(',', $manyFields)
        ]);

        $this->middleware->handle($request, function ($req) {
            return $req;
        });

        // Should remove fields parameter due to too many fields
        $this->assertNull($request->get('fields'));
    }

    /** @test */
    public function it_validates_relationship_depth()
    {
        config(['apiforge.field_selection.max_relationship_depth' => 2]);

        $request = Request::create('/test', 'GET', [
            'fields' => 'id,name,company.address.country.name' // 3 levels deep
        ]);

        $this->middleware->handle($request, function ($req) {
            return $req;
        });

        // Should remove fields due to excessive depth
        $this->assertNull($request->get('fields'));
    }

    /** @test */
    public function it_sanitizes_filter_values()
    {
        config(['apiforge.security.sanitize_input' => true]);

        $request = Request::create('/test', 'GET', [
            'name' => '<script>alert("xss")</script>',
            'description' => 'Normal text'
        ]);

        $this->middleware->handle($request, function ($req) {
            return $req;
        });

        $this->assertStringNotContainsString('<script>', $request->get('name'));
        $this->assertEquals('Normal text', $request->get('description'));
    }

    /** @test */
    public function it_removes_disallowed_filters()
    {
        $filterConfig = [
            'name' => ['type' => 'string'],
            'email' => ['type' => 'string']
        ];

        $request = Request::create('/test', 'GET', [
            'name' => 'John',
            'email' => 'john@example.com',
            'password' => 'secret', // Not in allowed filters
            'admin_flag' => 'true'   // Not in allowed filters
        ]);

        $request->attributes->set('filter_config', $filterConfig);

        $this->middleware->handle($request, function ($req) {
            return $req;
        });

        $this->assertEquals('John', $request->get('name'));
        $this->assertEquals('john@example.com', $request->get('email'));
        $this->assertNull($request->get('password'));
        $this->assertNull($request->get('admin_flag'));

        // Check that invalid filters were logged
        $invalidFilters = $request->attributes->get('invalid_filters', []);
        $this->assertContains('password', $invalidFilters);
        $this->assertContains('admin_flag', $invalidFilters);
    }

    /** @test */
    public function it_sets_pagination_headers()
    {
        $request = Request::create('/test', 'GET');

        $this->middleware->handle($request, function ($req) {
            return $req;
        });

        $this->assertEquals('true', $request->header('X-Pagination-Enabled'));
        $this->assertEquals('100', $request->header('X-Max-Per-Page'));
    }

    /** @test */
    public function it_generates_cache_key_when_requested()
    {
        $request = Request::create('/test', 'GET', [
            'cache' => 'true',
            'name' => 'John'
        ]);

        $this->middleware->handle($request, function ($req) {
            return $req;
        });

        $cacheKey = $request->attributes->get('cache_key');
        $this->assertIsString($cacheKey);
        $this->assertStringStartsWith(config('apiforge.cache.key_prefix', 'api_filters_'), $cacheKey);
    }

    /** @test */
    public function it_handles_search_parameter_sanitization()
    {
        $request = Request::create('/test', 'GET', [
            'search' => '  <script>malicious</script>  '
        ]);

        $this->middleware->handle($request, function ($req) {
            return $req;
        });

        $search = $request->get('search');
        $this->assertStringNotContainsString('<script>', $search);
        $this->assertEquals(trim($search), $search); // Should be trimmed
    }

    /** @test */
    public function it_validates_period_filters()
    {
        $validPeriods = ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month', 'this_year'];

        foreach ($validPeriods as $period) {
            $request = Request::create('/test', 'GET', ['period' => $period]);
            
            $this->middleware->handle($request, function ($req) {
                return $req;
            });
            
            $this->assertEquals($period, $request->get('period'));
        }

        // Test invalid period
        $request = Request::create('/test', 'GET', ['period' => 'invalid_period']);
        
        $this->middleware->handle($request, function ($req) {
            return $req;
        });
        
        $this->assertNull($request->get('period'));
    }

    /** @test */
    public function it_handles_complex_field_validation_patterns()
    {
        $testCases = [
            'valid.field' => true,
            'field_name' => true,
            'field123' => true,
            '.invalid' => false,
            'invalid.' => false,
            'field..name' => false,
            'field-name' => false,
            '123field' => false,
        ];

        foreach ($testCases as $fieldName => $shouldBeValid) {
            $request = Request::create('/test', 'GET', [
                'fields' => $fieldName
            ]);

            $this->middleware->handle($request, function ($req) {
                return $req;
            });

            if ($shouldBeValid) {
                $this->assertEquals($fieldName, $request->get('fields'), "Field '{$fieldName}' should be valid");
            } else {
                $this->assertNull($request->get('fields'), "Field '{$fieldName}' should be invalid");
            }
        }
    }

    /** @test */
    public function it_tracks_validation_errors()
    {
        $request = Request::create('/test', 'GET', [
            'fields' => 'invalid..field'
        ]);

        $this->middleware->handle($request, function ($req) {
            return $req;
        });

        $errors = $request->attributes->get('validation_errors', []);
        $this->assertArrayHasKey('fields', $errors);
        $this->assertIsArray($errors['fields']);
        $this->assertNotEmpty($errors['fields']);
    }
}