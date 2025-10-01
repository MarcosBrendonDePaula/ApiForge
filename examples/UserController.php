<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use MarcosBrendon\ApiForge\Http\Controllers\BaseApiController;

/**
 * Example User Controller using ApiForge
 * 
 * This demonstrates how to use the package with a User model
 */
class UserController extends BaseApiController
{
    /**
     * Get the model class for this controller
     *
     * @return string
     */
    protected function getModelClass(): string
    {
        return User::class;
    }

    /**
     * Setup filter configuration for the User model
     *
     * @return void
     */
    protected function setupFilterConfiguration(): void
    {
        // Configure available filters
        $this->configureFilters([
            'name' => [
                'type' => 'string',
                'operators' => ['eq', 'like', 'ne'],
                'description' => 'User full name',
                'searchable' => true,
                'sortable' => true,
                'example' => [
                    'eq' => 'name=John Doe',
                    'like' => 'name=John*',
                    'ne' => 'name=!=Admin'
                ]
            ],
            'email' => [
                'type' => 'string',
                'operators' => ['eq', 'like', 'ne'],
                'description' => 'User email address',
                'searchable' => true,
                'sortable' => true,
                'example' => [
                    'eq' => 'email=user@example.com',
                    'like' => 'email=*@gmail.com'
                ]
            ],
            'email_verified_at' => [
                'type' => 'datetime',
                'operators' => ['null', 'not_null', 'gte', 'lte'],
                'description' => 'Email verification date',
                'sortable' => true,
                'example' => [
                    'null' => 'email_verified_at=null',
                    'gte' => 'email_verified_at=>=2024-01-01'
                ]
            ],
            'active' => [
                'type' => 'boolean',
                'operators' => ['eq'],
                'description' => 'User active status',
                'example' => [
                    'eq' => 'active=true'
                ]
            ],
            'role' => [
                'type' => 'enum',
                'operators' => ['eq', 'in', 'ne'],
                'values' => ['admin', 'user', 'moderator'],
                'description' => 'User role',
                'example' => [
                    'eq' => 'role=admin',
                    'in' => 'role=admin,moderator'
                ]
            ],
            'created_at' => [
                'type' => 'datetime',
                'operators' => ['eq', 'gte', 'lte', 'between'],
                'description' => 'User registration date',
                'sortable' => true,
                'format' => 'Y-m-d H:i:s',
                'example' => [
                    'gte' => 'created_at=>=2024-01-01',
                    'between' => 'created_at=2024-01-01|2024-12-31'
                ]
            ]
        ]);

        // Configure virtual fields for computed data
        $this->configureVirtualFields([
            'display_name' => [
                'type' => 'string',
                'callback' => function($user) {
                    return $user->name ?: $user->email;
                },
                'dependencies' => ['name', 'email'],
                'operators' => ['eq', 'like', 'ne'],
                'searchable' => true,
                'sortable' => true,
                'description' => 'Display name (name or email if name is empty)',
                'example' => [
                    'eq' => 'display_name=John Doe',
                    'like' => 'display_name=John*'
                ]
            ],
            
            'full_name' => [
                'type' => 'string',
                'callback' => function($user) {
                    return trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                },
                'dependencies' => ['first_name', 'last_name'],
                'operators' => ['eq', 'like', 'ne'],
                'searchable' => true,
                'sortable' => true,
                'nullable' => true,
                'description' => 'Full name from first and last name fields'
            ],
            
            'age' => [
                'type' => 'integer',
                'callback' => function($user) {
                    return $user->birth_date ? 
                        \Carbon\Carbon::parse($user->birth_date)->age : null;
                },
                'dependencies' => ['birth_date'],
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'nullable' => true,
                'sortable' => true,
                'description' => 'Calculated age from birth date',
                'example' => [
                    'gte' => 'age=>=18',
                    'between' => 'age=18|65'
                ]
            ],
            
            'account_age_days' => [
                'type' => 'integer',
                'callback' => function($user) {
                    return $user->created_at ? 
                        $user->created_at->diffInDays(now()) : 0;
                },
                'dependencies' => ['created_at'],
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'description' => 'Days since account creation',
                'example' => [
                    'gte' => 'account_age_days=>=30',
                    'lt' => 'account_age_days=<365'
                ]
            ],
            
            'is_verified' => [
                'type' => 'boolean',
                'callback' => function($user) {
                    return !is_null($user->email_verified_at);
                },
                'dependencies' => ['email_verified_at'],
                'operators' => ['eq'],
                'description' => 'Whether user email is verified',
                'example' => [
                    'eq' => 'is_verified=true'
                ]
            ],
            
            'order_count' => [
                'type' => 'integer',
                'callback' => function($user) {
                    return $user->orders_count ?? $user->orders()->count();
                },
                'relationships' => ['orders'],
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'cacheable' => true,
                'cache_ttl' => 1800, // 30 minutes
                'description' => 'Total number of orders placed by user',
                'example' => [
                    'gt' => 'order_count=>5',
                    'between' => 'order_count=1|10'
                ]
            ],
            
            'total_spent' => [
                'type' => 'float',
                'callback' => function($user) {
                    return $user->orders()->sum('total') ?: 0.0;
                },
                'relationships' => ['orders'],
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'cacheable' => true,
                'cache_ttl' => 3600, // 1 hour
                'default_value' => 0.0,
                'description' => 'Total amount spent by user across all orders',
                'example' => [
                    'gte' => 'total_spent=>=1000.00',
                    'between' => 'total_spent=100.00|5000.00'
                ]
            ],
            
            'average_order_value' => [
                'type' => 'float',
                'callback' => function($user) {
                    $orderCount = $user->orders()->count();
                    if ($orderCount === 0) return 0.0;
                    
                    $totalSpent = $user->orders()->sum('total');
                    return round($totalSpent / $orderCount, 2);
                },
                'relationships' => ['orders'],
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'cacheable' => true,
                'cache_ttl' => 3600,
                'default_value' => 0.0,
                'description' => 'Average value per order',
                'example' => [
                    'gte' => 'average_order_value=>=50.00'
                ]
            ],
            
            'customer_tier' => [
                'type' => 'enum',
                'values' => ['bronze', 'silver', 'gold', 'platinum'],
                'callback' => [$this, 'calculateCustomerTier'],
                'relationships' => ['orders'],
                'operators' => ['eq', 'in', 'ne'],
                'sortable' => true,
                'cacheable' => true,
                'cache_ttl' => 3600,
                'default_value' => 'bronze',
                'description' => 'Customer tier based on total spending',
                'example' => [
                    'eq' => 'customer_tier=gold',
                    'in' => 'customer_tier=gold,platinum'
                ]
            ],
            
            'last_order_date' => [
                'type' => 'datetime',
                'callback' => function($user) {
                    $lastOrder = $user->orders()->latest()->first();
                    return $lastOrder ? $lastOrder->created_at : null;
                },
                'relationships' => ['orders'],
                'operators' => ['eq', 'gte', 'lte', 'between', 'null', 'not_null'],
                'sortable' => true,
                'nullable' => true,
                'cacheable' => true,
                'cache_ttl' => 1800,
                'description' => 'Date of most recent order',
                'example' => [
                    'gte' => 'last_order_date=>=2024-01-01',
                    'null' => 'last_order_date=null'
                ]
            ],
            
            'days_since_last_order' => [
                'type' => 'integer',
                'callback' => function($user) {
                    $lastOrder = $user->orders()->latest()->first();
                    return $lastOrder ? 
                        $lastOrder->created_at->diffInDays(now()) : null;
                },
                'relationships' => ['orders'],
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between', 'null'],
                'sortable' => true,
                'nullable' => true,
                'cacheable' => true,
                'cache_ttl' => 1800,
                'description' => 'Days since last order was placed',
                'example' => [
                    'gt' => 'days_since_last_order=>30',
                    'null' => 'days_since_last_order=null'
                ]
            ],
            
            'is_premium' => [
                'type' => 'boolean',
                'callback' => function($user) {
                    return $user->subscriptions()
                        ->where('status', 'active')
                        ->where('type', 'premium')
                        ->exists();
                },
                'relationships' => ['subscriptions'],
                'operators' => ['eq'],
                'cacheable' => true,
                'cache_ttl' => 900, // 15 minutes
                'description' => 'Whether user has active premium subscription',
                'example' => [
                    'eq' => 'is_premium=true'
                ]
            ],
            
            'subscription_status' => [
                'type' => 'enum',
                'values' => ['none', 'trial', 'active', 'expired', 'cancelled'],
                'callback' => function($user) {
                    $subscription = $user->subscriptions()->latest()->first();
                    return $subscription ? $subscription->status : 'none';
                },
                'relationships' => ['subscriptions'],
                'operators' => ['eq', 'in', 'ne'],
                'cacheable' => true,
                'cache_ttl' => 900,
                'default_value' => 'none',
                'description' => 'Current subscription status',
                'example' => [
                    'eq' => 'subscription_status=active',
                    'in' => 'subscription_status=active,trial'
                ]
            ],
            
            'profile_completion' => [
                'type' => 'integer',
                'callback' => function($user) {
                    $fields = ['name', 'email', 'phone', 'birth_date', 'avatar'];
                    $completed = 0;
                    
                    foreach ($fields as $field) {
                        if ($field === 'avatar') {
                            if ($user->profile && $user->profile->avatar) $completed++;
                        } elseif ($field === 'phone') {
                            if ($user->profile && $user->profile->phone) $completed++;
                        } else {
                            if (!empty($user->$field)) $completed++;
                        }
                    }
                    
                    return round(($completed / count($fields)) * 100);
                },
                'dependencies' => ['name', 'email', 'birth_date'],
                'relationships' => ['profile'],
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'description' => 'Profile completion percentage (0-100)',
                'example' => [
                    'gte' => 'profile_completion=>=80',
                    'lt' => 'profile_completion=<50'
                ]
            ],
            
            'risk_score' => [
                'type' => 'integer',
                'callback' => [$this, 'calculateRiskScore'],
                'relationships' => ['orders', 'loginAttempts', 'reports'],
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'cacheable' => true,
                'cache_ttl' => 1800,
                'default_value' => 0,
                'description' => 'User risk score based on various factors (0-100)',
                'example' => [
                    'gt' => 'risk_score=>50',
                    'lte' => 'risk_score=<=25'
                ]
            ]
        ]);

        // Configure field selection
        $this->configureFieldSelection([
            'selectable_fields' => [
                'id', 'name', 'email', 'email_verified_at', 
                'active', 'role', 'created_at', 'updated_at',
                'profile.avatar', 'profile.bio', 'profile.phone',
                // Virtual fields
                'display_name', 'full_name', 'age', 'account_age_days',
                'is_verified', 'order_count', 'total_spent', 'average_order_value',
                'customer_tier', 'last_order_date', 'days_since_last_order',
                'is_premium', 'subscription_status', 'profile_completion', 'risk_score'
            ],
            'required_fields' => ['id'],
            'blocked_fields' => ['password', 'remember_token', 'two_factor_secret'],
            'default_fields' => ['id', 'name', 'email', 'active', 'role', 'display_name'],
            'field_aliases' => [
                'user_id' => 'id',
                'user_name' => 'name',
                'user_email' => 'email',
                'username' => 'name',
                'tier' => 'customer_tier',
                'orders' => 'order_count',
                'spent' => 'total_spent'
            ],
            'max_fields' => 25
        ]);

        // Configure model hooks for advanced functionality
        $this->configureModelHooks([
            'beforeStore' => [
                'generateSlug' => [
                    'callback' => function($model, $context) {
                        if (empty($model->slug) && !empty($model->name)) {
                            $model->slug = \Str::slug($model->name);
                        }
                    },
                    'priority' => 1,
                    'description' => 'Generate slug from name if not provided'
                ],
                'validateBusinessRules' => [
                    'callback' => function($model, $context) {
                        // Custom business validation
                        if ($model->role === 'admin' && !auth()->user()->can('create-admin')) {
                            throw new \Illuminate\Validation\ValidationException(
                                validator([], []), ['role' => 'You cannot create admin users']
                            );
                        }
                    },
                    'priority' => 2,
                    'stopOnFailure' => true,
                    'description' => 'Validate business rules before creation'
                ]
            ],
            
            'afterStore' => [
                'sendWelcomeEmail' => [
                    'callback' => function($model, $context) {
                        // Send welcome email to new users
                        if ($model->email && $model->active) {
                            \Mail::to($model->email)->queue(new \App\Mail\WelcomeUser($model));
                        }
                    },
                    'priority' => 10,
                    'description' => 'Send welcome email to new users'
                ],
                'createUserProfile' => [
                    'callback' => function($model, $context) {
                        // Create default user profile
                        $model->profile()->create([
                            'bio' => 'New user',
                            'preferences' => json_encode(['theme' => 'light'])
                        ]);
                    },
                    'priority' => 5,
                    'description' => 'Create default user profile'
                ]
            ],
            
            'beforeUpdate' => [
                'trackChanges' => [
                    'callback' => function($model, $context) {
                        $changes = $model->getDirty();
                        if (!empty($changes)) {
                            \Log::info('User updated', [
                                'user_id' => $model->id,
                                'changes' => $changes,
                                'updated_by' => auth()->id()
                            ]);
                        }
                    },
                    'priority' => 1,
                    'description' => 'Log user changes for audit trail'
                ]
            ],
            
            'afterUpdate' => [
                'syncRelatedData' => [
                    'callback' => function($model, $context) {
                        // Sync role changes to related models
                        if ($model->wasChanged('role')) {
                            $model->permissions()->sync(
                                \App\Models\Role::where('name', $model->role)->first()->permissions ?? []
                            );
                        }
                    },
                    'priority' => 5,
                    'description' => 'Sync permissions when role changes'
                ],
                'invalidateCache' => [
                    'callback' => function($model, $context) {
                        // Clear user-related cache
                        \Cache::forget("user_permissions_{$model->id}");
                        \Cache::forget("user_profile_{$model->id}");
                    },
                    'priority' => 15,
                    'description' => 'Clear user cache after updates'
                ]
            ],
            
            'beforeDelete' => [
                'checkDependencies' => [
                    'callback' => function($model, $context) {
                        // Prevent deletion if user has active orders
                        if ($model->orders()->where('status', 'active')->exists()) {
                            throw new \Exception('Cannot delete user with active orders');
                        }
                        return true;
                    },
                    'priority' => 1,
                    'stopOnFailure' => true,
                    'description' => 'Check for dependencies before deletion'
                ]
            ],
            
            'afterDelete' => [
                'cleanupFiles' => [
                    'callback' => function($model, $context) {
                        // Delete user avatar and files
                        if ($model->profile && $model->profile->avatar) {
                            \Storage::delete($model->profile->avatar);
                        }
                    },
                    'priority' => 10,
                    'description' => 'Clean up user files after deletion'
                ],
                'notifyAdmins' => [
                    'callback' => function($model, $context) {
                        // Notify admins of user deletion
                        $admins = \App\Models\User::where('role', 'admin')->get();
                        foreach ($admins as $admin) {
                            $admin->notify(new \App\Notifications\UserDeleted($model));
                        }
                    },
                    'priority' => 20,
                    'description' => 'Notify administrators of user deletion'
                ]
            ]
        ]);

        // Configure helper hooks using convenience methods
        $this->configureAuditHooks([
            'fields' => ['name', 'email', 'role', 'active'],
            'track_user' => true,
            'audit_table' => 'user_audit_logs'
        ]);

        $this->configureNotificationHooks([
            'onCreate' => [
                'notification' => \App\Notifications\UserCreated::class,
                'recipients' => ['admin', 'current_user'],
                'channels' => ['mail', 'database'],
                'priority' => 10
            ],
            'onUpdate' => [
                'notification' => \App\Notifications\UserUpdated::class,
                'recipients' => ['owner', 'admin'],
                'channels' => ['database'],
                'watch_fields' => ['role', 'active'],
                'priority' => 10
            ]
        ]);

        $this->configureCacheInvalidationHooks([
            'user_list',
            'user_stats',
            'user_permissions_{model_id}',
            'user_profile_{model_id}'
        ]);

        $this->configureSlugHooks('name', 'slug', [
            'unique' => true,
            'overwrite' => false
        ]);

        $this->configurePermissionHooks([
            'create' => 'create-users',
            'update' => function($user, $model, $action) {
                // Users can update themselves, admins can update anyone
                return $user->id === $model->id || $user->can('update-users');
            },
            'delete' => ['delete-users', 'admin-access']
        ]);
    }

    /**
     * Get default relationships to load
     *
     * @return array
     */
    protected function getDefaultRelationships(): array
    {
        return ['profile:id,user_id,avatar,bio,phone'];
    }

    /**
     * Apply default scopes to the query
     *
     * @param mixed $query
     * @param Request $request
     * @return void
     */
    protected function applyDefaultScopes($query, Request $request): void
    {
        // Only show active users by default unless specifically filtered
        if (!$request->has('active') && !$request->has('filters')) {
            $query->where('active', true);
        }

        // Apply company scope if multi-tenant
        if (auth()->check() && auth()->user()->company_id) {
            $query->where('company_id', auth()->user()->company_id);
        }
    }

    /**
     * Get default sort configuration
     *
     * @return array [field, direction]
     */
    protected function getDefaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    /**
     * Validate data for user creation
     *
     * @param Request $request
     * @return array
     */
    protected function validateStoreData(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|in:admin,user,moderator',
            'active' => 'boolean'
        ]);
    }

    /**
     * Validate data for user update
     *
     * @param Request $request
     * @param mixed $user
     * @return array
     */
    protected function validateUpdateData(Request $request, $user): array
    {
        return $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'sometimes|required|string|min:8|confirmed',
            'role' => 'sometimes|required|string|in:admin,user,moderator',
            'active' => 'sometimes|boolean'
        ]);
    }

    /**
     * Transform data before storing
     *
     * @param array $data
     * @param Request $request
     * @return array
     */
    protected function transformStoreData(array $data, Request $request): array
    {
        // Hash password
        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        // Set default active status
        $data['active'] = $data['active'] ?? true;

        // Set company_id for multi-tenant
        if (auth()->check() && auth()->user()->company_id) {
            $data['company_id'] = auth()->user()->company_id;
        }

        return $data;
    }

    /**
     * Transform data before updating
     *
     * @param array $data
     * @param Request $request
     * @param mixed $user
     * @return array
     */
    protected function transformUpdateData(array $data, Request $request, $user): array
    {
        // Hash password if provided
        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        return $data;
    }

    /**
     * Get custom statistics for users
     *
     * @param mixed $query
     * @param Request $request
     * @return array
     */
    protected function getCustomStatistics($query, Request $request): array
    {
        $baseQuery = clone $query;

        return [
            'total_active' => (clone $baseQuery)->where('active', true)->count(),
            'total_inactive' => (clone $baseQuery)->where('active', false)->count(),
            'verified_users' => (clone $baseQuery)->whereNotNull('email_verified_at')->count(),
            'unverified_users' => (clone $baseQuery)->whereNull('email_verified_at')->count(),
            'admins' => (clone $baseQuery)->where('role', 'admin')->count(),
            'regular_users' => (clone $baseQuery)->where('role', 'user')->count(),
            'recent_registrations' => (clone $baseQuery)->where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }

    /**
     * Check if user can be deleted
     *
     * @param mixed $user
     * @param Request $request
     * @return bool
     */
    protected function canDelete($user, Request $request): bool
    {
        // Don't allow deleting the last admin
        if ($user->role === 'admin') {
            $adminCount = User::where('role', 'admin')->where('active', true)->count();
            if ($adminCount <= 1) {
                return false;
            }
        }

        // Don't allow users to delete themselves
        if (auth()->check() && auth()->id() === $user->id) {
            return false;
        }

        return true;
    }

    /**
     * Get fields for quick search
     *
     * @return array
     */
    protected function getQuickSearchFields(): array
    {
        return ['id', 'name', 'email', 'role', 'display_name'];
    }

    /**
     * Calculate customer tier based on total spending
     *
     * @param \App\Models\User $user
     * @return string
     */
    public function calculateCustomerTier($user): string
    {
        $totalSpent = $user->orders()->sum('total') ?: 0;
        
        if ($totalSpent >= 10000) {
            return 'platinum';
        } elseif ($totalSpent >= 5000) {
            return 'gold';
        } elseif ($totalSpent >= 1000) {
            return 'silver';
        }
        
        return 'bronze';
    }

    /**
     * Calculate user risk score based on various factors
     *
     * @param \App\Models\User $user
     * @return int Risk score from 0-100
     */
    public function calculateRiskScore($user): int
    {
        $score = 0;
        
        // Account age factor (newer accounts are riskier)
        $accountAgeDays = $user->created_at->diffInDays(now());
        if ($accountAgeDays < 30) {
            $score += 20;
        } elseif ($accountAgeDays < 90) {
            $score += 10;
        }
        
        // Email verification factor
        if (!$user->email_verified_at) {
            $score += 15;
        }
        
        // Order behavior factor
        $orderCount = $user->orders()->count();
        $totalSpent = $user->orders()->sum('total');
        
        if ($orderCount > 0) {
            $avgOrderValue = $totalSpent / $orderCount;
            
            // Unusually high order values can be risky
            if ($avgOrderValue > 1000) {
                $score += 10;
            }
            
            // Too many orders in short time
            $recentOrders = $user->orders()->where('created_at', '>=', now()->subDays(7))->count();
            if ($recentOrders > 10) {
                $score += 15;
            }
        } else {
            // No orders might indicate fake account
            if ($accountAgeDays > 30) {
                $score += 10;
            }
        }
        
        // Failed login attempts
        $failedLogins = $user->loginAttempts()
            ->where('successful', false)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        
        if ($failedLogins > 10) {
            $score += 20;
        } elseif ($failedLogins > 5) {
            $score += 10;
        }
        
        // User reports/complaints
        $reportCount = $user->reports()->where('status', 'open')->count();
        $score += min($reportCount * 5, 25); // Max 25 points from reports
        
        // Profile completion (incomplete profiles are riskier)
        $profileFields = ['name', 'phone', 'birth_date'];
        $completedFields = 0;
        
        foreach ($profileFields as $field) {
            if ($field === 'phone') {
                if ($user->profile && $user->profile->phone) $completedFields++;
            } else {
                if (!empty($user->$field)) $completedFields++;
            }
        }
        
        $completionRate = $completedFields / count($profileFields);
        if ($completionRate < 0.5) {
            $score += 10;
        }
        
        return min($score, 100); // Cap at 100
    }

    /**
     * Example of using virtual fields in custom endpoints
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function customerAnalytics(Request $request)
    {
        // Use virtual fields in custom analytics
        $users = $this->getFilteredQuery($request)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'customer_tier' => $this->calculateCustomerTier($user),
                    'total_spent' => $user->orders()->sum('total'),
                    'order_count' => $user->orders()->count(),
                    'risk_score' => $this->calculateRiskScore($user),
                    'profile_completion' => $this->calculateProfileCompletion($user)
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $users,
            'analytics' => [
                'tier_distribution' => $users->groupBy('customer_tier')->map->count(),
                'avg_risk_score' => $users->avg('risk_score'),
                'high_risk_users' => $users->where('risk_score', '>', 70)->count(),
                'incomplete_profiles' => $users->where('profile_completion', '<', 80)->count()
            ]
        ]);
    }

    /**
     * Helper method for profile completion calculation
     *
     * @param \App\Models\User $user
     * @return int
     */
    private function calculateProfileCompletion($user): int
    {
        $fields = ['name', 'email', 'phone', 'birth_date', 'avatar'];
        $completed = 0;
        
        foreach ($fields as $field) {
            if ($field === 'avatar') {
                if ($user->profile && $user->profile->avatar) $completed++;
            } elseif ($field === 'phone') {
                if ($user->profile && $user->profile->phone) $completed++;
            } else {
                if (!empty($user->$field)) $completed++;
            }
        }
        
        return round(($completed / count($fields)) * 100);
    }

    /**
     * Example of complex filtering with virtual fields
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function premiumCustomers(Request $request)
    {
        // Pre-filter for premium customers using virtual fields
        $request->merge([
            'customer_tier' => 'gold,platinum',
            'is_premium' => 'true',
            'total_spent' => '>=1000',
            'fields' => 'id,name,email,customer_tier,total_spent,order_count,is_premium'
        ]);

        return $this->indexWithFilters($request, [
            'cache' => true,
            'cache_ttl' => 1800,
            'cache_tags' => ['users', 'premium']
        ]);
    }

    /**
     * Example of risk assessment endpoint
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function riskAssessment(Request $request)
    {
        $request->merge([
            'risk_score' => '>=50',
            'sort_by' => 'risk_score',
            'sort_direction' => 'desc',
            'fields' => 'id,name,email,risk_score,account_age_days,is_verified,order_count'
        ]);

        return $this->indexWithFilters($request, [
            'per_page' => 50,
            'cache' => false // Don't cache risk data
        ]);
    }
}