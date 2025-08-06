<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use MarcosBrendon\ApiForge\Traits\HasAdvancedFilters;

class OrderController extends Controller
{
    use HasAdvancedFilters;

    /**
     * Classe do modelo
     */
    protected function getModelClass(): string
    {
        return Order::class;
    }

    /**
     * Configuração dos filtros para pedidos
     */
    protected function setupFilterConfiguration(): void
    {
        $this->configureFilters([
            // Filtros básicos do pedido
            'order_number' => [
                'type' => 'string',
                'operators' => ['eq', 'like'],
                'sortable' => true,
                'description' => 'Número do pedido',
                'example' => [
                    'eq' => 'order_number=ORD-2024-001',
                    'like' => 'order_number=ORD-2024*'
                ]
            ],

            // Filtros de status
            'status' => [
                'type' => 'enum',
                'values' => ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'],
                'operators' => ['eq', 'in', 'ne'],
                'sortable' => true,
                'description' => 'Status do pedido',
                'example' => [
                    'eq' => 'status=completed',
                    'in' => 'status=shipped,delivered',
                    'ne' => 'status=!=cancelled'
                ]
            ],
            'payment_status' => [
                'type' => 'enum',
                'values' => ['pending', 'paid', 'failed', 'refunded', 'partially_refunded'],
                'operators' => ['eq', 'in', 'ne'],
                'sortable' => true,
                'description' => 'Status do pagamento',
                'example' => [
                    'eq' => 'payment_status=paid',
                    'in' => 'payment_status=paid,partially_refunded'
                ]
            ],
            'payment_method' => [
                'type' => 'enum',
                'values' => ['credit_card', 'debit_card', 'pix', 'bank_transfer', 'paypal', 'cash'],
                'operators' => ['eq', 'in'],
                'description' => 'Método de pagamento',
                'example' => [
                    'eq' => 'payment_method=credit_card',
                    'in' => 'payment_method=credit_card,pix'
                ]
            ],

            // Filtros de valores
            'subtotal' => [
                'type' => 'decimal',
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'description' => 'Subtotal do pedido',
                'example' => [
                    'gte' => 'subtotal=>=100',
                    'between' => 'subtotal=100|500'
                ]
            ],
            'total' => [
                'type' => 'decimal',
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'description' => 'Total do pedido',
                'example' => [
                    'gte' => 'total=>=100',
                    'between' => 'total=100|1000'
                ]
            ],
            'tax_amount' => [
                'type' => 'decimal',
                'operators' => ['gt', 'gte', 'lte'],
                'sortable' => true,
                'description' => 'Valor dos impostos',
                'example' => [
                    'gte' => 'tax_amount=>=10'
                ]
            ],
            'shipping_amount' => [
                'type' => 'decimal',
                'operators' => ['eq', 'gt', 'gte', 'lte'],
                'sortable' => true,
                'description' => 'Valor do frete',
                'example' => [
                    'eq' => 'shipping_amount=0',
                    'lte' => 'shipping_amount=<=50'
                ]
            ],

            // Filtros de data
            'created_at' => [
                'type' => 'datetime',
                'operators' => ['gte', 'lte', 'between', 'eq'],
                'sortable' => true,
                'description' => 'Data do pedido',
                'example' => [
                    'gte' => 'created_at=>=2024-01-01',
                    'between' => 'created_at=2024-01-01|2024-12-31'
                ]
            ],
            'shipped_at' => [
                'type' => 'datetime',
                'operators' => ['null', 'not_null', 'gte', 'lte', 'between'],
                'sortable' => true,
                'description' => 'Data de envio',
                'example' => [
                    'not_null' => 'shipped_at=!null',
                    'gte' => 'shipped_at=>=2024-01-01'
                ]
            ],
            'delivered_at' => [
                'type' => 'datetime',
                'operators' => ['null', 'not_null', 'gte', 'lte', 'between'],
                'sortable' => true,
                'description' => 'Data de entrega',
                'example' => [
                    'not_null' => 'delivered_at=!null'
                ]
            ],

            // Filtros de relacionamento (usuário)
            'user_id' => [
                'type' => 'integer',
                'operators' => ['eq', 'in'],
                'description' => 'ID do usuário',
                'example' => [
                    'eq' => 'user_id=123',
                    'in' => 'user_id=123,456,789'
                ]
            ],
            'user.name' => [
                'type' => 'string',
                'operators' => ['eq', 'like'],
                'searchable' => true,
                'sortable' => true,
                'description' => 'Nome do cliente',
                'example' => [
                    'like' => 'user.name=João*',
                    'eq' => 'user.name=João Silva'
                ]
            ],
            'user.email' => [
                'type' => 'string',
                'operators' => ['eq', 'like'],
                'searchable' => true,
                'description' => 'Email do cliente',
                'example' => [
                    'like' => 'user.email=*@gmail.com'
                ]
            ],

            // Filtros monetários
            'currency' => [
                'type' => 'enum',
                'values' => ['BRL', 'USD', 'EUR'],
                'operators' => ['eq', 'in'],
                'description' => 'Moeda do pedido',
                'example' => [
                    'eq' => 'currency=BRL'
                ]
            ]
        ]);

        // Configurar field selection otimizada
        $this->configureFieldSelection([
            'selectable_fields' => [
                'id', 'order_number', 'status', 'payment_status', 'payment_method',
                'subtotal', 'tax_amount', 'shipping_amount', 'discount_amount', 'total',
                'currency', 'notes', 'created_at', 'shipped_at', 'delivered_at',
                'user.id', 'user.name', 'user.email',
                'items.id', 'items.quantity', 'items.price', 'items.total', 'items.product_name',
                'items.product.id', 'items.product.name', 'items.product.sku'
            ],
            'required_fields' => ['id', 'order_number'],
            'default_fields' => [
                'id', 'order_number', 'status', 'payment_status', 
                'total', 'created_at', 'user.name'
            ],
            'field_aliases' => [
                'order_id' => 'id',
                'customer_name' => 'user.name',
                'customer_email' => 'user.email',
                'order_total' => 'total',
                'order_date' => 'created_at'
            ],
            'max_fields' => 30
        ]);
    }

    /**
     * Relacionamentos padrão
     */
    protected function getDefaultRelationships(): array
    {
        return [
            'user:id,name,email',
            'items.product:id,name,sku'
        ];
    }

    /**
     * Ordenação padrão - pedidos mais recentes primeiro
     */
    protected function getDefaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    /**
     * Endpoint principal de pedidos
     */
    public function index(Request $request): JsonResponse
    {
        return $this->indexWithFilters($request, [
            'cache' => true,
            'cache_ttl' => 1800, // 30 minutos
            'per_page' => 15
        ]);
    }

    /**
     * Pedidos por status
     */
    public function byStatus(Request $request, string $status): JsonResponse
    {
        $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
        
        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status',
                'valid_statuses' => $validStatuses
            ], 400);
        }

        // Adicionar filtro de status à requisição
        $request->merge(['status' => $status]);

        return $this->indexWithFilters($request, [
            'cache' => true,
            'cache_ttl' => 900, // 15 minutos
            'per_page' => 20
        ]);
    }

    /**
     * Relatório de vendas
     */
    public function salesReport(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());
        $groupBy = $request->get('group_by', 'day'); // day, week, month

        $query = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        // Configurar agrupamento
        $dateFormat = match($groupBy) {
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d'
        };

        $sales = $query->selectRaw("
            DATE_FORMAT(created_at, '{$dateFormat}') as period,
            COUNT(*) as orders_count,
            SUM(total) as total_revenue,
            AVG(total) as average_order_value,
            SUM(subtotal) as subtotal_sum,
            SUM(tax_amount) as tax_sum,
            SUM(shipping_amount) as shipping_sum
        ")
        ->groupByRaw("DATE_FORMAT(created_at, '{$dateFormat}')")
        ->orderBy('period')
        ->get();

        $summary = [
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'total_orders' => $sales->sum('orders_count'),
            'total_revenue' => $sales->sum('total_revenue'),
            'average_order_value' => $sales->avg('average_order_value'),
            'total_tax' => $sales->sum('tax_sum'),
            'total_shipping' => $sales->sum('shipping_sum'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'by_period' => $sales
            ],
            'meta' => [
                'group_by' => $groupBy,
                'date_range' => [$dateFrom, $dateTo]
            ]
        ]);
    }

    /**
     * Estatísticas de pedidos
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_orders' => Order::count(),
            'orders_by_status' => Order::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'orders_by_payment_status' => Order::selectRaw('payment_status, COUNT(*) as count')
                ->groupBy('payment_status')
                ->pluck('count', 'payment_status'),
            'orders_by_payment_method' => Order::selectRaw('payment_method, COUNT(*) as count')
                ->groupBy('payment_method')
                ->pluck('count', 'payment_method'),
            'revenue_stats' => [
                'total_revenue' => Order::where('payment_status', 'paid')->sum('total'),
                'average_order_value' => Order::where('payment_status', 'paid')->avg('total'),
                'highest_order' => Order::where('payment_status', 'paid')->max('total'),
                'orders_this_month' => Order::where('created_at', '>=', now()->startOfMonth())->count(),
                'revenue_this_month' => Order::where('payment_status', 'paid')
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->sum('total'),
            ],
            'pending_orders' => Order::where('status', 'pending')->count(),
            'processing_orders' => Order::where('status', 'processing')->count(),
            'shipped_orders' => Order::where('status', 'shipped')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'generated_at' => now()->toISOString()
        ]);
    }

    /**
     * Mostrar pedido específico
     */
    public function show(Request $request, $id): JsonResponse
    {
        $fields = $request->get('fields');
        $query = Order::with(['user', 'items.product']);

        if ($fields) {
            $this->initializeFilterServices();
            $this->applyFieldSelection($query, $request);
        }

        $order = $query->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }
}