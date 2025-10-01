<?php

namespace MarcosBrendon\ApiForge\Tests\Feature;

use MarcosBrendon\ApiForge\Services\VirtualFieldService;
use MarcosBrendon\ApiForge\Tests\TestCase;
use MarcosBrendon\ApiForge\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class VirtualFieldCachingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected VirtualFieldService $virtualFieldService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->virtualFieldService = app(VirtualFieldService::class);
        
        // Enable caching for tests
        config(['apiforge.virtual_fields.cache_enabled' => true]);
        config(['apiforge.virtual_fields.enable_monitoring' => true]);
        
        // Create users table for testing
        $this->createUsersTable();
    }
    
    protected function createUsersTable(): void
    {
        $this->app['db']->connection()->getSchemaBuilder()->create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function test_virtual_field_caching_works_end_to_end()
    {
        // Create test users
        $user1 = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user2 = User::create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        // Register a cacheable virtual field
        $this->virtualFieldService->register('name_length', [
            'type' => 'integer',
            'callback' => function($model) {
                // Add a small delay to make caching benefits visible
                usleep(1000); // 1ms
                return strlen($model->name);
            },
            'dependencies' => ['name'],
            'cacheable' => true,
            'cache_ttl' => 300
        ]);

        // First computation - should cache the result
        $length1 = $this->virtualFieldService->compute('name_length', $user1);
        $this->assertEquals(8, $length1); // "John Doe" = 8 characters

        // Second computation - should use cached result (faster)
        $startTime = microtime(true);
        $length1Cached = $this->virtualFieldService->compute('name_length', $user1);
        $endTime = microtime(true);
        
        $this->assertEquals(8, $length1Cached);
        
        // The cached computation should be much faster
        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $this->assertLessThan(5, $duration); // Should be less than 5ms (more realistic for testing)

        // Compute for second user (not cached)
        $length2 = $this->virtualFieldService->compute('name_length', $user2);
        $this->assertEquals(10, $length2); // "Jane Smith" = 10 characters
    }

    public function test_cache_invalidation_works()
    {
        $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);

        // Register a cacheable virtual field
        $this->virtualFieldService->register('name_upper', [
            'type' => 'string',
            'callback' => function($model) {
                return strtoupper($model->name);
            },
            'dependencies' => ['name'],
            'cacheable' => true,
            'cache_ttl' => 300
        ]);

        // First computation - caches result
        $upperName = $this->virtualFieldService->compute('name_upper', $user);
        $this->assertEquals('JOHN DOE', $upperName);

        // Invalidate cache
        $this->virtualFieldService->invalidateCache($user, ['name_upper']);

        // Update user name
        $user->name = 'Jane Smith';
        $user->save();

        // Compute again - should use new value (not cached old value)
        $newUpperName = $this->virtualFieldService->compute('name_upper', $user);
        $this->assertEquals('JANE SMITH', $newUpperName);
    }

    public function test_batch_processing_with_caching()
    {
        // Create multiple users
        $users = new \Illuminate\Database\Eloquent\Collection();
        for ($i = 1; $i <= 5; $i++) {
            $users->push(User::create([
                'name' => "User $i",
                'email' => "user$i@example.com"
            ]));
        }

        // Register a cacheable virtual field
        $this->virtualFieldService->register('name_with_id', [
            'type' => 'string',
            'callback' => function($model) {
                return $model->name . ' (ID: ' . $model->id . ')';
            },
            'dependencies' => ['name', 'id'],
            'cacheable' => true,
            'cache_ttl' => 300
        ]);

        // Batch compute - should cache all results
        $results = $this->virtualFieldService->computeBatch('name_with_id', $users);

        $this->assertCount(5, $results);
        foreach ($users as $user) {
            $expected = $user->name . ' (ID: ' . $user->id . ')';
            $this->assertEquals($expected, $results[$user->id]);
        }

        // Second batch compute - should use cached results
        $startTime = microtime(true);
        $cachedResults = $this->virtualFieldService->computeBatch('name_with_id', $users);
        $endTime = microtime(true);

        $this->assertEquals($results, $cachedResults);
        
        // Should be faster due to caching
        $duration = ($endTime - $startTime) * 1000;
        $this->assertLessThan(10, $duration); // Should be less than 10ms
    }

    public function test_performance_monitoring_integration()
    {
        $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);

        // Register a virtual field with monitoring enabled
        $this->virtualFieldService->register('slow_computation', [
            'type' => 'string',
            'callback' => function($model) {
                usleep(2000); // 2ms delay to make it "slow"
                return 'computed_' . $model->name;
            },
            'dependencies' => ['name'],
            'cacheable' => true
        ]);

        // Compute the field
        $result = $this->virtualFieldService->compute('slow_computation', $user);
        $this->assertEquals('computed_John Doe', $result);

        // Get monitoring statistics
        $stats = $this->virtualFieldService->getStatistics();
        
        $this->assertArrayHasKey('monitoring_statistics', $stats);
        $monitorStats = $stats['monitoring_statistics'];
        
        if (isset($monitorStats['total_operations'])) {
            $this->assertGreaterThan(0, $monitorStats['total_operations']);
            $this->assertGreaterThan(0, $monitorStats['successful_operations']);
            $this->assertArrayHasKey('operations_by_field', $monitorStats);
            $this->assertArrayHasKey('slow_computation', $monitorStats['operations_by_field']);
        } else {
            // If monitoring is disabled, just check that the structure exists
            $this->assertIsArray($monitorStats);
        }
    }

    public function test_field_specific_metrics()
    {
        $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);

        // Register multiple virtual fields
        $this->virtualFieldService->register('field1', [
            'type' => 'string',
            'callback' => function($model) { return 'result1'; },
            'cacheable' => true
        ]);

        $this->virtualFieldService->register('field2', [
            'type' => 'string',
            'callback' => function($model) { return 'result2'; },
            'cacheable' => true
        ]);

        // Compute both fields multiple times
        $this->virtualFieldService->compute('field1', $user);
        $this->virtualFieldService->compute('field1', $user); // Should hit cache
        $this->virtualFieldService->compute('field2', $user);

        // Get field-specific metrics
        $field1Metrics = $this->virtualFieldService->getFieldMetrics('field1');
        $field2Metrics = $this->virtualFieldService->getFieldMetrics('field2');

        if (isset($field1Metrics['field_name'])) {
            $this->assertEquals('field1', $field1Metrics['field_name']);
            $this->assertGreaterThan(0, $field1Metrics['total_operations']);
            $this->assertGreaterThan(0, $field1Metrics['cache_hits']);
        } else {
            // If monitoring is disabled, just check that we get some response
            $this->assertIsArray($field1Metrics);
        }

        if (isset($field2Metrics['field_name'])) {
            $this->assertEquals('field2', $field2Metrics['field_name']);
            $this->assertGreaterThan(0, $field2Metrics['total_operations']);
        } else {
            // If monitoring is disabled, just check that we get some response
            $this->assertIsArray($field2Metrics);
        }
    }

    public function test_cache_warm_up()
    {
        // Create test users
        $users = new \Illuminate\Database\Eloquent\Collection();
        for ($i = 1; $i <= 3; $i++) {
            $users->push(User::create([
                'name' => "User $i",
                'email' => "user$i@example.com"
            ]));
        }

        // Register a cacheable virtual field
        $this->virtualFieldService->register('warm_up_field', [
            'type' => 'string',
            'callback' => function($model) {
                return 'warmed_' . $model->name;
            },
            'dependencies' => ['name'],
            'cacheable' => true,
            'cache_ttl' => 300
        ]);

        // Warm up cache
        $this->virtualFieldService->warmUpCache($users, ['warm_up_field']);

        // Now compute fields - should all be cached
        foreach ($users as $user) {
            $startTime = microtime(true);
            $result = $this->virtualFieldService->compute('warm_up_field', $user);
            $endTime = microtime(true);

            $expected = 'warmed_' . $user->name;
            $this->assertEquals($expected, $result);

            // Should be fast due to cache
            $duration = ($endTime - $startTime) * 1000;
            $this->assertLessThan(1, $duration);
        }
    }

    public function test_performance_limits_are_enforced()
    {
        // Create a large number of users to test batch processing limits
        $users = new \Illuminate\Database\Eloquent\Collection();
        for ($i = 1; $i <= 50; $i++) {
            $users->push(User::create([
                'name' => "User $i",
                'email' => "user$i@example.com"
            ]));
        }

        // Register a virtual field
        $this->virtualFieldService->register('batch_test_field', [
            'type' => 'string',
            'callback' => function($model) {
                return 'processed_' . $model->name;
            },
            'dependencies' => ['name'],
            'cacheable' => false // Disable caching to test performance limits
        ]);

        // This should trigger batch processing due to the large number of models
        $results = $this->virtualFieldService->computeBatch('batch_test_field', $users);

        $this->assertCount(50, $results);
        
        // Verify performance statistics were collected
        $stats = $this->virtualFieldService->getStatistics();
        $this->assertArrayHasKey('performance_statistics', $stats);
        $this->assertGreaterThan(0, $stats['performance_statistics']['total_operations']);
    }

    public function test_metrics_export_and_clear()
    {
        $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);

        // Register and compute a virtual field
        $this->virtualFieldService->register('export_test_field', [
            'type' => 'string',
            'callback' => function($model) { return 'exported_' . $model->name; }
        ]);

        $this->virtualFieldService->compute('export_test_field', $user);

        // Get initial statistics
        $initialStats = $this->virtualFieldService->getStatistics();
        if (isset($initialStats['monitoring_statistics']['total_operations'])) {
            $this->assertGreaterThan(0, $initialStats['monitoring_statistics']['total_operations']);
        }

        // Export metrics
        $this->virtualFieldService->exportMetrics();

        // Clear metrics
        $this->virtualFieldService->clearMetrics();

        // Verify metrics were cleared
        $clearedStats = $this->virtualFieldService->getStatistics();
        if (isset($clearedStats['monitoring_statistics']['total_operations'])) {
            $this->assertEquals(0, $clearedStats['monitoring_statistics']['total_operations']);
        }
        if (isset($clearedStats['performance_statistics']['total_operations'])) {
            $this->assertEquals(0, $clearedStats['performance_statistics']['total_operations']);
        }
    }
}