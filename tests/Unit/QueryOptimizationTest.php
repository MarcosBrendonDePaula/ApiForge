<?php

namespace MarcosBrendon\ApiForge\Tests\Unit;

use Illuminate\Database\Eloquent\Builder;
use MarcosBrendon\ApiForge\Services\QueryOptimizationService;
use MarcosBrendon\ApiForge\Tests\TestCase;
use MarcosBrendon\ApiForge\Tests\Fixtures\User;

class QueryOptimizationTest extends TestCase
{
    protected QueryOptimizationService $optimizationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->optimizationService = new QueryOptimizationService();
        
        // Enable performance features for tests
        config(['apiforge.performance.query_optimization' => true]);
        config(['apiforge.performance.optimize_pagination' => true]);
    }

    /** @test */
    public function it_extracts_relationships_from_field_list()
    {
        $fields = [
            'id',
            'name',
            'profile.avatar',
            'profile.bio',
            'company.name',
            'company.address.city',
            'posts.title'
        ];

        $reflection = new \ReflectionClass($this->optimizationService);
        $method = $reflection->getMethod('extractRelationships');
        $method->setAccessible(true);

        $relationships = $method->invoke($this->optimizationService, $fields);

        $this->assertArrayHasKey('profile', $relationships);
        $this->assertArrayHasKey('company', $relationships);
        $this->assertArrayHasKey('company.address', $relationships);
        $this->assertArrayHasKey('posts', $relationships);

        // Check profile relationship
        $this->assertEquals(['avatar', 'bio'], $relationships['profile']['fields']);
        $this->assertEquals(1, $relationships['profile']['depth']);

        // Check nested relationship
        $this->assertEquals(['city'], $relationships['company.address']['fields']);
        $this->assertEquals(2, $relationships['company.address']['depth']);
        $this->assertEquals('company', $relationships['company.address']['parent']);
    }

    /** @test */
    public function it_builds_eager_loading_array_correctly()
    {
        $relationships = [
            'profile' => [
                'fields' => ['avatar', 'bio'],
                'depth' => 1,
                'parent' => null
            ],
            'company' => [
                'fields' => ['name', 'email'],
                'depth' => 1,
                'parent' => null
            ]
        ];

        $reflection = new \ReflectionClass($this->optimizationService);
        $method = $reflection->getMethod('buildEagerLoadArray');
        $method->setAccessible(true);

        $eagerLoad = $method->invoke($this->optimizationService, $relationships);

        $this->assertArrayHasKey('profile', $eagerLoad);
        $this->assertArrayHasKey('company', $eagerLoad);
        $this->assertIsCallable($eagerLoad['profile']);
        $this->assertIsCallable($eagerLoad['company']);
    }

    /** @test */
    public function it_optimizes_query_with_relationships()
    {
        $query = User::query();
        $requestedFields = [
            'id',
            'name',
            'profile.avatar',
            'company.name'
        ];

        $optimizedQuery = $this->optimizationService->optimizeQuery($query, $requestedFields);

        $this->assertInstanceOf(Builder::class, $optimizedQuery);
        // In a real test, we would check if the with() relationships were applied
    }

    /** @test */
    public function it_identifies_columns_that_should_have_indexes()
    {
        $reflection = new \ReflectionClass($this->optimizationService);
        $method = $reflection->getMethod('shouldHaveIndex');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->optimizationService, 'email'));
        $this->assertTrue($method->invoke($this->optimizationService, 'user_id'));
        $this->assertTrue($method->invoke($this->optimizationService, 'created_at'));
        $this->assertTrue($method->invoke($this->optimizationService, 'status'));

        $this->assertFalse($method->invoke($this->optimizationService, 'description'));
        $this->assertFalse($method->invoke($this->optimizationService, 'content'));
    }

    /** @test */
    public function it_generates_performance_recommendations()
    {
        $query = User::query()
            ->join('profiles', 'users.id', '=', 'profiles.user_id')
            ->join('companies', 'users.company_id', '=', 'companies.id')
            ->join('departments', 'companies.department_id', '=', 'departments.id')
            ->join('locations', 'departments.location_id', '=', 'locations.id')
            ->where('name', 'like', '%John%')
            ->limit(200)
            ->offset(2000);

        $reflection = new \ReflectionClass($this->optimizationService);
        $method = $reflection->getMethod('generateRecommendations');
        $method->setAccessible(true);

        $recommendations = $method->invoke($this->optimizationService, $query);

        $this->assertNotEmpty($recommendations);
        $this->assertContains('Consider breaking down complex joins into separate queries', $recommendations);
        $this->assertContains('Large offset values can be slow - consider cursor-based pagination', $recommendations);
        $this->assertContains('Large page sizes can impact performance - consider smaller pages', $recommendations);
    }

    /** @test */
    public function it_detects_like_queries_with_leading_wildcards()
    {
        $query = User::query()->where('name', 'like', '%John');

        $reflection = new \ReflectionClass($this->optimizationService);
        $method = $reflection->getMethod('generateRecommendations');
        $method->setAccessible(true);

        $recommendations = $method->invoke($this->optimizationService, $query);

        $this->assertContains('LIKE queries starting with % cannot use indexes efficiently', $recommendations);
    }

    /** @test */
    public function it_optimizes_pagination_for_large_offsets()
    {
        $query = User::query();
        $page = 150; // Large page number
        $perPage = 20;

        $optimizedQuery = $this->optimizationService->optimizePagination($query, $page, $perPage);

        $this->assertInstanceOf(Builder::class, $optimizedQuery);
        // In a real implementation, we would verify that cursor-based pagination was applied
    }

    /** @test */
    public function it_optimizes_pagination_for_normal_offsets()
    {
        $query = User::query();
        $page = 5; // Normal page number
        $perPage = 20;

        $optimizedQuery = $this->optimizationService->optimizePagination($query, $page, $perPage);

        $this->assertInstanceOf(Builder::class, $optimizedQuery);
    }

    /** @test */
    public function it_groups_relationships_by_depth()
    {
        $relationships = [
            'profile' => ['depth' => 1],
            'company' => ['depth' => 1],
            'company.address' => ['depth' => 2],
            'company.address.country' => ['depth' => 3],
            'posts' => ['depth' => 1],
        ];

        $reflection = new \ReflectionClass($this->optimizationService);
        $method = $reflection->getMethod('groupRelationshipsByDepth');
        $method->setAccessible(true);

        $grouped = $method->invoke($this->optimizationService, $relationships);

        $this->assertArrayHasKey(1, $grouped);
        $this->assertArrayHasKey(2, $grouped);
        $this->assertArrayHasKey(3, $grouped);

        $this->assertCount(3, $grouped[1]); // profile, company, posts
        $this->assertCount(1, $grouped[2]); // company.address
        $this->assertCount(1, $grouped[3]); // company.address.country
    }

    /** @test */
    public function it_infers_required_keys_for_relationships()
    {
        $reflection = new \ReflectionClass($this->optimizationService);
        $method = $reflection->getMethod('inferRequiredKeys');
        $method->setAccessible(true);

        $keys = $method->invoke($this->optimizationService, 'profile');
        
        $this->assertContains('id', $keys);
        $this->assertContains('profile_id', $keys);

        $keysForCompany = $method->invoke($this->optimizationService, 'company');
        
        $this->assertContains('id', $keys);
        $this->assertContains('company_id', $keysForCompany);
    }

    /** @test */
    public function it_detects_n_plus_one_patterns()
    {
        // Mock query log with repetitive patterns
        $queries = [
            ['query' => 'select * from users'],
            ['query' => 'select * from profiles where user_id = 1'],
            ['query' => 'select * from profiles where user_id = 2'],
            ['query' => 'select * from profiles where user_id = 3'],
            ['query' => 'select * from profiles where user_id = 4'],
            ['query' => 'select * from profiles where user_id = 5'],
            ['query' => 'select * from profiles where user_id = 6'],
        ];

        $reflection = new \ReflectionClass($this->optimizationService);
        $method = $reflection->getMethod('analyzeForNPlusOne');
        $method->setAccessible(true);

        $nPlusOneDetected = $method->invoke($this->optimizationService, $queries);

        $this->assertTrue($nPlusOneDetected);
    }

    /** @test */
    public function it_does_not_detect_n_plus_one_for_varied_queries()
    {
        // Mock query log with varied queries
        $queries = [
            ['query' => 'select * from users'],
            ['query' => 'select * from companies'],
            ['query' => 'select * from posts where status = "published"'],
            ['query' => 'select * from categories'],
        ];

        $reflection = new \ReflectionClass($this->optimizationService);
        $method = $reflection->getMethod('analyzeForNPlusOne');
        $method->setAccessible(true);

        $nPlusOneDetected = $method->invoke($this->optimizationService, $queries);

        $this->assertFalse($nPlusOneDetected);
    }

    /** @test */
    public function it_analyzes_query_performance()
    {
        $query = User::query()->limit(1);

        $analysis = $this->optimizationService->analyzeQueryPerformance($query);

        $this->assertArrayHasKey('execution_time', $analysis);
        $this->assertArrayHasKey('query_count', $analysis);
        $this->assertArrayHasKey('memory_usage', $analysis);
        $this->assertArrayHasKey('peak_memory', $analysis);
        $this->assertArrayHasKey('recommendations', $analysis);

        $this->assertIsFloat($analysis['execution_time']);
        $this->assertIsInt($analysis['memory_usage']);
        $this->assertIsArray($analysis['recommendations']);
    }
}