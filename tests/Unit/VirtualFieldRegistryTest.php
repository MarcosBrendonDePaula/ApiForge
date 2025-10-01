<?php

namespace MarcosBrendon\ApiForge\Tests\Unit;

use MarcosBrendon\ApiForge\Support\VirtualFieldRegistry;
use MarcosBrendon\ApiForge\Support\VirtualFieldDefinition;
use MarcosBrendon\ApiForge\Exceptions\FilterValidationException;
use MarcosBrendon\ApiForge\Tests\TestCase;

class VirtualFieldRegistryTest extends TestCase
{
    protected VirtualFieldRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new VirtualFieldRegistry();
    }

    public function test_can_add_and_get_virtual_field()
    {
        $definition = new VirtualFieldDefinition(
            'test_field',
            'string',
            function ($model) {
                return $model->name;
            }
        );

        $this->registry->add('test_field', $definition);

        $this->assertTrue($this->registry->has('test_field'));
        $this->assertSame($definition, $this->registry->get('test_field'));
    }

    public function test_throws_exception_when_adding_duplicate_field()
    {
        $definition = new VirtualFieldDefinition(
            'test_field',
            'string',
            function ($model) {
                return $model->name;
            }
        );

        $this->registry->add('test_field', $definition);

        $this->expectException(FilterValidationException::class);
        $this->expectExceptionMessage("Virtual field 'test_field' is already registered");

        $this->registry->add('test_field', $definition);
    }

    public function test_returns_null_for_non_existent_field()
    {
        $this->assertNull($this->registry->get('non_existent'));
        $this->assertFalse($this->registry->has('non_existent'));
    }

    public function test_can_remove_virtual_field()
    {
        $definition = new VirtualFieldDefinition(
            'test_field',
            'string',
            function ($model) {
                return $model->name;
            }
        );

        $this->registry->add('test_field', $definition);
        $this->assertTrue($this->registry->has('test_field'));

        $removed = $this->registry->remove('test_field');
        $this->assertTrue($removed);
        $this->assertFalse($this->registry->has('test_field'));

        // Try to remove non-existent field
        $removed = $this->registry->remove('non_existent');
        $this->assertFalse($removed);
    }

    public function test_can_get_all_fields()
    {
        $definition1 = new VirtualFieldDefinition(
            'field1',
            'string',
            function ($model) {
                return $model->name;
            }
        );

        $definition2 = new VirtualFieldDefinition(
            'field2',
            'integer',
            function ($model) {
                return strlen($model->name);
            }
        );

        $this->registry->add('field1', $definition1);
        $this->registry->add('field2', $definition2);

        $all = $this->registry->all();
        $this->assertCount(2, $all);
        $this->assertArrayHasKey('field1', $all);
        $this->assertArrayHasKey('field2', $all);
    }

    public function test_can_get_fields_by_type()
    {
        $stringField = new VirtualFieldDefinition(
            'string_field',
            'string',
            function ($model) {
                return $model->name;
            }
        );

        $integerField = new VirtualFieldDefinition(
            'integer_field',
            'integer',
            function ($model) {
                return strlen($model->name);
            }
        );

        $this->registry->add('string_field', $stringField);
        $this->registry->add('integer_field', $integerField);

        $stringFields = $this->registry->getByType('string');
        $integerFields = $this->registry->getByType('integer');

        $this->assertCount(1, $stringFields);
        $this->assertCount(1, $integerFields);
        $this->assertArrayHasKey('string_field', $stringFields);
        $this->assertArrayHasKey('integer_field', $integerFields);
    }

    public function test_can_get_fields_by_operator()
    {
        $eqField = new VirtualFieldDefinition(
            'eq_field',
            'string',
            function ($model) {
                return $model->name;
            },
            [],
            [],
            ['eq', 'ne']
        );

        $likeField = new VirtualFieldDefinition(
            'like_field',
            'string',
            function ($model) {
                return $model->name;
            },
            [],
            [],
            ['like', 'eq']
        );

        $this->registry->add('eq_field', $eqField);
        $this->registry->add('like_field', $likeField);

        $eqFields = $this->registry->getByOperator('eq');
        $likeFields = $this->registry->getByOperator('like');
        $gtFields = $this->registry->getByOperator('gt');

        $this->assertCount(2, $eqFields);
        $this->assertCount(1, $likeFields);
        $this->assertCount(0, $gtFields);
        $this->assertArrayHasKey('like_field', $likeFields);
    }

    public function test_can_get_cacheable_fields()
    {
        $cacheableField = new VirtualFieldDefinition(
            'cacheable_field',
            'string',
            function ($model) {
                return $model->name;
            },
            [],
            [],
            [],
            true // cacheable
        );

        $nonCacheableField = new VirtualFieldDefinition(
            'non_cacheable_field',
            'string',
            function ($model) {
                return $model->name;
            },
            [],
            [],
            [],
            false // not cacheable
        );

        $this->registry->add('cacheable_field', $cacheableField);
        $this->registry->add('non_cacheable_field', $nonCacheableField);

        $cacheableFields = $this->registry->getCacheable();

        $this->assertCount(1, $cacheableFields);
        $this->assertArrayHasKey('cacheable_field', $cacheableFields);
        $this->assertArrayNotHasKey('non_cacheable_field', $cacheableFields);
    }

    public function test_can_get_sortable_and_searchable_fields()
    {
        $sortableField = new VirtualFieldDefinition(
            'sortable_field',
            'string',
            function ($model) {
                return $model->name;
            },
            [],
            [],
            [],
            false,
            3600,
            null,
            true,
            true, // sortable
            false // not searchable
        );

        $searchableField = new VirtualFieldDefinition(
            'searchable_field',
            'string',
            function ($model) {
                return $model->name;
            },
            [],
            [],
            [],
            false,
            3600,
            null,
            true,
            false, // not sortable
            true // searchable
        );

        $this->registry->add('sortable_field', $sortableField);
        $this->registry->add('searchable_field', $searchableField);

        $sortableFields = $this->registry->getSortable();
        $searchableFields = $this->registry->getSearchable();

        $this->assertCount(1, $sortableFields);
        $this->assertCount(1, $searchableFields);
        $this->assertArrayHasKey('sortable_field', $sortableFields);
        $this->assertArrayHasKey('searchable_field', $searchableFields);
    }

    public function test_can_get_fields_by_dependency()
    {
        $field1 = new VirtualFieldDefinition(
            'field1',
            'string',
            function ($model) {
                return $model->name;
            },
            ['name', 'email']
        );

        $field2 = new VirtualFieldDefinition(
            'field2',
            'string',
            function ($model) {
                return $model->name;
            },
            ['name', 'phone']
        );

        $this->registry->add('field1', $field1);
        $this->registry->add('field2', $field2);

        $nameFields = $this->registry->getByDependency('name');
        $emailFields = $this->registry->getByDependency('email');
        $phoneFields = $this->registry->getByDependency('phone');

        $this->assertCount(2, $nameFields);
        $this->assertCount(1, $emailFields);
        $this->assertCount(1, $phoneFields);
        $this->assertArrayHasKey('field1', $emailFields);
        $this->assertArrayHasKey('field2', $phoneFields);
    }

    public function test_can_get_fields_by_relationship()
    {
        $field1 = new VirtualFieldDefinition(
            'field1',
            'string',
            function ($model) {
                return $model->profile->bio;
            },
            [],
            ['profile', 'posts']
        );

        $field2 = new VirtualFieldDefinition(
            'field2',
            'string',
            function ($model) {
                return $model->profile->name;
            },
            [],
            ['profile']
        );

        $this->registry->add('field1', $field1);
        $this->registry->add('field2', $field2);

        $profileFields = $this->registry->getByRelationship('profile');
        $postsFields = $this->registry->getByRelationship('posts');

        $this->assertCount(2, $profileFields);
        $this->assertCount(1, $postsFields);
        $this->assertArrayHasKey('field1', $postsFields);
    }

    public function test_can_get_dependencies_for_multiple_fields()
    {
        $field1 = new VirtualFieldDefinition(
            'field1',
            'string',
            function ($model) {
                return $model->name;
            },
            ['name', 'email']
        );

        $field2 = new VirtualFieldDefinition(
            'field2',
            'string',
            function ($model) {
                return $model->phone;
            },
            ['phone', 'email']
        );

        $this->registry->add('field1', $field1);
        $this->registry->add('field2', $field2);

        $dependencies = $this->registry->getDependencies(['field1', 'field2']);
        $this->assertCount(3, $dependencies);
        $this->assertContains('name', $dependencies);
        $this->assertContains('email', $dependencies);
        $this->assertContains('phone', $dependencies);
    }

    public function test_can_get_all_dependencies()
    {
        $field1 = new VirtualFieldDefinition(
            'field1',
            'string',
            function ($model) {
                return $model->name;
            },
            ['name', 'email'],
            ['profile']
        );

        $field2 = new VirtualFieldDefinition(
            'field2',
            'string',
            function ($model) {
                return $model->phone;
            },
            ['phone'],
            ['contacts', 'profile']
        );

        $this->registry->add('field1', $field1);
        $this->registry->add('field2', $field2);

        $allDependencies = $this->registry->getAllDependencies(['field1', 'field2']);

        $this->assertArrayHasKey('fields', $allDependencies);
        $this->assertArrayHasKey('relationships', $allDependencies);

        $this->assertCount(3, $allDependencies['fields']);
        $this->assertContains('name', $allDependencies['fields']);
        $this->assertContains('email', $allDependencies['fields']);
        $this->assertContains('phone', $allDependencies['fields']);

        $this->assertCount(2, $allDependencies['relationships']);
        $this->assertContains('profile', $allDependencies['relationships']);
        $this->assertContains('contacts', $allDependencies['relationships']);
    }

    public function test_can_clear_all_fields()
    {
        $definition = new VirtualFieldDefinition(
            'test_field',
            'string',
            function ($model) {
                return $model->name;
            }
        );

        $this->registry->add('test_field', $definition);
        $this->assertEquals(1, $this->registry->count());

        $this->registry->clear();
        $this->assertEquals(0, $this->registry->count());
        $this->assertFalse($this->registry->has('test_field'));
    }

    public function test_can_get_field_names()
    {
        $definition1 = new VirtualFieldDefinition(
            'field1',
            'string',
            function ($model) {
                return $model->name;
            }
        );

        $definition2 = new VirtualFieldDefinition(
            'field2',
            'integer',
            function ($model) {
                return strlen($model->name);
            }
        );

        $this->registry->add('field1', $definition1);
        $this->registry->add('field2', $definition2);

        $fieldNames = $this->registry->getFieldNames();
        $this->assertCount(2, $fieldNames);
        $this->assertContains('field1', $fieldNames);
        $this->assertContains('field2', $fieldNames);
    }

    public function test_can_register_from_config()
    {
        $config = [
            'field1' => [
                'type' => 'string',
                'callback' => function ($model) {
                    return $model->name;
                },
                'dependencies' => ['name']
            ],
            'field2' => [
                'type' => 'integer',
                'callback' => function ($model) {
                    return strlen($model->name);
                },
                'dependencies' => ['name'],
                'cacheable' => true
            ]
        ];

        $this->registry->registerFromConfig($config);

        $this->assertTrue($this->registry->has('field1'));
        $this->assertTrue($this->registry->has('field2'));
        $this->assertEquals(2, $this->registry->count());

        $field2 = $this->registry->get('field2');
        $this->assertTrue($field2->cacheable);
    }

    public function test_validates_config_correctly()
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

        $validErrors = $this->registry->validateConfig($validConfig);
        $invalidErrors = $this->registry->validateConfig($invalidConfig);

        $this->assertEmpty($validErrors);
        $this->assertNotEmpty($invalidErrors);
        $this->assertArrayHasKey('invalid_field', $invalidErrors);
    }

    public function test_can_get_metadata()
    {
        $stringField = new VirtualFieldDefinition(
            'string_field',
            'string',
            function ($model) {
                return $model->name;
            },
            [],
            [],
            [],
            true, // cacheable
            3600,
            null,
            true,
            true, // sortable
            true // searchable
        );

        $integerField = new VirtualFieldDefinition(
            'integer_field',
            'integer',
            function ($model) {
                return strlen($model->name);
            },
            [],
            [],
            [],
            false, // not cacheable
            3600,
            null,
            true,
            false, // not sortable
            false // not searchable
        );

        $this->registry->add('string_field', $stringField);
        $this->registry->add('integer_field', $integerField);

        $metadata = $this->registry->getMetadata();

        $this->assertEquals(2, $metadata['total_fields']);
        $this->assertEquals(1, $metadata['cacheable_count']);
        $this->assertEquals(1, $metadata['sortable_count']);
        $this->assertEquals(1, $metadata['searchable_count']);
        $this->assertArrayHasKey('fields_by_type', $metadata);
        $this->assertArrayHasKey('fields', $metadata);
    }
}