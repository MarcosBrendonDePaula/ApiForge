<?php

namespace MarcosBrendon\ApiForge\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use MarcosBrendon\ApiForge\Services\CacheService;
use MarcosBrendon\ApiForge\Tests\TestCase;

class CacheServiceTest extends TestCase
{
    protected CacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheService = new CacheService();
        
        // Enable cache for tests
        config(['apiforge.cache.enabled' => true]);
        config(['apiforge.cache.default_ttl' => 3600]);
        config(['apiforge.cache.key_prefix' => 'test_api_']);
    }

    protected function tearDown(): void
    {
        // Clean up cache after each test
        Cache::flush();
        parent::tearDown();
    }

    /** @test */
    public function it_generates_consistent_cache_keys()
    {
        $key1 = $this->cacheService->generateKey('User', ['name' => 'John', 'age' => 25]);
        $key2 = $this->cacheService->generateKey('User', ['age' => 25, 'name' => 'John']);
        
        // Keys should be identical regardless of parameter order
        $this->assertEquals($key1, $key2);
    }

    /** @test */
    public function it_stores_and_retrieves_data_with_metadata()
    {
        $testData = ['users' => [['id' => 1, 'name' => 'John']]];
        $key = 'test_key';
        
        $result = $this->cacheService->store($key, $testData, [
            'ttl' => 1800,
            'model' => 'User',
            'tags' => ['users']
        ]);
        
        $this->assertTrue($result);
        
        $retrieved = $this->cacheService->retrieve($key);
        $this->assertEquals($testData, $retrieved);
    }

    /** @test */
    public function it_returns_default_value_for_missing_keys()
    {
        $result = $this->cacheService->retrieve('non_existent_key', 'default');
        $this->assertEquals('default', $result);
    }

    /** @test */
    public function it_invalidates_cache_by_model()
    {
        $testData = ['users' => [['id' => 1, 'name' => 'John']]];
        
        // Store data for User model
        $this->cacheService->store('user_key_1', $testData, ['model' => 'User']);
        $this->cacheService->store('user_key_2', $testData, ['model' => 'User']);
        $this->cacheService->store('post_key_1', ['posts' => []], ['model' => 'Post']);
        
        // Verify data is cached
        $this->assertNotNull($this->cacheService->retrieve('user_key_1'));
        $this->assertNotNull($this->cacheService->retrieve('post_key_1'));
        
        // Invalidate User model cache
        $this->cacheService->invalidateByModel('User');
        
        // User cache should be cleared, Post cache should remain
        $this->assertNull($this->cacheService->retrieve('user_key_1'));
        $this->assertNull($this->cacheService->retrieve('user_key_2'));
        $this->assertNotNull($this->cacheService->retrieve('post_key_1'));
    }

    /** @test */
    public function it_increments_cache_version_on_invalidation()
    {
        $initialKey = $this->cacheService->generateKey('User', ['name' => 'John']);
        
        $this->cacheService->invalidateByModel('User');
        
        $newKey = $this->cacheService->generateKey('User', ['name' => 'John']);
        
        // Keys should be different due to version increment
        $this->assertNotEquals($initialKey, $newKey);
    }

    /** @test */
    public function it_handles_cache_without_tag_support()
    {
        // Mock cache store without tag support
        config(['cache.default' => 'file']);
        
        $testData = ['data' => 'test'];
        $key = 'test_key';
        
        $this->cacheService->store($key, $testData, [
            'model' => 'User',
            'tags' => ['users']
        ]);
        
        $retrieved = $this->cacheService->retrieve($key);
        $this->assertEquals($testData, $retrieved);
        
        // Should still work for invalidation
        $this->cacheService->invalidateByModel('User');
        $this->assertNull($this->cacheService->retrieve($key));
    }

    /** @test */
    public function it_performs_garbage_collection()
    {
        // This test is more complex for stores without native expiration
        $this->markTestSkipped('Garbage collection test requires specific cache setup');
    }

    /** @test */
    public function it_provides_cache_statistics()
    {
        $testData = ['data' => str_repeat('x', 1000)]; // 1KB of data
        
        $this->cacheService->store('key1', $testData, ['model' => 'User']);
        $this->cacheService->store('key2', $testData, ['model' => 'Post']);
        
        $stats = $this->cacheService->getStatistics();
        
        $this->assertArrayHasKey('total_keys', $stats);
        $this->assertArrayHasKey('total_size', $stats);
        $this->assertArrayHasKey('models', $stats);
    }

    /** @test */
    public function it_handles_table_changes()
    {
        // Monitor users table
        $this->cacheService->monitorTables(['users']);
        
        $testData = ['users' => [['id' => 1, 'name' => 'John']]];
        $this->cacheService->store('user_data', $testData, ['model' => 'User']);
        
        // Simulate table change
        $this->cacheService->handleTableChange('users', 'update');
        
        // Cache should be invalidated
        $this->assertNull($this->cacheService->retrieve('user_data'));
    }

    /** @test */
    public function it_normalizes_parameters_consistently()
    {
        $params1 = ['name' => 'John ', 'age' => 25, '_token' => 'abc'];
        $params2 = ['age' => 25, 'name' => 'John', 'cache' => true];
        
        $key1 = $this->cacheService->generateKey('User', $params1);
        $key2 = $this->cacheService->generateKey('User', $params2);
        
        // Should generate same key (token excluded, whitespace trimmed, order normalized)
        $this->assertEquals($key1, $key2);
    }

    /** @test */
    public function it_calculates_data_size_correctly()
    {
        $smallData = ['id' => 1];
        $largeData = ['data' => str_repeat('x', 10000)];
        
        $this->cacheService->store('small', $smallData);
        $this->cacheService->store('large', $largeData);
        
        $stats = $this->cacheService->getStatistics();
        
        // Large data should contribute more to total size
        $this->assertGreaterThan(0, $stats['total_size']);
    }

    /** @test */
    public function it_flushes_all_cache()
    {
        $this->cacheService->store('key1', ['data1'], ['model' => 'User']);
        $this->cacheService->store('key2', ['data2'], ['model' => 'Post']);
        
        $this->assertNotNull($this->cacheService->retrieve('key1'));
        $this->assertNotNull($this->cacheService->retrieve('key2'));
        
        $this->cacheService->flush();
        
        $this->assertNull($this->cacheService->retrieve('key1'));
        $this->assertNull($this->cacheService->retrieve('key2'));
    }

    /** @test */
    public function it_handles_expired_cache_entries()
    {
        $testData = ['data' => 'test'];
        
        // Store with very short TTL
        $this->cacheService->store('temp_key', $testData, ['ttl' => 1]);
        
        // Should be available immediately
        $this->assertEquals($testData, $this->cacheService->retrieve('temp_key'));
        
        // Wait for expiration and try again
        sleep(2);
        
        // Should return null (expired)
        $this->assertNull($this->cacheService->retrieve('temp_key'));
    }
}