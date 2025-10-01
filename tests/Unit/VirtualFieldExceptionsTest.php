<?php

namespace MarcosBrendon\ApiForge\Tests\Unit;

use MarcosBrendon\ApiForge\Exceptions\VirtualFieldException;
use MarcosBrendon\ApiForge\Exceptions\VirtualFieldConfigurationException;
use MarcosBrendon\ApiForge\Exceptions\VirtualFieldComputationException;
use MarcosBrendon\ApiForge\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;

class VirtualFieldExceptionsTest extends TestCase
{
    public function test_virtual_field_configuration_exception_invalid_field_name()
    {
        $exception = VirtualFieldConfigurationException::invalidFieldName('');

        $this->assertInstanceOf(VirtualFieldConfigurationException::class, $exception);
        $this->assertStringContainsString('Invalid virtual field name', $exception->getMessage());
        $this->assertEquals('VIRTUAL_FIELD_CONFIGURATION_ERROR', $exception->getErrorCode());
        $this->assertEquals(400, $exception->getStatusCode());
        
        $context = $exception->getContext();
        $this->assertArrayHasKey('field_name', $context);
    }

    public function test_virtual_field_configuration_exception_invalid_field_type()
    {
        $exception = VirtualFieldConfigurationException::invalidFieldType(
            'test_field',
            'invalid_type',
            ['string', 'integer', 'boolean']
        );

        $this->assertStringContainsString('Invalid type', $exception->getMessage());
        $this->assertStringContainsString('test_field', $exception->getMessage());
        $this->assertStringContainsString('invalid_type', $exception->getMessage());
        
        $context = $exception->getContext();
        $this->assertEquals('test_field', $context['field_name']);
        $this->assertEquals('invalid_type', $context['invalid_type']);
        $this->assertEquals(['string', 'integer', 'boolean'], $context['valid_types']);
    }

    public function test_virtual_field_configuration_exception_invalid_callback()
    {
        $exception = VirtualFieldConfigurationException::invalidCallback('test_field', 'not_callable');

        $this->assertStringContainsString('Invalid callback', $exception->getMessage());
        $this->assertStringContainsString('test_field', $exception->getMessage());
        
        $context = $exception->getContext();
        $this->assertEquals('test_field', $context['field_name']);
        $this->assertEquals('string', $context['callback_type']);
    }

    public function test_virtual_field_configuration_exception_invalid_operators()
    {
        $exception = VirtualFieldConfigurationException::invalidOperators(
            'test_field',
            'boolean',
            ['like', 'starts_with'],
            ['eq', 'ne', 'null', 'not_null']
        );

        $this->assertStringContainsString('Invalid operators', $exception->getMessage());
        $this->assertStringContainsString('test_field', $exception->getMessage());
        $this->assertStringContainsString('boolean', $exception->getMessage());
        
        $context = $exception->getContext();
        $this->assertEquals('test_field', $context['field_name']);
        $this->assertEquals('boolean', $context['field_type']);
        $this->assertEquals(['like', 'starts_with'], $context['invalid_operators']);
        $this->assertEquals(['eq', 'ne', 'null', 'not_null'], $context['valid_operators']);
    }

    public function test_virtual_field_configuration_exception_duplicate_field()
    {
        $exception = VirtualFieldConfigurationException::duplicateField('existing_field');

        $this->assertStringContainsString('already registered', $exception->getMessage());
        $this->assertStringContainsString('existing_field', $exception->getMessage());
        
        $context = $exception->getContext();
        $this->assertEquals('existing_field', $context['field_name']);
    }

    public function test_virtual_field_configuration_exception_circular_dependency()
    {
        $dependencyChain = ['field_a', 'field_b', 'field_c', 'field_a'];
        $exception = VirtualFieldConfigurationException::circularDependency('field_a', $dependencyChain);

        $this->assertStringContainsString('Circular dependency', $exception->getMessage());
        $this->assertStringContainsString('field_a', $exception->getMessage());
        
        $context = $exception->getContext();
        $this->assertEquals('field_a', $context['field_name']);
        $this->assertEquals($dependencyChain, $context['dependency_chain']);
    }

    public function test_virtual_field_computation_exception_callback_failed()
    {
        $model = $this->createTestModel();
        $originalException = new \Exception('Original error', 0);
        
        $exception = VirtualFieldComputationException::callbackFailed('test_field', $model, $originalException);

        $this->assertInstanceOf(VirtualFieldComputationException::class, $exception);
        $this->assertStringContainsString('Failed to compute', $exception->getMessage());
        $this->assertStringContainsString('test_field', $exception->getMessage());
        $this->assertEquals('VIRTUAL_FIELD_COMPUTATION_ERROR', $exception->getErrorCode());
        $this->assertEquals(500, $exception->getStatusCode());
        
        $context = $exception->getContext();
        $this->assertEquals('test_field', $context['field_name']);
        $this->assertEquals(get_class($model), $context['model_class']);
        $this->assertEquals($model->getKey(), $context['model_id']);
        $this->assertEquals('Original error', $context['original_error']);
        
        $this->assertSame($originalException, $exception->getPrevious());
    }

    public function test_virtual_field_computation_exception_missing_dependency()
    {
        $model = $this->createTestModel();
        
        $exception = VirtualFieldComputationException::missingDependency('test_field', 'missing_field', $model);

        $this->assertStringContainsString('Missing dependency', $exception->getMessage());
        $this->assertStringContainsString('missing_field', $exception->getMessage());
        $this->assertStringContainsString('test_field', $exception->getMessage());
        
        $context = $exception->getContext();
        $this->assertEquals('test_field', $context['field_name']);
        $this->assertEquals('missing_field', $context['missing_dependency']);
        $this->assertEquals(get_class($model), $context['model_class']);
    }

    public function test_virtual_field_computation_exception_missing_relationship()
    {
        $model = $this->createTestModel();
        
        $exception = VirtualFieldComputationException::missingRelationship('test_field', 'missing_relation', $model);

        $this->assertStringContainsString('Missing relationship', $exception->getMessage());
        $this->assertStringContainsString('missing_relation', $exception->getMessage());
        $this->assertStringContainsString('test_field', $exception->getMessage());
        
        $context = $exception->getContext();
        $this->assertEquals('test_field', $context['field_name']);
        $this->assertEquals('missing_relation', $context['missing_relationship']);
    }

    public function test_virtual_field_computation_exception_invalid_return_type()
    {
        $model = $this->createTestModel();
        
        $exception = VirtualFieldComputationException::invalidReturnType('test_field', 'string', 123, $model);

        $this->assertStringContainsString('invalid type', $exception->getMessage());
        $this->assertStringContainsString('Expected \'string\'', $exception->getMessage());
        $this->assertStringContainsString('got \'integer\'', $exception->getMessage());
        
        $context = $exception->getContext();
        $this->assertEquals('test_field', $context['field_name']);
        $this->assertEquals('string', $context['expected_type']);
        $this->assertEquals('integer', $context['actual_type']);
        $this->assertEquals(123, $context['actual_value']);
    }

    public function test_virtual_field_computation_exception_timeout_exceeded()
    {
        $model = $this->createTestModel();
        
        $exception = VirtualFieldComputationException::timeoutExceeded('test_field', 30, $model);

        $this->assertStringContainsString('timeout', $exception->getMessage());
        $this->assertStringContainsString('30 seconds', $exception->getMessage());
        
        $context = $exception->getContext();
        $this->assertEquals('test_field', $context['field_name']);
        $this->assertEquals(30, $context['timeout_seconds']);
    }

    public function test_virtual_field_computation_exception_memory_limit_exceeded()
    {
        $model = $this->createTestModel();
        
        $exception = VirtualFieldComputationException::memoryLimitExceeded('test_field', 128, $model);

        $this->assertStringContainsString('memory limit', $exception->getMessage());
        $this->assertStringContainsString('128MB', $exception->getMessage());
        
        $context = $exception->getContext();
        $this->assertEquals('test_field', $context['field_name']);
        $this->assertEquals(128, $context['memory_limit_mb']);
    }

    public function test_virtual_field_computation_exception_batch_processing_failed()
    {
        $originalException = new \Exception('Batch error');
        
        $exception = VirtualFieldComputationException::batchProcessingFailed('test_field', 100, $originalException);

        $this->assertStringContainsString('batch process', $exception->getMessage());
        $this->assertStringContainsString('100 models', $exception->getMessage());
        
        $context = $exception->getContext();
        $this->assertEquals('test_field', $context['field_name']);
        $this->assertEquals(100, $context['model_count']);
        $this->assertEquals('Batch error', $context['original_error']);
    }

    public function test_exception_inheritance()
    {
        $configException = new VirtualFieldConfigurationException('Config error');
        $computationException = new VirtualFieldComputationException('Computation error');

        $this->assertInstanceOf(VirtualFieldException::class, $configException);
        $this->assertInstanceOf(VirtualFieldException::class, $computationException);
    }

    public function test_exception_json_response()
    {
        $exception = VirtualFieldConfigurationException::invalidFieldName('test_field');
        
        $response = $exception->render();
        
        $this->assertEquals(400, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('VIRTUAL_FIELD_CONFIGURATION_ERROR', $data['error']['code']);
        $this->assertEquals('VirtualFieldConfigurationException', $data['error']['type']);
    }

    public function test_exception_to_array()
    {
        $exception = VirtualFieldConfigurationException::invalidFieldName('test_field');
        
        $array = $exception->toArray();
        
        $this->assertArrayHasKey('exception', $array);
        $this->assertArrayHasKey('code', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('context', $array);
        $this->assertArrayHasKey('file', $array);
        $this->assertArrayHasKey('line', $array);
        $this->assertArrayHasKey('trace', $array);
        
        $this->assertEquals(VirtualFieldConfigurationException::class, $array['exception']);
        $this->assertEquals('VIRTUAL_FIELD_CONFIGURATION_ERROR', $array['code']);
    }

    protected function createTestModel(): Model
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