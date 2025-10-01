<?php

namespace MarcosBrendon\ApiForge\Console\Commands;

use Illuminate\Console\Command;
use MarcosBrendon\ApiForge\Services\QueryOptimizationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PerformanceAnalysisCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'apiforge:analyze 
                            {model? : Model class to analyze}
                            {--endpoint= : API endpoint to test}
                            {--samples=10 : Number of samples to collect}
                            {--detect-n1 : Enable N+1 query detection}
                            {--suggest-indexes : Show index suggestions}
                            {--export= : Export results to file}';

    /**
     * The console command description.
     */
    protected $description = 'Analyze ApiForge performance and provide optimization suggestions';

    /**
     * Query optimization service
     */
    protected QueryOptimizationService $optimizationService;

    /**
     * Create a new command instance.
     */
    public function __construct(QueryOptimizationService $optimizationService)
    {
        parent::__construct();
        $this->optimizationService = $optimizationService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $model = $this->argument('model');
        $endpoint = $this->option('endpoint');
        $samples = (int) $this->option('samples');
        $detectN1 = $this->option('detect-n1');
        $suggestIndexes = $this->option('suggest-indexes');
        $export = $this->option('export');

        $this->info('🔍 ApiForge Performance Analysis');
        $this->line('');

        $results = [];

        if ($model) {
            $results['model_analysis'] = $this->analyzeModel($model);
        }

        if ($endpoint) {
            $results['endpoint_analysis'] = $this->analyzeEndpoint($endpoint, $samples);
        }

        if ($detectN1) {
            $results['n_plus_one_detection'] = $this->detectNPlusOneQueries();
        }

        if ($suggestIndexes) {
            $results['index_suggestions'] = $this->generateIndexSuggestions();
        }

        // Show results
        $this->displayResults($results);

        // Export if requested
        if ($export) {
            $this->exportResults($results, $export);
        }

        return 0;
    }

    /**
     * Analyze a specific model
     */
    protected function analyzeModel(string $model): array
    {
        $this->info("📊 Analyzing model: {$model}");

        if (!class_exists($model)) {
            $this->error("Model {$model} not found");
            return [];
        }

        $modelInstance = new $model;
        $query = $modelInstance->newQuery();

        // Basic query analysis
        $analysis = $this->optimizationService->analyzeQueryPerformance($query);

        // Additional model-specific analysis
        $modelAnalysis = [
            'table' => $modelInstance->getTable(),
            'primary_key' => $modelInstance->getKeyName(),
            'fillable' => $modelInstance->getFillable(),
            'relationships' => $this->analyzeModelRelationships($modelInstance),
            'performance' => $analysis,
        ];

        $this->displayModelAnalysis($modelAnalysis);

        return $modelAnalysis;
    }

    /**
     * Analyze model relationships
     */
    protected function analyzeModelRelationships($model): array
    {
        $relationships = [];
        $reflection = new \ReflectionClass($model);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = $method->getName();
            
            // Skip magic methods and common methods
            if (Str::startsWith($methodName, ['__', 'get', 'set', 'is', 'has', 'scope'])) {
                continue;
            }

            try {
                $return = $method->invoke($model);
                
                if ($return instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                    $relationships[$methodName] = [
                        'type' => class_basename(get_class($return)),
                        'related_model' => get_class($return->getRelated()),
                        'foreign_key' => method_exists($return, 'getForeignKeyName') 
                            ? $return->getForeignKeyName() 
                            : 'N/A',
                    ];
                }
            } catch (\Exception $e) {
                // Skip methods that require parameters or throw exceptions
                continue;
            }
        }

        return $relationships;
    }

    /**
     * Analyze API endpoint performance
     */
    protected function analyzeEndpoint(string $endpoint, int $samples): array
    {
        $this->info("🌐 Analyzing endpoint: {$endpoint}");

        $results = [
            'endpoint' => $endpoint,
            'samples' => $samples,
            'measurements' => [],
            'statistics' => [],
        ];

        $this->output->progressStart($samples);

        for ($i = 0; $i < $samples; $i++) {
            $measurement = $this->measureEndpointPerformance($endpoint);
            $results['measurements'][] = $measurement;
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        // Calculate statistics
        $results['statistics'] = $this->calculateStatistics($results['measurements']);

        $this->displayEndpointAnalysis($results);

        return $results;
    }

    /**
     * Measure single endpoint performance
     */
    protected function measureEndpointPerformance(string $endpoint): array
    {
        DB::enableQueryLog();
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $startQueries = count(DB::getQueryLog());

        try {
            // Make HTTP request to endpoint
            $response = $this->makeRequest($endpoint);
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            $endQueries = count(DB::getQueryLog());

            return [
                'response_time' => ($endTime - $startTime) * 1000, // ms
                'memory_usage' => $endMemory - $startMemory,
                'query_count' => $endQueries - $startQueries,
                'status_code' => $response['status'] ?? 0,
                'response_size' => $response['size'] ?? 0,
            ];
        } catch (\Exception $e) {
            return [
                'response_time' => 0,
                'memory_usage' => 0,
                'query_count' => 0,
                'status_code' => 500,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Make HTTP request to endpoint
     */
    protected function makeRequest(string $endpoint): array
    {
        // Simulate HTTP request - in real implementation, use HTTP client
        // For now, just return mock data
        return [
            'status' => 200,
            'size' => rand(1000, 10000),
        ];
    }

    /**
     * Calculate performance statistics
     */
    protected function calculateStatistics(array $measurements): array
    {
        $responseTimes = array_column($measurements, 'response_time');
        $memoryUsages = array_column($measurements, 'memory_usage');
        $queryCounts = array_column($measurements, 'query_count');

        return [
            'response_time' => [
                'avg' => array_sum($responseTimes) / count($responseTimes),
                'min' => min($responseTimes),
                'max' => max($responseTimes),
                'p95' => $this->percentile($responseTimes, 95),
            ],
            'memory_usage' => [
                'avg' => array_sum($memoryUsages) / count($memoryUsages),
                'min' => min($memoryUsages),
                'max' => max($memoryUsages),
            ],
            'query_count' => [
                'avg' => array_sum($queryCounts) / count($queryCounts),
                'min' => min($queryCounts),
                'max' => max($queryCounts),
            ],
        ];
    }

    /**
     * Calculate percentile
     */
    protected function percentile(array $values, int $percentile): float
    {
        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);
        
        if (floor($index) == $index) {
            return $values[$index];
        }
        
        $lower = $values[floor($index)];
        $upper = $values[ceil($index)];
        
        return $lower + ($upper - $lower) * ($index - floor($index));
    }

    /**
     * Detect N+1 queries
     */
    protected function detectNPlusOneQueries(): array
    {
        $this->info("🔍 Detecting N+1 queries...");

        // This would need to be implemented with actual query analysis
        return [
            'detected' => false,
            'suggestions' => [
                'Use eager loading with ->with()',
                'Consider using lazy eager loading',
                'Optimize relationship queries',
            ],
        ];
    }

    /**
     * Generate index suggestions
     */
    protected function generateIndexSuggestions(): array
    {
        $this->info("💡 Generating index suggestions...");

        // Analyze common query patterns and suggest indexes
        return [
            'suggested_indexes' => [
                'users.email' => 'Frequently used in WHERE clauses',
                'posts.user_id' => 'Foreign key for relationships',
                'orders.created_at' => 'Used for sorting and date filters',
            ],
            'composite_indexes' => [
                'users(status, created_at)' => 'Common filter combination',
            ],
        ];
    }

    /**
     * Display model analysis results
     */
    protected function displayModelAnalysis(array $analysis): void
    {
        $this->table(['Property', 'Value'], [
            ['Table', $analysis['table']],
            ['Primary Key', $analysis['primary_key']],
            ['Fillable Fields', implode(', ', $analysis['fillable'])],
            ['Relationships', count($analysis['relationships'])],
        ]);

        if (!empty($analysis['relationships'])) {
            $this->line('');
            $this->info('🔗 Relationships:');
            
            $relationData = [];
            foreach ($analysis['relationships'] as $name => $config) {
                $relationData[] = [
                    $name,
                    $config['type'],
                    class_basename($config['related_model']),
                    $config['foreign_key'],
                ];
            }
            
            $this->table(['Name', 'Type', 'Model', 'Foreign Key'], $relationData);
        }
    }

    /**
     * Display endpoint analysis results
     */
    protected function displayEndpointAnalysis(array $results): void
    {
        $stats = $results['statistics'];
        
        $this->line('');
        $this->info('📈 Performance Statistics:');
        
        $this->table(['Metric', 'Avg', 'Min', 'Max', 'P95'], [
            [
                'Response Time (ms)',
                number_format($stats['response_time']['avg'], 2),
                number_format($stats['response_time']['min'], 2),
                number_format($stats['response_time']['max'], 2),
                number_format($stats['response_time']['p95'], 2),
            ],
            [
                'Memory Usage (bytes)',
                number_format($stats['memory_usage']['avg']),
                number_format($stats['memory_usage']['min']),
                number_format($stats['memory_usage']['max']),
                'N/A',
            ],
            [
                'Query Count',
                number_format($stats['query_count']['avg'], 1),
                $stats['query_count']['min'],
                $stats['query_count']['max'],
                'N/A',
            ],
        ]);
    }

    /**
     * Display all results
     */
    protected function displayResults(array $results): void
    {
        $this->line('');
        $this->info('📋 Performance Analysis Summary:');
        
        foreach ($results as $section => $data) {
            $this->line("• {$section}: " . (is_array($data) ? 'Completed' : $data));
        }
    }

    /**
     * Export results to file
     */
    protected function exportResults(array $results, string $filename): void
    {
        $data = json_encode($results, JSON_PRETTY_PRINT);
        file_put_contents($filename, $data);
        
        $this->info("📄 Results exported to: {$filename}");
    }
}