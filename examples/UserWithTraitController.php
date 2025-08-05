<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use MarcosBrendon\ApiForge\Traits\HasAdvancedFilters;

/**
 * Example User Controller using only the trait (without extending BaseApiController)
 * 
 * This demonstrates how to use just the trait in your existing controllers
 */
class UserWithTraitController extends Controller
{
    use HasAdvancedFilters;

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
            ],
            'email' => [
                'type' => 'string',
                'operators' => ['eq', 'like', 'ne'],
                'description' => 'User email address',
                'searchable' => true,
                'sortable' => true,
            ],
            'active' => [
                'type' => 'boolean',
                'operators' => ['eq'],
                'description' => 'User active status',
            ],
            'created_at' => [
                'type' => 'datetime',
                'operators' => ['eq', 'gte', 'lte', 'between'],
                'description' => 'User registration date',
                'sortable' => true,
            ]
        ]);

        // Configure field selection
        $this->configureFieldSelection([
            'selectable_fields' => ['id', 'name', 'email', 'active', 'created_at', 'updated_at'],
            'required_fields' => ['id'],
            'blocked_fields' => ['password', 'remember_token'],
            'default_fields' => ['id', 'name', 'email', 'active'],
            'max_fields' => 15
        ]);
    }

    /**
     * List users with advanced filtering
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Use the trait method
        return $this->indexWithFilters($request);
    }

    /**
     * Get filter metadata
     *
     * @return JsonResponse
     */
    public function metadata(): JsonResponse
    {
        return $this->filterMetadata();
    }

    /**
     * Get filter examples
     *
     * @return JsonResponse
     */
    public function examples(): JsonResponse
    {
        return $this->filterExamples();
    }

    /**
     * Quick search users
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $searchTerm = $request->get('q');
        $limit = min(50, max(1, (int) $request->get('limit', 10)));

        if (empty($searchTerm)) {
            return response()->json([
                'success' => false,
                'message' => 'Search term is required'
            ], 400);
        }

        $results = User::where(function ($query) use ($searchTerm) {
            $query->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('email', 'LIKE', "%{$searchTerm}%");
        })
        ->select(['id', 'name', 'email', 'active'])
        ->limit($limit)
        ->get();

        return response()->json([
            'success' => true,
            'data' => $results
        ]);
    }

    /**
     * Apply default scopes (optional implementation)
     *
     * @param mixed $query
     * @param Request $request
     * @return void
     */
    protected function applyDefaultScopes($query, Request $request): void
    {
        // Only show active users by default unless specifically filtered
        if (!$request->has('active')) {
            $query->where('active', true);
        }
    }

    /**
     * Get default sort (optional implementation)
     *
     * @return array [field, direction]
     */
    protected function getDefaultSort(): array
    {
        return ['name', 'asc'];
    }
}