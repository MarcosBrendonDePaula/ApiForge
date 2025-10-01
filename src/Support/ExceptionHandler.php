<?php

namespace MarcosBrendon\ApiForge\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MarcosBrendon\ApiForge\Exceptions\ApiForgeException;
use Throwable;

class ExceptionHandler
{
    /**
     * Handle an ApiForge exception
     */
    public static function handle(ApiForgeException $exception, ?Request $request = null): JsonResponse
    {
        // Log the exception if configured
        if ($exception->shouldLog()) {
            static::logException($exception, $request);
        }

        // Return formatted JSON response
        return $exception->render();
    }

    /**
     * Handle a generic exception within ApiForge context
     */
    public static function handleGeneric(Throwable $exception, ?Request $request = null): JsonResponse
    {
        // Log the exception
        static::logGenericException($exception, $request);

        // Create standardized error response
        $response = [
            'success' => false,
            'error' => [
                'code' => 'INTERNAL_ERROR',
                'message' => config('app.debug') 
                    ? $exception->getMessage() 
                    : 'An internal error occurred',
                'type' => 'UnhandledException',
            ]
        ];

        // Add debug information if enabled
        if (config('app.debug') || config('apiforge.debug.enabled', false)) {
            $response['error']['debug'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => collect($exception->getTrace())->take(5)->toArray(),
            ];
        }

        // Add trace ID
        if (config('apiforge.debug.include_trace_id', true)) {
            $response['trace_id'] = uniqid('apiforge_error_', true);
        }

        return response()->json($response, 500);
    }

    /**
     * Convert validation errors to ApiForge format
     */
    public static function handleValidationException($exception, ?Request $request = null): JsonResponse
    {
        $errors = method_exists($exception, 'errors') ? $exception->errors() : [];

        $response = [
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'The given data was invalid',
                'type' => 'ValidationException',
                'validation_errors' => $errors,
            ]
        ];

        // Log validation errors if configured
        if (config('apiforge.debug.log_validation_errors', false)) {
            Log::info('ApiForge validation error', [
                'errors' => $errors,
                'request_data' => $request ? $request->all() : null,
            ]);
        }

        return response()->json($response, 422);
    }

    /**
     * Create error response for rate limiting
     */
    public static function handleRateLimitException($exception, ?Request $request = null): JsonResponse
    {
        $response = [
            'success' => false,
            'error' => [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'message' => 'Too many requests. Please try again later.',
                'type' => 'RateLimitException',
                'retry_after' => $exception->getHeaders()['Retry-After'] ?? null,
            ]
        ];

        return response()->json($response, 429);
    }

    /**
     * Log an ApiForge exception
     */
    protected static function logException(ApiForgeException $exception, ?Request $request = null): void
    {
        $logData = [
            'exception' => $exception->toArray(),
            'request_id' => $request ? $request->header('X-Request-ID') : null,
            'user_id' => auth()->id(),
            'ip' => $request ? $request->ip() : null,
            'user_agent' => $request ? $request->userAgent() : null,
        ];

        // Add request data for certain exception types
        if ($exception instanceof \MarcosBrendon\ApiForge\Exceptions\FilterValidationException && $request) {
            $logData['request_data'] = $request->all();
        }

        Log::log($exception->getLogLevel(), $exception->getMessage(), $logData);
    }

    /**
     * Log a generic exception
     */
    protected static function logGenericException(Throwable $exception, ?Request $request = null): void
    {
        $logData = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'request_id' => $request ? $request->header('X-Request-ID') : null,
            'user_id' => auth()->id(),
            'ip' => $request ? $request->ip() : null,
            'url' => $request ? $request->fullUrl() : null,
        ];

        Log::error('Unhandled exception in ApiForge', $logData);
    }

    /**
     * Create error response for authentication errors
     */
    public static function handleAuthenticationException($exception, ?Request $request = null): JsonResponse
    {
        $response = [
            'success' => false,
            'error' => [
                'code' => 'AUTHENTICATION_ERROR',
                'message' => 'Authentication required',
                'type' => 'AuthenticationException',
            ]
        ];

        return response()->json($response, 401);
    }

    /**
     * Create error response for authorization errors
     */
    public static function handleAuthorizationException($exception, ?Request $request = null): JsonResponse
    {
        $response = [
            'success' => false,
            'error' => [
                'code' => 'AUTHORIZATION_ERROR',
                'message' => 'Insufficient privileges',
                'type' => 'AuthorizationException',
            ]
        ];

        return response()->json($response, 403);
    }

    /**
     * Create error response for method not allowed
     */
    public static function handleMethodNotAllowedException($exception, ?Request $request = null): JsonResponse
    {
        $response = [
            'success' => false,
            'error' => [
                'code' => 'METHOD_NOT_ALLOWED',
                'message' => 'The specified method is not allowed for this resource',
                'type' => 'MethodNotAllowedException',
                'allowed_methods' => $exception->getHeaders()['Allow'] ?? null,
            ]
        ];

        return response()->json($response, 405);
    }

    /**
     * Create error response for not found
     */
    public static function handleNotFoundException($exception, ?Request $request = null): JsonResponse
    {
        $response = [
            'success' => false,
            'error' => [
                'code' => 'NOT_FOUND',
                'message' => 'The requested resource was not found',
                'type' => 'NotFoundException',
            ]
        ];

        return response()->json($response, 404);
    }

    /**
     * Get appropriate HTTP status code for exception
     */
    public static function getStatusCode(Throwable $exception): int
    {
        if ($exception instanceof ApiForgeException) {
            return $exception->getStatusCode();
        }

        // Map common Laravel exceptions to status codes
        $exceptionMap = [
            'Illuminate\\Validation\\ValidationException' => 422,
            'Illuminate\\Auth\\AuthenticationException' => 401,
            'Illuminate\\Auth\\Access\\AuthorizationException' => 403,
            'Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException' => 404,
            'Symfony\\Component\\HttpKernel\\Exception\\MethodNotAllowedHttpException' => 405,
            'Illuminate\\Http\\Exceptions\\ThrottleRequestsException' => 429,
        ];

        return $exceptionMap[get_class($exception)] ?? 500;
    }

    /**
     * Check if exception should be logged
     */
    public static function shouldLog(Throwable $exception): bool
    {
        if ($exception instanceof ApiForgeException) {
            return $exception->shouldLog();
        }

        // Don't log validation errors by default
        if ($exception instanceof \Illuminate\Validation\ValidationException) {
            return config('apiforge.debug.log_validation_errors', false);
        }

        // Don't log 404s by default
        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            return config('apiforge.debug.log_not_found_errors', false);
        }

        return true;
    }
}