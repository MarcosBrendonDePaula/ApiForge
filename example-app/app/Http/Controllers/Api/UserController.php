<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use MarcosBrendon\ApiForge\Traits\HasAdvancedFilters;

class UserController extends Controller
{
    use HasAdvancedFilters;

    /**
     * Classe do modelo
     */
    protected function getModelClass(): string
    {
        return User::class;
    }

    /**
     * Configuração dos filtros
     */
    protected function setupFilterConfiguration(): void
    {
        $this->configureFilters([
            // Filtros básicos de texto
            'name' => [
                'type' => 'string',
                'operators' => ['eq', 'like', 'ne'],
                'searchable' => true,
                'sortable' => true,
                'description' => 'Nome do usuário',
                'example' => [
                    'eq' => 'name=João Silva',
                    'like' => 'name=João*',
                    'ne' => 'name=!=Admin'
                ]
            ],
            'email' => [
                'type' => 'string',
                'operators' => ['eq', 'like', 'ne'],
                'searchable' => true,
                'sortable' => true,
                'description' => 'Email do usuário',
                'example' => [
                    'eq' => 'email=joao@example.com',
                    'like' => 'email=*@gmail.com'
                ]
            ],

            // Filtros de enum/status
            'role' => [
                'type' => 'enum',
                'values' => ['admin', 'manager', 'user', 'guest'],
                'operators' => ['eq', 'in', 'ne'],
                'sortable' => true,
                'description' => 'Papel do usuário no sistema',
                'example' => [
                    'eq' => 'role=admin',
                    'in' => 'role=admin,manager'
                ]
            ],
            'status' => [
                'type' => 'enum',
                'values' => ['active', 'inactive', 'suspended', 'pending'],
                'operators' => ['eq', 'in', 'ne'],
                'sortable' => true,
                'description' => 'Status da conta do usuário',
                'example' => [
                    'eq' => 'status=active',
                    'in' => 'status=active,pending'
                ]
            ],

            // Filtros de data
            'created_at' => [
                'type' => 'datetime',
                'operators' => ['gte', 'lte', 'between', 'eq'],
                'sortable' => true,
                'format' => 'Y-m-d H:i:s',
                'description' => 'Data de cadastro do usuário',
                'example' => [
                    'gte' => 'created_at=>=2024-01-01',
                    'between' => 'created_at=2024-01-01|2024-12-31'
                ]
            ],
            'email_verified_at' => [
                'type' => 'datetime',
                'operators' => ['null', 'not_null', 'gte', 'lte'],
                'sortable' => true,
                'description' => 'Data de verificação do email',
                'example' => [
                    'null' => 'email_verified_at=null',
                    'not_null' => 'email_verified_at=!null'
                ]
            ],
            'last_login_at' => [
                'type' => 'datetime',
                'operators' => ['gte', 'lte', 'between', 'null'],
                'sortable' => true,
                'description' => 'Data do último login',
                'example' => [
                    'gte' => 'last_login_at=>=2024-01-01'
                ]
            ],

            // Filtros de relacionamento
            'profile.city' => [
                'type' => 'string',
                'operators' => ['eq', 'like', 'in'],
                'sortable' => true,
                'description' => 'Cidade do perfil do usuário',
                'example' => [
                    'eq' => 'profile.city=São Paulo',
                    'like' => 'profile.city=São*'
                ]
            ],
            'profile.country' => [
                'type' => 'string',
                'operators' => ['eq', 'in'],
                'sortable' => true,
                'description' => 'País do perfil do usuário',
                'example' => [
                    'eq' => 'profile.country=Brasil',
                    'in' => 'profile.country=Brasil,Argentina'
                ]
            ]
        ]);

        // Configurar field selection
        $this->configureFieldSelection([
            'selectable_fields' => [
                'id', 'name', 'email', 'role', 'status', 
                'created_at', 'updated_at', 'email_verified_at', 'last_login_at',
                'profile.bio', 'profile.avatar', 'profile.phone', 
                'profile.address', 'profile.city', 'profile.country',
                'profile.birth_date', 'profile.website'
            ],
            'required_fields' => ['id'],
            'blocked_fields' => ['password', 'remember_token'],
            'default_fields' => ['id', 'name', 'email', 'role', 'status', 'created_at'],
            'field_aliases' => [
                'user_id' => 'id',
                'user_name' => 'name',
                'user_email' => 'email',
                'user_role' => 'role',
                'profile_city' => 'profile.city',
                'profile_country' => 'profile.country'
            ],
            'max_fields' => 20
        ]);
    }

    /**
     * Relacionamentos padrão
     */
    protected function getDefaultRelationships(): array
    {
        return ['profile'];
    }

    /**
     * Aplicar scopes padrão
     */
    protected function applyDefaultScopes($query, Request $request): void
    {
        // Por padrão, não incluir usuários suspensos a menos que explicitamente solicitado
        if (!$request->has('status')) {
            $query->where('status', '!=', 'suspended');
        }
    }

    /**
     * Ordenação padrão
     */
    protected function getDefaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    /**
     * Endpoint principal com filtros
     */
    public function index(Request $request): JsonResponse
    {
        return $this->indexWithFilters($request, [
            'cache' => true,
            'cache_ttl' => 1800, // 30 minutos
            'per_page' => 20
        ]);
    }

    /**
     * Busca rápida de usuários
     */
    public function quickSearch(Request $request): JsonResponse
    {
        $this->initializeFilterServices();

        $query = $request->get('q', '');
        $limit = min(20, (int) $request->get('limit', 10));

        if (empty($query)) {
            return response()->json([
                'success' => false,
                'message' => 'Query parameter "q" is required'
            ], 400);
        }

        $users = User::where(function ($q) use ($query) {
            $q->where('name', 'LIKE', "%{$query}%")
              ->orWhere('email', 'LIKE', "%{$query}%");
        })
        ->with('profile:user_id,bio,city,country')
        ->select('id', 'name', 'email', 'role', 'status')
        ->limit($limit)
        ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
            'meta' => [
                'query' => $query,
                'count' => $users->count(),
                'limit' => $limit
            ]
        ]);
    }

    /**
     * Estatísticas de usuários
     */
    public function statistics(Request $request): JsonResponse
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'verified_users' => User::whereNotNull('email_verified_at')->count(),
            'users_by_role' => User::selectRaw('role, COUNT(*) as count')
                ->groupBy('role')
                ->pluck('count', 'role'),
            'users_by_status' => User::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'recent_registrations' => User::where('created_at', '>=', now()->subDays(30))->count(),
            'recent_logins' => User::where('last_login_at', '>=', now()->subDays(7))->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'generated_at' => now()->toISOString()
        ]);
    }

    /**
     * Mostrar usuário específico
     */
    public function show(Request $request, $id): JsonResponse
    {
        $fields = $request->get('fields');
        $query = User::with('profile');

        if ($fields) {
            $this->initializeFilterServices();
            $this->applyFieldSelection($query, $request);
        }

        $user = $query->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }
}