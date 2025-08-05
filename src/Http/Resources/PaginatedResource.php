<?php

namespace MarcosBrendon\ApiForge\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class PaginatedResource extends JsonResource
{
    /**
     * Metadados dos filtros
     *
     * @var array
     */
    protected array $filterMetadata = [];

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        if ($this->resource instanceof LengthAwarePaginator) {
            return [
                'success' => true,
                'data' => $this->resource->items(),
                'pagination' => [
                    'current_page' => $this->resource->currentPage(),
                    'per_page' => $this->resource->perPage(),
                    'total' => $this->resource->total(),
                    'last_page' => $this->resource->lastPage(),
                    'from' => $this->resource->firstItem(),
                    'to' => $this->resource->lastItem(),
                    'has_more_pages' => $this->resource->hasMorePages(),
                    'prev_page_url' => $this->resource->previousPageUrl(),
                    'next_page_url' => $this->resource->nextPageUrl(),
                ],
                'metadata' => $this->getMetadata($request),
            ];
        }

        return [
            'success' => true,
            'data' => $this->resource,
            'metadata' => $this->getMetadata($request),
        ];
    }

    /**
     * Adicionar metadados de filtros
     *
     * @param array $metadata
     * @return self
     */
    public function withFilterMetadata(array $metadata): self
    {
        $this->filterMetadata = $metadata;
        return $this;
    }

    /**
     * Obter metadados da resposta
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    protected function getMetadata($request): array
    {
        $metadata = [];

        // Incluir informações de resposta se configurado
        if (config('apiforge.response.include_metadata', true)) {
            $metadata['timestamp'] = now()->format(config('apiforge.response.timestamp_format', 'c'));
            $metadata['timezone'] = config('apiforge.response.timezone', 'UTC');
            $metadata['api_version'] = 'v2';
        }

        // Incluir metadados de filtros se disponíveis e configurado
        if (config('apiforge.response.include_filter_info', true) && !empty($this->filterMetadata)) {
            $metadata['available_filters'] = array_keys($this->filterMetadata);
            
            // Incluir configuração detalhada apenas se solicitado
            if ($request->get('include_filter_config') === 'true') {
                $metadata['filter_config'] = $this->filterMetadata;
            }
        }

        // Incluir informações de debug se habilitado
        if (config('apiforge.debug.enabled', false)) {
            $metadata['debug'] = [
                'query_time' => config('apiforge.debug.include_query_time') ? $this->getQueryTime() : null,
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
            ];
        }

        return array_filter($metadata); // Remove valores null
    }

    /**
     * Obter tempo de execução da query (placeholder)
     *
     * @return float|null
     */
    protected function getQueryTime(): ?float
    {
        // Esta funcionalidade pode ser implementada usando listeners de query
        // ou outros mecanismos de profiling
        return null;
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function with($request)
    {
        $additional = parent::with($request);
        
        // Adicionar headers de resposta se configurado
        if (config('apiforge.response.include_pagination_info', true)) {
            if ($this->resource instanceof LengthAwarePaginator) {
                $additional['headers'] = [
                    'X-Total-Count' => $this->resource->total(),
                    'X-Per-Page' => $this->resource->perPage(),
                    'X-Current-Page' => $this->resource->currentPage(),
                    'X-Last-Page' => $this->resource->lastPage(),
                ];
            }
        }

        return $additional;
    }
}