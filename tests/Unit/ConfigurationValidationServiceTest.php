<?php

namespace MarcosBrendon\ApiForge\Tests\Unit;

use MarcosBrendon\ApiForge\Services\ConfigurationValidationService;
use MarcosBrendon\ApiForge\Exceptions\VirtualFieldConfigurationException;
use MarcosBrendon\ApiForge\Exceptions\ModelHookConfigurationException;
use MarcosBrendon\ApiForge\Tests\TestCase;
use Illuminate\Support\Facades\Config;

class ConfigurationValidationServiceTest extends TestCase
{
    protected ConfigurationValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ConfigurationValidationService();
    }

    public function test_validates_valid_virtual_fields_configuration()
    {
        $config = [
            'user_full_name' => [
                'type' => 'string',
                'callback' => function ($model) {
                    return $model->first_name . ' ' . $model->last_name;
                },
                'dependencies' => ['first_name', 'last_name'],
                'description' => 'Full name of the user'
            ]
        ];

        $errors = $this->service->validateVirtualFieldsConfig($config);

        $this->assertEmpty($errors);
    }

    public function test_validates_invalid_virtual_fields_configuration()
    {
        $config = [
            'invalid_field' => [
                'type' => 'invalid_type',
                'callback' => 'not_callable'
            ]
        ];

        $this->service->setThrowOnFailure(false);
        $errors = $this->service->validateVirtualFieldsConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('invalid_field', $errors);
    }

    public function test_validates_valid_model_hooks_configuration()
    {
        $config = [
            'beforeStore' => [
                'validate_user' => [
                    'callback' => function ($model, $context) {
                        return true;
                    },
                    'priority' => 10,
                    'description' => 'Validates user data'
                ]
            ]
        ];

        $errors = $this->service->validateModelHooksConfig($config);

        $this->assertEmpty($errors);
    }

    public function test_validates_invalid_model_hooks_configuration()
    {
        $config = [
            'invalidHookType' => [
                'test_hook' => [
                    'callback' => 'not_callable'
                ]
            ]
        ];

        $this->service->setThrowOnFailure(false);
        $errors = $this->service->validateModelHooksConfig($config);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('invalidHookType', $errors);
    }

    public function test_validates_single_virtual_field()
    {
        $config = [
            'type' => 'string',
            'callback' => function ($model) {
                return 'test';
            }
        ];

        $errors = $this->service->validateVirtualField('test_field', $config);

        $this->assertEmpty($errors);
    }

    public function test_validates_single_model_hook()
    {
        $hookConfig = [
            'callback' => function ($model, $context) {
                return true;
            },
            'priority' => 10
        ];

        $errors = $this->service->validateModelHook('beforeStore', 'test_hook', $hookConfig);

        $this->assertEmpty($errors);
    }

    public function test_startup_validation_with_valid_configuration()
    {
        // Mock configuration
        Config::set('apiforge.virtual_fields', [
            'test_field' => [
                'type' => 'string',
                'callback' => function ($model) {
                    return 'test';
                }
            ]
        ]);

        Config::set('apiforge.model_hooks', [
            'beforeStore' => [
                'test_hook' => function ($model, $context) {
                    return true;
                }
            ]
        ]);

        $results = $this->service->validateStartup();

        $this->assertTrue($results['success']);
        $this->assertEmpty($results['errors']);
    }

    public function test_startup_validation_with_invalid_configuration()
    {
        // Mock invalid configuration
        Config::set('apiforge.virtual_fields', [
            'invalid_field' => [
                'type' => 'invalid_type',
                'callback' => 'not_callable'
            ]
        ]);

        $this->service->setThrowOnFailure(false);
        $results = $this->service->validateStartup();

        $this->assertFalse($results['success']);
        $this->assertNotEmpty($results['errors']);
        $this->assertNotEmpty($results['virtual_fields']);
    }

    public function test_throws_exception_on_validation_failure_when_configured()
    {
        $config = [
            'invalid_field' => [
                'type' => 'invalid_type',
                'callback' => 'not_callable'
            ]
        ];

        $this->service->setThrowOnFailure(true);

        $this->expectException(VirtualFieldConfigurationException::class);
        $this->service->validateVirtualFieldsConfig($config);
    }

    public function test_does_not_throw_exception_when_configured()
    {
        $config = [
            'invalid_field' => [
                'type' => 'invalid_type',
                'callback' => 'not_callable'
            ]
        ];

        $this->service->setThrowOnFailure(false);

        $errors = $this->service->validateVirtualFieldsConfig($config);
        $this->assertNotEmpty($errors);
    }

    public function test_validation_with_suggestions()
    {
        $virtualFields = [
            'invalid_field' => [
                'type' => 'invalid_type',
                'callback' => 'not_callable'
            ]
        ];

        $modelHooks = [
            'invalidHookType' => [
                'test_hook' => [
                    'callback' => 'not_callable'
                ]
            ]
        ];

        $this->service->setThrowOnFailure(false);
        $results = $this->service->validateWithSuggestions($virtualFields, $modelHooks);

        $this->assertFalse($results['success']);
        $this->assertNotEmpty($results['suggestions']);
        $this->assertNotEmpty($results['virtual_fields']);
        $this->assertNotEmpty($results['model_hooks']);
    }

    public function test_generates_virtual_field_suggestions()
    {
        $virtualFields = [
            'field_with_invalid_type' => [
                'type' => 'invalid_type',
                'callback' => function ($model) {
                    return 'test';
                }
            ],
            'field_with_invalid_operators' => [
                'type' => 'boolean',
                'callback' => function ($model) {
                    return true;
                },
                'operators' => ['like', 'starts_with'] // Invalid for boolean
            ],
            'field_with_invalid_callback' => [
                'type' => 'string',
                'callback' => 'not_callable'
            ]
        ];

        $this->service->setThrowOnFailure(false);
        $results = $this->service->validateWithSuggestions($virtualFields, []);

        $this->assertNotEmpty($results['suggestions']);
        
        $suggestions = implode(' ', $results['suggestions']);
        $this->assertStringContainsString('valid types', $suggestions);
        $this->assertStringContainsString('operators', $suggestions);
    }

    public function test_generates_model_hook_suggestions()
    {
        $modelHooks = [
            'invalidHookType' => [
                'test_hook' => [
                    'callback' => 'not_callable'
                ]
            ],
            'beforeStore' => [
                'hook_with_invalid_condition' => [
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

        $this->service->setThrowOnFailure(false);
        $results = $this->service->validateWithSuggestions([], $modelHooks);

        $this->assertNotEmpty($results['suggestions']);
        
        $suggestions = implode(' ', $results['suggestions']);
        $this->assertStringContainsString('valid hook types', $suggestions);
        $this->assertStringContainsString('condition operators', $suggestions);
    }

    public function test_tracks_validation_results()
    {
        $this->assertTrue($this->service->isValid());
        $this->assertEmpty($this->service->getErrors());
        $this->assertEmpty($this->service->getWarnings());

        // Trigger validation failure
        $config = [
            'invalid_field' => [
                'type' => 'invalid_type',
                'callback' => 'not_callable'
            ]
        ];

        $this->service->setThrowOnFailure(false);
        $this->service->validateVirtualFieldsConfig($config);

        $this->assertFalse($this->service->isValid());
        $this->assertNotEmpty($this->service->getErrors());
    }

    public function test_clears_validation_results()
    {
        // Trigger validation failure first
        $config = [
            'invalid_field' => [
                'type' => 'invalid_type',
                'callback' => 'not_callable'
            ]
        ];

        $this->service->setThrowOnFailure(false);
        $this->service->validateVirtualFieldsConfig($config);

        $this->assertFalse($this->service->isValid());

        // Clear results
        $this->service->clearResults();

        $this->assertTrue($this->service->isValid());
        $this->assertEmpty($this->service->getErrors());
        $this->assertEmpty($this->service->getWarnings());
    }

    public function test_configuration_settings()
    {
        $this->service->setThrowOnFailure(false);
        $this->service->setLogValidation(false);

        // These should not throw exceptions or log
        $config = [
            'invalid_field' => [
                'type' => 'invalid_type',
                'callback' => 'not_callable'
            ]
        ];

        $errors = $this->service->validateVirtualFieldsConfig($config);
        $this->assertNotEmpty($errors);
    }

    public function test_gets_validation_results()
    {
        // Set empty configurations to avoid errors
        Config::set('apiforge.virtual_fields', []);
        Config::set('apiforge.model_hooks', []);
        
        // Initialize validation results first
        $this->service->validateStartup();
        
        $results = $this->service->getValidationResults();

        $this->assertArrayHasKey('virtual_fields', $results);
        $this->assertArrayHasKey('model_hooks', $results);
        $this->assertArrayHasKey('success', $results);
        $this->assertArrayHasKey('errors', $results);
        $this->assertArrayHasKey('warnings', $results);
    }
}