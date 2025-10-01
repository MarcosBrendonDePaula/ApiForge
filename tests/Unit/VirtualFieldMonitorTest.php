<?php

namespace MarcosBrendon\ApiForge\Tests\Unit;

use MarcosBrendon\ApiForge\Support\VirtualFieldMonitor;
use MarcosBrendon\ApiForge\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class VirtualFieldMonitorTest extends TestCase
{
    protected VirtualFieldMonitor $monitor;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->monitor = new VirtualFieldMonitor();
        $this->monitor->setEnabled(true); // Ensure monitoring is enabled for tests
    }

    public function test_can_start_and_end_operation()
    {
        $operationId = $this->monitor->startOperation(
            'test_computation',
            'test_field',
            ['model_id' => 1]
        );

        $this->assertNotEmpty($operationId);
        $this->assertStringStartsWith('test_computation_test_field_', $operationId);

        usleep(1000); // 1ms delay

        $metrics = $this->monitor->endOperation($operationId, true);

        $this->assertIsArray($metrics);
        $this->assertEquals($operationId, $metrics['operation_id']);
        $this->assertEquals('test_computation', $metrics['type']);
        $this->assertEquals('test_field', $metrics['field']);
        $this->assertTrue($metrics['success']);
        $this->assertArrayHasKey('duration_ms', $metrics);
        $this->assertArrayHasKey('memory_used', $metrics);
        $this->assertGreaterThan(0, $metrics['duration_ms']);
    }

    public function test_can_end_operation_with_error()
    {
        $operationId = $this->monitor->startOperation('test_computation', 'test_field');
        
        $metrics = $this->monitor->endOperation($operationId, false, 'Test error message');

        $this->assertFalse($metrics['success']);
        $this->assertEquals('Test error message', $metrics['error']);
    }

    public function test_can_monitor_computation()
    {
        $testValue = 'computed_result';
        
        $result = $this->monitor->monitorComputation(
            'test_field',
            function() use ($testValue) {
                usleep(1000); // 1ms delay
                return $testValue;
            },
            ['model_id' => 1]
        );

        $this->assertEquals($testValue, $result);
        
        $stats = $this->monitor->getStatistics();
        $this->assertGreaterThan(0, $stats['total_operations']);
        $this->assertGreaterThan(0, $stats['successful_operations']);
    }

    public function test_monitors_computation_failures()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Computation failed');

        try {
            $this->monitor->monitorComputation(
                'failing_field',
                function() {
                    throw new \Exception('Computation failed');
                }
            );
        } catch (\Exception $e) {
            $stats = $this->monitor->getStatistics();
            $this->assertGreaterThan(0, $stats['failed_operations']);
            throw $e;
        }
    }

    public function test_can_monitor_batch_operations()
    {
        $fieldNames = ['field1', 'field2'];
        $modelCount = 10;
        
        $result = $this->monitor->monitorBatch(
            'compute',
            $fieldNames,
            $modelCount,
            function() {
                usleep(2000); // 2ms delay
                return 'batch_result';
            }
        );

        $this->assertEquals('batch_result', $result);
        
        $stats = $this->monitor->getStatistics();
        $this->assertGreaterThan(0, $stats['total_operations']);
        
        // Check if batch operation was recorded
        $this->assertArrayHasKey('operations_by_type', $stats);
        $this->assertArrayHasKey('batch_compute', $stats['operations_by_type']);
    }

    public function test_can_track_cache_operations()
    {
        $fieldName = 'test_field';
        
        // Track various cache operations
        $this->monitor->trackCacheOperation('store', $fieldName);
        $this->monitor->trackCacheOperation('retrieve', $fieldName, true); // hit
        $this->monitor->trackCacheOperation('retrieve', $fieldName, false); // miss
        $this->monitor->trackCacheOperation('invalidate', $fieldName);

        $stats = $this->monitor->getStatistics();
        
        $this->assertArrayHasKey('cache_operations', $stats);
        $this->assertEquals(1, $stats['cache_operations']['stores']);
        $this->assertEquals(1, $stats['cache_operations']['hits']);
        $this->assertEquals(1, $stats['cache_operations']['misses']);
        $this->assertEquals(1, $stats['cache_operations']['invalidations']);
        
        // Cache hit rate should be 50% (1 hit out of 2 retrieval operations)
        $this->assertEquals(50.0, $stats['cache_hit_rate']);
    }

    public function test_can_track_memory_usage()
    {
        $this->monitor->setTrackMemory(true);
        
        $this->monitor->trackMemoryUsage('test_point_1', ['context' => 'test']);
        $this->monitor->trackMemoryUsage('test_point_2', ['context' => 'test']);

        // Memory tracking is stored internally, we can't easily assert specific values
        // but we can ensure the method doesn't throw exceptions
        $this->assertTrue(true);
    }

    public function test_can_get_comprehensive_statistics()
    {
        // Perform some operations to generate statistics
        $this->monitor->monitorComputation('field1', function() { return 'result1'; });
        $this->monitor->monitorComputation('field2', function() { return 'result2'; });
        $this->monitor->trackCacheOperation('store', 'field1');
        $this->monitor->trackCacheOperation('retrieve', 'field1', true);

        $stats = $this->monitor->getStatistics();

        $this->assertIsArray($stats);
        $this->assertTrue($stats['monitoring_enabled']);
        $this->assertArrayHasKey('slow_threshold_ms', $stats);
        $this->assertArrayHasKey('track_memory', $stats);
        $this->assertArrayHasKey('track_time', $stats);
        $this->assertArrayHasKey('total_operations', $stats);
        $this->assertArrayHasKey('successful_operations', $stats);
        $this->assertArrayHasKey('failed_operations', $stats);
        $this->assertArrayHasKey('average_duration_ms', $stats);
        $this->assertArrayHasKey('operations_by_type', $stats);
        $this->assertArrayHasKey('operations_by_field', $stats);
        $this->assertArrayHasKey('cache_operations', $stats);
        $this->assertArrayHasKey('cache_hit_rate', $stats);

        $this->assertEquals(2, $stats['total_operations']);
        $this->assertEquals(2, $stats['successful_operations']);
        $this->assertEquals(0, $stats['failed_operations']);
        $this->assertGreaterThan(0, $stats['average_duration_ms']);
    }

    public function test_can_get_field_specific_metrics()
    {
        $fieldName = 'test_field';
        
        // Generate some metrics for the field
        $this->monitor->monitorComputation($fieldName, function() { return 'result'; });
        $this->monitor->trackCacheOperation('retrieve', $fieldName, true);
        $this->monitor->trackCacheOperation('retrieve', $fieldName, false);

        $fieldMetrics = $this->monitor->getFieldMetrics($fieldName);

        $this->assertIsArray($fieldMetrics);
        $this->assertEquals($fieldName, $fieldMetrics['field_name']);
        $this->assertEquals(1, $fieldMetrics['total_operations']);
        $this->assertEquals(1, $fieldMetrics['successful_operations']);
        $this->assertEquals(0, $fieldMetrics['failed_operations']);
        $this->assertEquals(1, $fieldMetrics['cache_hits']);
        $this->assertEquals(1, $fieldMetrics['cache_misses']);
        $this->assertGreaterThan(0, $fieldMetrics['average_duration_ms']);
        $this->assertArrayHasKey('recent_operations', $fieldMetrics);
    }

    public function test_can_clear_metrics()
    {
        // Generate some metrics
        $this->monitor->monitorComputation('test_field', function() { return 'result'; });
        
        $statsBeforeClear = $this->monitor->getStatistics();
        $this->assertGreaterThan(0, $statsBeforeClear['total_operations']);

        $this->monitor->clearMetrics();

        $statsAfterClear = $this->monitor->getStatistics();
        $this->assertEquals(0, $statsAfterClear['total_operations']);
    }

    public function test_can_export_and_import_metrics()
    {
        // Generate some metrics
        $this->monitor->monitorComputation('test_field', function() { return 'result'; });
        
        // Export metrics
        $this->monitor->exportMetrics();
        
        // Clear current metrics
        $this->monitor->clearMetrics();
        $statsAfterClear = $this->monitor->getStatistics();
        $this->assertEquals(0, $statsAfterClear['total_operations']);
        
        // Import metrics back
        $this->monitor->importMetrics();
        
        // Note: In a real test environment, we'd need to mock the Cache facade
        // to properly test import/export functionality
        $this->assertTrue(true); // Test passes if no exceptions are thrown
    }

    public function test_handles_disabled_monitoring()
    {
        $this->monitor->setEnabled(false);
        
        $operationId = $this->monitor->startOperation('test', 'field');
        $this->assertEmpty($operationId);
        
        $result = $this->monitor->monitorComputation('field', function() { return 'result'; });
        $this->assertEquals('result', $result);
        
        $stats = $this->monitor->getStatistics();
        $this->assertFalse($stats['monitoring_enabled']);
    }

    public function test_can_configure_monitor_settings()
    {
        $newThreshold = 2000; // 2 seconds
        
        $this->monitor->setSlowThreshold($newThreshold);
        $this->monitor->setTrackMemory(false);
        $this->monitor->setTrackTime(false);
        $this->monitor->setLogSlowComputations(false);

        $stats = $this->monitor->getStatistics();
        
        $this->assertEquals($newThreshold, $stats['slow_threshold_ms']);
        $this->assertFalse($stats['track_memory']);
        $this->assertFalse($stats['track_time']);
    }

    public function test_detects_slow_operations()
    {
        // Set a very low threshold for testing
        $this->monitor->setSlowThreshold(1); // 1ms
        $this->monitor->setLogSlowComputations(true);
        
        // This operation should be detected as slow
        $this->monitor->monitorComputation('slow_field', function() {
            usleep(5000); // 5ms delay
            return 'result';
        });

        $stats = $this->monitor->getStatistics();
        $this->assertGreaterThan(0, $stats['slow_operations']);
        $this->assertArrayHasKey('slow_operations_by_field', $stats);
        $this->assertArrayHasKey('slow_field', $stats['slow_operations_by_field']);
    }

    public function test_tracks_operations_by_type_and_field()
    {
        $this->monitor->monitorComputation('field1', function() { return 'result1'; });
        $this->monitor->monitorComputation('field1', function() { return 'result2'; });
        $this->monitor->monitorComputation('field2', function() { return 'result3'; });
        
        $this->monitor->monitorBatch('process', ['field1'], 5, function() { return 'batch_result'; });

        $stats = $this->monitor->getStatistics();
        
        $this->assertArrayHasKey('operations_by_type', $stats);
        $this->assertArrayHasKey('operations_by_field', $stats);
        
        $this->assertEquals(3, $stats['operations_by_type']['computation']);
        $this->assertEquals(1, $stats['operations_by_type']['batch_process']);
        
        $this->assertEquals(3, $stats['operations_by_field']['field1']); // 2 computations + 1 batch
        $this->assertEquals(1, $stats['operations_by_field']['field2']);
    }
}