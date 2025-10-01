<?php

namespace MarcosBrendon\ApiForge\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

abstract class ApiForgeException extends Exception
{
    /**
     * Código de erro específico do ApiForge
     */
    protected string $errorCode;

    /**
     * Contexto adicional do erro
     */
    protected array $context = [];

    /**
     * HTTP status code
     */
    protected int $statusCode = 400;

    /**
     * Se o erro deve ser logado
     */
    protected bool $shouldLog = true;

    /**
     * Level do log
     */
    protected string $logLevel = 'error';

    /**
     * Constructor
     */
    public function __construct(
        string $message = '',
        array $context = [],
        ?Exception $previous = null
    ) {
        $this->context = $context;
        parent::__construct($message, 0, $previous);
    }

    /**
     * Obter código de erro do ApiForge
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Obter contexto do erro
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Obter HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Verificar se deve ser logado
     */
    public function shouldLog(): bool
    {
        return $this->shouldLog;
    }

    /**
     * Obter level do log
     */
    public function getLogLevel(): string
    {
        return $this->logLevel;
    }

    /**
     * Renderizar como resposta JSON
     */
    public function render(): JsonResponse
    {
        $response = [
            'success' => false,
            'error' => [
                'code' => $this->getErrorCode(),
                'message' => $this->getMessage(),
                'type' => class_basename(static::class),
            ]
        ];

        // Adicionar contexto se debug estiver habilitado
        if (config('app.debug') || config('apiforge.debug.enabled', false)) {
            $response['error']['context'] = $this->getContext();
            $response['error']['file'] = $this->getFile();
            $response['error']['line'] = $this->getLine();
            
            if ($this->getPrevious()) {
                $response['error']['previous'] = [
                    'message' => $this->getPrevious()->getMessage(),
                    'file' => $this->getPrevious()->getFile(),
                    'line' => $this->getPrevious()->getLine(),
                ];
            }
        }

        // Adicionar trace ID para tracking
        if (config('apiforge.debug.include_trace_id', true)) {
            $response['trace_id'] = $this->generateTraceId();
        }

        return response()->json($response, $this->getStatusCode());
    }

    /**
     * Gerar ID único para tracking do erro
     */
    protected function generateTraceId(): string
    {
        return uniqid('apiforge_', true);
    }

    /**
     * Converter para array para logging
     */
    public function toArray(): array
    {
        return [
            'exception' => static::class,
            'code' => $this->getErrorCode(),
            'message' => $this->getMessage(),
            'context' => $this->getContext(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString(),
        ];
    }
}