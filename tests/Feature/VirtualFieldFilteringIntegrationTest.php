<?php

namespace MarcosBrendon\ApiForge\Tests\Feature;

use MarcosBrendon\ApiForge\Services\ApiFilterService;
use MarcosBrendon\ApiForge\Services\VirtualFieldService;
use MarcosBrendon\ApiForge\Services\FilterConfigService;
use MarcosBrendon\ApiForge\Tests\TestCase;
use MarcosBrendon\ApiForge\Tests\Fixtures\User;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Mockery;

class VirtualFieldFilteringIntegrationTest extends TestCase
{
    protected ApiFilterService $apiFilterService;
    protected VirtualFieldService $virtualFieldService;
    protected FilterConfigService $filterConfigService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->virtualFieldService = new VirtualFieldService();
        $this->apiFilterService = new ApiFilterService();
        $this->filterConfigService = new FilterConfigService();
        
        // Connect services
        $this->apiFilterService->setVirtualFieldService($this->virtualFieldService);
        $this->filterConfigService->setVirtualFieldService($this->virtualFieldService);
        
        // Register test virtual fields
        $this->registerTestVirtualFields();
    }

    protected function registerTestVirtualFields(): void
    {
        $virtualFieldsConfig = [
            'full_name' => [
                'type' => 'string',
                'callback' => function ($model) {
                    return $model->first_name . ' ' . $model->last_name;
                },
                'dependencies' => ['first_name', 'last_name'],
                'operators' => ['eq', 'like', 'starts_with', 'ends_with'],
                'searchable' => true,
                'sortable' => true,
                'description' => 'Full name combining first and last name'
            ],
            'name_length' => [
                'type' => 'integer',
                'callback' => function ($model) {
                    return strlen($model->first_name . ' ' . $model->last_name);
                },
                'dependencies' => ['first_name', 'last_name'],
                'operators' => ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'between'],
                'searchable' => false,
                'sortable' => true,
                'description' => 'Length of the full name'
            ],
            'email_domain' => [
                'type' => 'string',
                'callback' => function ($model) {
                    return substr(strrchr($model->email, '@'), 1);
                },
                'dependencies' => ['email'],
                'operators' => ['eq', 'ne', 'like', 'in', 'not_in'],
                'searchable' => true,
                'sortable' => true,
                'description' => 'Domain part of email address'
            ],
            'is_admin' => [
                'type' => 'boolean',
                'callback' => function ($model) {
                    return $model->role === 'admin';
                },
                'dependencies' => ['role'],
                'operators' => ['eq', 'ne'],
                'searchable' => false,
                'sortable' => false,
                'description' => 'Whether user is an admin'
            ]
        ];

        $this->filterConfigService->configureVirtualFields($virtualFieldsConfig);
    }

    public function test_can_filter_by_virtual_field_with_eq_operator()
    {
        $request = new Request([
            'full_name' => 'John Doe'
        ]);

        $filterConfig = [
            'full_name' => [
                'type' => 'string',
                'operators' => ['eq', 'like'],
                'virtual' => true
            ]
        ];

        $this->apiFilterService->configure($filterConfig);

        $query = Mockery::mock(Builder::class);
        $queryBuilder = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        
        $query->shouldReceive('getQuery')->andReturn($queryBuilder);
        $query->shouldReceive('getBindings')->andReturn([]);
        $queryBuilder->shouldReceive('columns')->andReturn(null);
        $query->shouldReceive('addSelect')->with(['first_name', 'last_name'])->once();
        $query->shouldReceive('getEagerLoads')->andReturn([]);
        $query->shouldReceive('where')->once();

        $result = $this->apiFilterService->applyAdvancedFilters($query, $request);

        $this->assertSame($query, $result);
    }

    public function test_can_filter_by_virtual_field_with_complex_filters()
    {
        $complexFilters = [
            [
                'field' => 'name_length',
                'operator' => 'gt',
                'value' => 10,
                'logic' => 'and'
            ],
            [
                'field' => 'email_domain',
                'operator' => 'eq',
                'value' => 'example.com',
                'logic' => 'and'
            ]
        ];

        $request = new Request([
            'filters' => json_encode($complexFilters)
        ]);

        $filterConfig = [
            'name_length' => [
                'type' => 'integer',
                'operators' => ['gt', 'lt', 'eq'],
                'virtual' => true
            ],
            'email_domain' => [
                'type' => 'string',
                'operators' => ['eq', 'like'],
                'virtual' => true
            ]
        ];

        $this->apiFilterService->configure($filterConfig);

        $query = Mockery::mock(Builder::class);
        $queryBuilder = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        
        $query->shouldReceive('getQuery')->andReturn($queryBuilder);
        $query->shouldReceive('getBindings')->andReturn([]);
        $queryBuilder->shouldReceive('columns')->andReturn(null);
        $query->shouldReceive('addSelect')->with(['first_name', 'last_name', 'email'])->once();
        $query->shouldReceive('getEagerLoads')->andReturn([]);
        $query->shouldReceive('where')->twice();

        $result = $this->apiFilterService->applyAdvancedFilters($query, $request);

        $this->assertSame($query, $result);
    }

    public function test_virtual_field_metadata_included_in_filter_metadata()
    {
        $filterConfig = [
            'regular_field' => [
                'type' => 'string',
                'operators' => ['eq', 'like']
            ],
            'full_name' => [
                'type' => 'string',
                'operators' => ['eq', 'like'],
                'virtual' => true
            ]
        ];

        $this->apiFilterService->configure($filterConfig);

        $metadata = $this->apiFilterService->getFilterMetadata();

        $this->assertArrayHasKey('virtual_fields', $metadata);
        $this->assertArrayHasKey('virtual_field_config', $metadata);
        $this->assertContains('full_name', $metadata['virtual_fields']);
        $this->assertArrayHasKey('full_name', $metadata['virtual_field_config']);
    }

    public function test_filter_config_service_handles_virtual_fields()
    {
        $metadata = $this->filterConfigService->getCompleteMetadata();

        $this->assertArrayHasKey('virtual_field_config', $metadata);
        $this->assertArrayHasKey('virtual_fields', $metadata);
        $this->assertArrayHasKey('virtual_field_service', $metadata);

        $virtualFields = $metadata['virtual_fields'];
        $this->assertArrayHasKey('searchable', $virtualFields);
        $this->assertArrayHasKey('sortable', $virtualFields);
        $this->assertArrayHasKey('total_count', $virtualFields);
        $this->assertArrayHasKey('by_type', $virtualFields);

        $this->assertEquals(4, $virtualFields['total_count']);
        $this->assertContains('full_name', $virtualFields['searchable']);
        $this->assertContains('name_length', $virtualFields['sortable']);
    }

    public function test_can_validate_virtual_field_filters()
    {
        // Test valid filter
        [$valid, $errors] = $this->filterConfigService->validateVirtualFieldFilter(
            'full_name',
            'eq',
            'John Doe'
        );

        $this->assertTrue($valid);
        $this->assertEmpty($errors);

        // Test invalid operator
        [$valid, $errors] = $this->filterConfigService->validateVirtualFieldFilter(
            'full_name',
            'gt',
            'John Doe'
        );

        $this->assertFalse($valid);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('not supported', $errors[0]);

        // Test non-existent field
        [$valid, $errors] = $this->filterConfigService->validateVirtualFieldFilter(
            'non_existent_field',
            'eq',
            'value'
        );

        $this->assertFalse($valid);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('not configured', $errors[0]);
    }

    public function test_virtual_field_dependencies_are_resolved()
    {
        $dependencies = $this->filterConfigService->getVirtualFieldDependencies([
            'full_name',
            'email_domain'
        ]);

        $this->assertArrayHasKey('fields', $dependencies);
        $this->assertArrayHasKey('relationships', $dependencies);

        $expectedFields = ['first_name', 'last_name', 'email'];
        foreach ($expectedFields as $field) {
            $this->assertContains($field, $dependencies['fields']);
        }
    }

    public function test_virtual_field_selection_is_supported()
    {
        $this->assertTrue($this->filterConfigService->isFieldSelectable('full_name'));
        $this->assertTrue($this->filterConfigService->isFieldSelectable('email_domain'));
        $this->assertFalse($this->filterConfigService->isFieldSelectable('non_existent_virtual_field'));
    }

    public function test_can_get_virtual_field_configuration()
    {
        $config = $this->filterConfigService->getVirtualFieldConfig('full_name');

        $this->assertNotNull($config);
        $this->assertEquals('string', $config['type']);
        $this->assertTrue($config['virtual']);
        $this->assertTrue($config['searchable']);
        $this->assertTrue($config['sortable']);
        $this->assertContains('eq', $config['operators']);
        $this->assertContains('like', $config['operators']);
    }

    public function test_can_get_all_virtual_fields()
    {
        $virtualFields = $this->filterConfigService->getVirtualFields();

        $this->assertCount(4, $virtualFields);
        $this->assertArrayHasKey('full_name', $virtualFields);
        $this->assertArrayHasKey('name_length', $virtualFields);
        $this->assertArrayHasKey('email_domain', $virtualFields);
        $this->assertArrayHasKey('is_admin', $virtualFields);

        foreach ($virtualFields as $field => $config) {
            $this->assertTrue($config['virtual']);
        }
    }

    public function test_can_get_searchable_and_sortable_virtual_fields()
    {
        $searchableVirtualFields = $this->filterConfigService->getSearchableVirtualFields();
        $sortableVirtualFields = $this->filterConfigService->getSortableVirtualFields();

        $this->assertContains('full_name', $searchableVirtualFields);
        $this->assertContains('email_domain', $searchableVirtualFields);
        $this->assertNotContains('name_length', $searchableVirtualFields);
        $this->assertNotContains('is_admin', $searchableVirtualFields);

        $this->assertContains('full_name', $sortableVirtualFields);
        $this->assertContains('name_length', $sortableVirtualFields);
        $this->assertContains('email_domain', $sortableVirtualFields);
        $this->assertNotContains('is_admin', $sortableVirtualFields);
    }

    public function test_virtual_field_type_validation()
    {
        // Test string validation
        $result = $this->invokeMethod(
            $this->filterConfigService,
            'validateValueForType',
            ['John Doe', 'string', []]
        );
        $this->assertTrue($result[0]);

        // Test integer validation
        $result = $this->invokeMethod(
            $this->filterConfigService,
            'validateValueForType',
            [25, 'integer', []]
        );
        $this->assertTrue($result[0]);

        // Test invalid integer
        $result = $this->invokeMethod(
            $this->filterConfigService,
            'validateValueForType',
            ['not_a_number', 'integer', []]
        );
        $this->assertFalse($result[0]);

        // Test boolean validation
        $result = $this->invokeMethod(
            $this->filterConfigService,
            'validateValueForType',
            ['true', 'boolean', []]
        );
        $this->assertTrue($result[0]);

        // Test enum validation
        $result = $this->invokeMethod(
            $this->filterConfigService,
            'validateValueForType',
            ['admin', 'enum', ['values' => ['admin', 'user', 'guest']]]
        );
        $this->assertTrue($result[0]);

        // Test invalid enum value
        $result = $this->invokeMethod(
            $this->filterConfigService,
            'validateValueForType',
            ['invalid_role', 'enum', ['values' => ['admin', 'user', 'guest']]]
        );
        $this->assertFalse($result[0]);
    }

    public function test_virtual_field_operators_are_correctly_filtered()
    {
        // String field should support string operators
        $stringConfig = $this->filterConfigService->getVirtualFieldConfig('full_name');
        $this->assertContains('eq', $stringConfig['operators']);
        $this->assertContains('like', $stringConfig['operators']);
        $this->assertNotContains('gt', $stringConfig['operators']); // Not supported for strings

        // Integer field should support numeric operators
        $integerConfig = $this->filterConfigService->getVirtualFieldConfig('name_length');
        $this->assertContains('eq', $integerConfig['operators']);
        $this->assertContains('gt', $integerConfig['operators']);
        $this->assertContains('between', $integerConfig['operators']);
        $this->assertNotContains('like', $integerConfig['operators']); // Not supported for integers

        // Boolean field should only support eq/ne
        $booleanConfig = $this->filterConfigService->getVirtualFieldConfig('is_admin');
        $this->assertContains('eq', $booleanConfig['operators']);
        $this->assertContains('ne', $booleanConfig['operators']);
        $this->assertNotContains('gt', $booleanConfig['operators']);
        $this->assertNotContains('like', $booleanConfig['operators']);
    }

    public function test_usage_guide_includes_virtual_fields()
    {
        $metadata = $this->filterConfigService->getCompleteMetadata();
        
        $this->assertArrayHasKey('usage_guide', $metadata);
        $this->assertArrayHasKey('virtual_fields', $metadata['usage_guide']);
        $this->assertStringContainsString('computados dinamicamente', $metadata['usage_guide']['virtual_fields']);
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