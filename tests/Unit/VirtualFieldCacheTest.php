<?php

namespace MarcosBrendon\ApiForge\Tests\Unit;

use MarcosBrendon\ApiForge\Support\VirtualFieldCache;
use MarcosBrendon\ApiForge\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class VirtualFieldCacheTest extends TestCase
{
    protected VirtualFieldCache $cache;
    protected Model $testModel;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->cache = new VirtualFieldCache();
        
        // Create a test model
        $this->testModel = new class extends Model {
            protected $fillable = ['id', 'name'];
            
            public function getKey()
            {
                return $this->attributes['id'] ?? 1;
            }
        };
        
        $this->testModel->fill(['id' => 1, 'name' => 'Test Model']);
    }

    public function test_can_store_and_retrieve_cache_value()
    {
        $fieldName = 'test_field';
        $value = 'cached_value';
        $ttl = 60;

        // Store value
        $success = $this->cache->store($fieldName, $this->testModel, $value, $ttl);
        $this->assertTrue($success);

        // Retrieve value
        $retrieved = $this->cache->retrieve($fieldName, $this->testModel);
        $this->assertEquals($value, $retrieved);
    }

    public function test_can_invalidate_cache_entry()
    {
        $fieldName = 'test_field';
        $value = 'cached_value';

        // Store value
        $this->cache->store($fieldName, $this->testModel, $value);
        
        // Verify it's cached
        $retrieved = $this->cache->retrieve($fieldName, $this->testModel);
        $this->assertEquals($value, $retrieved);

        // Invalidate
        $success = $this->cache->invalidate($fieldName, $this->testModel);
        $this->assertTrue($success);

        // Verify it's gone
        $retrievedAfterInvalidate = $this->cache->retrieve($fieldName, $this->testModel);
        $this->assertNull($retrievedAfterInvalidate);
    }

    public function test_can_batch_store_values()
    {
        $model1 = clone $this->testModel;
        $model1->fill(['id' => 1]);
        
        $model2 = clone $this->testModel;
        $model2->fill(['id' => 2]);

        $values = [
            [
                'field' => 'field1',
                'model' => $model1,
                'value' => 'value1',
                'ttl' => 60
            ],
            [
                'field' => 'field2',
                'model' => $model2,
                'value' => 'value2',
                'ttl' => 120
            ]
        ];

        $results = $this->cache->storeBatch($values);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertTrue($results[1]['success']);

        // Verify values were stored
        $this->assertEquals('value1', $this->cache->retrieve('field1', $model1));
        $this->assertEquals('value2', $this->cache->retrieve('field2', $model2));
    }

    public function test_can_batch_retrieve_values()
    {
        $model1 = clone $this->testModel;
        $model1->fill(['id' => 1]);
        
        $model2 = clone $this->testModel;
        $model2->fill(['id' => 2]);

        // Store some values first
        $this->cache->store('field1', $model1, 'value1');
        $this->cache->store('field2', $model2, 'value2');

        $requests = [
            ['field' => 'field1', 'model' => $model1],
            ['field' => 'field2', 'model' => $model2],
            ['field' => 'nonexistent', 'model' => $model1]
        ];

        $results = $this->cache->retrieveBatch($requests);

        $this->assertCount(3, $results);
        $this->assertEquals('value1', $results[0]['value']);
        $this->assertTrue($results[0]['hit']);
        $this->assertEquals('value2', $results[1]['value']);
        $this->assertTrue($results[1]['hit']);
        $this->assertNull($results[2]['value']);
        $this->assertFalse($results[2]['hit']);
    }

    public function test_can_invalidate_model_cache()
    {
        $fields = ['field1', 'field2', 'field3'];
        
        // Store multiple fields for the model
        foreach ($fields as $field) {
            $this->cache->store($field, $this->testModel, "value_$field");
        }

        // Verify all are cached
        foreach ($fields as $field) {
            $this->assertEquals("value_$field", $this->cache->retrieve($field, $this->testModel));
        }

        // Invalidate specific fields
        $invalidated = $this->cache->invalidateModel($this->testModel, ['field1', 'field2']);
        $this->assertGreaterThan(0, $invalidated);

        // Verify specific fields are invalidated but field3 remains
        $this->assertNull($this->cache->retrieve('field1', $this->testModel));
        $this->assertNull($this->cache->retrieve('field2', $this->testModel));
        $this->assertEquals('value_field3', $this->cache->retrieve('field3', $this->testModel));
    }

    public function test_generates_correct_cache_keys()
    {
        $fieldName = 'test_field';
        $modelClass = get_class($this->testModel);
        $modelId = $this->testModel->getKey();
        
        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($this->cache);
        $method = $reflection->getMethod('generateKey');
        $method->setAccessible(true);
        
        $key = $method->invoke($this->cache, $fieldName, $this->testModel);
        
        $expectedKey = 'vf_' . str_replace('\\', '_', $modelClass) . '_' . $modelId . '_' . $fieldName;
        $this->assertEquals($expectedKey, $key);
    }

    public function test_can_get_statistics()
    {
        $stats = $this->cache->getStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('key_prefix', $stats);
        $this->assertArrayHasKey('default_ttl', $stats);
        $this->assertArrayHasKey('store', $stats);
        $this->assertArrayHasKey('tags', $stats);
        $this->assertArrayHasKey('log_operations', $stats);
    }

    public function test_can_configure_cache_settings()
    {
        $newPrefix = 'custom_prefix_';
        $newTtl = 7200;
        $newStore = 'redis';

        $this->cache->setKeyPrefix($newPrefix);
        $this->cache->setDefaultTtl($newTtl);
        $this->cache->setStore($newStore);

        $stats = $this->cache->getStatistics();
        
        $this->assertEquals($newPrefix, $stats['key_prefix']);
        $this->assertEquals($newTtl, $stats['default_ttl']);
        $this->assertEquals($newStore, $stats['store']);
    }

    public function test_handles_cache_errors_gracefully()
    {
        // Mock Cache facade to throw exception
        Cache::shouldReceive('store')->andThrow(new \Exception('Cache error'));
        
        $result = $this->cache->store('test_field', $this->testModel, 'test_value');
        $this->assertFalse($result);
        
        $retrieved = $this->cache->retrieve('test_field', $this->testModel);
        $this->assertNull($retrieved);
    }

    public function test_can_flush_all_cache_entries()
    {
        // Store some values
        $this->cache->store('field1', $this->testModel, 'value1');
        $this->cache->store('field2', $this->testModel, 'value2');

        // Verify they're cached
        $this->assertEquals('value1', $this->cache->retrieve('field1', $this->testModel));
        $this->assertEquals('value2', $this->cache->retrieve('field2', $this->testModel));

        // Flush all
        $success = $this->cache->flush();
        $this->assertTrue($success);

        // Note: Since we can't easily test actual cache flushing in unit tests,
        // we just verify the method doesn't throw exceptions
    }
}