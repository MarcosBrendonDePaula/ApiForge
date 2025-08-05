<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use MarcosBrendon\ApiForge\Http\Controllers\BaseApiController;

/**
 * Example User Controller using Laravel Advanced API Filters
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