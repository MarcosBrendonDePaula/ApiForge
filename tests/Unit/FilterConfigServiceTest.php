<?php

namespace MarcosBrendon\ApiForge\Tests\Unit;

use Illuminate\Http\Request;
use MarcosBrendon\ApiForge\Services\FilterConfigService;
use MarcosBrendon\ApiForge\Tests\TestCase;

class FilterConfigServiceTest extends TestCase
{
    protected FilterConfigService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FilterConfigService();
    }

    /** @test */
    public function it_configures_filters_correctly()
    {
        $config = [
            'name' => [
                'type' => 'string',
                'operators' => ['eq', 'like'],
                'searchable' => true,
                'sortable' => true
            ],
            'age' => [
                'type' => 'integer',
                'operators' => ['gte', 'lte']
            ]
        ];

        $this->service->configure($config);
        $metadata = $this->service->getFilterMetadata();

        $this->assertArrayHasKey('name', $metadata);
        $this->assertArrayHasKey('age', $metadata);
        $this->assertEquals('string', $metadata['name']['type']);
        $this->assertEquals(['eq', 'like'], $metadata['name']['operators']);
        $this->assertTrue($metadata['name']['searchable']);
    }

    /** @test */
    public function it_validates_operators_for_field_types()
    {
        $config = [
            'name' => [
                'type' => 'string',
                'operators' => ['eq', 'like', 'gte'] // gte not valid for string
            ]
        ];

        $this->service->configure($config);
        $metadata = $this->service->getFilterMetadata();

        // Should only include valid operators for string type
        $this->assertNotContains('gte', $metadata['name']['operators']);
        $this->assertContains('eq', $metadata['name']['operators']);
        $this->assertContains('like', $metadata['name']['operators']);
    }

    /** @test */
    public function it_generates_automatic_examples()
    {
        $config = [
            'name' => [
                'type' => 'string',
                'operators' => ['eq', 'like']
            ],
            'age' => [
                'type' => 'integer',
                'operators' => ['gte', 'lte']
            ]
        ];

        $this->service->configure($config);
        $metadata = $this->service->getFilterMetadata();

        $this->assertArrayHasKey('example', $metadata['name']);
        $this->assertArrayHasKey('eq', $metadata['name']['example']);
        $this->assertArrayHasKey('like', $metadata['name']['example']);
        
        $this->assertStringContainsString('name=', $metadata['name']['example']['eq']);
        $this->assertStringContainsString('age=>=', $metadata['age']['example']['gte']);
    }

    /** @test */
    public function it_validates_required_filters()
    {
        $config = [
            'company_id' => [
                'type' => 'integer',
                'operators' => ['eq'],
                'required' => true
            ],
            'name' => [
                'type' => 'string',
                'operators' => ['eq'],
                'required' => false
            ]
        ];

        $this->service->configure($config);

        $request = Request::create('/test', 'GET', ['name' => 'John']);
        $missing = $this->service->validateRequiredFilters($request);

        $this->assertContains('company_id', $missing);
        $this->assertNotContains('name', $missing);
    }

    /** @test */
    public function it_returns_searchable_fields()
    {
        $config = [
            'name' => [
                'type' => 'string',
                'operators' => ['eq'],
                'searchable' => true
            ],
            'email' => [
                'type' => 'string',
                'operators' => ['eq'],
                'searchable' => true
            ],
            'age' => [
                'type' => 'integer',
                'operators' => ['eq'],
                'searchable' => false
            ]
        ];

        $this->service->configure($config);
        $searchable = $this->service->getSearchableFields();

        $this->assertContains('name', $searchable);
        $this->assertContains('email', $searchable);
        $this->assertNotContains('age', $searchable);
    }

    /** @test */
    public function it_returns_sortable_fields()
    {
        $config = [
            'name' => [
                'type' => 'string',
                'operators' => ['eq'],
                'sortable' => true
            ],
            'created_at' => [
                'type' => 'datetime',
                'operators' => ['eq'],
                'sortable' => true
            ],
            'description' => [
                'type' => 'string',
                'operators' => ['eq'],
                'sortable' => false
            ]
        ];

        $this->service->configure($config);
        $sortable = $this->service->getSortableFields();

        $this->assertContains('name', $sortable);
        $this->assertContains('created_at', $sortable);
        $this->assertNotContains('description', $sortable);
    }

    /** @test */
    public function it_configures_field_selection()
    {
        $config = [
            'selectable_fields' => ['id', 'name', 'email'],
            'blocked_fields' => ['password'],
            'required_fields' => ['id'],
            'max_fields' => 5
        ];

        $this->service->configureFieldSelection($config);
        $fieldConfig = $this->service->getFieldSelectionConfig();

        $this->assertEquals(['id', 'name', 'email'], $fieldConfig['selectable_fields']);
        $this->assertEquals(['password'], $fieldConfig['blocked_fields']);
        $this->assertEquals(['id'], $fieldConfig['required_fields']);
        $this->assertEquals(5, $fieldConfig['max_fields']);
    }

    /** @test */
    public function it_validates_field_selection()
    {
        $this->service->configureFieldSelection([
            'selectable_fields' => ['id', 'name', 'email'],
            'blocked_fields' => ['password']
        ]);

        $fields = ['id', 'name', 'password', 'invalid_field'];
        [$valid, $invalid] = $this->service->validateFieldSelection($fields);

        $this->assertContains('id', $valid);
        $this->assertContains('name', $valid);
        $this->assertNotContains('password', $valid);
        $this->assertContains('password', $invalid);
        $this->assertContains('invalid_field', $invalid);
    }

    /** @test */
    public function it_resolves_field_aliases()
    {
        $this->service->configureFieldSelection([
            'field_aliases' => [
                'user_id' => 'id',
                'user_name' => 'name'
            ]
        ]);

        $this->assertEquals('id', $this->service->resolveFieldAlias('user_id'));
        $this->assertEquals('name', $this->service->resolveFieldAlias('user_name'));
        $this->assertEquals('email', $this->service->resolveFieldAlias('email')); // No alias
    }

    /** @test */
    public function it_applies_field_limits()
    {
        $this->service->configureFieldSelection([
            'max_fields' => 3,
            'required_fields' => ['id']
        ]);

        $fields = ['name', 'email', 'age', 'status', 'description']; // 5 fields
        $limited = $this->service->applyFieldLimits($fields);

        $this->assertLessThanOrEqual(3, count($limited));
        $this->assertContains('id', $limited); // Required field should be added
    }

    /** @test */
    public function it_checks_if_field_is_selectable()
    {
        $this->service->configureFieldSelection([
            'selectable_fields' => ['id', 'name', 'email'],
            'blocked_fields' => ['password'],
            'allow_all_fields' => false
        ]);

        $this->assertTrue($this->service->isFieldSelectable('name'));
        $this->assertFalse($this->service->isFieldSelectable('password')); // Blocked
        $this->assertFalse($this->service->isFieldSelectable('invalid')); // Not in selectable
    }

    /** @test */
    public function it_allows_all_fields_when_configured()
    {
        $this->service->configureFieldSelection([
            'blocked_fields' => ['password'],
            'allow_all_fields' => true
        ]);

        $this->assertTrue($this->service->isFieldSelectable('any_field'));
        $this->assertFalse($this->service->isFieldSelectable('password')); // Still blocked
    }

    /** @test */
    public function it_validates_operator_for_field_type()
    {
        $this->assertTrue($this->service->isOperatorValidForType('like', 'string'));
        $this->assertTrue($this->service->isOperatorValidForType('gte', 'integer'));
        $this->assertFalse($this->service->isOperatorValidForType('like', 'integer'));
        $this->assertFalse($this->service->isOperatorValidForType('gte', 'string'));
    }

    /** @test */
    public function it_returns_operators_for_field_type()
    {
        $stringOperators = $this->service->getOperatorsForType('string');
        $integerOperators = $this->service->getOperatorsForType('integer');

        $this->assertContains('eq', $stringOperators);
        $this->assertContains('like', $stringOperators);
        $this->assertNotContains('gte', $stringOperators);

        $this->assertContains('eq', $integerOperators);
        $this->assertContains('gte', $integerOperators);
        $this->assertNotContains('like', $integerOperators);
    }

    /** @test */
    public function it_provides_complete_metadata()
    {
        $config = [
            'name' => [
                'type' => 'string',
                'operators' => ['eq', 'like'],
                'searchable' => true,
                'sortable' => true
            ]
        ];

        $this->service->configure($config);
        $this->service->configureFieldSelection([
            'selectable_fields' => ['id', 'name']
        ]);

        $metadata = $this->service->getCompleteMetadata();

        $this->assertArrayHasKey('enabled_filters', $metadata);
        $this->assertArrayHasKey('filter_config', $metadata);
        $this->assertArrayHasKey('available_operators', $metadata);
        $this->assertArrayHasKey('field_selection', $metadata);
        $this->assertArrayHasKey('searchable_fields', $metadata);
        $this->assertArrayHasKey('sortable_fields', $metadata);
        $this->assertArrayHasKey('usage_guide', $metadata);

        $this->assertContains('name', $metadata['enabled_filters']);
        $this->assertContains('name', $metadata['searchable_fields']);
        $this->assertContains('name', $metadata['sortable_fields']);
    }

    /** @test */
    public function it_generates_examples_for_enum_fields()
    {
        $config = [
            'status' => [
                'type' => 'enum',
                'operators' => ['eq', 'in'],
                'values' => ['active', 'inactive', 'pending']
            ]
        ];

        $this->service->configure($config);
        $metadata = $this->service->getFilterMetadata();

        $this->assertArrayHasKey('example', $metadata['status']);
        $this->assertStringContainsString('status=active', $metadata['status']['example']['eq']);
        $this->assertStringContainsString('status=active', $metadata['status']['example']['in']);
    }

    /** @test */
    public function it_handles_default_field_configuration()
    {
        $this->service->configureFieldSelection([
            'default_fields' => ['id', 'name'],
            'required_fields' => ['id']
        ]);

        $defaults = $this->service->getDefaultFields();

        $this->assertContains('id', $defaults);
        $this->assertContains('name', $defaults);
        $this->assertEquals(2, count(array_unique($defaults))); // No duplicates
    }
}