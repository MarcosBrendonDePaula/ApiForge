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

        // Configure field selection
        $this->configureFieldSelection([
            'selectable_fields' => [
                'id', 'name', 'email', 'email_verified_at', 
                'active', 'role', 'created_at', 'updated_at',
                'profile.avatar', 'profile.bio', 'profile.phone'
            ],
            'required_fields' => ['id'],
            'blocked_fields' => ['password', 'remember_token', 'two_factor_secret'],
            'default_fields' => ['id', 'name', 'email', 'active', 'role'],
            'field_aliases' => [
                'user_id' => 'id',
                'user_name' => 'name',
                'user_email' => 'email',
                'username' => 'name'
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
        return ['id', 'name', 'email', 'role'];
    }
}