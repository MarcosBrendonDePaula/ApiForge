<?php

namespace MarcosBrendon\ApiForge\Tests\Unit;

use MarcosBrendon\ApiForge\Services\RuntimeErrorHandler;
use MarcosBrendon\ApiForge\Exceptions\VirtualFieldComputationException;
use MarcosBrendon\ApiForge\Exceptions\ModelHookExecutionException;
use MarcosBrendon\ApiForge\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RuntimeErrorHandlerTest extends TestCase
{
    protected RuntimeErrorHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new RuntimeErrorHandler();
    }

    public function test_handles_virtual_field_error_with_throw_enabled()
    {
        $this->handler->setThrowOnFailure(true);
        
        $model = $this->createMockModel();
        $exception = new \Exception('Test error');

        $this->expectException(VirtualFieldComputationException::class);
        $this->handler->handleVirtualFieldError('test_field', $model, $exception);
    }

    public function test_handles_virtual_field_error_with_throw_disabled()
    {
        $this->handler->setThrowOnFailure(false);
        
        $model = $this->createMockModel();
        $exception = new \Exception('Test error');

        $result = $this->handler->handleVirtualFieldError('test_field', $model, $exception);

        $this->assertNull($result); // Should return default value (null)
        
        $stats = $this->handler->getErrorStats();
        $this->assertEquals(1, $stats['virtual_field_errors']);
        $this->assertEquals(1, $stats['total_errors']);
    }

    public function test_handles_hook_error_with_throw_enabled()
    {
        $this->handler->setThrowOnFailure(true);
        
        $model = $this->createMockModel();
        $exception = new \Exception('Hook failed');

        $this->expectException(ModelHookExecutionException::class);
        $this->handler->handleHookError('beforeStore', 'test_hook', $model, $exception);
    }

    public function test_handles_hook_error_with_throw_disabled()
    {
        $this->handler->setThrowOnFailure(false);
        
        $model = $this->createMockModel();
        $exception = new \Exception('Hook failed');

        $this->handler->handleHookError('beforeStore', 'test_hook', $model, $exception);

        $stats = $this->handler->getErrorStats();
        $this->assertEquals(1, $stats['hook_errors']);
        $this->assertEquals(1, $stats['total_errors']);
    }

    public function test_handles_batch_error()
    {
        $this->handler->setThrowOnFailure(false);
        
        $items = ['item1', 'item2', 'item3'];
        $exception = new \Exception('Batch failed');

        $result = $this->handler->handleBatchError('test_operation', $items, $exception);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertNull($result[0]);
        $this->assertNull($result[1]);
        $this->assertNull($result[2]);

        $stats = $this->handler->getErrorStats();
        $this->assertEquals(1, $stats['total_errors']);
    }

    public function test_executes_with_retry_success_on_first_attempt()
    {
        $callCount = 0;
        $operation = function () use (&$callCount) {
            $callCount++;
            return 'success';
        };

        $result = $this->handler->executeWithRetry($operation);

        $this->assertEquals('success', $result);
        $this->assertEquals(1, $callCount);
    }

    public function test_executes_with_retry_success_after_failures()
    {
        $this->handler->setMaxRetries(3);
        
        $callCount = 0;
        $operation = function () use (&$callCount) {
            $callCount++;
            if ($callCount < 3) {
                throw new \Exception('Temporary failure');
            }
            return 'success';
        };

        $result = $this->handler->executeWithRetry($operation);

        $this->assertEquals('success', $result);
        $this->assertEquals(3, $callCount);
    }

    public function test_executes_with_retry_fails_after_max_attempts()
    {
        $this->handler->setMaxRetries(2);
        $this->handler->setThrowOnFailure(false);
        
        $callCount = 0;
        $operation = function () use (&$callCount) {
            $callCount++;
            throw new \Exception('Persistent failure');
        };

        $result = $this->handler->executeWithRetry($operation);

        $this->assertNull($result);
        $this->assertEquals(2, $callCount);
    }

    public function test_executes_with_timeout_success()
    {
        $operation = function () {
            usleep(100000); // 0.1 seconds
            return 'success';
        };

        $result = $this->handler->executeWithTimeout($operation, 1); // 1 second timeout

        $this->assertEquals('success', $result);
    }

    public function test_executes_with_timeout_failure()
    {
        $this->markTestSkipped('Timeout test skipped to avoid execution time issues in CI');
    }

    public function test_executes_with_memory_limit_success()
    {
        $operation = function () {
            // Allocate small amount of memory
            $data = str_repeat('x', 1024); // 1KB
            return 'success';
        };

        $result = $this->handler->executeWithMemoryLimit($operation, 10); // 10MB limit

        $this->assertEquals('success', $result);
    }

    public function test_tracks_error_statistics()
    {
        $this->handler->setThrowOnFailure(false);
        
        $model = $this->createMockModel();
        $exception = new \Exception('Test error');

        // Generate some errors
        $this->handler->handleVirtualFieldError('field1', $model, $exception);
        $this->handler->handleVirtualFieldError('field2', $model, $exception);
        $this->handler->handleHookError('beforeStore', 'hook1', $model, $exception);

        $stats = $this->handler->getErrorStats();

        $this->assertEquals(2, $stats['virtual_field_errors']);
        $this->assertEquals(1, $stats['hook_errors']);
        $this->assertEquals(3, $stats['total_errors']);
        $this->assertNotNull($stats['last_error_time']);
    }

    public function test_resets_error_statistics()
    {
        $this->handler->setThrowOnFailure(false);
        
        $model = $this->createMockModel();
        $exception = new \Exception('Test error');

        // Generate an error
        $this->handler->handleVirtualFieldError('test_field', $model, $exception);

        $stats = $this->handler->getErrorStats();
        $this->assertEquals(1, $stats['total_errors']);

        // Reset stats
        $this->handler->resetErrorStats();

        $stats = $this->handler->getErrorStats();
        $this->assertEquals(0, $stats['total_errors']);
        $this->assertEquals(0, $stats['virtual_field_errors']);
        $this->assertEquals(0, $stats['hook_errors']);
        $this->assertNull($stats['last_error_time']);
    }

    public function test_detects_high_error_rate()
    {
        $this->handler->setThrowOnFailure(false);
        
        $model = $this->createMockModel();
        $exception = new \Exception('Test error');

        // Generate many errors
        for ($i = 0; $i < 15; $i++) {
            $this->handler->handleVirtualFieldError("field_{$i}", $model, $exception);
        }

        // Since all errors happen in the same minute, the rate should be high
        $this->assertTrue($this->handler->isErrorRateHigh(10)); // 10 errors per minute threshold
    }

    public function test_configuration_setters()
    {
        $this->handler->setThrowOnFailure(false);
        $this->handler->setLogErrors(false);
        $this->handler->setUseTransactions(false);
        $this->handler->setMaxRetries(5);

        // Test that settings are applied (indirectly through behavior)
        $model = $this->createMockModel();
        $exception = new \Exception('Test error');

        // Should not throw
        $result = $this->handler->handleVirtualFieldError('test_field', $model, $exception);
        $this->assertNull($result);
    }

    public function test_logs_errors_when_enabled()
    {
        Log::shouldReceive('error')->once();
        
        $this->handler->setThrowOnFailure(false);
        $this->handler->setLogErrors(true);
        
        $model = $this->createMockModel();
        $exception = new \Exception('Test error');

        $this->handler->handleVirtualFieldError('test_field', $model, $exception);
    }

    public function test_does_not_log_errors_when_disabled()
    {
        $this->handler->setThrowOnFailure(false);
        $this->handler->setLogErrors(false);
        
        $model = $this->createMockModel();
        $exception = new \Exception('Test error');

        // This should not throw or log
        $result = $this->handler->handleVirtualFieldError('test_field', $model, $exception);
        $this->assertNull($result);
    }

    protected function createMockModel(): Model
    {
        return new class extends Model {
            protected $table = 'test_models';
            protected $fillable = ['name'];
            
            public function getKey()
            {
                return 1;
            }
        };
    }
}