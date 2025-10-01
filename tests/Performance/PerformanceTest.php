<?php

namespace MarcosBrendon\ApiForge\Tests\Performance;

use Illuminate\Http\Request;
use MarcosBrendon\ApiForge\Services\ApiFilterService;
use MarcosBrendon\ApiForge\Services\FilterConfigService;
use MarcosBrendon\ApiForge\Services\CacheService;
use MarcosBrendon\ApiForge\Services\QueryOptimizationService;
use MarcosBrendon\ApiForge\Tests\TestCase;
use MarcosBrendon\ApiForge\Tests\Fixtures\User;

class PerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Enable all performance features
        config(['apiforge.performance.query_optimization' => true]);
        config(['apiforge.performance.optimize_pagination' => true]);
        config(['apiforge.cache.enabled' => true]);
    }

    /** @test */
    public function it_performs_filtering_within_acceptable_time()
    {
        $filterService = new ApiFilterService();
        $filterService->configure([
            'name' => ['type' => 'string', 'operators' => ['eq', 'like']],
            'email' => ['type' => 'string', 'operators' => ['eq', 'like']],
            'age' => ['type' => 'integer', 'operators' => ['gte', 'lte', 'between']],
            'status' => ['type' => 'enum', 'operators' => ['eq', 'in'], 'values' => ['active', 'inactive']],
            'created_at' => ['type' => 'datetime', 'operators' => ['gte', 'lte', 'between']]
        ]);

        $request = Request::create('/test', 'GET', [
            'name' => 'John*',
            'age' => '>=18',
            'status' => 'active,inactive',
            'created_at' => '2024-01-01|2024-12-31',
            'search' => 'John Doe'
        ]);

        $query = User::query();

        $startTime = microtime(true);
        $filterService->applyAdvancedFilters($query, $request);
        $endTime = microtime(true);

        $executionTime = ($endTime - $startTime) * 1000; // ms
        
        // Should complete within 50ms for typical filtering operations
        $this->assertLessThan(50, $executionTime, "Filtering took {$executionTime}ms, expected < 50ms");
    }

    /** @test */
    public function it_handles_complex_field_selection_efficiently()
    {
        $configService = new FilterConfigService();
        $configService->configureFieldSelection([
            'selectable_fields' => array_merge(
                ['id', 'name', 'email'],
                // Generate many selectable fields
                array_map(function($i) { return "field_{$i}"; }, range(1, 100))
            ),
            'max_fields' => 50
        ]);

        $fields = array_merge(
            ['id', 'name', 'email'],
            array_map(function($i) { return "field_{$i}"; }, range(1, 30))
        );

        $startTime = microtime(true);
        [$valid, $invalid] = $configService->validateFieldSelection($fields);
        $endTime = microtime(true);

        $executionTime = ($endTime - $startTime) * 1000; // ms
        
        $this->assertLessThan(10, $executionTime, "Field validation took {$executionTime}ms, expected < 10ms");
        $this->assertCount(33, $valid); // id, name, email + 30 fields
    }

    /** @test */
    public function it_caches_responses_effectively()
    {
        $cacheService = new CacheService();
        
        $testData = [
            'users' => array_fill(0, 1000, ['id' => 1, 'name' => 'Test User']),
            'pagination' => ['total' => 1000, 'per_page' => 50]
        ];

        $key = 'performance_test_key';

        // First store operation
        $startTime = microtime(true);
        $cacheService->store($key, $testData, ['ttl' => 3600, 'model' => 'User']);
        $endTime = microtime(true);

        $storeTime = ($endTime - $startTime) * 1000;
        $this->assertLessThan(20, $storeTime, "Cache store took {$storeTime}ms, expected < 20ms");

        // Retrieval operation
        $startTime = microtime(true);
        $retrieved = $cacheService->retrieve($key);
        $endTime = microtime(true);

        $retrieveTime = ($endTime - $startTime) * 1000;
        $this->assertLessThan(5, $retrieveTime, "Cache retrieve took {$retrieveTime}ms, expected < 5ms");

        $this->assertEquals($testData, $retrieved);
    }

    /** @test */
    public function it_optimizes_queries_without_significant_overhead()
    {
        $optimizationService = new QueryOptimizationService();
        
        $query = User::query();
        $requestedFields = [
            'id', 'name', 'email',
            'profile.avatar', 'profile.bio',
            'company.name', 'company.address',
            'posts.title', 'posts.content'
        ];

        $startTime = microtime(true);
        $optimizedQuery = $optimizationService->optimizeQuery($query, $requestedFields);
        $endTime = microtime(true);

        $optimizationTime = ($endTime - $startTime) * 1000;
        
        $this->assertLessThan(15, $optimizationTime, "Query optimization took {$optimizationTime}ms, expected < 15ms");
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $optimizedQuery);
    }

    /** @test */
    public function it_handles_large_filter_configurations_efficiently()
    {
        $configService = new FilterConfigService();
        
        // Generate large filter configuration
        $largeConfig = [];
        for ($i = 1; $i <= 100; $i++) {
            $largeConfig["field_{$i}"] = [
                'type' => $i % 4 == 0 ? 'integer' : 'string',
                'operators' => ['eq', 'like', 'gte', 'lte'],
                'searchable' => $i % 3 == 0,
                'sortable' => $i % 2 == 0
            ];
        }

        $startTime = microtime(true);
        $configService->configure($largeConfig);
        $endTime = microtime(true);

        $configTime = ($endTime - $startTime) * 1000;
        $this->assertLessThan(50, $configTime, "Large config setup took {$configTime}ms, expected < 50ms");

        // Test metadata generation
        $startTime = microtime(true);
        $metadata = $configService->getCompleteMetadata();
        $endTime = microtime(true);

        $metadataTime = ($endTime - $startTime) * 1000;
        $this->assertLessThan(30, $metadataTime, "Metadata generation took {$metadataTime}ms, expected < 30ms");

        $this->assertCount(100, $metadata['filter_config']);
    }

    /** @test */
    public function it_processes_multiple_concurrent_requests_efficiently()
    {
        $filterService = new ApiFilterService();
        $filterService->configure([
            'name' => ['type' => 'string', 'operators' => ['eq', 'like']],
            'email' => ['type' => 'string', 'operators' => ['eq']],
            'status' => ['type' => 'enum', 'operators' => ['eq'], 'values' => ['active', 'inactive']]
        ]);

        $requests = [];
        for ($i = 0; $i < 10; $i++) {
            $requests[] = Request::create('/test', 'GET', [
                'name' => "User_{$i}",
                'email' => "user{$i}@example.com",
                'status' => $i % 2 == 0 ? 'active' : 'inactive'
            ]);
        }

        $startTime = microtime(true);
        
        foreach ($requests as $request) {
            $query = User::query();
            $filterService->applyAdvancedFilters($query, $request);
        }
        
        $endTime = microtime(true);

        $totalTime = ($endTime - $startTime) * 1000;
        $averageTime = $totalTime / 10;
        
        $this->assertLessThan(100, $totalTime, "10 requests took {$totalTime}ms, expected < 100ms");
        $this->assertLessThan(10, $averageTime, "Average per request: {$averageTime}ms, expected < 10ms");
    }

    /** @test */
    public function it_handles_memory_usage_efficiently()
    {
        $startMemory = memory_get_usage(true);
        
        $filterService = new ApiFilterService();
        $configService = new FilterConfigService();
        $cacheService = new CacheService();
        
        // Configure large dataset
        $largeConfig = [];
        for ($i = 1; $i <= 50; $i++) {
            $largeConfig["field_{$i}"] = [
                'type' => 'string',
                'operators' => ['eq', 'like'],
                'description' => str_repeat("Description for field {$i}. ", 10)
            ];
        }
        
        $configService->configure($largeConfig);
        $filterService->configure($largeConfig);
        
        // Process multiple requests
        for ($i = 0; $i < 20; $i++) {
            $request = Request::create('/test', 'GET', [
                'field_1' => "value_{$i}",
                'field_2' => "another_value_{$i}"
            ]);
            
            $query = User::query();
            $filterService->applyAdvancedFilters($query, $request);
            
            // Store in cache
            $cacheService->store("key_{$i}", ['data' => $i], ['model' => 'User']);
        }
        
        $endMemory = memory_get_usage(true);
        $memoryUsed = $endMemory - $startMemory;
        
        // Should not use more than 10MB for this test
        $maxMemoryMB = 10;
        $maxMemoryBytes = $maxMemoryMB * 1024 * 1024;
        
        $this->assertLessThan(
            $maxMemoryBytes, 
            $memoryUsed, 
            "Memory usage: " . round($memoryUsed / 1024 / 1024, 2) . "MB, expected < {$maxMemoryMB}MB"
        );
    }

    /** @test */
    public function it_detects_performance_issues()
    {
        $optimizationService = new QueryOptimizationService();
        
        // Create a query with potential performance issues
        $query = User::query()
            ->where('name', 'like', '%slow%')  // Leading wildcard
            ->offset(5000)                     // Large offset
            ->limit(200);                      // Large limit

        $analysis = $optimizationService->analyzeQueryPerformance($query);
        
        $this->assertArrayHasKey('recommendations', $analysis);
        $this->assertNotEmpty($analysis['recommendations']);
        
        // Should detect the performance issues
        $recommendations = implode(' ', $analysis['recommendations']);
        $this->assertStringContainsString('LIKE queries starting with % cannot use indexes efficiently', $recommendations);
    }

    /** @test */
    public function it_maintains_consistent_performance_across_iterations()
    {
        $filterService = new ApiFilterService();
        $filterService->configure([
            'name' => ['type' => 'string', 'operators' => ['like']],
            'status' => ['type' => 'enum', 'operators' => ['eq'], 'values' => ['active', 'inactive']]
        ]);

        $times = [];
        
        for ($i = 0; $i < 20; $i++) {
            $request = Request::create('/test', 'GET', [
                'name' => 'Test*',
                'status' => 'active'
            ]);
            
            $query = User::query();
            
            $startTime = microtime(true);
            $filterService->applyAdvancedFilters($query, $request);
            $endTime = microtime(true);
            
            $times[] = ($endTime - $startTime) * 1000;
        }
        
        $avgTime = array_sum($times) / count($times);
        $maxTime = max($times);
        $minTime = min($times);
        $variance = $maxTime - $minTime;
        
        // Performance should be consistent (variance < 10ms)
        $this->assertLessThan(10, $variance, "Performance variance: {$variance}ms, expected < 10ms");
        $this->assertLessThan(20, $avgTime, "Average time: {$avgTime}ms, expected < 20ms");
    }

    /** @test */
    public function it_scales_linearly_with_filter_count()
    {
        $filterService = new ApiFilterService();
        
        // Test with different numbers of filters
        $filterCounts = [5, 10, 20];
        $times = [];
        
        foreach ($filterCounts as $count) {
            $config = [];
            $requestData = [];
            
            for ($i = 1; $i <= $count; $i++) {
                $config["field_{$i}"] = ['type' => 'string', 'operators' => ['eq']];
                $requestData["field_{$i}"] = "value_{$i}";
            }
            
            $filterService->configure($config);
            $request = Request::create('/test', 'GET', $requestData);
            $query = User::query();
            
            $startTime = microtime(true);
            $filterService->applyAdvancedFilters($query, $request);
            $endTime = microtime(true);
            
            $times[$count] = ($endTime - $startTime) * 1000;
        }
        
        // Performance should scale roughly linearly
        // 20 filters shouldn't take more than 4x the time of 5 filters
        $scalingFactor = $times[20] / $times[5];
        $this->assertLessThan(4, $scalingFactor, "Scaling factor: {$scalingFactor}, expected < 4");
    }
}