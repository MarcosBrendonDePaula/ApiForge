<?php

namespace MarcosBrendon\ApiForge\Tests\Unit;

use MarcosBrendon\ApiForge\Support\ModelHookValidator;
use MarcosBrendon\ApiForge\Tests\TestCase;

class ModelHookValidatorTest extends TestCase
{
    public function test_validates_valid_hook_configuration()
    {
        $config = [
            'beforeStore' => [
                'validate_user' => [
                    'callback' => function ($model, $context) {
                        return true;
                    },
                    'priority' => 10,
                    'stopOnFailure' => false,
                    'description' => 'Validates user data before storing'
                ]
            ]
        ];

        $errors = ModelHookValidator::validateConfig($config);

        $this->assertEmpty($errors);
    }

    public function test_validates_simple_callable_configuration()
    {
        $config = [
            'afterStore' => [
                'log_creation' => function ($model, $context) {
                    // Simple callable hook
                }
            ]
        ];

        $errors = ModelHookValidator::validateConfig($config);

        $this->assertEmpty($errors);
    }

    public function test_validates_invalid_hook_type()
    {
        $config = [
            'invalidHookType' => [
                'test_hook' => function ($model, $context) {
                    return true;
                }
            ]
        ];

        $errors = ModelHookValidator::validateConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('invalidHookType', $errors);
        
        // The error structure might be different, let's check if it's an array or string
        if (is_array($errors['invalidHookType'])) {
            $errorMessage = is_array($errors['invalidHookType']['test_hook']) 
                ? $errors['invalidHookType']['test_hook'][0] 
                : $errors['invalidHookType']['test_hook'];
        } else {
            $errorMessage = $errors['invalidHookType'];
        }
        
        $this->assertStringContainsString('Invalid hook type', $errorMessage);
    }

    public function test_validates_hook_name_format()
    {
        $config = [
            'beforeStore' => [
                'invalid-hook-name' => function ($model, $context) {
                    return true;
                }
            ]
        ];

        $errors = ModelHookValidator::validateConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('beforeStore', $errors);
        $this->assertArrayHasKey('invalid-hook-name', $errors['beforeStore']);
        $this->assertStringContainsString('invalid characters', $errors['beforeStore']['invalid-hook-name'][0]);
    }

    public function test_validates_missing_callback()
    {
        $config = [
            'beforeStore' => [
                'test_hook' => [
                    'priority' => 10,
                    'description' => 'Missing callback'
                ]
            ]
        ];

        $errors = ModelHookValidator::validateConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('beforeStore', $errors);
        $this->assertArrayHasKey('test_hook', $errors['beforeStore']);
        $this->assertStringContainsString('Missing required configuration', $errors['beforeStore']['test_hook'][0]);
    }

    public function test_validates_non_callable_callback()
    {
        $config = [
            'beforeStore' => [
                'test_hook' => [
                    'callback' => 'not_a_callable_string'
                ]
            ]
        ];

        $errors = ModelHookValidator::validateConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('beforeStore', $errors);
        $this->assertArrayHasKey('test_hook', $errors['beforeStore']);
        $this->assertStringContainsString('not callable', $errors['beforeStore']['test_hook'][0]);
    }

    public function test_validates_invalid_priority()
    {
        $config = [
            'beforeStore' => [
                'test_hook' => [
                    'callback' => function ($model, $context) {
                        return true;
                    },
                    'priority' => -5 // Invalid negative priority
                ]
            ]
        ];

        $errors = ModelHookValidator::validateConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('beforeStore', $errors);
        $this->assertArrayHasKey('test_hook', $errors['beforeStore']);
        $this->assertStringContainsString('non-negative integer', $errors['beforeStore']['test_hook'][0]);
    }

    public function test_validates_invalid_stop_on_failure()
    {
        $config = [
            'beforeStore' => [
                'test_hook' => [
                    'callback' => function ($model, $context) {
                        return true;
                    },
                    'stopOnFailure' => 'yes' // Should be boolean
                ]
            ]
        ];

        $errors = ModelHookValidator::validateConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('beforeStore', $errors);
        $this->assertArrayHasKey('test_hook', $errors['beforeStore']);
        $this->assertStringContainsString('must be a boolean', $errors['beforeStore']['test_hook'][0]);
    }

    public function test_validates_hook_conditions()
    {
        $config = [
            'beforeStore' => [
                'conditional_hook' => [
                    'callback' => function ($model, $context) {
                        return true;
                    },
                    'conditions' => [
                        [
                            'field' => 'status',
                            'operator' => 'eq',
                            'value' => 'active'
                        ]
                    ]
                ]
            ]
        ];

        $errors = ModelHookValidator::validateConfig($config);

        $this->assertEmpty($errors);
    }

    public function test_validates_invalid_condition_structure()
    {
        $config = [
            'beforeStore' => [
                'test_hook' => [
                    'callback' => function ($model, $context) {
                        return true;
                    },
                    'conditions' => [
                        [
                            'field' => 'status',
                            // Missing operator and value
                        ]
                    ]
                ]
            ]
        ];

        $errors = ModelHookValidator::validateConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('beforeStore', $errors);
        $this->assertArrayHasKey('test_hook', $errors['beforeStore']);
    }

    public function test_validates_invalid_condition_operator()
    {
        $config = [
            'beforeStore' => [
                'test_hook' => [
                    'callback' => function ($model, $context) {
                        return true;
                    },
                    'conditions' => [
                        [
                            'field' => 'status',
                            'operator' => 'invalid_operator',
                            'value' => 'active'
                        ]
                    ]
                ]
            ]
        ];

        $errors = ModelHookValidator::validateConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('beforeStore', $errors);
        $this->assertArrayHasKey('test_hook', $errors['beforeStore']);
        $this->assertStringContainsString('Invalid operator', $errors['beforeStore']['test_hook'][0]);
    }

    public function test_validates_invalid_description_type()
    {
        $config = [
            'beforeStore' => [
                'test_hook' => [
                    'callback' => function ($model, $context) {
                        return true;
                    },
                    'description' => 123 // Should be string
                ]
            ]
        ];

        $errors = ModelHookValidator::validateConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('beforeStore', $errors);
        $this->assertArrayHasKey('test_hook', $errors['beforeStore']);
        $this->assertStringContainsString('must be a string', $errors['beforeStore']['test_hook'][0]);
    }

    public function test_normalizes_configuration_with_defaults()
    {
        $config = [
            'beforeStore' => [
                'simple_hook' => function ($model, $context) {
                    return true;
                },
                'complex_hook' => [
                    'callback' => function ($model, $context) {
                        return true;
                    },
                    'priority' => 5
                ]
            ]
        ];

        $normalized = ModelHookValidator::normalizeConfig($config);

        // Check simple callable hook normalization
        $this->assertArrayHasKey('beforeStore', $normalized);
        $this->assertArrayHasKey('simple_hook', $normalized['beforeStore']);
        $this->assertEquals(10, $normalized['beforeStore']['simple_hook']['priority']);
        $this->assertEquals(false, $normalized['beforeStore']['simple_hook']['stopOnFailure']);
        $this->assertEquals([], $normalized['beforeStore']['simple_hook']['conditions']);
        $this->assertEquals('', $normalized['beforeStore']['simple_hook']['description']);

        // Check complex hook normalization
        $this->assertArrayHasKey('complex_hook', $normalized['beforeStore']);
        $this->assertEquals(5, $normalized['beforeStore']['complex_hook']['priority']);
        $this->assertEquals(false, $normalized['beforeStore']['complex_hook']['stopOnFailure']);
    }

    public function test_gets_valid_hook_types()
    {
        $validTypes = ModelHookValidator::getValidHookTypes();

        $expectedTypes = [
            'beforeStore', 'afterStore', 'beforeUpdate', 'afterUpdate',
            'beforeDelete', 'afterDelete', 'beforeValidation', 'afterValidation',
            'beforeTransform', 'afterTransform', 'beforeAuthorization', 'afterAuthorization',
            'beforeAudit', 'afterAudit', 'beforeNotification', 'afterNotification',
            'beforeCache', 'afterCache', 'beforeQuery', 'afterQuery',
            'beforeResponse', 'afterResponse'
        ];

        foreach ($expectedTypes as $type) {
            $this->assertContains($type, $validTypes);
        }
    }

    public function test_gets_valid_condition_operators()
    {
        $validOperators = ModelHookValidator::getValidConditionOperators();

        $expectedOperators = [
            'eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in',
            'like', 'not_like', 'null', 'not_null'
        ];

        foreach ($expectedOperators as $operator) {
            $this->assertContains($operator, $validOperators);
        }
    }

    public function test_checks_return_value_support()
    {
        $this->assertTrue(ModelHookValidator::supportsReturnValue('beforeDelete'));
        $this->assertTrue(ModelHookValidator::supportsReturnValue('beforeValidation'));
        $this->assertFalse(ModelHookValidator::supportsReturnValue('afterStore'));
        $this->assertFalse(ModelHookValidator::supportsReturnValue('afterUpdate'));
    }

    public function test_checks_stop_execution_support()
    {
        $this->assertTrue(ModelHookValidator::supportsStopExecution('beforeStore'));
        $this->assertTrue(ModelHookValidator::supportsStopExecution('beforeDelete'));
        $this->assertFalse(ModelHookValidator::supportsStopExecution('afterStore'));
        $this->assertFalse(ModelHookValidator::supportsStopExecution('afterUpdate'));
    }

    public function test_validates_empty_hook_name()
    {
        $config = [
            'beforeStore' => [
                '' => function ($model, $context) {
                    return true;
                }
            ]
        ];

        $errors = ModelHookValidator::validateConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('beforeStore', $errors);
        $this->assertArrayHasKey('', $errors['beforeStore']);
        $this->assertStringContainsString('cannot be empty', $errors['beforeStore'][''][0]);
    }

    public function test_validates_non_array_hook_type_configuration()
    {
        $config = [
            'beforeStore' => 'not_an_array'
        ];

        $errors = ModelHookValidator::validateConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('beforeStore', $errors);
        $this->assertStringContainsString('must contain an array', $errors['beforeStore'][0]);
    }
}