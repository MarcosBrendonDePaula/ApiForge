<?php

namespace MarcosBrendon\ApiForge\Tests\Unit;

use Illuminate\Http\Request;
use MarcosBrendon\ApiForge\Exceptions\FilterValidationException;
use MarcosBrendon\ApiForge\Exceptions\FieldSelectionException;
use MarcosBrendon\ApiForge\Exceptions\CacheException;
use MarcosBrendon\ApiForge\Support\ExceptionHandler;
use MarcosBrendon\ApiForge\Tests\TestCase;

class ExceptionHandlingTest extends TestCase
{
    /** @test */
    public function it_handles_filter_validation_exceptions()
    {
        $exception = FilterValidationException::invalidOperator('age', 'invalid', ['eq', 'gte', 'lte']);
        $request = Request::create('/test', 'GET');
        
        $response = ExceptionHandler::handle($exception, $request);
        
        $this->assertEquals(422, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        
        $this->assertFalse($data['success']);
        $this->assertEquals('FILTER_VALIDATION_ERROR', $data['error']['code']);
        $this->assertStringContainsString('Invalid operator', $data['error']['message']);
        $this->assertEquals('FilterValidationException', $data['error']['type']);
    }

    /** @test */
    public function it_includes_debug_information_when_enabled()
    {
        config(['app.debug' => true]);
        
        $exception = FilterValidationException::blockedValue('name', 'malicious<script>');
        $request = Request::create('/test', 'GET');
        
        $response = ExceptionHandler::handle($exception, $request);
        $data = json_decode($response->getContent(), true);
        
        $this->assertArrayHasKey('context', $data['error']);
        $this->assertArrayHasKey('file', $data['error']);
        $this->assertArrayHasKey('line', $data['error']);
    }

    /** @test */
    public function it_hides_sensitive_information_in_production()
    {
        config(['app.debug' => false]);
        config(['apiforge.debug.enabled' => false]);
        
        $exception = FilterValidationException::blockedValue('name', 'malicious<script>');
        $request = Request::create('/test', 'GET');
        
        $response = ExceptionHandler::handle($exception, $request);
        $data = json_decode($response->getContent(), true);
        
        $this->assertArrayNotHasKey('context', $data['error']);
        $this->assertArrayNotHasKey('file', $data['error']);
        $this->assertArrayNotHasKey('line', $data['error']);
    }

    /** @test */
    public function it_generates_trace_ids_for_tracking()
    {
        $exception = FilterValidationException::invalidFieldType('age', 'integer', 'not_a_number');
        $request = Request::create('/test', 'GET');
        
        $response = ExceptionHandler::handle($exception, $request);
        $data = json_decode($response->getContent(), true);
        
        $this->assertArrayHasKey('trace_id', $data);
        $this->assertStringStartsWith('apiforge_', $data['trace_id']);
    }

    /** @test */
    public function it_handles_field_selection_exceptions()
    {
        $exception = FieldSelectionException::tooManyFields(60, 50);
        $request = Request::create('/test', 'GET');
        
        $response = ExceptionHandler::handle($exception, $request);
        
        $this->assertEquals(422, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        
        $this->assertEquals('FIELD_SELECTION_ERROR', $data['error']['code']);
        $this->assertStringContainsString('Too many fields', $data['error']['message']);
    }

    /** @test */
    public function it_handles_cache_exceptions_with_higher_severity()
    {
        $exception = CacheException::storeFailure('test_key', 'Redis connection failed');
        $request = Request::create('/test', 'GET');
        
        $response = ExceptionHandler::handle($exception, $request);
        
        $this->assertEquals(500, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        
        $this->assertEquals('CACHE_ERROR', $data['error']['code']);
    }

    /** @test */
    public function it_handles_generic_exceptions()
    {
        $exception = new \RuntimeException('Something went wrong');
        $request = Request::create('/test', 'GET');
        
        $response = ExceptionHandler::handleGeneric($exception, $request);
        
        $this->assertEquals(500, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        
        $this->assertFalse($data['success']);
        $this->assertEquals('INTERNAL_ERROR', $data['error']['code']);
        $this->assertEquals('UnhandledException', $data['error']['type']);
    }

    /** @test */
    public function it_handles_validation_exceptions()
    {
        // Create a proper validator instance
        $validator = \Illuminate\Support\Facades\Validator::make([], ['field' => 'required']);
        $validator->fails(); // This will populate errors
        
        $mockException = new \Illuminate\Validation\ValidationException($validator);
        
        // Mock the errors method
        $mockException = $this->createMock(\Illuminate\Validation\ValidationException::class);
        $mockException->method('errors')->willReturn([
            'name' => ['The name field is required.'],
            'email' => ['The email field must be a valid email address.']
        ]);
        
        $request = Request::create('/test', 'POST');
        
        $response = ExceptionHandler::handleValidationException($mockException, $request);
        
        $this->assertEquals(422, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        
        $this->assertEquals('VALIDATION_ERROR', $data['error']['code']);
        $this->assertArrayHasKey('validation_errors', $data['error']);
    }

    /** @test */
    public function it_handles_rate_limit_exceptions()
    {
        $mockException = $this->createMock(\Illuminate\Http\Exceptions\ThrottleRequestsException::class);
        $mockException->method('getHeaders')->willReturn(['Retry-After' => 60]);
        
        $request = Request::create('/test', 'GET');
        
        $response = ExceptionHandler::handleRateLimitException($mockException, $request);
        
        $this->assertEquals(429, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        
        $this->assertEquals('RATE_LIMIT_EXCEEDED', $data['error']['code']);
        $this->assertEquals(60, $data['error']['retry_after']);
    }

    /** @test */
    public function it_determines_correct_status_codes()
    {
        $filterException = FilterValidationException::invalidOperator('field', 'op');
        $this->assertEquals(422, ExceptionHandler::getStatusCode($filterException));
        
        $cacheException = CacheException::storeFailure('key');
        $this->assertEquals(500, ExceptionHandler::getStatusCode($cacheException));
        
        $genericException = new \RuntimeException('Error');
        $this->assertEquals(500, ExceptionHandler::getStatusCode($genericException));
    }

    /** @test */
    public function it_determines_logging_requirements()
    {
        $filterException = FilterValidationException::invalidOperator('field', 'op');
        $this->assertTrue(ExceptionHandler::shouldLog($filterException));
        
        $mockValidationException = $this->createMock(\Illuminate\Validation\ValidationException::class);
        $this->assertFalse(ExceptionHandler::shouldLog($mockValidationException));
        
        $genericException = new \RuntimeException('Error');
        $this->assertTrue(ExceptionHandler::shouldLog($genericException));
    }

    /** @test */
    public function filter_exceptions_provide_helpful_context()
    {
        $exception = FilterValidationException::enumValueNotAllowed('status', 'invalid', ['active', 'inactive']);
        
        $context = $exception->getContext();
        
        $this->assertEquals('status', $context['field']);
        $this->assertEquals('invalid', $context['invalid_value']);
        $this->assertEquals(['active', 'inactive'], $context['allowed_values']);
    }

    /** @test */
    public function field_selection_exceptions_provide_helpful_context()
    {
        $exception = FieldSelectionException::relationshipTooDeep('user.company.address.city', 4, 3);
        
        $context = $exception->getContext();
        
        $this->assertEquals('user.company.address.city', $context['field']);
        $this->assertEquals(4, $context['depth']);
        $this->assertEquals(3, $context['max_depth']);
    }

    /** @test */
    public function exceptions_can_be_converted_to_arrays_for_logging()
    {
        $exception = FilterValidationException::blockedValue('name', 'test', 'Security policy');
        
        $array = $exception->toArray();
        
        $this->assertArrayHasKey('exception', $array);
        $this->assertArrayHasKey('code', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('context', $array);
        $this->assertArrayHasKey('file', $array);
        $this->assertArrayHasKey('line', $array);
        $this->assertArrayHasKey('trace', $array);
    }
}