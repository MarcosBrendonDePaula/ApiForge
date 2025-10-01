<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use Illuminate\Http\Request;
use MarcosBrendon\ApiForge\Http\Controllers\BaseApiController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Advanced Business Logic Controller Example
 * 
 * This demonstrates complex virtual fields and hooks for business scenarios
 * including e-commerce, analytics, and automated business processes.
 */
class AdvancedBusinessLogicController extends BaseApiController
{
    protected function getModelClass(): string
    {
        return Order::class;
    }

    protected function setupFilterConfiguration(): void
    {
        // Configure regular filters
        $this->configureFilters([
            'status' => [
                'type' => 'enum',
                'values' => ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'],
                'operators' => ['eq', 'in', 'ne'],
                'description' => 'Order status'
            ],
            'total' => [
                'type' => 'float',
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'description' => 'Order total amount'
            ],
            'created_at' => [
                'type' => 'datetime',
                'operators' => ['eq', 'gte', 'lte', 'between'],
                'description' => 'Order creation date'
            ]
        ]);

        // Configure advanced virtual fields for business intelligence
        $this->configureVirtualFields([
            // Revenue and profitability metrics
            'profit_margin' => [
                'type' => 'float',
                'callback' => function($order) {
                    $cost = $order->items->sum(function($item) {
                        return $item->quantity * $item->product->cost_price;
                    });
                    $revenue = $order->total;
                    return $revenue > 0 ? round((($revenue - $cost) / $revenue) * 100, 2) : 0;
                },
                'relationships' => ['items.product'],
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'cacheable' => true,
                'cache_ttl' => 3600,
                'description' => 'Profit margin percentage'
            ],

            'profit_amount' => [
                'type' => 'float',
                'callback' => function($order) {
                    $cost = $order->items->sum(function($item) {
                        return $item->quantity * $item->product->cost_price;
                    });
                    return round($order->total - $cost, 2);
                },
                'relationships' => ['items.product'],
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'cacheable' => true,
                'cache_ttl' => 3600,
                'description' => 'Absolute profit amount'
            ],

            // Customer behavior analysis
            'customer_lifetime_orders' => [
                'type' => 'integer',
                'callback' => function($order) {
                    return $order->customer->orders()->count();
                },
                'relationships' => ['customer.orders'],
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'cacheable' => true,
                'cache_ttl' => 1800,
                'description' => 'Total orders by this customer'
            ],

            'customer_lifetime_value' => [
                'type' => 'float',
                'callback' => function($order) {
                    return $order->customer->orders()->sum('total');
                },
                'relationships' => ['customer.orders'],
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'cacheable' => true,
                'cache_ttl' => 1800,
                'description' => 'Total value of all customer orders'
            ],

            'is_repeat_customer' => [
                'type' => 'boolean',
                'callback' => function($order) {
                    return $order->customer->orders()->where('id', '!=', $order->id)->exists();
                },
                'relationships' => ['customer.orders'],
                'operators' => ['eq'],
                'cacheable' => true,
                'cache_ttl' => 3600,
                'description' => 'Whether customer has other orders'
            ],

            // Fulfillment and logistics
            'processing_time_hours' => [
                'type' => 'integer',
                'callback' => function($order) {
                    if (!$order->processed_at) return null;
                    return $order->created_at->diffInHours($order->processed_at);
                },
                'dependencies' => ['created_at', 'processed_at'],
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between', 'null'],
                'sortable' => true,
                'nullable' => true,
                'description' => 'Hours from order to processing'
            ],

            'shipping_time_days' => [
                'type' => 'integer',
                'callback' => function($order) {
                    if (!$order->shipped_at || !$order->delivered_at) return null;
                    return $order->shipped_at->diffInDays($order->delivered_at);
                },
                'dependencies' => ['shipped_at', 'delivered_at'],
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between', 'null'],
                'sortable' => true,
                'nullable' => true,
                'description' => 'Days from shipping to delivery'
            ],

            'fulfillment_status' => [
                'type' => 'enum',
                'values' => ['not_started', 'processing', 'ready_to_ship', 'shipped', 'delivered', 'delayed'],
                'callback' => [$this, 'calculateFulfillmentStatus'],
                'dependencies' => ['status', 'created_at', 'processed_at', 'shipped_at', 'delivered_at'],
                'operators' => ['eq', 'in', 'ne'],
                'description' => 'Detailed fulfillment status'
            ],

            // Risk and fraud detection
            'fraud_risk_score' => [
                'type' => 'integer',
                'callback' => [$this, 'calculateFraudRisk'],
                'relationships' => ['customer', 'items.product', 'payments'],
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'cacheable' => true,
                'cache_ttl' => 900, // 15 minutes
                'description' => 'Fraud risk score (0-100)'
            ],

            'payment_risk_level' => [
                'type' => 'enum',
                'values' => ['low', 'medium', 'high', 'critical'],
                'callback' => function($order) {
                    $fraudScore = $this->calculateFraudRisk($order);
                    if ($fraudScore >= 80) return 'critical';
                    if ($fraudScore >= 60) return 'high';
                    if ($fraudScore >= 40) return 'medium';
                    return 'low';
                },
                'relationships' => ['customer', 'payments'],
                'operators' => ['eq', 'in', 'ne'],
                'cacheable' => true,
                'cache_ttl' => 900,
                'description' => 'Payment risk assessment level'
            ],

            // Product and inventory insights
            'unique_products_count' => [
                'type' => 'integer',
                'callback' => function($order) {
                    return $order->items->unique('product_id')->count();
                },
                'relationships' => ['items'],
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'description' => 'Number of unique products in order'
            ],

            'total_items_quantity' => [
                'type' => 'integer',
                'callback' => function($order) {
                    return $order->items->sum('quantity');
                },
                'relationships' => ['items'],
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'description' => 'Total quantity of all items'
            ],

            'average_item_price' => [
                'type' => 'float',
                'callback' => function($order) {
                    $totalItems = $order->items->sum('quantity');
                    return $totalItems > 0 ? round($order->total / $totalItems, 2) : 0;
                },
                'relationships' => ['items'],
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'description' => 'Average price per item'
            ],

            // Seasonal and temporal analysis
            'order_day_of_week' => [
                'type' => 'enum',
                'values' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                'callback' => function($order) {
                    return $order->created_at->format('l');
                },
                'dependencies' => ['created_at'],
                'operators' => ['eq', 'in', 'ne'],
                'description' => 'Day of week when order was placed'
            ],

            'order_hour' => [
                'type' => 'integer',
                'callback' => function($order) {
                    return (int) $order->created_at->format('H');
                },
                'dependencies' => ['created_at'],
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'description' => 'Hour of day when order was placed (0-23)'
            ],

            'is_weekend_order' => [
                'type' => 'boolean',
                'callback' => function($order) {
                    return $order->created_at->isWeekend();
                },
                'dependencies' => ['created_at'],
                'operators' => ['eq'],
                'description' => 'Whether order was placed on weekend'
            ],

            // Geographic and shipping analysis
            'shipping_distance_km' => [
                'type' => 'float',
                'callback' => [$this, 'calculateShippingDistance'],
                'relationships' => ['shippingAddress', 'warehouse'],
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'nullable' => true,
                'description' => 'Distance from warehouse to shipping address'
            ],

            'estimated_shipping_cost' => [
                'type' => 'float',
                'callback' => function($order) {
                    $distance = $this->calculateShippingDistance($order);
                    if (!$distance) return null;
                    
                    $weight = $order->items->sum(function($item) {
                        return $item->quantity * $item->product->weight;
                    });
                    
                    // Simple shipping cost calculation
                    $baseCost = 5.00;
                    $distanceCost = $distance * 0.10;
                    $weightCost = $weight * 0.50;
                    
                    return round($baseCost + $distanceCost + $weightCost, 2);
                },
                'relationships' => ['items.product', 'shippingAddress', 'warehouse'],
                'operators' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
                'sortable' => true,
                'nullable' => true,
                'description' => 'Estimated shipping cost based on distance and weight'
            ]
        ]);

        // Configure comprehensive model hooks for business automation
        $this->configureModelHooks([
            'beforeStore' => [
                'validateInventory' => [
                    'callback' => function($order, $context) {
                        foreach ($order->items as $item) {
                            if ($item->product->stock < $item->quantity) {
                                throw new \Exception("Insufficient stock for product: {$item->product->name}");
                            }
                        }
                    },
                    'priority' => 1,
                    'stopOnFailure' => true,
                    'description' => 'Validate product inventory before order creation'
                ],

                'calculateTotals' => [
                    'callback' => function($order, $context) {
                        $subtotal = $order->items->sum(function($item) {
                            return $item->quantity * $item->price;
                        });
                        
                        $tax = $subtotal * 0.08; // 8% tax
                        $shipping = $this->calculateShippingCost($order);
                        
                        $order->subtotal = $subtotal;
                        $order->tax_amount = $tax;
                        $order->shipping_cost = $shipping;
                        $order->total = $subtotal + $tax + $shipping;
                    },
                    'priority' => 2,
                    'description' => 'Calculate order totals including tax and shipping'
                ],

                'assignOrderNumber' => [
                    'callback' => function($order, $context) {
                        if (empty($order->order_number)) {
                            $order->order_number = 'ORD-' . date('Y') . '-' . str_pad(
                                Order::whereYear('created_at', date('Y'))->count() + 1,
                                6,
                                '0',
                                STR_PAD_LEFT
                            );
                        }
                    },
                    'priority' => 3,
                    'description' => 'Generate unique order number'
                ],

                'fraudCheck' => [
                    'callback' => function($order, $context) {
                        $riskScore = $this->calculateFraudRisk($order);
                        
                        if ($riskScore >= 80) {
                            $order->status = 'fraud_review';
                            $order->notes = 'Order flagged for fraud review (Risk Score: ' . $riskScore . ')';
                            
                            // Notify fraud team
                            \Notification::route('slack', config('notifications.fraud_channel'))
                                ->notify(new \App\Notifications\HighRiskOrder($order, $riskScore));
                        }
                    },
                    'priority' => 4,
                    'description' => 'Perform fraud risk assessment'
                ]
            ],

            'afterStore' => [
                'reserveInventory' => [
                    'callback' => function($order, $context) {
                        foreach ($order->items as $item) {
                            $item->product->decrement('stock', $item->quantity);
                            $item->product->increment('reserved', $item->quantity);
                        }
                    },
                    'priority' => 1,
                    'description' => 'Reserve inventory for order items'
                ],

                'sendOrderConfirmation' => [
                    'callback' => function($order, $context) {
                        if ($order->customer->email) {
                            \Mail::to($order->customer->email)
                                ->queue(new \App\Mail\OrderConfirmation($order));
                        }
                    },
                    'priority' => 5,
                    'description' => 'Send order confirmation email'
                ],

                'notifyWarehouse' => [
                    'callback' => function($order, $context) {
                        if ($order->status !== 'fraud_review') {
                            \App\Jobs\NotifyWarehouse::dispatch($order)
                                ->delay(now()->addMinutes(5));
                        }
                    },
                    'priority' => 10,
                    'description' => 'Notify warehouse for order fulfillment'
                ],

                'updateCustomerStats' => [
                    'callback' => function($order, $context) {
                        $customer = $order->customer;
                        $customer->increment('total_orders');
                        $customer->increment('total_spent', $order->total);
                        $customer->last_order_at = now();
                        $customer->save();
                    },
                    'priority' => 15,
                    'description' => 'Update customer statistics'
                ],

                'triggerLoyaltyProgram' => [
                    'callback' => function($order, $context) {
                        $customer = $order->customer;
                        $points = floor($order->total * 0.1); // 10% of order value as points
                        
                        $customer->loyalty_points += $points;
                        $customer->save();
                        
                        // Check for tier upgrades
                        $this->checkLoyaltyTierUpgrade($customer);
                    },
                    'priority' => 20,
                    'description' => 'Award loyalty points and check tier upgrades'
                ]
            ],

            'beforeUpdate' => [
                'trackStatusChanges' => [
                    'callback' => function($order, $context) {
                        if ($order->isDirty('status')) {
                            $oldStatus = $order->getOriginal('status');
                            $newStatus = $order->status;
                            
                            // Log status change
                            $order->statusHistory()->create([
                                'from_status' => $oldStatus,
                                'to_status' => $newStatus,
                                'changed_by' => auth()->id(),
                                'changed_at' => now(),
                                'notes' => $context->get('status_change_notes', '')
                            ]);
                        }
                    },
                    'priority' => 1,
                    'description' => 'Track order status changes'
                ],

                'validateStatusTransition' => [
                    'callback' => function($order, $context) {
                        if ($order->isDirty('status')) {
                            $oldStatus = $order->getOriginal('status');
                            $newStatus = $order->status;
                            
                            $validTransitions = [
                                'pending' => ['processing', 'cancelled'],
                                'processing' => ['shipped', 'cancelled'],
                                'shipped' => ['delivered', 'returned'],
                                'delivered' => ['returned', 'refunded'],
                                'cancelled' => [], // Cannot transition from cancelled
                                'returned' => ['refunded'],
                                'refunded' => [] // Cannot transition from refunded
                            ];
                            
                            if (!in_array($newStatus, $validTransitions[$oldStatus] ?? [])) {
                                throw new \Exception("Invalid status transition from {$oldStatus} to {$newStatus}");
                            }
                        }
                    },
                    'priority' => 2,
                    'stopOnFailure' => true,
                    'description' => 'Validate order status transitions'
                ]
            ],

            'afterUpdate' => [
                'handleStatusChange' => [
                    'callback' => function($order, $context) {
                        if ($order->wasChanged('status')) {
                            $this->handleOrderStatusChange($order, $order->getOriginal('status'));
                        }
                    },
                    'priority' => 5,
                    'description' => 'Handle order status change side effects'
                ],

                'updateDeliveryTracking' => [
                    'callback' => function($order, $context) {
                        if ($order->wasChanged('tracking_number') && $order->tracking_number) {
                            // Send tracking info to customer
                            if ($order->customer->email) {
                                \Mail::to($order->customer->email)
                                    ->queue(new \App\Mail\TrackingInformation($order));
                            }
                        }
                    },
                    'priority' => 10,
                    'description' => 'Send tracking information when available'
                ]
            ],

            'beforeDelete' => [
                'checkDeletionRules' => [
                    'callback' => function($order, $context) {
                        // Prevent deletion of processed orders
                        if (in_array($order->status, ['shipped', 'delivered'])) {
                            throw new \Exception('Cannot delete shipped or delivered orders');
                        }
                        
                        // Prevent deletion of orders with payments
                        if ($order->payments()->exists()) {
                            throw new \Exception('Cannot delete orders with payment records');
                        }
                        
                        return true;
                    },
                    'priority' => 1,
                    'stopOnFailure' => true,
                    'description' => 'Validate order deletion rules'
                ]
            ],

            'afterDelete' => [
                'restoreInventory' => [
                    'callback' => function($order, $context) {
                        foreach ($order->items as $item) {
                            $item->product->increment('stock', $item->quantity);
                            $item->product->decrement('reserved', $item->quantity);
                        }
                    },
                    'priority' => 1,
                    'description' => 'Restore inventory when order is deleted'
                ],

                'updateCustomerStats' => [
                    'callback' => function($order, $context) {
                        $customer = $order->customer;
                        $customer->decrement('total_orders');
                        $customer->decrement('total_spent', $order->total);
                        $customer->save();
                    },
                    'priority' => 5,
                    'description' => 'Update customer statistics after order deletion'
                ]
            ]
        ]);

        // Configure field selection with virtual fields
        $this->configureFieldSelection([
            'selectable_fields' => [
                'id', 'order_number', 'status', 'total', 'created_at', 'updated_at',
                'customer.name', 'customer.email',
                // Virtual fields
                'profit_margin', 'profit_amount', 'customer_lifetime_orders',
                'customer_lifetime_value', 'is_repeat_customer', 'processing_time_hours',
                'shipping_time_days', 'fulfillment_status', 'fraud_risk_score',
                'payment_risk_level', 'unique_products_count', 'total_items_quantity',
                'average_item_price', 'order_day_of_week', 'order_hour',
                'is_weekend_order', 'shipping_distance_km', 'estimated_shipping_cost'
            ],
            'default_fields' => [
                'id', 'order_number', 'status', 'total', 'created_at',
                'customer.name', 'fulfillment_status', 'profit_margin'
            ],
            'field_aliases' => [
                'profit' => 'profit_amount',
                'margin' => 'profit_margin',
                'customer_orders' => 'customer_lifetime_orders',
                'clv' => 'customer_lifetime_value',
                'risk' => 'fraud_risk_score'
            ]
        ]);
    }

    /**
     * Calculate fulfillment status based on order data
     */
    public function calculateFulfillmentStatus($order): string
    {
        if ($order->status === 'cancelled') return 'cancelled';
        if ($order->status === 'delivered') return 'delivered';
        if ($order->status === 'shipped') return 'shipped';
        
        if ($order->processed_at) {
            return 'ready_to_ship';
        }
        
        if ($order->status === 'processing') {
            // Check if processing is taking too long
            $hoursInProcessing = $order->created_at->diffInHours(now());
            if ($hoursInProcessing > 48) {
                return 'delayed';
            }
            return 'processing';
        }
        
        return 'not_started';
    }

    /**
     * Calculate fraud risk score
     */
    public function calculateFraudRisk($order): int
    {
        $score = 0;
        
        // Customer factors
        $customer = $order->customer;
        
        // New customer risk
        if ($customer->created_at->diffInDays(now()) < 7) {
            $score += 20;
        }
        
        // Order value risk
        if ($order->total > 1000) {
            $score += 15;
        } elseif ($order->total > 500) {
            $score += 10;
        }
        
        // Quantity risk
        $totalItems = $order->items->sum('quantity');
        if ($totalItems > 10) {
            $score += 10;
        }
        
        // Payment method risk
        $paymentMethod = $order->payments()->first()?->method;
        if ($paymentMethod === 'credit_card') {
            $score += 5;
        }
        
        // Geographic risk (simplified)
        if ($order->shippingAddress && $order->billingAddress) {
            if ($order->shippingAddress->country !== $order->billingAddress->country) {
                $score += 15;
            }
        }
        
        // Time-based risk
        $orderHour = (int) $order->created_at->format('H');
        if ($orderHour < 6 || $orderHour > 22) {
            $score += 10;
        }
        
        return min($score, 100);
    }

    /**
     * Calculate shipping distance
     */
    public function calculateShippingDistance($order): ?float
    {
        if (!$order->shippingAddress || !$order->warehouse) {
            return null;
        }
        
        // Simplified distance calculation (in real app, use proper geolocation service)
        $lat1 = $order->warehouse->latitude;
        $lon1 = $order->warehouse->longitude;
        $lat2 = $order->shippingAddress->latitude;
        $lon2 = $order->shippingAddress->longitude;
        
        if (!$lat1 || !$lon1 || !$lat2 || !$lon2) {
            return null;
        }
        
        // Haversine formula for distance calculation
        $earthRadius = 6371; // km
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return round($earthRadius * $c, 2);
    }

    /**
     * Handle order status changes
     */
    private function handleOrderStatusChange($order, $oldStatus): void
    {
        switch ($order->status) {
            case 'processing':
                $order->processed_at = now();
                $order->save();
                break;
                
            case 'shipped':
                $order->shipped_at = now();
                $order->save();
                
                // Send shipping notification
                if ($order->customer->email) {
                    \Mail::to($order->customer->email)
                        ->queue(new \App\Mail\OrderShipped($order));
                }
                break;
                
            case 'delivered':
                $order->delivered_at = now();
                $order->save();
                
                // Send delivery confirmation and request review
                if ($order->customer->email) {
                    \Mail::to($order->customer->email)
                        ->queue(new \App\Mail\OrderDelivered($order));
                }
                break;
                
            case 'cancelled':
                // Restore inventory
                foreach ($order->items as $item) {
                    $item->product->increment('stock', $item->quantity);
                    $item->product->decrement('reserved', $item->quantity);
                }
                break;
        }
    }

    /**
     * Check and upgrade customer loyalty tier
     */
    private function checkLoyaltyTierUpgrade($customer): void
    {
        $totalSpent = $customer->total_spent;
        $currentTier = $customer->loyalty_tier;
        
        $newTier = 'bronze';
        if ($totalSpent >= 10000) $newTier = 'platinum';
        elseif ($totalSpent >= 5000) $newTier = 'gold';
        elseif ($totalSpent >= 1000) $newTier = 'silver';
        
        if ($newTier !== $currentTier) {
            $customer->loyalty_tier = $newTier;
            $customer->save();
            
            // Send tier upgrade notification
            if ($customer->email) {
                \Mail::to($customer->email)
                    ->queue(new \App\Mail\LoyaltyTierUpgrade($customer, $newTier));
            }
        }
    }

    /**
     * Calculate shipping cost
     */
    private function calculateShippingCost($order): float
    {
        $distance = $this->calculateShippingDistance($order);
        if (!$distance) return 10.00; // Default shipping cost
        
        $weight = $order->items->sum(function($item) {
            return $item->quantity * ($item->product->weight ?? 1);
        });
        
        $baseCost = 5.00;
        $distanceCost = $distance * 0.10;
        $weightCost = $weight * 0.50;
        
        return round($baseCost + $distanceCost + $weightCost, 2);
    }

    /**
     * Custom endpoint for business analytics
     */
    public function businessAnalytics(Request $request)
    {
        $query = $this->getFilteredQuery($request);
        
        return response()->json([
            'success' => true,
            'analytics' => [
                'revenue_metrics' => [
                    'total_revenue' => $query->sum('total'),
                    'average_order_value' => $query->avg('total'),
                    'total_profit' => $query->get()->sum(function($order) {
                        return $this->calculateProfitAmount($order);
                    }),
                    'average_profit_margin' => $query->get()->avg(function($order) {
                        return $this->calculateProfitMargin($order);
                    })
                ],
                'customer_insights' => [
                    'repeat_customer_rate' => $query->get()->where('is_repeat_customer', true)->count() / $query->count() * 100,
                    'average_customer_lifetime_value' => $query->get()->avg('customer_lifetime_value'),
                    'high_value_customers' => $query->get()->where('customer_lifetime_value', '>', 5000)->count()
                ],
                'operational_metrics' => [
                    'average_processing_time' => $query->whereNotNull('processed_at')->get()->avg('processing_time_hours'),
                    'on_time_delivery_rate' => $query->where('status', 'delivered')->get()->where('shipping_time_days', '<=', 3)->count() / $query->where('status', 'delivered')->count() * 100,
                    'fraud_detection_rate' => $query->get()->where('fraud_risk_score', '>', 70)->count() / $query->count() * 100
                ]
            ]
        ]);
    }

    private function calculateProfitAmount($order): float
    {
        $cost = $order->items->sum(function($item) {
            return $item->quantity * ($item->product->cost_price ?? 0);
        });
        return $order->total - $cost;
    }

    private function calculateProfitMargin($order): float
    {
        $cost = $order->items->sum(function($item) {
            return $item->quantity * ($item->product->cost_price ?? 0);
        });
        return $order->total > 0 ? (($order->total - $cost) / $order->total) * 100 : 0;
    }
}