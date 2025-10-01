<?php

namespace MarcosBrendon\ApiForge\Tests\Unit;

use MarcosBrendon\ApiForge\Support\VirtualFieldPerformanceManager;
use MarcosBrendon\ApiForge\Support\VirtualFieldRegistry;
use MarcosBrendon\ApiForge\Support\VirtualFieldDefinition;
use MarcosBrendon\ApiForge\Tests\TestCase;
use MarcosBrendon\ApiForge\Exceptions\FilterValidationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class VirtualFieldPerformanceManagerTest extends TestCase
{
    protected VirtualFieldPerformanceManager $performanceManager;
    protected Collection $testModels;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->performanceManager = new VirtualFieldPerformanceManager();
        
        // Create test models
        $modelClass = new class extends Model {
            protected $fillable = ['id', 'name'];
            
            public function getKey()
            {
                return $this->attributes['id'] ?? 1;
            }
        };
        
        $this->testModels = new Collection([
            $modelClass->newInstance(['id' => 1, 'name' => 'Model 1']),
            $modelClass->newInstance(['id' => 2, 'name' => 'Model 2']),
            $modelClass->newInstance(['id' => 3, 'name' => 'Model 3']),
        ]);
    }

    public function test_can_execute_with_monitoring()
    {
        $testValue = 'test_result';
        
        $result = $this->performanceManager->executeWithMonitoring(
            'test_operation',
            function() use ($testValue) {
                usleep(1000); // 1ms delay
                return $testValue;
            },
            ['test' => 'context']
        );

        $this->assertEquals($testValue, $result);
        
        $stats = $this->performanceManager->getPerformanceStatistics();
        $this->assertGreaterThan(0, $stats['total_operations']);
        $this->assertGreaterThan(0, $stats['successful_operations']);
        $this->assertEquals(0, $stats['failed_operations']);
    }

    public function test_monitors_failed_operations()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test exception');

        try {
            $this->performanceManager->executeWithMonitoring(
                'failing_operation',
                function() {
                    throw new \Exception('Test exception');
                },
                ['test' => 'context']
            );
        } catch (\Exception $e) {
            $stats = $this->performanceManager->getPerformanceStatistics();
            $this->assertGreaterThan(0, $stats['total_operations']);
            $this->assertGreaterThan(0, $stats['failed_operations']);
            throw $e;
        }
    }

    public function test_can_process_batches()
    {
        $processedBatches = [];
        
        $results = $this->performanceManager->processBatches(
            $this->testModels,
            function($batch, $batchIndex) use (&$processedBatches) {
                $processedBatches[] = $batchIndex;
                return $batch->count();
            },
            ['batch_size' => 2]
        );

        $this->assertNotEmpty($results);
        $this->assertNotEmpty($processedBatches);
        
        // With batch size 2 and 3 models, we should have 2 batches
        $this->assertCount(2, $processedBatches);
    }

    public function test_enforces_memory_limits()
    {
        // Set a very low memory limit for testing
        $this->performanceManager->setMemoryLimit(1); // 1 byte
        
        $this->expectException(FilterValidationException::class);
        $this->expectExceptionMessageMatches('/memory limit/');
        
        $this->performanceManager->checkMemoryLimit();
    }

    public function test_enforces_time_limits()
    {
        // Set a very low time limit
        $this->performanceManager->setTimeLimit(0.001); // 1ms
        
        $this->expectException(FilterValidationException::class);
        $this->expectExceptionMessageMatches('/time limit/');
        
        $startTime = microtime(true) - 1; // 1 second ago
        $this->performanceManager->checkTimeLimit($startTime);
    }

    public function test_can_create_lazy_loader()
    {
        $registry = new VirtualFieldRegistry();
        
        // Register a test virtual field
        $definition = new VirtualFieldDefinition(
            'test_field',
            'string',
            function($model) {
                return 'computed_' . $model->name;
            }
        );
        $registry->add('test_field', $definition);
        
        $lazyLoader = $this->performanceManager->lazyLoadVirtualFields(
            $this->testModels,
            ['test_field'],
            $registry
        );

        $this->assertInstanceOf(\Closure::class, $lazyLoader);
        
        // Execute the lazy loader
        $results = $lazyLoader(['test_field']);
        
        $this->assertArrayHasKey('test_field', $results);
        $this->assertCount(3, $results['test_field']);
        $this->assertEquals('computed_Model 1', $results['test_field'][1]);
    }

    public function test_can_optimize_sorting()
    {
        $registry = new VirtualFieldRegistry();
        
        // Register a sortable virtual field
        $definition = new VirtualFieldDefinition(
            'name_length',
            'integer',
            function($model) {
                return strlen($model->name);
            },
            [],
            [],
            [],
            false,
            3600,
            null,
            true,
            true // sortable
        );
        $registry->add('name_length', $definition);
        
        $sortedModels = $this->performanceManager->optimizeSorting(
            $this->testModels,
            'name_length',
            'asc',
            $registry
        );

        $this->assertInstanceOf(Collection::class, $sortedModels);
        $this->assertCount(3, $sortedModels);
    }

    public function test_handles_large_dataset_sorting()
    {
        // Set a very low max sort records for testing
        $registry = new VirtualFieldRegistry();
        
        $definition = new VirtualFieldDefinition(
            'test_field',
            'string',
            function($model) { return $model->name; },
            [],
            [],
            [],
            false,
            3600,
            null,
            true,
            true
        );
        $registry->add('test_field', $definition);
        
        // Create a large collection (larger than max_sort_records)
        $largeCollection = new Collection();
        for ($i = 0; $i < 20000; $i++) {
            $model = $this->testModels->first()->newInstance(['id' => $i, 'name' => "Model $i"]);
            $largeCollection->push($model);
        }
        
        // This should trigger the large dataset warning
        $result = $this->performanceManager->optimizeSorting(
            $largeCollection,
            'test_field',
            'asc',
            $registry
        );
        
        // Should return the original collection (fallback behavior)
        $this->assertEquals($largeCollection, $result);
    }

    public function test_can_force_garbage_collection()
    {
        // This test just ensures the method doesn't throw exceptions
        $this->performanceManager->forceGarbageCollection();
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function test_can_get_performance_statistics()
    {
        // Execute some operations first
        $this->performanceManager->executeWithMonitoring(
            'test_op_1',
            function() { return 'result1'; }
        );
        
        $this->performanceManager->executeWithMonitoring(
            'test_op_2',
            function() { return 'result2'; }
        );

        $stats = $this->performanceManager->getPerformanceStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_operations', $stats);
        $this->assertArrayHasKey('successful_operations', $stats);
        $this->assertArrayHasKey('failed_operations', $stats);
        $this->assertArrayHasKey('average_duration', $stats);
        $this->assertArrayHasKey('memory_limit', $stats);
        $this->assertArrayHasKey('time_limit', $stats);
        $this->assertArrayHasKey('batch_size', $stats);
        $this->assertArrayHasKey('lazy_loading_enabled', $stats);
        
        $this->assertEquals(2, $stats['total_operations']);
        $this->assertEquals(2, $stats['successful_operations']);
        $this->assertEquals(0, $stats['failed_operations']);
    }

    public function test_can_clear_performance_data()
    {
        // Execute an operation first
        $this->performanceManager->executeWithMonitoring(
            'test_operation',
            function() { return 'result'; }
        );
        
        $statsBeforeClear = $this->performanceManager->getPerformanceStatistics();
        $this->assertGreaterThan(0, $statsBeforeClear['total_operations']);
        
        $this->performanceManager->clearPerformanceData();
        
        $statsAfterClear = $this->performanceManager->getPerformanceStatistics();
        $this->assertEquals(0, $statsAfterClear['total_operations']);
    }

    public function test_can_configure_performance_settings()
    {
        $newMemoryLimit = 256 * 1024 * 1024; // 256MB
        $newTimeLimit = 60;
        $newBatchSize = 500;
        
        $this->performanceManager->setMemoryLimit($newMemoryLimit);
        $this->performanceManager->setTimeLimit($newTimeLimit);
        $this->performanceManager->setBatchSize($newBatchSize);
        $this->performanceManager->setLazyLoadingEnabled(false);
        $this->performanceManager->setLogPerformance(true);
        
        $stats = $this->performanceManager->getPerformanceStatistics();
        
        $this->assertArrayHasKey('memory_limit', $stats);
        $this->assertArrayHasKey('time_limit', $stats);
        $this->assertArrayHasKey('batch_size', $stats);
        $this->assertArrayHasKey('lazy_loading_enabled', $stats);
        $this->assertEquals($newMemoryLimit, $stats['memory_limit']);
        $this->assertEquals($newTimeLimit, $stats['time_limit']);
        $this->assertEquals($newBatchSize, $stats['batch_size']);
        $this->assertFalse($stats['lazy_loading_enabled']);
    }

    public function test_batch_processing_continues_on_error_when_configured()
    {
        $processedBatches = [];
        $errorBatch = 1; // Second batch will fail
        
        $results = $this->performanceManager->processBatches(
            $this->testModels,
            function($batch, $batchIndex) use (&$processedBatches, $errorBatch) {
                $processedBatches[] = $batchIndex;
                
                if ($batchIndex === $errorBatch) {
                    throw new \Exception('Batch processing error');
                }
                
                return $batch->count();
            },
            [
                'batch_size' => 1,
                'continue_on_error' => true
            ]
        );

        // Should have processed some batches despite the error
        $this->assertNotEmpty($processedBatches);
        $this->assertContains($errorBatch, $processedBatches);
    }

    public function test_batch_processing_stops_on_error_when_strict()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Batch processing error');
        
        $this->performanceManager->processBatches(
            $this->testModels,
            function($batch, $batchIndex) {
                if ($batchIndex === 0) {
                    throw new \Exception('Batch processing error');
                }
                return $batch->count();
            },
            [
                'batch_size' => 1,
                'continue_on_error' => false
            ]
        );
    }
}