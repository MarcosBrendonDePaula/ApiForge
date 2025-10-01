<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use MarcosBrendon\ApiForge\Http\Controllers\BaseApiController;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\AuditService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserController extends BaseApiController
{
    /**
     * Get the model class for this controller
     */
    protected function getModelClass(): string
    {
        return User::class;
    }

    /**
     * Validate data for store operations
     */
    protected function validateStoreData(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'sometimes|string|in:user,admin,moderator'
        ]);
    }

    /**
     * Validate data for update operations
     */
    protected function validateUpdateData(Request $request, $resource): array
    {
        return $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $resource->id,
            'password' => 'sometimes|string|min:8',
            'role' => 'sometimes|string|in:user,admin,moderator'
        ]);
    }

    /**
     * Setup filter configuration and model hooks
     */
    protected function setupFilterConfiguration(): void
    {
        // Configure regular filters
        $this->configureFilters([
            'name' => ['like', 'eq'],
            'email' => ['like', 'eq'],
            'role' => ['eq', 'in'],
            'created_at' => ['gte', 'lte', 'between'],
            'updated_at' => ['gte', 'lte', 'between']
        ]);

        // Configure advanced model hooks
        $this->configureModelHooks([
            // Authorization hooks
            'beforeAuthorization' => [
                'checkUserPermissions' => [
                    'callback' => function($model, $context) {
                        $user = auth()->user();
                        $action = $context->data['action'] ?? '';
                        
                        // Admin can do everything
                        if ($user && $user->role === 'admin') {
                            return true;
                        }
                        
                        // Users can only view and update their own data
                        if ($action === 'show' || $action === 'index') {
                            return true;
                        }
                        
                        if (($action === 'update' || $action === 'delete') && $model->exists) {
                            return $user && $user->id === $model->id;
                        }
                        
                        // Only admins can create users
                        if ($action === 'store') {
                            return $user && $user->role === 'admin';
                        }
                        
                        return false;
                    },
                    'priority' => 1,
                    'stopOnFailure' => true,
                    'description' => 'Check user permissions for the requested action'
                ]
            ],

            // Validation hooks
            'beforeValidation' => [
                'sanitizeInput' => [
                    'callback' => function($model, $context) {
                        $data = $context->data;
                        
                        // Sanitize name
                        if (isset($data['name'])) {
                            $data['name'] = trim($data['name']);
                        }
                        
                        // Normalize email
                        if (isset($data['email'])) {
                            $data['email'] = strtolower(trim($data['email']));
                        }
                        
                        return $data;
                    },
                    'priority' => 1,
                    'description' => 'Sanitize and normalize input data'
                ]
            ],

            'afterValidation' => [
                'logValidation' => [
                    'callback' => function($model, $context) {
                        Log::info('User data validated', [
                            'user_id' => auth()->id(),
                            'validated_fields' => array_keys($context->data)
                        ]);
                    },
                    'description' => 'Log successful validation'
                ]
            ],

            // Transformation hooks
            'beforeTransform' => [
                'generateSlug' => [
                    'callback' => function($model, $context) {
                        $data = $context->data;
                        
                        // Generate username from name if not provided
                        if (isset($data['name']) && !isset($data['username'])) {
                            $data['username'] = Str::slug($data['name']) . '_' . time();
                        }
                        
                        return $data;
                    },
                    'description' => 'Generate username slug from name'
                ],
                
                'hashPassword' => [
                    'callback' => function($model, $context) {
                        $data = $context->data;
                        
                        // Hash password if provided
                        if (isset($data['password'])) {
                            $data['password'] = bcrypt($data['password']);
                        }
                        
                        return $data;
                    },
                    'priority' => 5,
                    'description' => 'Hash user password'
                ]
            ],

            // Store hooks
            'beforeStore' => [
                'setDefaults' => [
                    'callback' => function($model, $context) {
                        // Set default role if not provided
                        if (!$model->role) {
                            $model->role = 'user';
                        }
                        
                        // Set email verification timestamp for admins
                        if (auth()->user() && auth()->user()->role === 'admin') {
                            $model->email_verified_at = now();
                        }
                    },
                    'description' => 'Set default values for new users'
                ]
            ],

            'afterStore' => [
                'sendWelcomeEmail' => [
                    'callback' => function($model, $context) {
                        // Send welcome email to new users
                        NotificationService::sendWelcomeEmail($model);
                    },
                    'priority' => 10,
                    'description' => 'Send welcome email to new users'
                ],
                
                'clearUserCache' => [
                    'callback' => function($model, $context) {
                        Cache::tags(['users'])->flush();
                    },
                    'priority' => 20,
                    'description' => 'Clear user-related cache'
                ]
            ],

            // Update hooks
            'beforeUpdate' => [
                'trackChanges' => [
                    'callback' => function($model, $context) {
                        $changes = $context->data['changes'] ?? [];
                        
                        if (!empty($changes)) {
                            // Store changes for audit
                            $model->setAttribute('_audit_changes', $changes);
                        }
                    },
                    'description' => 'Track changes for audit purposes'
                ]
            ],

            'afterUpdate' => [
                'invalidateCache' => [
                    'callback' => function($model, $context) {
                        // Invalidate specific user cache
                        Cache::forget("user_{$model->id}");
                        Cache::tags(['users'])->flush();
                    },
                    'priority' => 1,
                    'description' => 'Invalidate user cache after update'
                ],
                
                'notifyProfileUpdate' => [
                    'callback' => function($model, $context) {
                        $changes = $context->data['changes'] ?? [];
                        
                        // Notify user of profile changes
                        if (!empty($changes)) {
                            NotificationService::sendProfileUpdateNotification($model, $changes);
                        }
                    },
                    'priority' => 10,
                    'description' => 'Notify user of profile updates'
                ]
            ],

            // Delete hooks
            'beforeDelete' => [
                'checkDependencies' => [
                    'callback' => function($model, $context) {
                        // Prevent deletion if user has orders
                        if ($model->orders()->exists()) {
                            throw new \Exception('Cannot delete user with existing orders');
                        }
                        
                        // Prevent deletion of admin users by non-admins
                        if ($model->role === 'admin' && (!auth()->user() || auth()->user()->role !== 'admin')) {
                            return false;
                        }
                        
                        return true;
                    },
                    'priority' => 1,
                    'stopOnFailure' => true,
                    'description' => 'Check dependencies before deletion'
                ]
            ],

            'afterDelete' => [
                'cleanupUserData' => [
                    'callback' => function($model, $context) {
                        // Clean up user-related data
                        Cache::forget("user_{$model->id}");
                        Cache::tags(['users'])->flush();
                        
                        // Delete user files if any
                        if ($model->avatar) {
                            \Storage::delete($model->avatar);
                        }
                    },
                    'priority' => 1,
                    'description' => 'Clean up user data after deletion'
                ],
                
                'notifyAdmins' => [
                    'callback' => function($model, $context) {
                        // Notify admins of user deletion
                        NotificationService::notifyAdmins('User deleted', [
                            'deleted_user' => $model->email,
                            'deleted_by' => auth()->user()->email ?? 'system',
                            'deleted_at' => now()
                        ]);
                    },
                    'priority' => 10,
                    'description' => 'Notify admins of user deletion'
                ]
            ],

            // Audit hooks
            'beforeAudit' => [
                'prepareAuditData' => [
                    'callback' => function($model, $context) {
                        $auditData = [
                            'user_id' => auth()->id(),
                            'ip_address' => request()->ip(),
                            'user_agent' => request()->userAgent(),
                            'timestamp' => now()
                        ];
                        
                        // Store audit data in context for afterAudit
                        $context->set('audit_metadata', $auditData);
                    },
                    'description' => 'Prepare audit metadata'
                ]
            ],

            'afterAudit' => [
                'logAuditTrail' => [
                    'callback' => function($model, $context) {
                        $auditMetadata = $context->get('audit_metadata', []);
                        
                        AuditService::log([
                            'model_type' => get_class($model),
                            'model_id' => $model->getKey(),
                            'action' => $context->data['action'] ?? 'unknown',
                            'changes' => $context->data['changes'] ?? [],
                            'metadata' => $auditMetadata
                        ]);
                    },
                    'description' => 'Log audit trail'
                ]
            ],

            // Query hooks
            'beforeQuery' => [
                'optimizeQuery' => [
                    'callback' => function($model, $context) {
                        $query = $context->data['query'] ?? null;
                        
                        if ($query) {
                            // Add common eager loading
                            $query->with(['profile', 'roles']);
                        }
                    },
                    'description' => 'Optimize queries with eager loading'
                ]
            ],

            // Response hooks
            'beforeResponse' => [
                'formatResponse' => [
                    'callback' => function($model, $context) {
                        $responseData = $context->data;
                        
                        // Add metadata to response
                        if (isset($responseData['data'])) {
                            $responseData['metadata'] = array_merge(
                                $responseData['metadata'] ?? [],
                                [
                                    'processed_at' => now()->toISOString(),
                                    'version' => '2.0'
                                ]
                            );
                        }
                        
                        return $responseData;
                    },
                    'description' => 'Format response with additional metadata'
                ]
            ],

            // Cache hooks
            'beforeCache' => [
                'prepareCacheKey' => [
                    'callback' => function($model, $context) {
                        $queryParams = $context->data['query_params'] ?? [];
                        
                        // Generate cache key based on query parameters
                        $cacheKey = 'users_' . md5(serialize($queryParams));
                        
                        return ['cache_key' => $cacheKey];
                    },
                    'description' => 'Prepare cache key for query results'
                ]
            ],

            // Notification hooks
            'beforeNotification' => [
                'prepareNotificationData' => [
                    'callback' => function($model, $context) {
                        $notificationData = $context->data;
                        
                        // Add user preferences to notification data
                        $notificationData['user_preferences'] = $model->notification_preferences ?? [];
                        
                        return $notificationData;
                    },
                    'description' => 'Prepare notification data with user preferences'
                ]
            ]
        ]);
    }
}