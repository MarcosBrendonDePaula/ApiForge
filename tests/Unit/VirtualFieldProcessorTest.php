<?php

namespace MarcosBrendon\ApiForge\Tests\Unit;

use MarcosBrendon\ApiForge\Support\VirtualFieldProcessor;
use MarcosBrendon\ApiForge\Support\VirtualFieldRegistry;
use MarcosBrendon\ApiForge\Support\VirtualFieldDefinition;
use MarcosBrendon\ApiForge\Exceptions\FilterValidationException;
use MarcosBrendon\ApiForge\Tests\TestCase;
use MarcosBrendon\ApiForge\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Mockery;

class VirtualFieldProcessorTest extends TestCase
{
    protected VirtualFieldProcessor $processor;
    protected VirtualFieldRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new VirtualFieldRegistry();
        $this->processor = new VirtualFieldProcessor($this->registry);
    }

    public function test_can_process_virtual_fields_for_selection()
    {
        $definition = new VirtualFieldDefinition(
            'upper_name',
            'string',
            function ($model) {
                return strtoupper($model->name);
            },
            ['name']
        );

        $this->registry->add('upper_name', $definition);

        $users = new Collection([
            new User(['name' => 'john doe']),
            new User(['name' => 'jane smith'])
        ]);

        $result = $this->processor->processForSelection($users, ['upper_name']);

        $this->assertEquals('JOHN DOE', $result->first()->upper_name);
        $this->assertEquals('JANE SMITH', $result->last()->upper_name);
    }

    public function test_returns_empty_collection_when_no_models()
    {
        $users = new Collection();
        $result = $this->processor->processForSelection($users, ['upper_name']);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_skips_undefined_virtual_fields()
    {
        $users = new Collection([
            new User(['name' => 'john doe'])
        ]);

        $result = $this->processor->processForSelection($users, ['undefined_field']);

        $this->assertEquals(1, $result->count());
        $this->assertFalse(isset($result->first()->undefined_field));
    }

    public function test_can_batch_compute_virtual_fields()
    {
        $definition = new VirtualFieldDefinition(
            'name_length',
            'integer',
            function ($model) {
                return strlen($model->name);
            },
            ['name']
        );

        $this->registry->add('name_length', $definition);

        $users = new Collection([
            new User(['id' => 1, 'name' => 'john']),
            new User(['id' => 2, 'name' => 'jane smith'])
        ]);

        $results = $this->processor->computeBatch(['name_length'], $users);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('name_length', $results);
        $this->assertCount(2, $results['name_length']);
    }

    public function test_can_optimize_query_for_virtual_fields()
    {
        $definition = new VirtualFieldDefinition(
            'full_info',
            'string',
            function ($model) {
                return $model->name . ' - ' . $model->email;
            },
            ['name', 'email'],
            ['profile']
        );

        $this->registry->add('full_info', $definition);

        $query = Mockery::mock(Builder::class);
        $queryBuilder = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        
        $query->shouldReceive('getQuery')->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('columns')->andReturn(null);
        $query->shouldReceive('addSelect')->with(['name', 'email'])->once();
        $query->shouldReceive('getEagerLoads')->andReturn([]);
        $query->shouldReceive('with')->with(['profile'])->once();

        $result = $this->processor->optimizeQuery($query, ['full_info']);

        $this->assertSame($query, $result);
    }

    public function test_can_optimize_query_for_filtering()
    {
        $definition = new VirtualFieldDefinition(
            'test_field',
            'string',
            function ($model) {
                return $model->name;
            },
            ['name'],
            ['profile']
        );

        $this->registry->add('test_field', $definition);

        $virtualFieldFilters = [
            [
                'field' => 'test_field',
                'operator' => 'eq',
                'value' => 'test'
            ]
        ];

        $query = Mockery::mock(Builder::class);
        $queryBuilder = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        
        $query->shouldReceive('getQuery')->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('columns')->andReturn(null);
        $query->shouldReceive('addSelect')->with(['name'])->once();
        $query->shouldReceive('getEagerLoads')->andReturn([]);
        $query->shouldReceive('with')->with(['profile'])->once();

        $result = $this->processor->optimizeForFiltering($query, $virtualFieldFilters);

        $this->assertSame($query, $result);
    }

    public function test_can_process_virtual_field_filters()
    {
        $definition = new VirtualFieldDefinition(
            'name_length',
            'integer',
            function ($model) {
                return strlen($model->name);
            },
            ['name']
        );

        $this->registry->add('name_length', $definition);

        $users = new Collection([
            new User(['id' => 1, 'name' => 'john']),      // length: 4
            new User(['id' => 2, 'name' => 'jane smith']), // length: 10
            new User(['id' => 3, 'name' => 'bob'])         // length: 3
        ]);

        $virtualFieldFilters = [
            [
                'field' => 'name_length',
                'operator' => 'gt',
                'value' => 4,
                'logic' => 'and'
            ]
        ];

        $result = $this->processor->processVirtualFieldFilters($users, $virtualFieldFilters);

        $this->assertEquals(1, $result->count());
        $this->assertEquals('jane smith', $result->first()->name);
    }

    public function test_evaluates_different_operators_correctly()
    {
        $testCases = [
            ['eq', 5, 5, true],
            ['eq', 5, 4, false],
            ['ne', 5, 4, true],
            ['ne', 5, 5, false],
            ['gt', 5, 4, true],
            ['gt', 5, 6, false],
            ['gte', 5, 5, true],
            ['gte', 5, 4, true],
            ['gte', 5, 6, false],
            ['lt', 5, 6, true],
            ['lt', 5, 4, false],
            ['lte', 5, 5, true],
            ['lte', 5, 6, true],
            ['lte', 5, 4, false],
            ['in', 5, [3, 5, 7], true],
            ['in', 5, [3, 4, 7], false],
            ['not_in', 5, [3, 4, 7], true],
            ['not_in', 5, [3, 5, 7], false],
            ['null', null, null, true],
            ['null', 5, null, false],
            ['not_null', 5, null, true],
            ['not_null', null, null, false],
            ['like', 'john doe', 'john*', true],
            ['like', 'john doe', 'jane*', false],
            ['not_like', 'john doe', 'jane*', true],
            ['not_like', 'john doe', 'john*', false],
            ['starts_with', 'john doe', 'john', true],
            ['starts_with', 'john doe', 'doe', false],
            ['ends_with', 'john doe', 'doe', true],
            ['ends_with', 'john doe', 'john', false],
            ['between', 5, [3, 7], true],
            ['between', 5, [6, 8], false],
            ['not_between', 5, [6, 8], true],
            ['not_between', 5, [3, 7], false],
        ];

        foreach ($testCases as [$operator, $computedValue, $filterValue, $expected]) {
            $result = $this->invokeMethod(
                $this->processor,
                'evaluateVirtualFieldFilter',
                [$computedValue, $operator, $filterValue]
            );

            $this->assertEquals(
                $expected,
                $result,
                "Failed for operator '{$operator}' with computed value '{$computedValue}' and filter value '" . json_encode($filterValue) . "'"
            );
        }
    }

    public function test_handles_complex_filter_logic()
    {
        $definition = new VirtualFieldDefinition(
            'name_length',
            'integer',
            function ($model) {
                return strlen($model->name);
            },
            ['name']
        );

        $this->registry->add('name_length', $definition);

        $users = new Collection([
            new User(['id' => 1, 'name' => 'john']),      // length: 4
            new User(['id' => 2, 'name' => 'jane smith']), // length: 10
            new User(['id' => 3, 'name' => 'bob'])         // length: 3
        ]);

        // Test OR logic - should return records with length < 4 OR length > 8
        $virtualFieldFilters = [
            [
                'field' => 'name_length',
                'operator' => 'lt',
                'value' => 4,
                'logic' => 'or'
            ],
            [
                'field' => 'name_length',
                'operator' => 'gt',
                'value' => 8,
                'logic' => 'or'
            ]
        ];

        $result = $this->processor->processVirtualFieldFilters($users, $virtualFieldFilters);

        $this->assertEquals(2, $result->count());
        $names = $result->pluck('name')->toArray();
        $this->assertContains('jane smith', $names); // length 10 > 8
        $this->assertContains('bob', $names);        // length 3 < 4
        $this->assertNotContains('john', $names);    // length 4 doesn't match either condition
    }

    /**
     * Helper method to invoke private/protected methods for testing
     */
    protected function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}