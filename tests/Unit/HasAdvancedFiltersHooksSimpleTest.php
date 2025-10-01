<?php

namespace MarcosBrendon\ApiForge\Tests\Unit;

use Illuminate\Http\Request;
use MarcosBrendon\ApiForge\Services\ModelHookService;
use MarcosBrendon\ApiForge\Tests\TestCase;
use MarcosBrendon\ApiForge\Tests\Fixtures\TestControllerWithHooks;
use MarcosBrendon\ApiForge\Tests\Fixtures\TestModel;

class HasAdvancedFiltersHooksSimpleTest extends TestCase
{
    protected TestControllerWithHooks $controller;
    protected Request $request;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->controller = new TestControllerWithHooks();
        $this->request = new Request();
    }

    public function test_can_configure_model_hooks()
    {
        $config = [
            'beforeStore' => [
                'testHook' => [
                    'callback' => function($model, $context) {
                        return 'test';
                    },
                    'priority' => 1
                ]
            ]
        ];

        $this->controller->configureModelHooks($config);

        $hookService = $this->controller->getHookService();
        $this->assertTrue($hookService->hasHook('beforeStore'));
    }

    public function test_can_register_individual_hook()
    {
        $callback = function($model, $context) {
            return 'individual hook';
        };

        $this->controller->registerHook('beforeStore', 'individualHook', $callback, ['priority' => 5]);

        $hookService = $this->controller->getHookService();
        $hooks = $hookService->getHooks('beforeStore');
        
        $this->assertArrayHasKey('individualHook', $hooks);
    }

    public function test_can_get_hook_service()
    {
        $hookService = $this->controller->getHookService();
        
        $this->assertInstanceOf(ModelHookService::class, $hookService);
    }

    public function test_can_clear_hooks()
    {
        $this->controller->registerHook('beforeStore', 'testHook', function() {});
        
        $hookService = $this->controller->getHookService();
        $this->assertTrue($hookService->hasHook('beforeStore'));

        $this->controller->clearHooks('beforeStore');
        
        $this->assertFalse($hookService->hasHook('beforeStore'));
    }

    public function test_resolve_cache_key_placeholders()
    {
        $model = new TestModel(['id' => 123, 'name' => 'Test', 'category_id' => 456]);
        
        $key = 'user_data_{name}_category_{category_id}';
        $resolvedKey = $this->controller->resolveCacheKeyPlaceholders($key, $model);
        
        $this->assertEquals('user_data_Test_category_456', $resolvedKey);
    }

    public function test_check_permission_without_auth_returns_false()
    {
        $model = new TestModel();
        $result = $this->controller->checkPermission('test-permission', $model, 'create');

        $this->assertFalse($result);
    }

    public function test_check_permission_with_callback()
    {
        $model = new TestModel();
        $callback = function($user, $model, $action) {
            return $action === 'create'; // Allow create, deny others
        };

        $result = $this->controller->checkPermission($callback, $model, 'create');
        $this->assertFalse($result); // Should be false because no user is authenticated

        $result2 = $this->controller->checkPermission($callback, $model, 'update');
        $this->assertFalse($result2); // Should be false because no user is authenticated
    }

    public function test_configure_slug_hooks_registers_hooks()
    {
        $this->controller->configureSlugHooks('name', 'slug', [
            'unique' => false, // Disable unique check to avoid database queries
            'overwrite' => false,
            'separator' => '-'
        ]);

        $hookService = $this->controller->getHookService();
        
        $this->assertTrue($hookService->hasHook('beforeStore'));
        $this->assertTrue($hookService->hasHook('beforeUpdate'));
    }

    public function test_configure_permission_hooks_registers_hooks()
    {
        $permissions = [
            'create' => 'create-test',
            'update' => 'update-test',
            'delete' => ['delete-test', 'admin-access']
        ];

        $this->controller->configurePermissionHooks($permissions, [
            'throw_on_failure' => false // Don't throw exceptions in tests
        ]);

        $hookService = $this->controller->getHookService();
        
        $this->assertTrue($hookService->hasHook('beforeStore'));
        $this->assertTrue($hookService->hasHook('beforeUpdate'));
        $this->assertTrue($hookService->hasHook('beforeDelete'));
    }

    public function test_configure_validation_hooks_registers_hooks()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email'
        ];

        $this->controller->configureValidationHooks($rules, [
            'stop_on_failure' => false // Don't stop on failure in tests
        ]);

        $hookService = $this->controller->getHookService();
        
        $this->assertTrue($hookService->hasHook('beforeStore'));
        $this->assertTrue($hookService->hasHook('beforeUpdate'));
    }

    public function test_configure_notification_hooks_registers_hooks()
    {
        $config = [
            'onCreate' => [
                'notification' => 'TestNotification',
                'recipients' => ['admin'],
                'channels' => ['mail'],
                'priority' => 10
            ]
        ];

        $this->controller->configureNotificationHooks($config);

        $hookService = $this->controller->getHookService();
        $this->assertTrue($hookService->hasHook('afterStore'));
    }

    public function test_resolve_notification_recipients_returns_array()
    {
        $model = new TestModel(['id' => 123]);
        $context = new \stdClass(); // Simple mock context

        $recipients = ['admin', 'current_user'];
        $resolved = $this->controller->resolveNotificationRecipients($recipients, $model, $context);

        $this->assertIsArray($resolved);
        // Should be empty since no users exist in test environment
        $this->assertEmpty($resolved);
    }

    public function test_hook_execution_flow()
    {
        $executed = [];

        // Configure hooks with different priorities
        $this->controller->configureModelHooks([
            'beforeStore' => [
                'first' => [
                    'callback' => function($model, $context) use (&$executed) {
                        $executed[] = 'first';
                    },
                    'priority' => 1
                ],
                'second' => [
                    'callback' => function($model, $context) use (&$executed) {
                        $executed[] = 'second';
                    },
                    'priority' => 5
                ],
                'third' => [
                    'callback' => function($model, $context) use (&$executed) {
                        $executed[] = 'third';
                    },
                    'priority' => 10
                ]
            ]
        ]);

        $model = new TestModel(['name' => 'Test']);
        $hookService = $this->controller->getHookService();
        
        $hookService->executeBeforeStore($model, $this->request);

        // Verify hooks executed in priority order
        $this->assertEquals(['first', 'second', 'third'], $executed);
    }
}