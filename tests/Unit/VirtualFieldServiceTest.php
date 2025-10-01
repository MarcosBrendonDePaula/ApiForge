<?php

namespace MarcosBrendon\ApiForge\Tests\Unit;

use MarcosBrendon\ApiForge\Services\VirtualFieldService;
use MarcosBrendon\ApiForge\Support\VirtualFieldDefinition;
use MarcosBrendon\ApiForge\Exceptions\FilterValidationException;
use MarcosBrendon\ApiForge\Tests\TestCase;
use MarcosBrendon\ApiForge\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Collection;

class VirtualFieldServiceTest extends TestCase
{
    protected VirtualFieldService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VirtualFieldService();
    }

    public function test_can_register_virtual_field()
    {
        $config = [
            'type' => 'string',
            'callback' => function ($model) {
                return strtoupper($model->name);
            },
            'dependencies' => ['name'],
            'operators' => ['eq', 'like']
        ];

        $this->service->register('upper_name', $config);

        $this->assertTrue($this->service->isVirtualField('upper_name'));
        $this->assertContains('upper_name', $this->service->getVirtualFields());
    }

    public function test_throws_exception_for_invalid_registration()
    {
        $this->expectException(FilterValidationException::class);

        $config = [
            'type' => 'invalid_type',
            'callback' => function ($model) {
                return $model->name;
            }
        ];

        $this->service->register('invalid_field', $config);
    }

    public function test_can_compute_virtual_field_value()
    {
        $config = [
            'type' => 'string',
            'callback' => function ($model) {
                return strtoupper($model->name);
            },
            'dependencies' => ['name']
        ];

        $this->service->register('upper_name', $config);

        $user = new User(['name' => 'john doe']);
        $result = $this->service->compute('upper_name', $user);

        $this->assertEquals('JOHN DOE', $result);
    }

    public function test_returns_default_value_on_computation_error()
    {
        $config = [
            'type' => 'string',
            'callback' => function ($model) {
                throw new \Exception('Computation error');
            },
            'dependencies' => ['name'],
            'default_value' => 'DEFAULT'
        ];

        $this->service->register('error_field', $config);

        $user = new User(['name' => 'john doe']);
        $result = $this->service->compute('error_field', $user);

        $this->assertEquals('DEFAULT', $result);
    }

    public function test_can_batch_compute_virtual_fields()
    {
        $config = [
            'type' => 'string',
            'callback' => function ($model) {
                return strtoupper($model->name);
            },
            'dependencies' => ['name']
        ];

        $this->service->register('upper_name', $config);

        $users = new Collection([
            new User(['id' => 1, 'name' => 'john doe']),
            new User(['id' => 2, 'name' => 'jane smith'])
        ]);

        $results = $this->service->computeBatch('upper_name', $users);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('upper_name', $results);
        $this->assertCount(2, $results['upper_name']);
    }

    public function test_can_get_field_dependencies()
    {
        $config = [
            'type' => 'string',
            'callback' => function ($model) {
                return $model->name . ' - ' . $model->email;
            },
            'dependencies' => ['name', 'email'],
            'relationships' => ['profile']
        ];

        $this->service->register('full_info', $config);

        $dependencies = $this->service->getDependencies('full_info');

        $this->assertEquals(['name', 'email'], $dependencies['fields']);
        $this->assertEquals(['profile'], $dependencies['relationships']);
        $this->assertEquals(['name', 'email', 'profile'], $dependencies['all']);
    }

    public function test_can_check_operator_support()
    {
        $config = [
            'type' => 'string',
            'callback' => function ($model) {
                return $model->name;
            },
            'operators' => ['eq', 'like', 'in']
        ];

        $this->service->register('test_field', $config);

        $this->assertTrue($this->service->supportsOperator('test_field', 'eq'));
        $this->assertTrue($this->service->supportsOperator('test_field', 'like'));
        $this->assertFalse($this->service->supportsOperator('test_field', 'gt'));
    }

    public function test_can_get_fields_by_type()
    {
        $stringConfig = [
            'type' => 'string',
            'callback' => function ($model) {
                return $model->name;
            }
        ];

        $integerConfig = [
            'type' => 'integer',
            'callback' => function ($model) {
                return strlen($model->name);
            }
        ];

        $this->service->register('string_field', $stringConfig);
        $this->service->register('integer_field', $integerConfig);

        $stringFields = $this->service->getFieldsByType('string');
        $integerFields = $this->service->getFieldsByType('integer');

        $this->assertContains('string_field', $stringFields);
        $this->assertContains('integer_field', $integerFields);
        $this->assertNotContains('integer_field', $stringFields);
    }

    public function test_can_get_sortable_and_searchable_fields()
    {
        $sortableConfig = [
            'type' => 'string',
            'callback' => function ($model) {
                return $model->name;
            },
            'sortable' => true,
            'searchable' => false
        ];

        $searchableConfig = [
            'type' => 'string',
            'callback' => function ($model) {
                return $model->email;
            },
            'sortable' => false,
            'searchable' => true
        ];

        $this->service->register('sortable_field', $sortableConfig);
        $this->service->register('searchable_field', $searchableConfig);

        $sortableFields = $this->service->getSortableFields();
        $searchableFields = $this->service->getSearchableFields();

        $this->assertContains('sortable_field', $sortableFields);
        $this->assertContains('searchable_field', $searchableFields);
        $this->assertNotContains('searchable_field', $sortableFields);
        $this->assertNotContains('sortable_field', $searchableFields);
    }

    public function test_can_register_from_config()
    {
        $config = [
            'field1' => [
                'type' => 'string',
                'callback' => function ($model) {
                    return $model->name;
                }
            ],
            'field2' => [
                'type' => 'integer',
                'callback' => function ($model) {
                    return strlen($model->name);
                }
            ]
        ];

        $this->service->registerFromConfig($config);

        $this->assertTrue($this->service->isVirtualField('field1'));
        $this->assertTrue($this->service->isVirtualField('field2'));
    }

    public function test_can_validate_config()
    {
        $validConfig = [
            'valid_field' => [
                'type' => 'string',
                'callback' => function ($model) {
                    return $model->name;
                }
            ]
        ];

        $invalidConfig = [
            'invalid_field' => [
                'type' => 'string'
                // Missing callback
            ]
        ];

        $validErrors = $this->service->validateConfig($validConfig);
        $invalidErrors = $this->service->validateConfig($invalidConfig);

        $this->assertEmpty($validErrors);
        $this->assertNotEmpty($invalidErrors);
        $this->assertArrayHasKey('invalid_field', $invalidErrors);
    }

    public function test_can_clear_and_remove_fields()
    {
        $config = [
            'type' => 'string',
            'callback' => function ($model) {
                return $model->name;
            }
        ];

        $this->service->register('test_field', $config);
        $this->assertTrue($this->service->isVirtualField('test_field'));

        $removed = $this->service->removeField('test_field');
        $this->assertTrue($removed);
        $this->assertFalse($this->service->isVirtualField('test_field'));

        $this->service->register('test_field', $config);
        $this->service->clearFields();
        $this->assertEmpty($this->service->getVirtualFields());
    }

    public function test_can_get_statistics()
    {
        $config = [
            'type' => 'string',
            'callback' => function ($model) {
                return $model->name;
            },
            'cacheable' => true,
            'sortable' => true,
            'searchable' => true
        ];

        $this->service->register('test_field', $config);

        $stats = $this->service->getStatistics();

        $this->assertArrayHasKey('total_fields', $stats);
        $this->assertArrayHasKey('cacheable_fields', $stats);
        $this->assertArrayHasKey('sortable_fields', $stats);
        $this->assertArrayHasKey('searchable_fields', $stats);
        $this->assertEquals(1, $stats['total_fields']);
    }
}