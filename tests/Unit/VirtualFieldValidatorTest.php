<?php

namespace MarcosBrendon\ApiForge\Tests\Unit;

use MarcosBrendon\ApiForge\Support\VirtualFieldValidator;
use MarcosBrendon\ApiForge\Tests\TestCase;

class VirtualFieldValidatorTest extends TestCase
{
    public function test_validates_valid_field_configuration()
    {
        $config = [
            'user_full_name' => [
                'type' => 'string',
                'callback' => function ($model) {
                    return $model->first_name . ' ' . $model->last_name;
                },
                'dependencies' => ['first_name', 'last_name'],
                'cacheable' => true,
                'cache_ttl' => 3600,
                'description' => 'Full name of the user'
            ]
        ];

        $errors = VirtualFieldValidator::validateConfig($config);

        $this->assertEmpty($errors);
    }

    public function test_validates_field_name_format()
    {
        $config = [
            'invalid-field-name' => [
                'type' => 'string',
                'callback' => fn($model) => 'test'
            ]
        ];

        $errors = VirtualFieldValidator::validateConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('invalid-field-name', $errors);
        $this->assertStringContainsString('invalid characters', $errors['invalid-field-name'][0]);
    }

    public function test_validates_reserved_field_names()
    {
        $config = [
            'id' => [
                'type' => 'string',
                'callback' => fn($model) => 'test'
            ]
        ];

        $errors = VirtualFieldValidator::validateConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('id', $errors);
        $this->assertStringContainsString('reserved', $errors['id'][0]);
    }

    public function test_validates_required_configuration_keys()
    {
        $config = [
            'test_field' => [
                'description' => 'Missing type and callback'
            ]
        ];

        $errors = VirtualFieldValidator::validateConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('test_field', $errors);
        $this->assertStringContainsString('Missing required configuration', $errors['test_field'][0]);
    }

    public function test_validates_field_type()
    {
        $config = [
            'test_field' => [
                'type' => 'invalid_type',
                'callback' => fn($model) => 'test'
            ]
        ];

        $errors = VirtualFieldValidator::validateConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('test_field', $errors);
        $this->assertStringContainsString('Invalid type', $errors['test_field'][0]);
    }

    public function test_validates_callback_is_callable()
    {
        $config = [
            'test_field' => [
                'type' => 'string',
                'callback' => 'not_callable'
            ]
        ];

        $errors = VirtualFieldValidator::validateConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('test_field', $errors);
        $this->assertStringContainsString('callable', $errors['test_field'][0]);
    }

    public function test_validates_operators_for_field_type()
    {
        $config = [
            'test_field' => [
                'type' => 'boolean',
                'callback' => fn($model) => true,
                'operators' => ['like', 'starts_with'] // Invalid for boolean
            ]
        ];

        $errors = VirtualFieldValidator::validateConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('test_field', $errors);
        $this->assertStringContainsString('Invalid operators', $errors['test_field'][0]);
    }

    public function test_validates_dependencies_are_strings()
    {
        $config = [
            'test_field' => [
                'type' => 'string',
                'callback' => fn($model) => 'test',
                'dependencies' => ['valid_dep', 123, null] // Invalid dependencies
            ]
        ];

        $errors = VirtualFieldValidator::validateConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('test_field', $errors);
        $this->assertStringContainsString('Invalid dependency', $errors['test_field'][0]);
    }

    public function test_validates_cache_ttl_is_non_negative_integer()
    {
        $config = [
            'test_field' => [
                'type' => 'string',
                'callback' => fn($model) => 'test',
                'cache_ttl' => -100
            ]
        ];

        $errors = VirtualFieldValidator::validateConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('test_field', $errors);
        $this->assertStringContainsString('Invalid cache TTL', $errors['test_field'][0]);
    }

    public function test_validates_enum_values_for_enum_type()
    {
        $config = [
            'status_field' => [
                'type' => 'enum',
                'callback' => fn($model) => 'active',
                'enum_values' => [] // Empty enum values
            ]
        ];

        $errors = VirtualFieldValidator::validateConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('status_field', $errors);
        $this->assertStringContainsString('cannot be empty', $errors['status_field'][0]);
    }

    public function test_detects_circular_dependencies()
    {
        $config = [
            'field_a' => [
                'type' => 'string',
                'callback' => fn($model) => 'test',
                'dependencies' => ['field_b']
            ],
            'field_b' => [
                'type' => 'string',
                'callback' => fn($model) => 'test',
                'dependencies' => ['field_c']
            ],
            'field_c' => [
                'type' => 'string',
                'callback' => fn($model) => 'test',
                'dependencies' => ['field_a'] // Circular dependency
            ]
        ];

        $errors = VirtualFieldValidator::validateConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertTrue(
            isset($errors['field_a']) || isset($errors['field_b']) || isset($errors['field_c'])
        );
    }

    public function test_normalizes_configuration_with_defaults()
    {
        $config = [
            'test_field' => [
                'type' => 'string',
                'callback' => fn($model) => 'test'
            ]
        ];

        $normalized = VirtualFieldValidator::normalizeConfig($config);

        $this->assertArrayHasKey('test_field', $normalized);
        $this->assertEquals([], $normalized['test_field']['dependencies']);
        $this->assertEquals([], $normalized['test_field']['relationships']);
        $this->assertEquals(false, $normalized['test_field']['cacheable']);
        $this->assertEquals(3600, $normalized['test_field']['cache_ttl']);
        $this->assertEquals(true, $normalized['test_field']['nullable']);
        $this->assertEquals(true, $normalized['test_field']['sortable']);
        $this->assertEquals(true, $normalized['test_field']['searchable']);
        $this->assertEquals('', $normalized['test_field']['description']);
    }

    public function test_sets_default_operators_based_on_type()
    {
        $config = [
            'string_field' => [
                'type' => 'string',
                'callback' => fn($model) => 'test'
            ],
            'integer_field' => [
                'type' => 'integer',
                'callback' => fn($model) => 123
            ]
        ];

        $normalized = VirtualFieldValidator::normalizeConfig($config);

        $stringOperators = $normalized['string_field']['operators'];
        $integerOperators = $normalized['integer_field']['operators'];

        $this->assertContains('like', $stringOperators);
        $this->assertContains('starts_with', $stringOperators);
        $this->assertNotContains('like', $integerOperators);
        $this->assertContains('gt', $integerOperators);
        $this->assertContains('between', $integerOperators);
    }

    public function test_validates_boolean_flags()
    {
        $config = [
            'test_field' => [
                'type' => 'string',
                'callback' => fn($model) => 'test',
                'cacheable' => 'yes', // Should be boolean
                'nullable' => 1, // Should be boolean
                'sortable' => 'true' // Should be boolean
            ]
        ];

        $errors = VirtualFieldValidator::validateConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('test_field', $errors);
        $this->assertGreaterThanOrEqual(1, count($errors['test_field'])); // At least 1 validation error
    }

    public function test_gets_valid_types()
    {
        $validTypes = VirtualFieldValidator::getValidTypes();

        $expectedTypes = ['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'enum', 'array', 'object'];
        
        foreach ($expectedTypes as $type) {
            $this->assertContains($type, $validTypes);
        }
    }

    public function test_gets_valid_operators_for_type()
    {
        $stringOperators = VirtualFieldValidator::getValidOperators('string');
        $booleanOperators = VirtualFieldValidator::getValidOperators('boolean');

        $this->assertContains('like', $stringOperators);
        $this->assertContains('starts_with', $stringOperators);
        $this->assertNotContains('like', $booleanOperators);
        $this->assertContains('eq', $booleanOperators);
        $this->assertContains('ne', $booleanOperators);
    }
}