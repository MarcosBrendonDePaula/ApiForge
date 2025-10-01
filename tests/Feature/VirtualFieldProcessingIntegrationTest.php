<?php

namespace MarcosBrendon\ApiForge\Tests\Feature;

use MarcosBrendon\ApiForge\Services\VirtualFieldService;
use MarcosBrendon\ApiForge\Support\VirtualFieldProcessor;
use MarcosBrendon\ApiForge\Tests\TestCase;
use MarcosBrendon\ApiForge\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Mockery;

class VirtualFieldProcessingIntegrationTest extends TestCase
{
    protected VirtualFieldService $virtualFieldService;
    protected VirtualFieldProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->virtualFieldService = new VirtualFieldService();
        $this->processor = $this->virtualFieldService->getProcessor();
        
        $this->registerTestVirtualFields();
    }

    protected function registerTestVirtualFields(): void
    {
        $virtualFieldsConfig = [
            'full_name' => [
                'type' => 'string',
                'callback' => function ($model) {
                    return trim(($model->first_name ?? '') . ' ' . ($model->last_name ?? ''));
                },
                'dependencies' => ['first_name', 'last_name'],
                'operators' => ['eq', 'like', 'starts_with', 'ends_with'],
                'searchable' => true,
                'sortable' => true
            ],
            'name_length' => [
                'type' => 'integer',
                'callback' => function ($model) {
                    $fullName = trim(($model->first_name ?? '') . ' ' . ($model->last_name ?? ''));
                    return strlen($fullName);
                },
                'dependencies' => ['first_name', 'last_name'],
                'operators' => ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'between'],
                'searchable' => false,
                'sortable' => true
            ],
            'email_domain' => [
                'type' => 'string',
                'callback' => function ($model) {
                    if (!$model->email || !str_contains($model->email, '@')) {
                        return '';
                    }
                    return substr(strrchr($model->email, '@'), 1);
                },
                'dependencies' => ['email'],
                'operators' => ['eq', 'ne', 'like', 'in', 'not_in'],
                'searchable' => true,
                'sortable' => true
            ],
            'is_premium' => [
                'type' => 'boolean',
                'callback' => function ($model) {
                    return ($model->subscription_type ?? 'free') === 'premium';
                },
                'dependencies' => ['subscription_type'],
                'operators' => ['eq', 'ne'],
                'searchable' => false,
                'sortable' => false
            ]
        ];

        $this->virtualFieldService->registerFromConfig($virtualFieldsConfig);
    }

    public function test_can_process_virtual_fields_for_selection()
    {
        $users = new Collection([
            new User([
                'id' => 1,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'subscription_type' => 'premium'
            ]),
            new User([
                'id' => 2,
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane@gmail.com',
                'subscription_type' => 'free'
            ]),
            new User([
                'id' => 3,
                'first_name' => 'Bob',
                'last_name' => '',
                'email' => 'bob@company.org',
                'subscription_type' => 'premium'
            ])
        ]);

        $virtualFields = ['full_name', 'name_length', 'email_domain', 'is_premium'];
        $result = $this->processor->processForSelection($users, $virtualFields);

        // Check first user
        $firstUser = $result->first();
        $this->assertEquals('John Doe', $firstUser->full_name);
        $this->assertEquals(8, $firstUser->name_length);
        $this->assertEquals('example.com', $firstUser->email_domain);
        $this->assertTrue($firstUser->is_premium);

        // Check second user
        $secondUser = $result->get(1);
        $this->assertEquals('Jane Smith', $secondUser->full_name);
        $this->assertEquals(10, $secondUser->name_length);
        $this->assertEquals('gmail.com', $secondUser->email_domain);
        $this->assertFalse($secondUser->is_premium);

        // Check third user (empty last name)
        $thirdUser = $result->get(2);
        $this->assertEquals('Bob', $thirdUser->full_name);
        $this->assertEquals(3, $thirdUser->name_length);
        $this->assertEquals('company.org', $thirdUser->email_domain);
        $this->assertTrue($thirdUser->is_premium);
    }

    public function test_can_batch_compute_virtual_fields()
    {
        $users = new Collection([
            new User([
                'id' => 1,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com'
            ]),
            new User([
                'id' => 2,
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane@gmail.com'
            ])
        ]);

        $results = $this->processor->computeBatch(['full_name', 'email_domain'], $users);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('full_name', $results);
        $this->assertArrayHasKey('email_domain', $results);
        $this->assertCount(2, $results['full_name']);
        $this->assertCount(2, $results['email_domain']);
    }

    public function test_can_process_virtual_field_filters()
    {
        $users = new Collection([
            new User([
                'id' => 1,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com'
            ]),
            new User([
                'id' => 2,
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane@gmail.com'
            ]),
            new User([
                'id' => 3,
                'first_name' => 'Bob',
                'last_name' => 'Johnson',
                'email' => 'bob@example.com'
            ])
        ]);

        // Filter by email domain
        $virtualFieldFilters = [
            [
                'field' => 'email_domain',
                'operator' => 'eq',
                'value' => 'example.com',
                'logic' => 'and'
            ]
        ];

        $result = $this->processor->processVirtualFieldFilters($users, $virtualFieldFilters);

        $this->assertEquals(2, $result->count());
        $resultEmails = $result->pluck('email')->toArray();
        $this->assertContains('john@example.com', $resultEmails);
        $this->assertContains('bob@example.com', $resultEmails);
        $this->assertNotContains('jane@gmail.com', $resultEmails);
    }

    public function test_can_filter_by_name_length()
    {
        $users = new Collection([
            new User([
                'id' => 1,
                'first_name' => 'Jo',
                'last_name' => 'Do',
                'email' => 'jo@test.com'
            ]), // name_length = 5
            new User([
                'id' => 2,
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane@test.com'
            ]), // name_length = 10
            new User([
                'id' => 3,
                'first_name' => 'Alexander',
                'last_name' => 'Johnson',
                'email' => 'alex@test.com'
            ]) // name_length = 17
        ]);

        // Filter by name length greater than 8
        $virtualFieldFilters = [
            [
                'field' => 'name_length',
                'operator' => 'gt',
                'value' => 8,
                'logic' => 'and'
            ]
        ];

        $result = $this->processor->processVirtualFieldFilters($users, $virtualFieldFilters);

        $this->assertEquals(2, $result->count());
        $resultNames = $result->map(function ($user) {
            return $user->first_name . ' ' . $user->last_name;
        })->toArray();
        $this->assertContains('Jane Smith', $resultNames);
        $this->assertContains('Alexander Johnson', $resultNames);
        $this->assertNotContains('Jo Do', $resultNames);
    }

    public function test_can_filter_with_like_operator()
    {
        $users = new Collection([
            new User([
                'id' => 1,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@test.com'
            ]),
            new User([
                'id' => 2,
                'first_name' => 'Jane',
                'last_name' => 'Johnson',
                'email' => 'jane@test.com'
            ]),
            new User([
                'id' => 3,
                'first_name' => 'Bob',
                'last_name' => 'Smith',
                'email' => 'bob@test.com'
            ])
        ]);

        // Filter by full name containing "Jo"
        $virtualFieldFilters = [
            [
                'field' => 'full_name',
                'operator' => 'like',
                'value' => '*Jo*',
                'logic' => 'and'
            ]
        ];

        $result = $this->processor->processVirtualFieldFilters($users, $virtualFieldFilters);

        $this->assertEquals(2, $result->count());
        $resultNames = $result->map(function ($user) {
            return $user->first_name . ' ' . $user->last_name;
        })->toArray();
        $this->assertContains('John Doe', $resultNames);
        $this->assertContains('Jane Johnson', $resultNames);
        $this->assertNotContains('Bob Smith', $resultNames);
    }

    public function test_can_filter_with_in_operator()
    {
        $users = new Collection([
            new User([
                'id' => 1,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com'
            ]),
            new User([
                'id' => 2,
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane@gmail.com'
            ]),
            new User([
                'id' => 3,
                'first_name' => 'Bob',
                'last_name' => 'Johnson',
                'email' => 'bob@yahoo.com'
            ])
        ]);

        // Filter by email domain in list
        $virtualFieldFilters = [
            [
                'field' => 'email_domain',
                'operator' => 'in',
                'value' => ['example.com', 'yahoo.com'],
                'logic' => 'and'
            ]
        ];

        $result = $this->processor->processVirtualFieldFilters($users, $virtualFieldFilters);

        $this->assertEquals(2, $result->count());
        $resultEmails = $result->pluck('email')->toArray();
        $this->assertContains('john@example.com', $resultEmails);
        $this->assertContains('bob@yahoo.com', $resultEmails);
        $this->assertNotContains('jane@gmail.com', $resultEmails);
    }

    public function test_can_filter_with_between_operator()
    {
        $users = new Collection([
            new User([
                'id' => 1,
                'first_name' => 'Jo',
                'last_name' => '',
                'email' => 'jo@test.com'
            ]), // name_length = 2
            new User([
                'id' => 2,
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane@test.com'
            ]), // name_length = 10
            new User([
                'id' => 3,
                'first_name' => 'Alexander',
                'last_name' => 'Johnson',
                'email' => 'alex@test.com'
            ]) // name_length = 17
        ]);

        // Filter by name length between 5 and 15
        $virtualFieldFilters = [
            [
                'field' => 'name_length',
                'operator' => 'between',
                'value' => [5, 15],
                'logic' => 'and'
            ]
        ];

        $result = $this->processor->processVirtualFieldFilters($users, $virtualFieldFilters);

        $this->assertEquals(1, $result->count());
        $this->assertEquals('Jane Smith', $result->first()->first_name . ' ' . $result->first()->last_name);
    }

    public function test_can_filter_with_boolean_virtual_field()
    {
        $users = new Collection([
            new User([
                'id' => 1,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'subscription_type' => 'premium'
            ]),
            new User([
                'id' => 2,
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'subscription_type' => 'free'
            ]),
            new User([
                'id' => 3,
                'first_name' => 'Bob',
                'last_name' => 'Johnson',
                'subscription_type' => 'premium'
            ])
        ]);

        // Filter by premium users
        $virtualFieldFilters = [
            [
                'field' => 'is_premium',
                'operator' => 'eq',
                'value' => true,
                'logic' => 'and'
            ]
        ];

        $result = $this->processor->processVirtualFieldFilters($users, $virtualFieldFilters);

        $this->assertEquals(2, $result->count());
        $resultNames = $result->map(function ($user) {
            return $user->first_name;
        })->toArray();
        $this->assertContains('John', $resultNames);
        $this->assertContains('Bob', $resultNames);
        $this->assertNotContains('Jane', $resultNames);
    }

    public function test_can_handle_complex_filter_combinations()
    {
        $users = new Collection([
            new User([
                'id' => 1,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'subscription_type' => 'premium'
            ]),
            new User([
                'id' => 2,
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane@example.com',
                'subscription_type' => 'free'
            ]),
            new User([
                'id' => 3,
                'first_name' => 'Bob',
                'last_name' => 'Johnson',
                'email' => 'bob@gmail.com',
                'subscription_type' => 'premium'
            ])
        ]);

        // Filter by example.com domain AND premium subscription
        $virtualFieldFilters = [
            [
                'field' => 'email_domain',
                'operator' => 'eq',
                'value' => 'example.com',
                'logic' => 'and'
            ],
            [
                'field' => 'is_premium',
                'operator' => 'eq',
                'value' => true,
                'logic' => 'and'
            ]
        ];

        $result = $this->processor->processVirtualFieldFilters($users, $virtualFieldFilters);

        $this->assertEquals(1, $result->count());
        $this->assertEquals('John', $result->first()->first_name);
    }

    public function test_can_optimize_query_for_virtual_field_dependencies()
    {
        $query = Mockery::mock(Builder::class);
        $queryBuilder = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        
        $query->shouldReceive('getQuery')->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('columns')->andReturn(null);
        $query->shouldReceive('addSelect')->with(['first_name', 'last_name', 'email'])->once();
        $query->shouldReceive('getEagerLoads')->andReturn([]);

        $virtualFields = ['full_name', 'email_domain'];
        $result = $this->processor->optimizeQuery($query, $virtualFields);

        $this->assertSame($query, $result);
    }

    public function test_handles_empty_collections_gracefully()
    {
        $emptyCollection = new Collection();

        $result = $this->processor->processForSelection($emptyCollection, ['full_name']);
        $this->assertTrue($result->isEmpty());

        $batchResult = $this->processor->computeBatch(['full_name'], $emptyCollection);
        $this->assertEmpty($batchResult);

        $filterResult = $this->processor->processVirtualFieldFilters($emptyCollection, [
            ['field' => 'full_name', 'operator' => 'eq', 'value' => 'test', 'logic' => 'and']
        ]);
        $this->assertTrue($filterResult->isEmpty());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}