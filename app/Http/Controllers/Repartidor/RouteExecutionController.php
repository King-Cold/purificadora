<?php

namespace App\Http\Controllers\Repartidor;

use App\Exceptions\OrderBusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\BottleCustodyMovement;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\OrderCharge;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\OrderStatusHistory;
use App\Models\RouteStop;
use App\Services\DebtAndCreditService;
use App\Services\DeliveryRouteService;
use App\Services\OrderCustodyService;
use App\Services\OrderService;
use App\Services\SettingService;
use App\Services\TruckInventoryService;
use App\Services\WeeklyPromotionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RouteExecutionController extends Controller
{
    public function __construct(
        protected DeliveryRouteService $routeService,
        protected DebtAndCreditService $debtService,
        protected WeeklyPromotionService $promotionService,
        protected TruckInventoryService $truckInventoryService,
        protected SettingService $settingService,
        protected OrderCustodyService $custodyService,
        protected OrderService $orderService
    ) {}

    /**
     * Pantalla principal del Repartidor ("Mi jornada de reparto").
     */
    public function dashboard(Request $request): Response
    {
        $user = $request->user();

        // 1. Obtener todas las rutas del repartidor ordenadas
        $allDriverRoutes = DeliveryRoute::with([
            'stops' => fn ($q) => $q->orderBy('stop_number', 'asc')->with([
                'order.customer',
                'order.items',
            ]),
        ])
            ->where('driver_user_id', $user->id)
            ->orderBy('id', 'desc')
            ->get();

        // Ruta activa principal: en curso, en incidencia o asignada
        $activeRouteModel = $allDriverRoutes->first(function ($r) {
            return in_array($r->status, [
                DeliveryRoute::STATUS_IN_PROGRESS,
                DeliveryRoute::STATUS_INCIDENT,
                DeliveryRoute::STATUS_ASSIGNED,
            ], true);
        }) ?? $allDriverRoutes->first();

        // Si se envió un route_id específico para inspección
        $selectedRouteId = $request->query('route_id');
        if ($selectedRouteId) {
            $matched = $allDriverRoutes->firstWhere('id', (int) $selectedRouteId);
            if ($matched) {
                $activeRouteModel = $matched;
            }
        }

        // Formatear rutas disponibles para el selector
        $availableRoutes = $allDriverRoutes->map(fn ($r) => [
            'id' => $r->id,
            'folio' => $r->folio,
            'date' => $r->date?->format('d/m/Y'),
            'status' => $r->status,
            'status_label' => match ($r->status) {
                DeliveryRoute::STATUS_ASSIGNED => 'Asignada',
                DeliveryRoute::STATUS_IN_PROGRESS => 'En curso',
                DeliveryRoute::STATUS_INCIDENT => 'Avería / En pausa',
                DeliveryRoute::STATUS_COMPLETED => 'Completada',
                default => ucfirst($r->status),
            },
            'total_stops' => $r->stops->count(),
            'is_active' => in_array($r->status, [
                DeliveryRoute::STATUS_ASSIGNED,
                DeliveryRoute::STATUS_IN_PROGRESS,
                DeliveryRoute::STATUS_INCIDENT,
            ], true),
        ])->values()->all();

        // Formatear la ruta seleccionada y calcular zonas reales
        $formattedActiveRoute = null;
        if ($activeRouteModel) {
            $stops = $activeRouteModel->stops;

            // Zonas canónicas desde CustomerAddress::ZONES
            $canonicalZones = CustomerAddress::ZONES;

            $zonesSummary = [];
            $zonesSummary[] = [
                'key' => 'all',
                'label' => 'Todas las zonas',
                'total_stops' => $stops->count(),
                'pending_stops' => $stops->where('status', RouteStop::STATUS_PENDING)->count(),
                'completed_stops' => $stops->where('status', RouteStop::STATUS_COMPLETED)->count(),
                'failed_stops' => $stops->where('status', RouteStop::STATUS_FAILED)->count(),
            ];

            foreach ($canonicalZones as $zKey => $zLabel) {
                $zStops = $stops->filter(function ($stop) use ($zKey, $zLabel) {
                    $z = strtolower(trim($stop->order?->delivery_zone ?? ''));
                    return in_array($z, [$zKey, strtolower($zLabel)], true);
                });
                if ($zStops->count() > 0) {
                    $zonesSummary[] = [
                        'key' => $zKey,
                        'label' => "Zona {$zLabel}",
                        'total_stops' => $zStops->count(),
                        'pending_stops' => $zStops->where('status', RouteStop::STATUS_PENDING)->count(),
                        'completed_stops' => $zStops->where('status', RouteStop::STATUS_COMPLETED)->count(),
                        'failed_stops' => $zStops->where('status', RouteStop::STATUS_FAILED)->count(),
                    ];
                }
            }

            // Paradas sin zona registrada
            $noneStops = $stops->filter(fn ($s) => empty($s->order?->delivery_zone));
            if ($noneStops->count() > 0) {
                $zonesSummary[] = [
                    'key' => 'none',
                    'label' => 'Sin zona asignada',
                    'total_stops' => $noneStops->count(),
                    'pending_stops' => $noneStops->where('status', RouteStop::STATUS_PENDING)->count(),
                    'completed_stops' => $noneStops->where('status', RouteStop::STATUS_COMPLETED)->count(),
                    'failed_stops' => $noneStops->where('status', RouteStop::STATUS_FAILED)->count(),
                ];
            }

            $formattedActiveRoute = [
                'id' => $activeRouteModel->id,
                'folio' => $activeRouteModel->folio,
                'date' => $activeRouteModel->date?->format('d/m/Y'),
                'status' => $activeRouteModel->status,
                'status_label' => match ($activeRouteModel->status) {
                    DeliveryRoute::STATUS_ASSIGNED => 'Asignada (Pendiente de inicio)',
                    DeliveryRoute::STATUS_IN_PROGRESS => 'En curso',
                    DeliveryRoute::STATUS_INCIDENT => 'Avería / Incidencia en pausa',
                    DeliveryRoute::STATUS_COMPLETED => 'Completada',
                    default => ucfirst($activeRouteModel->status),
                },
                'notes' => $activeRouteModel->notes,
                'started_at' => $activeRouteModel->started_at?->format('H:i'),
                'total_stops' => $stops->count(),
                'pending_stops_count' => $stops->where('status', RouteStop::STATUS_PENDING)->count(),
                'completed_stops_count' => $stops->where('status', RouteStop::STATUS_COMPLETED)->count(),
                'failed_stops_count' => $stops->where('status', RouteStop::STATUS_FAILED)->count(),
                'zones' => $zonesSummary,
                'is_in_progress' => $activeRouteModel->status === DeliveryRoute::STATUS_IN_PROGRESS,
                'is_assigned' => $activeRouteModel->status === DeliveryRoute::STATUS_ASSIGNED,
                'is_incident' => $activeRouteModel->status === DeliveryRoute::STATUS_INCIDENT,
                'is_completed' => $activeRouteModel->status === DeliveryRoute::STATUS_COMPLETED,
            ];
        }

        // 2. Existencias del camión e inventario de fijos por cliente
        $truckState = $this->truckInventoryService->getTruckInventoryState($user);

        // Fijos en posesión en camión (separados por cliente)
        $fixedCustody = [];
        $totalFixedInTruck = 0;
        $fixedByCustomer = $truckState['fixed_by_customer'] ?? [];
        foreach ($fixedByCustomer as $custCustody) {
            $emptyQty = (int) ($custCustody['fixed_empty_collected'] ?? 0);
            $fullQty = (int) ($custCustody['fixed_full_to_deliver'] ?? 0);
            if ($emptyQty > 0 || $fullQty > 0) {
                $totalFixedInTruck += ($emptyQty + $fullQty);
                $fixedCustody[] = [
                    'customer_id' => $custCustody['customer_id'],
                    'customer_code' => $custCustody['customer_code'],
                    'customer_name' => $custCustody['customer_name'],
                    'customer_nickname' => $custCustody['customer_nickname'],
                    'empty_collected' => $emptyQty,
                    'full_to_deliver' => $fullQty,
                    'total_bottles' => $emptyQty + $fullQty,
                ];
            }
        }

        // Fecha actual formateada en español
        Carbon::setLocale('es');
        $now = Carbon::now('America/Merida');
        $formattedDate = ucfirst($now->translatedFormat('l, j \d\e F \d\e Y'));

        // Contadores operativos en vivo
        $collectionsCount = Order::whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_IN_ROUTE, Order::STATUS_IN_PROCESS])
            ->whereHas('items', fn ($q) => $q->where('type', OrderItem::TYPE_FIXED)->whereRaw('(quantity - collected_quantity - cancelled_quantity) > 0'))
            ->count();

        $deliveriesCount = Order::whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_IN_ROUTE, Order::STATUS_IN_PROCESS])
            ->where(function ($q) {
                $q->whereHas('items', fn ($sub) => $sub->where('type', OrderItem::TYPE_EXCHANGE)->whereRaw('(quantity - delivered_quantity - cancelled_quantity) > 0'))
                  ->orWhereHas('items', fn ($sub) => $sub->where('type', OrderItem::TYPE_FIXED)->where('prepared_quantity', '>', 0));
            })->count();

        $pendingDebtCount = OrderCharge::whereIn('status', [OrderCharge::STATUS_PENDING, OrderCharge::STATUS_PARTIALLY_PAID])
            ->distinct('customer_id')
            ->count('customer_id');

        $pendingTransfersCount = count($truckState['pending_transfers'] ?? []);

        return Inertia::render('Repartidor/Dashboard', [
            'driver' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'formattedDate' => $formattedDate,
            'activeRoute' => $formattedActiveRoute,
            'availableRoutes' => $availableRoutes,
            'counters' => [
                'por_recoger' => $collectionsCount,
                'por_entregar' => $deliveriesCount,
                'cobrar_pendientes' => $pendingDebtCount,
                'transferencias_pendientes' => $pendingTransfersCount,
            ],
            'truckInventory' => [
                'exchange_full_available' => (int) $truckState['truck_inventory']['exchange_full_available'],
                'exchange_empty_collected' => (int) $truckState['truck_inventory']['exchange_empty_collected'],
                'total_fixed_in_truck' => $totalFixedInTruck,
                'fixed_custody' => $fixedCustody,
                'pending_transfers_count' => $pendingTransfersCount,
            ],
        ]);
    }

    /**
     * Pantalla móvil "Mi Ruta" para el Repartidor.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Buscar ruta activa del repartidor (en curso, en incidencia o asignada)
        $activeRoute = DeliveryRoute::with([
            'stops' => fn ($q) => $q->orderBy('stop_number', 'asc')->with([
                'order.customer',
                'order.items',
            ]),
        ])
            ->where('driver_user_id', $user->id)
            ->whereIn('status', [
                DeliveryRoute::STATUS_IN_PROGRESS,
                DeliveryRoute::STATUS_INCIDENT,
                DeliveryRoute::STATUS_ASSIGNED,
            ])
            ->orderBy('id', 'desc')
            ->first();

        $formattedRoute = null;

        if ($activeRoute) {
            $stops = $activeRoute->stops->map(function ($stop) {
                $order = $stop->order;
                $customer = $order?->customer;

                $exchangeItem = $order?->items->firstWhere('type', 'cambio');
                $fixedItem = $order?->items->firstWhere('type', 'fijo');

                $exchangeQty = (int) ($exchangeItem?->quantity ?? 0);
                $fixedQty = (int) ($fixedItem?->quantity ?? 0);

                // Determinar tipo de operación
                $opType = 'intercambio';
                $opLabel = 'Intercambio de botellones de cambio';

                if ($exchangeQty > 0 && $fixedQty > 0) {
                    $opType = 'mixto';
                    $opLabel = 'Operación mixta (Cambio + Fijos)';
                } elseif ($fixedQty > 0) {
                    if ($fixedItem->prepared_quantity > 0) {
                        $opType = 'devolucion_fijos';
                        $opLabel = 'Devolución de fijos rellenados';
                    } else {
                        $opType = 'recoleccion_fijos';
                        $opLabel = 'Recolección de fijos vacíos';
                    }
                }

                // Ubicación real o sin registrar
                $hasAddress = !empty($order?->delivery_street);
                $address = $hasAddress 
                    ? ($order->delivery_street . ($order->delivery_exterior_number ? ' #' . $order->delivery_exterior_number : '') . ($order->delivery_neighborhood ? ', ' . $order->delivery_neighborhood : ''))
                    : 'Sin ubicación registrada';

                // Normalización de zona de reparto (Norte, Sur, Este, Oeste o Sin zona)
                $rawZone = $order?->delivery_zone;
                $zoneKey = 'none';
                $zoneLabel = 'Sin zona';
                if (!empty($rawZone)) {
                    $norm = strtolower(trim($rawZone));
                    if (in_array($norm, ['north', 'norte'])) {
                        $zoneKey = 'north';
                        $zoneLabel = 'Norte';
                    } elseif (in_array($norm, ['south', 'sur'])) {
                        $zoneKey = 'south';
                        $zoneLabel = 'Sur';
                    } elseif (in_array($norm, ['east', 'este'])) {
                        $zoneKey = 'east';
                        $zoneLabel = 'Este';
                    } elseif (in_array($norm, ['west', 'oeste'])) {
                        $zoneKey = 'west';
                        $zoneLabel = 'Oeste';
                    }
                }

                // Semáforo de deuda con límite configurable
                $limit = $this->settingService->getMaxPendingPaymentBottles();
                $trafficLight = $customer ? $this->debtService->getTrafficLight($customer) : [
                    'pending_bottles' => 0,
                    'limit' => $limit,
                    'color' => 'green',
                    'status' => $limit === 0 ? 'credit_disabled' : 'no_debt',
                    'label' => $limit === 0 ? 'Fiado deshabilitado' : "Al día · Sin pendientes (0/{$limit})",
                    'can_receive_more_debt' => $limit > 0,
                ];

                // Teléfono limpio
                $rawPhone = $order?->recipient_phone ?: $customer?->phone;
                $phone = !empty($rawPhone) ? trim($rawPhone) : null;

                return [
                    'id' => $stop->id,
                    'stop_number' => $stop->stop_number,
                    'status' => $stop->status,
                    'status_label' => match ($stop->status) {
                        RouteStop::STATUS_PENDING => 'Pendiente',
                        RouteStop::STATUS_COMPLETED => 'Atendida',
                        RouteStop::STATUS_FAILED => 'Fallida',
                        default => ucfirst($stop->status),
                    },
                    'failure_reason' => $stop->failure_reason,
                    'notes' => $stop->notes,
                    'completed_at' => $stop->completed_at?->format('H:i'),
                    'order_id' => $order?->id,
                    'order_folio' => $order?->folio,
                    'order_status' => $order?->status,
                    'customer_id' => $customer?->id,
                    'customer_code' => $customer?->code ?? "CLI-{$customer?->id}",
                    'customer_name' => $customer?->name,
                    'customer_nickname' => $customer?->nickname,
                    'customer_phone' => $phone,
                    'has_phone' => !empty($phone),
                    'delivery_zone' => $zoneLabel,
                    'delivery_zone_key' => $zoneKey,
                    'delivery_zone_label' => $zoneLabel,
                    'address_summary' => $address,
                    'has_address' => $hasAddress,
                    'operation_type' => $opType,
                    'operation_label' => $opLabel,
                    'exchange_quantity' => $exchangeQty,
                    'fixed_quantity' => $fixedQty,
                    'total_bottles' => $exchangeQty + $fixedQty,
                    'traffic_light' => $trafficLight,
                ];
            });

            $formattedRoute = [
                'id' => $activeRoute->id,
                'folio' => $activeRoute->folio,
                'date' => $activeRoute->date?->format('Y-m-d'),
                'status' => $activeRoute->status,
                'status_label' => match ($activeRoute->status) {
                    DeliveryRoute::STATUS_ASSIGNED => 'Asignada (Lista para iniciar)',
                    DeliveryRoute::STATUS_IN_PROGRESS => 'En curso',
                    DeliveryRoute::STATUS_INCIDENT => 'Avería / Incidencia en pausa',
                    DeliveryRoute::STATUS_COMPLETED => 'Completada',
                    default => ucfirst($activeRoute->status),
                },
                'notes' => $activeRoute->notes,
                'started_at' => $activeRoute->started_at?->format('H:i'),
                'total_stops' => $stops->count(),
                'pending_stops_count' => $stops->where('status', RouteStop::STATUS_PENDING)->count(),
                'completed_stops_count' => $stops->where('status', RouteStop::STATUS_COMPLETED)->count(),
                'failed_stops_count' => $stops->where('status', RouteStop::STATUS_FAILED)->count(),
                'stops' => $stops,
            ];
        }

        return Inertia::render('Repartidor/Route/Index', [
            'activeRoute' => $formattedRoute,
            'driver' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
        ]);
    }

    /**
     * Inicia la ruta formalmente.
     */
    public function start(DeliveryRoute $route, Request $request): RedirectResponse
    {
        try {
            $this->routeService->startRoute($route, $request->user());
            return back()->with('success', 'Ruta iniciada correctamente. ¡Buen viaje!');
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['route' => $e->getMessage()]);
        }
    }

    /**
     * Registra una avería de unidad o incidencia en ruta.
     */
    public function recordBreakdown(DeliveryRoute $route, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $this->routeService->recordRouteBreakdown($route, $validated['reason'], $request->user());
            return back()->with('success', 'Avería / Incidencia registrada. Las paradas pendientes se conservaron para su reanudación.');
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['route' => $e->getMessage()]);
        }
    }

    /**
     * Reanuda la ruta tras resolver la avería.
     */
    public function resume(DeliveryRoute $route, Request $request): RedirectResponse
    {
        try {
            $this->routeService->resumeRoute($route, $request->user(), $request->input('notes'));
            return back()->with('success', 'Ruta reanudada exitosamente.');
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['route' => $e->getMessage()]);
        }
    }

    /**
     * Completa formalmente la hoja de ruta.
     */
    public function complete(DeliveryRoute $route, Request $request): RedirectResponse
    {
        try {
            $this->routeService->completeRoute($route, $request->user());
            return back()->with('success', 'Hoja de ruta completada con éxito.');
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['route' => $e->getMessage()]);
        }
    }

    /**
     * Marca una parada como visita fallida con motivo obligatorio.
     * No cancela automáticamente el pedido.
     */
    public function failStop(RouteStop $stop, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'failure_reason' => ['required', 'string', 'min:3', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->routeService->recordStopFailed(
                $stop,
                $validated['failure_reason'],
                $validated['notes'] ?? null,
                $request->user()
            );

            return back()->with('success', 'Parada registrada como visita fallida. El pedido no ha sido cancelado.');
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['stop' => $e->getMessage()]);
        }
    }

    /**
     * Pantalla táctil móvil para atender una parada específica.
     */
    public function serve(RouteStop $stop, Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $stop->load([
            'deliveryRoute',
            'order.customer.address',
            'order.items',
        ]);

        $route = $stop->deliveryRoute;

        // Validar que la ruta pertenezca al chofer o a roles autorizados
        if ($route->driver_user_id !== $user->id && !$user->hasRole(['Administrador', 'Super Admin'])) {
            abort(403, 'No tienes autorización para atender paradas de esta ruta.');
        }

        // Bloquear si la ruta está detenida por incidencia
        if ($route->status === DeliveryRoute::STATUS_INCIDENT) {
            return redirect()->route('repartidor.route.index')
                ->withErrors(['stop' => 'La ruta se encuentra en pausa por avería mecánica o incidencia. Reanúdala para atender paradas.']);
        }

        if ($route->status !== DeliveryRoute::STATUS_IN_PROGRESS) {
            return redirect()->route('repartidor.route.index')
                ->withErrors(['stop' => 'Solo se pueden atender paradas cuando la ruta se encuentre en curso.']);
        }

        // Validar que la parada se encuentre pendiente
        if ($stop->status !== RouteStop::STATUS_PENDING) {
            return redirect()->route('repartidor.route.index')
                ->withErrors(['stop' => 'Esta parada ya ha sido atendida previamente y no permite modificaciones.']);
        }

        $order = $stop->order;
        $customer = $order->customer;

        // Semáforo, adeudos y promoción semanal
        $trafficLight = $this->debtService->getTrafficLight($customer);
        $pendingDebtBottles = $this->debtService->getPendingDebtBottles($customer);
        $pendingDebtAmount = $this->debtService->getPendingDebtAmount($customer);
        $promoProgress = $this->promotionService->getWeeklyProgress($customer);

        // Inventario continuo del camión
        $truckState = $this->truckInventoryService->getTruckInventoryState($user);

        // Fijos del camión cargados específicamente para este cliente propietario
        $loadedFixed = (int) BottleCustodyMovement::where('customer_id', $customer->id)
            ->where('bottle_type', OrderItem::TYPE_FIXED)
            ->where('movement_type', BottleCustodyMovement::TYPE_TRUCK_LOAD_FIXED)
            ->where('destination', 'truck')
            ->whereIn('status', [BottleCustodyMovement::STATUS_ACCEPTED, BottleCustodyMovement::STATUS_ACCEPTED_WITH_DISCREPANCY])
            ->sum('quantity');

        $deliveredFixed = (int) BottleCustodyMovement::where('customer_id', $customer->id)
            ->where('bottle_type', OrderItem::TYPE_FIXED)
            ->where('movement_type', BottleCustodyMovement::TYPE_DELIVERY_REFILL_TO_CUSTOMER)
            ->where('origin', 'truck')
            ->whereIn('status', [BottleCustodyMovement::STATUS_ACCEPTED, BottleCustodyMovement::STATUS_ACCEPTED_WITH_DISCREPANCY])
            ->sum('quantity');

        $availableFixedOnTruck = max(0, $loadedFixed - $deliveredFixed);

        $exchangeItem = $order->items->firstWhere('type', OrderItem::TYPE_EXCHANGE);
        $fixedItem = $order->items->firstWhere('type', OrderItem::TYPE_FIXED);

        $exchangeRequested = (int) ($exchangeItem?->quantity ?? 0);
        $fixedRequested = (int) ($fixedItem?->quantity ?? 0);
        $fixedPrepared = (int) ($fixedItem?->prepared_quantity ?? 0);

        // Domicilio y teléfono limpios sin fabricar datos
        $hasAddress = !empty($order->delivery_street);
        $address = $hasAddress
            ? ($order->delivery_street . ($order->delivery_exterior_number ? ' #' . $order->delivery_exterior_number : '') . ($order->delivery_neighborhood ? ', ' . $order->delivery_neighborhood : ''))
            : 'Sin domicilio registrado';

        $phone = $order->recipient_phone ?: $customer->phone;

        return Inertia::render('Repartidor/Route/ServeStop', [
            'stop' => [
                'id' => $stop->id,
                'stop_number' => $stop->stop_number,
                'status' => $stop->status,
                'notes' => $stop->notes,
            ],
            'route' => [
                'id' => $route->id,
                'folio' => $route->folio,
                'status' => $route->status,
            ],
            'order' => [
                'id' => $order->id,
                'folio' => $order->folio,
                'status' => $order->status,
                'exchange_requested' => $exchangeRequested,
                'fixed_requested' => $fixedRequested,
                'fixed_prepared' => $fixedPrepared,
                'notes' => $order->notes,
                'delivery_zone' => $order->delivery_zone ?: 'Sin zona',
                'address_summary' => $address,
                'has_address' => $hasAddress,
                'reference' => $order->address_snapshot['delivery_reference'] ?? null,
            ],
            'customer' => [
                'id' => $customer->id,
                'code' => $customer->code ?? "CLI-{$customer->id}",
                'name' => $customer->name,
                'nickname' => $customer->nickname,
                'phone' => $phone,
                'has_phone' => !empty($phone),
                'fixed_bottles_count' => (int) $customer->fixed_bottles_count,
                'exchange_bottles_count' => (int) $customer->exchange_bottles_count,
            ],
            'traffic_light' => $trafficLight,
            'pending_debt' => [
                'bottles' => $pendingDebtBottles,
                'amount' => $pendingDebtAmount,
            ],
            'weekly_promo' => $promoProgress,
            'truck' => [
                'exchange_full_available' => (int) $truckState['truck_inventory']['exchange_full_available'],
                'exchange_empty_collected' => (int) $truckState['truck_inventory']['exchange_empty_collected'],
                'fixed_available_for_customer' => $availableFixedOnTruck,
            ],
            'unit_price' => $this->settingService->getCurrentRefillPrice(),
            'return_tab' => $request->query('return_tab', 'pendientes'),
            'return_zone' => $request->query('return_zone', 'all'),
        ]);
    }

    /**
     * Procesa la atención de la parada registrando entregas, cancelaciones y cobro.
     */
    public function processServe(RouteStop $stop, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'exchange_delivered' => ['nullable', 'integer', 'min:0'],
            'exchange_empty_received' => ['nullable', 'integer', 'min:0'],
            'cancellation_reason' => ['nullable', 'string', 'max:255'],
            'fixed_collected' => ['nullable', 'integer', 'min:0'],
            'fixed_uncollected_cancelled_reason' => ['nullable', 'string', 'max:255'],
            'fixed_cancellation_reason' => ['nullable', 'string', 'max:255'],
            'fixed_delivered' => ['nullable', 'integer', 'min:0'],
            'units_paid_cash' => ['nullable', 'integer', 'min:0'],
            'cash_tendered' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $this->routeService->recordStopCompleted($stop, $validated, $request->user());

            if ($request->filled('return_tab') || $request->filled('return_zone')) {
                return redirect()->route('repartidor.reparto', [
                    'tab' => $request->input('return_tab', 'pendientes'),
                    'zone' => $request->input('return_zone', 'all'),
                ])->with('success', 'Parada atendida y registrada exitosamente.');
            }

            return redirect()->route('repartidor.route.index')->with('success', 'Parada atendida y registrada exitosamente.');
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['serve' => $e->getMessage()]);
        }
    }

    /**
     * Liquida saldos o botellones anteriores de forma completa según sus precios históricos.
     */
    public function settleDebt(Customer $customer, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bottles_count' => ['required', 'integer', 'min:1'],
            'cash_tendered' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $payment = $this->debtService->recordPayment(
                $customer,
                (float) $validated['cash_tendered'],
                $request->user(),
                null,
                $validated['notes'] ?? 'Cobro de saldo anterior en ruta',
                (int) $validated['bottles_count']
            );

            $changeMsg = $payment->change_returned > 0 
                ? " Cambio a devolver: \${$payment->change_returned}."
                : '';

            return back()->with('success', "Se liquidaron {$payment->bottles_covered_count} botellón(es) pendiente(s) por un importe exacto de \${$payment->amount}.{$changeMsg}");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['debt' => $e->getMessage()]);
        }
    }

    /**
     * Pantalla móvil para registro de servicio directo en ruta ("Atender cliente al paso").
     */
    public function directService(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        $activeRoute = DeliveryRoute::where('driver_user_id', $user->id)
            ->whereIn('status', [DeliveryRoute::STATUS_IN_PROGRESS, DeliveryRoute::STATUS_ASSIGNED])
            ->orderBy('id', 'desc')
            ->first();

        $truckState = $this->truckInventoryService->getTruckInventoryState($user);

        $customerId = $request->query('customer_id');
        $initialCustomer = null;
        if ($customerId) {
            $c = Customer::find($customerId);
            if ($c) {
                $initialCustomer = [
                    'id' => $c->id,
                    'code' => $c->code ?? "CLI-{$c->id}",
                    'name' => $c->name,
                    'nickname' => $c->nickname,
                    'phone' => $c->phone,
                    'fixed_bottles_count' => (int) $c->fixed_bottles_count,
                    'exchange_bottles_count' => (int) $c->exchange_bottles_count,
                    'traffic_light' => $this->debtService->getTrafficLight($c),
                    'pending_debt' => [
                        'bottles' => $this->debtService->getPendingDebtBottles($c),
                        'amount' => $this->debtService->getPendingDebtAmount($c),
                    ],
                ];
            }
        }

        return Inertia::render('Repartidor/Route/DirectService', [
            'activeRoute' => $activeRoute ? [
                'id' => $activeRoute->id,
                'folio' => $activeRoute->folio,
                'status' => $activeRoute->status,
            ] : null,
            'truck' => [
                'exchange_full_available' => (int) $truckState['truck_inventory']['exchange_full_available'],
                'exchange_empty_collected' => (int) $truckState['truck_inventory']['exchange_empty_collected'],
            ],
            'unit_price' => $this->settingService->getCurrentRefillPrice(),
            'max_pending_payment_bottles' => $this->settingService->getMaxPendingPaymentBottles(),
            'initialCustomer' => $initialCustomer,
        ]);
    }

    /**
     * Guarda y ejecuta atómicamente el servicio directo al paso.
     */
    public function storeDirectService(Request $request): RedirectResponse
    {
        $user = $request->user();
        $customerId = $request->input('customer_id');

        try {
            if ($customerId) {
                $customer = Customer::findOrFail($customerId);
                $exchangeQty = max(0, (int) $request->input('exchange_quantity', 0));
                $fixedQty = max(0, (int) $request->input('fixed_quantity', 0));
                if ($fixedQty > (int) $customer->fixed_bottles_count) {
                    $customer->update(['fixed_bottles_count' => $fixedQty]);
                }
                if ($exchangeQty > (int) $customer->exchange_bottles_count) {
                    $customer->update(['exchange_bottles_count' => $exchangeQty]);
                }
            } else {
                $custValidated = $request->validate([
                    'nickname' => ['required', 'string', 'max:100'],
                    'name' => ['nullable', 'string', 'max:150'],
                    'phone' => ['nullable', 'string', 'max:25'],
                    'fixed_bottles_count' => ['nullable', 'integer', 'min:0'],
                    'exchange_bottles_count' => ['nullable', 'integer', 'min:0'],
                    'street' => ['nullable', 'string', 'max:150'],
                    'exterior_number' => ['nullable', 'string', 'max:20'],
                    'neighborhood' => ['nullable', 'string', 'max:100'],
                    'delivery_zone' => ['nullable', 'string', 'in:north,south,east,west'],
                    'reference' => ['nullable', 'string', 'max:500'],
                ]);

                $exchangeQty = max(0, (int) $request->input('exchange_quantity', 0));
                $fixedQty = max(0, (int) $request->input('fixed_quantity', 0));

                $custValidated['exchange_bottles_count'] = max($exchangeQty, (int) ($custValidated['exchange_bottles_count'] ?? 0));
                $custValidated['fixed_bottles_count'] = max($fixedQty, (int) ($custValidated['fixed_bottles_count'] ?? 0));

                $customer = $this->routeService->registerDirectRouteCustomer($user, $custValidated);
            }

            $activeRoute = DeliveryRoute::where('driver_user_id', $user->id)
                ->where('status', DeliveryRoute::STATUS_IN_PROGRESS)
                ->orderBy('id', 'desc')
                ->first();

            $order = $this->routeService->registerDirectRouteOrder($user, $customer, $request->all(), $activeRoute);

            return redirect()->route('repartidor.reparto')->with('success', "Servicio directo registrado exitosamente (Folio {$order->folio}).");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['direct' => $e->getMessage()]);
        }
    }

    /**
     * Pantalla móvil "Por Recoger": Lista pedidos confirmados con fijos pendientes de recoger.
     */
    public function porRecoger(Request $request): Response
    {
        $user = $request->user();

        // Ruta activa del chofer
        $activeRoute = DeliveryRoute::with('stops')
            ->where('driver_user_id', $user->id)
            ->whereIn('status', [DeliveryRoute::STATUS_IN_PROGRESS, DeliveryRoute::STATUS_INCIDENT, DeliveryRoute::STATUS_ASSIGNED])
            ->orderBy('id', 'desc')
            ->first();

        // Pedidos pendientes / confirmados / en ruta / en proceso con botellones fijos pendientes de recolectar
        $orders = Order::with(['customer.address', 'items', 'deliveryRoute.stops'])
            ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_IN_ROUTE, Order::STATUS_IN_PROCESS])
            ->whereHas('items', function ($q) {
                $q->where('type', OrderItem::TYPE_FIXED)
                  ->whereRaw('(quantity - collected_quantity - cancelled_quantity) > 0');
            })
            ->orderBy('id', 'desc')
            ->get();

        $activeRouteStopsByOrderId = $activeRoute ? $activeRoute->stops->keyBy('order_id') : collect();

        $canonicalZones = CustomerAddress::ZONES;
        $zoneCounts = ['all' => $orders->count(), 'none' => 0];
        foreach (array_keys($canonicalZones) as $zk) {
            $zoneCounts[$zk] = 0;
        }

        $formattedOrders = $orders->map(function ($order) use ($activeRoute, $activeRouteStopsByOrderId, &$zoneCounts) {
            $fixedItem = $order->items->firstWhere('type', OrderItem::TYPE_FIXED);
            $pendingToCollect = max(0, (int) ($fixedItem?->quantity ?? 0) - (int) ($fixedItem?->collected_quantity ?? 0) - (int) ($fixedItem?->cancelled_quantity ?? 0));

            $customer = $order->customer;

            // Domicilio o sin registrar
            $hasAddress = !empty($order->delivery_street) || !empty($customer?->address?->street);
            $street = $order->delivery_street ?: $customer?->address?->street;
            $ext = $order->delivery_exterior_number ?: $customer?->address?->exterior_number;
            $col = $order->delivery_neighborhood ?: $customer?->address?->neighborhood;
            $addressSummary = $hasAddress
                ? ($street . ($ext ? ' #' . $ext : '') . ($col ? ', ' . $col : ''))
                : 'Sin domicilio registrado (Servicio al paso)';

            // Normalización de zona
            $rawZone = $order->delivery_zone ?: $customer?->address?->delivery_zone;
            $zoneKey = 'none';
            $zoneLabel = 'Sin zona';
            if (!empty($rawZone)) {
                $norm = strtolower(trim($rawZone));
                if (in_array($norm, ['north', 'norte'])) {
                    $zoneKey = 'north';
                    $zoneLabel = 'Norte';
                } elseif (in_array($norm, ['south', 'sur'])) {
                    $zoneKey = 'south';
                    $zoneLabel = 'Sur';
                } elseif (in_array($norm, ['east', 'este'])) {
                    $zoneKey = 'east';
                    $zoneLabel = 'Este';
                } elseif (in_array($norm, ['west', 'oeste'])) {
                    $zoneKey = 'west';
                    $zoneLabel = 'Oeste';
                }
            }

            if (isset($zoneCounts[$zoneKey])) {
                $zoneCounts[$zoneKey]++;
            } else {
                $zoneCounts['none']++;
            }

            // Asignación a ruta
            $activeStop = $activeRouteStopsByOrderId->get($order->id);
            $isAssignedToCurrentRoute = $activeStop !== null;
            $isAssignedOtherRoute = !$isAssignedToCurrentRoute && $order->delivery_route_id !== null;

            $phone = $order->recipient_phone ?: $customer?->phone;

            return [
                'id' => $order->id,
                'folio' => $order->folio,
                'status' => $order->status,
                'status_label' => $order->status_label,
                'customer' => [
                    'id' => $customer?->id,
                    'code' => $customer?->code ?? "CLI-{$customer?->id}",
                    'name' => $customer?->name,
                    'nickname' => $customer?->nickname,
                    'phone' => $phone,
                    'has_phone' => !empty($phone),
                ],
                'fixed_quantity_pending' => $pendingToCollect,
                'total_fixed_in_order' => (int) ($fixedItem?->quantity ?? 0),
                'address_summary' => $addressSummary,
                'delivery_zone' => $zoneLabel,
                'delivery_zone_key' => $zoneKey,
                'is_assigned_to_current_route' => $isAssignedToCurrentRoute,
                'is_assigned_to_other_route' => $isAssignedOtherRoute,
                'assigned_route_folio' => $order->deliveryRoute?->folio,
                'stop_id' => $activeStop?->id,
                'stop_number' => $activeStop?->stop_number,
                'stop_status' => $activeStop?->status,
            ];
        });

        return Inertia::render('Repartidor/Collections/Index', [
            'orders' => $formattedOrders,
            'zoneCounts' => $zoneCounts,
            'activeRoute' => $activeRoute ? [
                'id' => $activeRoute->id,
                'folio' => $activeRoute->folio,
                'status' => $activeRoute->status,
                'is_in_progress' => $activeRoute->status === DeliveryRoute::STATUS_IN_PROGRESS,
                'is_assigned' => $activeRoute->status === DeliveryRoute::STATUS_ASSIGNED,
                'is_incident' => $activeRoute->status === DeliveryRoute::STATUS_INCIDENT,
            ] : null,
        ]);
    }

    /**
     * Confirma la recolección física de botellones fijos vacíos.
     * Soporta recogida regular y recogida con pago anticipado simultáneo.
     */
    public function collectFixed(Order $order, Request $request): RedirectResponse
    {
        $user = $request->user();
        $fixedItem = $order->items()->where('type', OrderItem::TYPE_FIXED)->firstOrFail();
        $pendingToCollect = max(0, (int) $fixedItem->quantity - (int) $fixedItem->collected_quantity - (int) $fixedItem->cancelled_quantity);
        $customerFixedMax = max($pendingToCollect, (int) ($order->customer?->fixed_bottles_count ?? $pendingToCollect));

        if (!$request->has('quantity') && $request->has('collected_quantity')) {
            $request->merge(['quantity' => $request->input('collected_quantity')]);
        }

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', "max:{$customerFixedMax}"],
            'is_advance_payment' => ['nullable', 'boolean'],
            'cash_tendered' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'collection_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $quantity = (int) $validated['quantity'];
        $isAdvance = !empty($validated['is_advance_payment']);
        $notes = $validated['notes'] ?? $validated['collection_notes'] ?? null;

        $refillPrice = (float) (\App\Models\BottlePresentation::where('capacity', 20.00)
            ->where('is_available_for_delivery', true)
            ->first()?->refill_price ?? 15.00);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($order, $fixedItem, $quantity, $isAdvance, $validated, $refillPrice, $user, $notes) {
                // 1. Recolección física en custodia
                $this->custodyService->recordFixedBottlesCollected(
                    $order,
                    $quantity,
                    $user,
                    $notes ?: ($isAdvance ? 'Recolección física con pago anticipado' : 'Recolección física en domicilio - Llevando a planta')
                );

                // 2. Si es pago anticipado, registrar cargo y pago en la misma transacción atómica
                if ($isAdvance) {
                    $exactAmount = round($quantity * $refillPrice, 2);
                    $cashTendered = isset($validated['cash_tendered']) ? (float) $validated['cash_tendered'] : $exactAmount;

                    if ($cashTendered < $exactAmount) {
                        throw new OrderBusinessRuleException("El efectivo recibido (\${$cashTendered}) es menor al total requerido (\${$exactAmount}).", 'cash_tendered', 422);
                    }

                    $charge = OrderCharge::create([
                        'order_id' => $order->id,
                        'order_item_id' => $fixedItem->id,
                        'customer_id' => $order->customer_id,
                        'bottle_type' => OrderItem::TYPE_FIXED,
                        'quantity_delivered' => $quantity,
                        'unit_price' => $refillPrice,
                        'total_amount' => $exactAmount,
                        'paid_amount' => 0.00,
                        'status' => OrderCharge::STATUS_PENDING,
                    ]);

                    $this->debtService->recordPayment(
                        $order->customer,
                        $cashTendered,
                        $user,
                        $order,
                        "Pago anticipado por {$quantity} botellones fijos de 20 L",
                        $quantity
                    );
                }

                // Parada de ruta activa si existiera
                $activeRoute = DeliveryRoute::where('driver_user_id', $user->id)
                    ->where('status', DeliveryRoute::STATUS_IN_PROGRESS)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($activeRoute) {
                    $stop = RouteStop::where('delivery_route_id', $activeRoute->id)
                        ->where('order_id', $order->id)
                        ->where('status', RouteStop::STATUS_PENDING)
                        ->first();

                    if ($stop) {
                        $hasPendingExchange = $order->items()->where('type', OrderItem::TYPE_EXCHANGE)
                            ->whereRaw('(quantity - delivered_quantity - cancelled_quantity) > 0')
                            ->exists();

                        if (!$hasPendingExchange) {
                            $stop->update([
                                'status' => RouteStop::STATUS_COMPLETED,
                                'completed_at' => now(),
                                'notes' => 'Recolección de fijos concluida exitosamente',
                            ]);
                        }
                    }
                }
            });

            if ($isAdvance) {
                $totalPaid = round($quantity * $refillPrice, 2);
                return back()->with('success', "Se recolectaron exitosamente {$quantity} botellón(es) fijos con pago anticipado (\${$totalPaid}). En camión rumbo a Planta.");
            }

            return back()->with('success', "Se recolectaron exitosamente {$quantity} botellón(es) fijos. En camión rumbo a Planta.");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['collect' => $e->getMessage()]);
        }
    }

    /**
     * Cancela un pedido de botellones fijos antes de recoger envases o según las reglas vigentes.
     */
    public function cancelOrder(Order $order, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'El motivo de cancelación es obligatorio.',
            'reason.min' => 'El motivo debe tener al menos 5 caracteres.',
        ]);

        try {
            $this->orderService->cancelOrder($order, $request->user(), $validated['reason']);
            return back()->with('success', "Pedido {$order->folio} cancelado exitosamente.");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['cancel' => $e->getMessage()]);
        } catch (\Exception $e) {
            return back()->withErrors(['cancel' => $e->getMessage()]);
        }
    }

    /**
     * Pantalla de fijos preparados en Planta listos para cargar al camión.
     */
    public function listosParaCargar(Request $request): Response
    {
        $user = $request->user();

        $items = OrderItem::with(['order.customer.address'])
            ->where('type', OrderItem::TYPE_FIXED)
            ->where('prepared_quantity', '>', 0)
            ->whereRaw('(prepared_quantity - loaded_quantity - damaged_quantity) > 0')
            ->whereHas('order', function ($q) {
                $q->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_DELIVERED]);
            })
            ->get();

        $formattedReady = $items->map(function ($item) {
            $order = $item->order;
            $customer = $order?->customer;
            $readyQty = $item->getReadyToLoadQuantity();

            $hasAddress = !empty($order->delivery_street) || !empty($customer?->address?->street);
            $street = $order->delivery_street ?: $customer?->address?->street;
            $ext = $order->delivery_exterior_number ?: $customer?->address?->exterior_number;
            $col = $order->delivery_neighborhood ?: $customer?->address?->neighborhood;
            $addressSummary = $hasAddress
                ? ($street . ($ext ? ' #' . $ext : '') . ($col ? ', ' . $col : ''))
                : 'Sin domicilio registrado';

            $phone = $order->recipient_phone ?: $customer?->phone;

            return [
                'item_id' => $item->id,
                'order_id' => $order->id,
                'order_folio' => $order->folio,
                'customer' => [
                    'id' => $customer?->id,
                    'name' => $customer?->name,
                    'nickname' => $customer?->nickname,
                    'phone' => $phone,
                    'code' => $customer?->code,
                ],
                'address_summary' => $addressSummary,
                'delivery_zone' => $order->delivery_zone ?: 'Sin zona',
                'ready_quantity' => $readyQty,
                'prepared_total' => (int) $item->prepared_quantity,
                'loaded_quantity' => (int) $item->loaded_quantity,
            ];
        })->filter(fn ($i) => $i['ready_quantity'] > 0)->values();

        return Inertia::render('Repartidor/ReadyToLoad/Index', [
            'readyItems' => $formattedReady,
            'totalOrders' => $formattedReady->count(),
            'totalBottles' => $formattedReady->sum('ready_quantity'),
        ]);
    }

    /**
     * Confirma la carga física de botellones fijos de un pedido hacia el camión.
     */
    public function acceptTruckLoadFixed(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'difference_reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $order = Order::findOrFail($validated['order_id']);
        $item = $order->items()->where('type', OrderItem::TYPE_FIXED)->firstOrFail();
        $readyQty = $item->getReadyToLoadQuantity();
        $quantity = (int) $validated['quantity'];

        if ($quantity > $readyQty) {
            return back()->withErrors(['load' => "No se pueden cargar {$quantity} fijos. Solo hay {$readyQty} listos para cargar."]);
        }

        $notes = $validated['notes'] ?? 'Fijos llenos cargados al camión';
        if ($quantity < $readyQty && !empty($validated['difference_reason'])) {
            $notes .= " | Discrepancia: {$validated['difference_reason']}";
        }

        try {
            $this->custodyService->recordFixedBottlesLoadedOnTruck($order, $quantity, $request->user(), $notes);

            $plural = $quantity === 1 ? '1 fijo cargado' : "{$quantity} fijos cargados";
            if ($quantity < $readyQty) {
                $diff = $readyQty - $quantity;
                return back()->with('success', "{$plural}. Ya aparecen en Por entregar ({$diff} restante en Planta).");
            }

            return back()->with('success', "{$plural}. Ya aparecen en Por entregar.");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['load' => $e->getMessage()]);
        }
    }

    /**
     * Reporta incidencia o daño de botellones fijos en Planta durante la carga.
     */
    public function reportFixedLoadIncident(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'damage_type' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $order = Order::findOrFail($validated['order_id']);
        $item = $order->items()->where('type', OrderItem::TYPE_FIXED)->firstOrFail();
        $readyQty = $item->getReadyToLoadQuantity();
        $qty = min($readyQty, (int) $validated['quantity']);

        if ($qty <= 0) {
            return back()->withErrors(['incident' => 'No hay unidades disponibles para reportar incidencia.']);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $item, $qty, $validated, $request) {
            $item->increment('damaged_quantity', $qty);

            \App\Models\BottleIncident::create([
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'reported_by_user_id' => $request->user()->id,
                'bottle_type' => OrderItem::TYPE_FIXED,
                'bottle_condition' => \App\Models\BottleCustodyMovement::CONDITION_FULL,
                'quantity' => $qty,
                'stage' => \App\Models\BottleIncident::STAGE_DELIVERY,
                'damage_type' => $validated['damage_type'],
                'status' => \App\Models\BottleIncident::STATUS_PENDING,
                'notes' => $validated['notes'] ?? "Incidencia reportada en carga en Planta ({$validated['damage_type']})",
            ]);
        });

        $plural = $qty === 1 ? '1 fijo' : "{$qty} fijos";
        return back()->with('success', "Incidencia registrada para {$plural}. Se apartó de los disponibles para cargar.");
    }

    /**
     * Confirma la carga física masiva de todos los botellones fijos listos visibles.
     */
    public function acceptAllTruckLoadFixed(Request $request): RedirectResponse
    {
        $items = OrderItem::with('order')
            ->where('type', OrderItem::TYPE_FIXED)
            ->where('prepared_quantity', '>', 0)
            ->whereRaw('(prepared_quantity - loaded_quantity - damaged_quantity) > 0')
            ->whereHas('order', function ($q) {
                $q->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_DELIVERED]);
            })
            ->get();

        $confirmedOrders = 0;
        $confirmedBottles = 0;

        \Illuminate\Support\Facades\DB::transaction(function () use ($items, $request, &$confirmedOrders, &$confirmedBottles) {
            foreach ($items as $item) {
                $ready = $item->getReadyToLoadQuantity();
                if ($ready <= 0) continue;

                $this->custodyService->recordFixedBottlesLoadedOnTruck(
                    $item->order,
                    $ready,
                    $request->user(),
                    'Carga masiva física al camión'
                );
                $confirmedOrders++;
                $confirmedBottles += $ready;
            }
        });

        return back()->with('success', "{$confirmedBottles} fijos cargados al camión ({$confirmedOrders} pedidos).");
    }

    /**
     * Confirma la entrega física de botellones fijos de 20 L al cliente con su estado de cobro.
     */
    public function deliverFixed(Order $order, Request $request): RedirectResponse
    {
        $user = $request->user();
        $item = $order->items()->where('type', OrderItem::TYPE_FIXED)->firstOrFail();
        $inTruck = max(0, (int)$item->loaded_quantity - (int)$item->delivered_quantity);

        $validated = $request->validate([
            'quantity_delivered' => ['required', 'integer', 'min:1', "max:{$inTruck}"],
            'payment_action' => ['required', 'string', 'in:already_paid,full_cash,partial_cash,defer'],
            'cash_tendered' => ['nullable', 'numeric', 'min:0'],
            'units_paid_cash' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $delivered = (int) $validated['quantity_delivered'];
        $action = $validated['payment_action'];
        $refillPrice = (float) (\App\Models\BottlePresentation::where('capacity', 20.00)
            ->where('is_available_for_delivery', true)
            ->first()?->refill_price ?? 15.00);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($order, $item, $delivered, $action, $validated, $refillPrice, $user) {
                $customer = $order->customer;

                // 1. Movimiento de custodia de entrega
                BottleCustodyMovement::create([
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'customer_id' => $customer->id,
                    'user_id' => $user->id,
                    'movement_type' => BottleCustodyMovement::TYPE_DELIVERY_REFILL_TO_CUSTOMER,
                    'bottle_type' => OrderItem::TYPE_FIXED,
                    'bottle_condition' => BottleCustodyMovement::CONDITION_FULL,
                    'quantity' => $delivered,
                    'origin' => 'truck',
                    'destination' => 'customer',
                    'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                    'notes' => $validated['notes'] ?? 'Devolución de fijos llenos entregados al cliente',
                    'created_at' => now(),
                ]);

                // 2. Cobros / Pagos
                if ($action === 'already_paid') {
                    // Ya liquidado por anticipado; no crear nuevo cargo
                } elseif ($action === 'full_cash') {
                    $charge = $this->debtService->recordCharge($order, $item, $delivered, $refillPrice, false);
                    $cash = isset($validated['cash_tendered']) ? (float) $validated['cash_tendered'] : round($delivered * $refillPrice, 2);
                    $this->debtService->recordPayment(
                        $customer,
                        $cash,
                        $user,
                        $order,
                        "Cobro de {$delivered} fijos al entregar",
                        $delivered
                    );
                } elseif ($action === 'partial_cash') {
                    $paidUnits = min($delivered, max(0, (int) ($validated['units_paid_cash'] ?? 0)));
                    $deferUnits = $delivered - $paidUnits;

                    if ($deferUnits > 0 && !$this->debtService->canChargeDebt($customer, $deferUnits)) {
                        $limit = $this->settingService->getMaxPendingPaymentBottles();
                        $pending = $this->debtService->getPendingDebtBottles($customer);
                        throw new OrderBusinessRuleException("No se pueden fiar {$deferUnits} botellones. El cliente cuenta con {$pending} pendientes y el límite es {$limit}.", 'debt_limit', 422);
                    }

                    $charge = $this->debtService->recordCharge($order, $item, $delivered, $refillPrice, false);
                    if ($paidUnits > 0) {
                        $cash = isset($validated['cash_tendered']) ? (float) $validated['cash_tendered'] : round($paidUnits * $refillPrice, 2);
                        $this->debtService->recordPayment(
                            $customer,
                            $cash,
                            $user,
                            $order,
                            "Pago parcial de {$paidUnits} fijos en efectivo al entregar",
                            $paidUnits
                        );
                    }
                } elseif ($action === 'defer') {
                    if (!$this->debtService->canChargeDebt($customer, $delivered)) {
                        $limit = $this->settingService->getMaxPendingPaymentBottles();
                        $pending = $this->debtService->getPendingDebtBottles($customer);
                        throw new OrderBusinessRuleException("No se pueden fiar {$delivered} botellones. El cliente cuenta con {$pending} pendientes y el límite es {$limit}.", 'debt_limit', 422);
                    }
                    $this->debtService->recordCharge($order, $item, $delivered, $refillPrice, false);
                }

                // 3. Actualizar Item y Pedido
                $newDelivered = (int) $item->delivered_quantity + $delivered;
                $allDelivered = ($newDelivered + (int)$item->cancelled_quantity) >= (int)$item->quantity;

                $item->update([
                    'delivered_quantity' => $newDelivered,
                    'status' => $allDelivered ? OrderItem::STATUS_DELIVERED : OrderItem::STATUS_OUT_FOR_DELIVERY,
                ]);

                if ($allDelivered) {
                    $order->update(['status' => Order::STATUS_DELIVERED]);
                    OrderStatusHistory::create([
                        'order_id' => $order->id,
                        'previous_status' => $order->status,
                        'new_status' => Order::STATUS_DELIVERED,
                        'user_id' => $user->id,
                        'reason' => "Entrega de {$delivered} fijos concluida exitosamente",
                        'created_at' => now(),
                    ]);
                }
            });

            return back()->with('success', "{$delivered} botellón(es) fijos entregados exitosamente al cliente.");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['deliver' => $e->getMessage()]);
        }
    }

    /**
     * Pantalla móvil "Por Entregar": Muestra pedidos de cambio y fijos preparados para devolución.
     */
    public function porEntregar(Request $request): Response
    {
        $user = $request->user();

        // 1. Inventario del camión
        $truckState = $this->truckInventoryService->getTruckInventoryState($user);
        $exchangeAvailable = (int) $truckState['truck_inventory']['exchange_full_available'];

        // 2. Ruta activa
        $activeRoute = DeliveryRoute::with('stops')
            ->where('driver_user_id', $user->id)
            ->whereIn('status', [DeliveryRoute::STATUS_IN_PROGRESS, DeliveryRoute::STATUS_INCIDENT, DeliveryRoute::STATUS_ASSIGNED])
            ->orderBy('id', 'desc')
            ->first();

        $activeRouteStopsByOrderId = $activeRoute ? $activeRoute->stops->keyBy('order_id') : collect();

        // 3. Pedidos con entregas pendientes
        $orders = Order::with(['customer.address', 'items', 'deliveryRoute.stops', 'charges', 'payments'])
            ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_IN_ROUTE, Order::STATUS_IN_PROCESS])
            ->where(function ($q) {
                $q->whereHas('items', fn ($sub) => $sub->where('type', OrderItem::TYPE_EXCHANGE)->whereRaw('(quantity - delivered_quantity - cancelled_quantity) > 0'))
                  ->orWhereHas('items', fn ($sub) => $sub->where('type', OrderItem::TYPE_FIXED)->whereRaw('(loaded_quantity - delivered_quantity) > 0'));
            })
            ->orderBy('id', 'desc')
            ->get();

        $canonicalZones = CustomerAddress::ZONES;
        $zoneCounts = ['all' => $orders->count(), 'none' => 0];
        foreach (array_keys($canonicalZones) as $zk) {
            $zoneCounts[$zk] = 0;
        }

        $refillPrice = (float) (\App\Models\BottlePresentation::where('capacity', 20.00)
            ->where('is_available_for_delivery', true)
            ->first()?->refill_price ?? 15.00);

        $formattedDeliveries = $orders->map(function ($order) use ($activeRoute, $activeRouteStopsByOrderId, $exchangeAvailable, &$zoneCounts, $refillPrice) {
            $customer = $order->customer;
            $exchangeItem = $order->items->firstWhere('type', OrderItem::TYPE_EXCHANGE);
            $fixedItem = $order->items->firstWhere('type', OrderItem::TYPE_FIXED);

            $exchangeQty = max(0, (int) ($exchangeItem?->quantity ?? 0) - (int) ($exchangeItem?->delivered_quantity ?? 0) - (int) ($exchangeItem?->cancelled_quantity ?? 0));
            $fixedQty = max(0, (int) ($fixedItem?->quantity ?? 0) - (int) ($fixedItem?->delivered_quantity ?? 0) - (int) ($fixedItem?->cancelled_quantity ?? 0));
            $fixedPrepared = (int) ($fixedItem?->prepared_quantity ?? 0);
            $fixedInTruck = $fixedItem ? max(0, (int)$fixedItem->loaded_quantity - (int)$fixedItem->delivered_quantity) : 0;

            // Análisis de pagos anticipados para botellones fijos
            $advancePaidBottles = (int) $order->payments->sum('bottles_covered_count');
            $paidChargesBottles = (int) $order->charges->where('status', OrderCharge::STATUS_PAID)->sum('quantity_delivered');
            $coveredBottles = max($advancePaidBottles, $paidChargesBottles);

            $alreadyDelivered = (int) ($fixedItem?->delivered_quantity ?? 0);
            $coveredRemaining = max(0, $coveredBottles - $alreadyDelivered);
            $unpaidInTruck = max(0, $fixedInTruck - $coveredRemaining);
            $isAdvancePaid = ($fixedInTruck > 0 && $unpaidInTruck === 0);
            $unpaidAmount = round($unpaidInTruck * $refillPrice, 2);

            // Ubicación
            $hasAddress = !empty($order->delivery_street) || !empty($customer?->address?->street);
            $street = $order->delivery_street ?: $customer?->address?->street;
            $ext = $order->delivery_exterior_number ?: $customer?->address?->exterior_number;
            $col = $order->delivery_neighborhood ?: $customer?->address?->neighborhood;
            $addressSummary = $hasAddress
                ? ($street . ($ext ? ' #' . $ext : '') . ($col ? ', ' . $col : ''))
                : 'Sin domicilio registrado';

            // Normalización de zona
            $rawZone = $order->delivery_zone ?: $customer?->address?->delivery_zone;
            $zoneKey = 'none';
            $zoneLabel = 'Sin zona';
            if (!empty($rawZone)) {
                $norm = strtolower(trim($rawZone));
                if (in_array($norm, ['north', 'norte'])) {
                    $zoneKey = 'north';
                    $zoneLabel = 'Norte';
                } elseif (in_array($norm, ['south', 'sur'])) {
                    $zoneKey = 'south';
                    $zoneLabel = 'Sur';
                } elseif (in_array($norm, ['east', 'este'])) {
                    $zoneKey = 'east';
                    $zoneLabel = 'Este';
                } elseif (in_array($norm, ['west', 'oeste'])) {
                    $zoneKey = 'west';
                    $zoneLabel = 'Oeste';
                }
            }

            if (isset($zoneCounts[$zoneKey])) {
                $zoneCounts[$zoneKey]++;
            } else {
                $zoneCounts['none']++;
            }

            // Semáforo de deuda
            $trafficLight = $customer ? $this->debtService->getTrafficLight($customer) : null;

            // Evaluación de disponibilidad y bloqueos
            $isBlocked = false;
            $blockReason = null;

            if ($exchangeQty > 0 && $exchangeAvailable < $exchangeQty) {
                $isBlocked = true;
                $blockReason = "Falta carga en camión: Requiere {$exchangeQty} garrafón(es) de cambio, pero hay {$exchangeAvailable} disponibles.";
            }

            if ($fixedQty > 0 && $fixedInTruck === 0) {
                $isBlocked = true;
                $blockReason = "Pendiente de cargar en camión: {$fixedQty} fijos del cliente aún no han sido cargados y aceptados en 'Listos para cargar'.";
            }

            $activeStop = $activeRouteStopsByOrderId->get($order->id);
            $isAssignedToCurrentRoute = $activeStop !== null;

            $phone = $order->recipient_phone ?: $customer?->phone;

            return [
                'id' => $order->id,
                'folio' => $order->folio,
                'status' => $order->status,
                'status_label' => $order->status_label,
                'customer' => [
                    'id' => $customer?->id,
                    'code' => $customer?->code ?? "CLI-{$customer?->id}",
                    'name' => $customer?->name,
                    'nickname' => $customer?->nickname,
                    'phone' => $phone,
                    'has_phone' => !empty($phone),
                ],
                'fixed_item_id' => $fixedItem?->id,
                'exchange_quantity' => $exchangeQty,
                'fixed_quantity' => $fixedQty,
                'fixed_prepared' => $fixedPrepared,
                'fixed_in_truck' => $fixedInTruck,
                'quantity_to_deliver' => $fixedInTruck > 0 ? $fixedInTruck : ($exchangeQty + $fixedQty),
                'total_bottles_to_deliver' => $exchangeQty + $fixedQty,
                'is_advance_paid' => $isAdvancePaid,
                'advance_paid_bottles' => $coveredRemaining,
                'unpaid_bottles' => $unpaidInTruck,
                'unpaid_amount' => $unpaidAmount,
                'unit_price' => $refillPrice,
                'address_summary' => $addressSummary,
                'delivery_zone' => $zoneLabel,
                'delivery_zone_key' => $zoneKey,
                'traffic_light' => $trafficLight,
                'is_blocked' => $isBlocked,
                'block_reason' => $blockReason,
                'is_assigned_to_current_route' => $isAssignedToCurrentRoute,
                'stop_id' => $activeStop?->id,
                'stop_number' => $activeStop?->stop_number,
                'stop_status' => $activeStop?->status,
            ];
        });

        return Inertia::render('Repartidor/Deliveries/Index', [
            'deliveries' => $formattedDeliveries,
            'zoneCounts' => $zoneCounts,
            'truckExchangeAvailable' => $exchangeAvailable,
            'activeRoute' => $activeRoute ? [
                'id' => $activeRoute->id,
                'folio' => $activeRoute->folio,
                'status' => $activeRoute->status,
                'is_in_progress' => $activeRoute->status === DeliveryRoute::STATUS_IN_PROGRESS,
                'is_assigned' => $activeRoute->status === DeliveryRoute::STATUS_ASSIGNED,
                'is_incident' => $activeRoute->status === DeliveryRoute::STATUS_INCIDENT,
            ] : null,
        ]);
    }

    /**
     * Pantalla móvil "Cobrar Pendientes": Acceso independiente para liquidar saldos de clientes.
     */
    public function cobrarPendientes(Request $request): Response
    {
        $searchTerm = trim((string) $request->query('q', ''));

        $customersQuery = Customer::query()
            ->whereHas('charges', function ($q) {
                $q->whereIn('status', [OrderCharge::STATUS_PENDING, OrderCharge::STATUS_PARTIALLY_PAID]);
            })
            ->with(['charges' => function ($q) {
                $q->whereIn('status', [OrderCharge::STATUS_PENDING, OrderCharge::STATUS_PARTIALLY_PAID])
                  ->orderBy('created_at', 'asc');
            }, 'address']);

        if (!empty($searchTerm)) {
            $customersQuery->where(function ($q) use ($searchTerm) {
                $q->where('code', 'like', "%{$searchTerm}%")
                  ->orWhere('name', 'like', "%{$searchTerm}%")
                  ->orWhere('nickname', 'like', "%{$searchTerm}%")
                  ->orWhere('phone', 'like', "%{$searchTerm}%");
            });
        }

        $customersWithDebt = $customersQuery->limit(25)->get()->map(function ($cust) {
            $pendingBottles = $this->debtService->getPendingDebtBottles($cust);
            $pendingAmount = $this->debtService->getPendingDebtAmount($cust);
            $trafficLight = $this->debtService->getTrafficLight($cust);
            $promo = $this->promotionService->getWeeklyProgress($cust);

            $chargesDetail = $cust->charges->map(function ($charge) {
                $unitPrice = (float) $charge->unit_price;
                $alreadyPaidUnits = $unitPrice > 0 ? (int) floor((float) $charge->paid_amount / $unitPrice) : 0;
                $unpaidUnits = max(0, (int) $charge->quantity_delivered - $alreadyPaidUnits);

                return [
                    'id' => $charge->id,
                    'order_folio' => $charge->order?->folio ?? "PED-{$charge->order_id}",
                    'unit_price' => $unitPrice,
                    'quantity_delivered' => (int) $charge->quantity_delivered,
                    'unpaid_units' => $unpaidUnits,
                    'remaining_amount' => (float) $charge->remainingAmount(),
                    'date' => $charge->created_at?->format('d/m/Y'),
                ];
            })->values();

            return [
                'id' => $cust->id,
                'code' => $cust->code ?? "CLI-{$cust->id}",
                'name' => $cust->name,
                'nickname' => $cust->nickname,
                'phone' => $cust->phone,
                'pending_bottles' => $pendingBottles,
                'pending_amount' => $pendingAmount,
                'traffic_light' => $trafficLight,
                'weekly_promo' => $promo,
                'charges' => $chargesDetail,
            ];
        });

        $selectedCustomerId = $request->query('customer_id');
        $selectedCustomer = null;
        if ($selectedCustomerId) {
            $selectedCustomer = $customersWithDebt->firstWhere('id', (int) $selectedCustomerId);
            if (!$selectedCustomer) {
                $single = Customer::with(['charges' => function ($q) {
                    $q->whereIn('status', [OrderCharge::STATUS_PENDING, OrderCharge::STATUS_PARTIALLY_PAID])
                      ->orderBy('created_at', 'asc');
                }])->find($selectedCustomerId);

                if ($single) {
                    $pendingBottles = $this->debtService->getPendingDebtBottles($single);
                    $pendingAmount = $this->debtService->getPendingDebtAmount($single);
                    $trafficLight = $this->debtService->getTrafficLight($single);
                    $promo = $this->promotionService->getWeeklyProgress($single);

                    $selectedCustomer = [
                        'id' => $single->id,
                        'code' => $single->code ?? "CLI-{$single->id}",
                        'name' => $single->name,
                        'nickname' => $single->nickname,
                        'phone' => $single->phone,
                        'pending_bottles' => $pendingBottles,
                        'pending_amount' => $pendingAmount,
                        'traffic_light' => $trafficLight,
                        'weekly_promo' => $promo,
                        'charges' => $single->charges->map(fn ($c) => [
                            'id' => $c->id,
                            'order_folio' => $c->order?->folio ?? "PED-{$c->order_id}",
                            'unit_price' => (float) $c->unit_price,
                            'quantity_delivered' => (int) $c->quantity_delivered,
                            'unpaid_units' => max(0, (int) $c->quantity_delivered - (int) floor((float) $c->paid_amount / max(0.01, (float) $c->unit_price))),
                            'remaining_amount' => (float) $c->remainingAmount(),
                            'date' => $c->created_at?->format('d/m/Y'),
                        ])->values(),
                    ];
                }
            }
        }

        return Inertia::render('Repartidor/Debt/Index', [
            'customersWithDebt' => $customersWithDebt,
            'selectedCustomer' => $selectedCustomer,
            'searchTerm' => $searchTerm,
        ]);
    }

    /**
     * Inicia explícitamente el recorrido del repartidor único activo.
     */
    public function startTodayRoute(Request $request): RedirectResponse
    {
        try {
            $route = $this->routeService->startOrCreateSoleDriverRoute($request->user());
            return redirect()->route('repartidor.route.index')->with('success', "¡Jornada de reparto iniciada con la hoja de ruta {$route->folio}!");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['route' => $e->getMessage()]);
        }
    }

    /**
     * Pantalla unificada: REPARTO
     * Reúne las 5 pestañas operativas (pendientes, por_recoger, por_entregar, en_proceso, atendidos)
     * y el filtrado por zonas en una sola vista móvil optimizada.
     */
    public function reparto(Request $request): Response
    {
        $user = $request->user();
        $activeTab = $request->query('tab', 'por_recoger');
        if (!in_array($activeTab, ['por_recoger', 'por_recoger_planta', 'por_entregar'], true)) {
            $activeTab = 'por_recoger';
        }

        $activeZone = $request->query('zone', 'all');
        if (!in_array($activeZone, ['all', 'north', 'south', 'east', 'west', 'none'], true)) {
            $activeZone = 'all';
        }

        // 1. Resolver chofer único y ruta activa
        $driver = $this->routeService->resolveSoleActiveDriver();
        $activeRoute = DeliveryRoute::with([
            'stops' => fn ($q) => $q->orderBy('stop_number', 'asc')->with([
                'order.customer',
                'order.items',
                'order.charges',
            ]),
        ])
            ->where('driver_user_id', $driver->id)
            ->whereIn('status', [
                DeliveryRoute::STATUS_ASSIGNED,
                DeliveryRoute::STATUS_IN_PROGRESS,
                DeliveryRoute::STATUS_INCIDENT,
            ])
            ->orderBy('id', 'desc')
            ->first();

        // Si no hay ruta activa, buscar la última de hoy
        $completedTodayRoute = null;
        if (!$activeRoute) {
            $completedTodayRoute = DeliveryRoute::with([
                'stops' => fn ($q) => $q->orderBy('stop_number', 'asc')->with([
                    'order.customer',
                    'order.items',
                ]),
            ])
                ->where('driver_user_id', $driver->id)
                ->where('status', DeliveryRoute::STATUS_COMPLETED)
                ->whereDate('date', now()->toDateString())
                ->orderBy('id', 'desc')
                ->first();
        }

        // Estado de la jornada
        $journeyStatus = 'sin_iniciar';
        $journeyStatusLabel = 'Sin iniciar';
        if ($activeRoute) {
            if ($activeRoute->status === DeliveryRoute::STATUS_IN_PROGRESS) {
                $journeyStatus = 'en_ruta';
                $journeyStatusLabel = 'En ruta';
            } elseif ($activeRoute->status === DeliveryRoute::STATUS_INCIDENT) {
                $journeyStatus = 'detenida';
                $journeyStatusLabel = 'Detenida por avería';
            } elseif ($activeRoute->status === DeliveryRoute::STATUS_ASSIGNED) {
                $journeyStatus = 'sin_iniciar';
                $journeyStatusLabel = 'Ruta asignada (lista para iniciar)';
            }
        } elseif ($completedTodayRoute) {
            $journeyStatus = 'cerrada';
            $journeyStatusLabel = 'Jornada cerrada';
        }

        // 2. Inventario del camión
        $truckState = $this->truckInventoryService->getTruckInventoryState($driver);
        $exchangeAvailable = (int) ($truckState['truck_inventory']['exchange_full_available'] ?? $truckState['exchange_full_available'] ?? 0);
        $exchangeEmpty = (int) ($truckState['truck_inventory']['exchange_empty_collected'] ?? $truckState['exchange_empty_collected'] ?? 0);
        $fixedTransporting = (int) ($truckState['truck_inventory']['fixed_empty_collected_total'] ?? $truckState['fixed_transporting_to_plant'] ?? 0);

        // 3. Obtener paradas e identificar pedidos gestionados
        $pendingStopMap = $activeRoute ? $activeRoute->stops->where('status', RouteStop::STATUS_PENDING)->keyBy('order_id') : collect();
        $handledOrderIds = $activeRoute ? $activeRoute->stops->whereIn('status', [RouteStop::STATUS_COMPLETED, RouteStop::STATUS_FAILED])->pluck('order_id')->all() : [];
        if ($completedTodayRoute) {
            $handledOrderIds = array_merge($handledOrderIds, $completedTodayRoute->stops->whereIn('status', [RouteStop::STATUS_COMPLETED, RouteStop::STATUS_FAILED])->pluck('order_id')->all());
        }
        $handledOrderIds = array_unique($handledOrderIds);

        // Pedidos activos disponibles para atender
        $allOrders = Order::with(['customer', 'items', 'charges', 'payments'])
            ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_IN_PROCESS, Order::STATUS_IN_ROUTE])
            ->whereNotIn('id', $handledOrderIds)
            ->orderBy('id', 'asc')
            ->get();

        $refillPrice = (float) (\App\Models\BottlePresentation::where('capacity', 20.00)
            ->where('is_available_for_delivery', true)
            ->first()?->refill_price ?? 15.00);

        // Precargar movimientos de custodia de fijos por cliente (evitar N+1)
        $customerIds = $allOrders->pluck('customer_id')->filter()->unique()->values()->all();

        $loadedFixedByCustomer = BottleCustodyMovement::where('bottle_type', OrderItem::TYPE_FIXED)
            ->where('movement_type', BottleCustodyMovement::TYPE_TRUCK_LOAD_FIXED)
            ->where('destination', 'truck')
            ->whereIn('status', [BottleCustodyMovement::STATUS_ACCEPTED, BottleCustodyMovement::STATUS_ACCEPTED_WITH_DISCREPANCY])
            ->whereIn('customer_id', $customerIds)
            ->groupBy('customer_id')
            ->selectRaw('customer_id, SUM(quantity) as total')
            ->pluck('total', 'customer_id')
            ->all();

        $deliveredFixedByCustomer = BottleCustodyMovement::where('bottle_type', OrderItem::TYPE_FIXED)
            ->where('movement_type', BottleCustodyMovement::TYPE_DELIVERY_REFILL_TO_CUSTOMER)
            ->where('origin', 'truck')
            ->whereIn('status', [BottleCustodyMovement::STATUS_ACCEPTED, BottleCustodyMovement::STATUS_ACCEPTED_WITH_DISCREPANCY])
            ->whereIn('customer_id', $customerIds)
            ->groupBy('customer_id')
            ->selectRaw('customer_id, SUM(quantity) as total')
            ->pluck('total', 'customer_id')
            ->all();

        $proposedFixedByCustomer = BottleCustodyMovement::where('bottle_type', OrderItem::TYPE_FIXED)
            ->where('movement_type', BottleCustodyMovement::TYPE_TRUCK_LOAD_FIXED)
            ->where('destination', 'truck')
            ->where('status', BottleCustodyMovement::STATUS_PROPOSED)
            ->whereIn('customer_id', $customerIds)
            ->groupBy('customer_id')
            ->selectRaw('customer_id, SUM(quantity) as total')
            ->pluck('total', 'customer_id')
            ->all();

        // Mapeo canónico de zona
        $mapOrderZone = function ($order) {
            $z = strtolower(trim((string) ($order->delivery_zone ?: ($order->customer?->address?->delivery_zone ?? ''))));
            if (in_array($z, ['north', 'norte'], true)) return 'north';
            if (in_array($z, ['south', 'sur'], true)) return 'south';
            if (in_array($z, ['east', 'este'], true)) return 'east';
            if (in_array($z, ['west', 'oeste'], true)) return 'west';
            return 'none';
        };

        // Formateador de tarjeta
        $formatOrderCard = function ($order, $stop = null) use ($mapOrderZone, $exchangeAvailable, $loadedFixedByCustomer, $deliveredFixedByCustomer, $proposedFixedByCustomer, $refillPrice) {
            $customer = $order->customer;
            $exchangeItem = $order->items->firstWhere('type', OrderItem::TYPE_EXCHANGE);
            $fixedItem = $order->items->firstWhere('type', OrderItem::TYPE_FIXED);

            $exchangeQty = (int) ($exchangeItem?->quantity ?? 0);
            $exchangeDelivered = (int) ($exchangeItem?->delivered_quantity ?? 0);
            $exchangePending = max(0, $exchangeQty - $exchangeDelivered);

            $fixedQty = (int) ($fixedItem?->quantity ?? 0);
            $fixedCollected = (int) ($fixedItem?->collected_quantity ?? 0);
            $fixedPrepared = (int) ($fixedItem?->prepared_quantity ?? 0);
            $fixedDelivered = (int) ($fixedItem?->delivered_quantity ?? 0);
            $fixedToCollect = max(0, $fixedQty - $fixedCollected);
            $fixedToDeliver = max(0, min($fixedQty, $fixedPrepared) - $fixedDelivered);

            $customerId = $order->customer_id;
            $custLoaded = (int) ($loadedFixedByCustomer[$customerId] ?? 0);
            $custDelivered = (int) ($deliveredFixedByCustomer[$customerId] ?? 0);
            $fixedAvailableOnTruck = max(0, $custLoaded - $custDelivered);
            $fixedProposedOnTruck = (int) ($proposedFixedByCustomer[$customerId] ?? 0);

            // Disponibilidad real de fijos en camión
            $fixedInTruck = $fixedItem ? max(0, (int)$fixedItem->loaded_quantity - (int)$fixedItem->delivered_quantity) : 0;
            if ($fixedInTruck === 0 && $fixedAvailableOnTruck > 0) {
                $fixedInTruck = min($fixedToDeliver, $fixedAvailableOnTruck);
            }

            // Análisis de pagos anticipados para botellones fijos
            $advancePaidBottles = (int) $order->payments->sum('bottles_covered_count');
            $paidChargesBottles = (int) $order->charges->where('status', OrderCharge::STATUS_PAID)->sum('quantity_delivered');
            $coveredBottles = max($advancePaidBottles, $paidChargesBottles);

            $alreadyDelivered = (int) ($fixedItem?->delivered_quantity ?? 0);
            $coveredRemaining = max(0, $coveredBottles - $alreadyDelivered);
            $unpaidInTruck = max(0, $fixedInTruck - $coveredRemaining);
            $isAdvancePaid = ($fixedInTruck > 0 && $unpaidInTruck === 0);
            $unpaidAmount = round($unpaidInTruck * $refillPrice, 2);

            $hasExchange = ($exchangePending > 0);
            $hasFixedCollect = ($fixedToCollect > 0);
            $hasFixedDeliver = ($fixedToDeliver > 0);

            $canDeliverExchange = ($hasExchange && $exchangeAvailable > 0);
            $canDeliverFixed = ($hasFixedDeliver && $fixedAvailableOnTruck > 0);
            $hasFixedPendingAcceptance = ($hasFixedDeliver && $fixedAvailableOnTruck == 0 && $fixedProposedOnTruck > 0);
            $fixedInPlant = ($hasFixedDeliver && $fixedAvailableOnTruck == 0 && $fixedProposedOnTruck == 0);

            $actionType = 'serve_order';
            $actionLabel = 'Atender pedido';
            $canExecute = true;
            $blockedReason = null;
            $situationLabel = '';
            $isPartial = false;
            $breakdown = [];

            if ($exchangeQty > 0 && $fixedQty > 0) {
                $opType = 'mixto';
                $opLabel = 'Mixto (Cambio + Fijos)';

                if ($hasExchange) {
                    if ($canDeliverExchange) {
                        $breakdown[] = "Entregar {$exchangePending} de cambio";
                    } else {
                        $breakdown[] = "Sin botellones de cambio en camión ({$exchangePending} sol.)";
                    }
                }
                if ($hasFixedCollect) {
                    $breakdown[] = "Recoger {$fixedToCollect} fijos";
                }
                if ($hasFixedDeliver) {
                    if ($canDeliverFixed) {
                        $deliverable = min($fixedToDeliver, $fixedAvailableOnTruck);
                        $breakdown[] = "Entregar {$deliverable} fijos";
                    } elseif ($hasFixedPendingAcceptance) {
                        $breakdown[] = "Fijos por aceptar en Mi Carga ({$fixedToDeliver} u.)";
                    } else {
                        $breakdown[] = "Fijos en preparación en Planta ({$fixedToDeliver} u.)";
                    }
                }

                $hasAnyAction = $canDeliverExchange || $hasFixedCollect || $canDeliverFixed;
                if ($hasAnyAction) {
                    $actionType = 'serve_mixed';
                    $actionLabel = 'Atender pedido';
                    $canExecute = true;
                } elseif ($hasFixedPendingAcceptance) {
                    $actionType = 'review_load';
                    $actionLabel = 'Revisar carga';
                    $situationLabel = 'Fijos pendientes de aceptar en Mi Carga';
                    $canExecute = true;
                } else {
                    $actionType = 'blocked';
                    $canExecute = false;
                    if ($exchangeAvailable == 0 && $fixedInPlant) {
                        $blockedReason = 'Sin existencias de cambio y fijos en Planta';
                    } elseif ($exchangeAvailable == 0) {
                        $blockedReason = 'Sin botellones de cambio en camión';
                    } else {
                        $blockedReason = 'En preparación en Planta';
                    }
                }
            } elseif ($fixedQty > 0) {
                if ($hasFixedCollect) {
                    $opType = 'recoleccion_fijos';
                    $opLabel = 'Recolección de fijos';
                    $actionType = 'collect_fixed';
                    $actionLabel = "Recoger {$fixedToCollect} fijos";
                    $canExecute = true;
                } elseif ($hasFixedDeliver) {
                    $opType = 'devolucion_fijos';
                    $opLabel = 'Devolución de fijos';
                    if ($canDeliverFixed) {
                        $deliverable = min($fixedToDeliver, $fixedAvailableOnTruck);
                        $actionType = 'deliver_fixed';
                        $actionLabel = "Entregar {$deliverable} fijos";
                        $canExecute = true;
                        if ($fixedAvailableOnTruck < $fixedToDeliver) {
                            $isPartial = true;
                        }
                    } elseif ($hasFixedPendingAcceptance) {
                        $actionType = 'review_load';
                        $actionLabel = 'Revisar carga';
                        $situationLabel = 'Fijos pendientes de aceptar en Mi Carga';
                        $canExecute = true;
                    } else {
                        $actionType = 'blocked';
                        $canExecute = false;
                        $blockedReason = 'En preparación en Planta';
                    }
                } else {
                    $opType = 'recoleccion_fijos';
                    $opLabel = 'Fijos procesados';
                    $actionType = 'completed';
                    $actionLabel = 'Completado';
                    $canExecute = false;
                }
            } else {
                $opType = 'intercambio';
                $opLabel = 'Cambio de botellones';
                if ($hasExchange) {
                    if ($exchangeAvailable >= $exchangePending) {
                        $actionType = 'deliver_exchange';
                        $actionLabel = "Entregar {$exchangePending} de cambio";
                        $canExecute = true;
                    } elseif ($exchangeAvailable > 0) {
                        $actionType = 'deliver_exchange';
                        $actionLabel = "Entregar {$exchangeAvailable} de cambio";
                        $canExecute = true;
                        $isPartial = true;
                    } else {
                        $actionType = 'blocked';
                        $canExecute = false;
                        $blockedReason = 'Sin botellones de cambio en camión';
                    }
                } else {
                    $actionType = 'completed';
                    $actionLabel = 'Completado';
                    $canExecute = false;
                }
            }

            $tl = $customer ? $this->debtService->getTrafficLight($customer) : null;
            $debtAmount = $customer ? $this->debtService->getPendingDebtAmount($customer) : 0;
            $debtBottles = $customer ? $this->debtService->getPendingDebtBottles($customer) : 0;

            $street = trim((string) ($order->delivery_street ?? ''));
            $ext = trim((string) ($order->delivery_exterior_number ?? ''));
            $col = trim((string) ($order->delivery_neighborhood ?? ''));
            $cp = trim((string) ($order->delivery_postal_code ?? ''));

            $addressShort = $street !== ''
                ? ($street . ($ext !== '' ? ' #' . $ext : ''))
                : 'Sin ubicación registrada';

            $addressFull = $street !== ''
                ? ($street . ($ext !== '' ? ' #' . $ext : '') . ($col !== '' ? ', Col. ' . $col : '') . ($cp !== '' ? ', C.P. ' . $cp : ''))
                : 'Sin ubicación registrada';

            return [
                'id' => $order->id,
                'folio' => $order->folio,
                'status' => $order->status,
                'status_label' => $order->status_label,
                'customer_id' => $order->customer_id,
                'customer_code' => $customer?->code ?? "CLI-{$order->customer_id}",
                'customer_name' => $customer?->name,
                'customer_nickname' => $customer?->nickname ?? 'Cliente',
                'customer_phone' => $order->recipient_phone ?: $customer?->phone,
                'notes' => $order->notes,
                'address_short' => $addressShort,
                'address_summary' => $addressFull,
                'address_reference' => $order->address_snapshot['delivery_reference'] ?? $order->delivery_notes ?? null,
                'zone_key' => $mapOrderZone($order),
                'zone_label' => match ($mapOrderZone($order)) {
                    'north' => 'Norte',
                    'south' => 'Sur',
                    'east' => 'Este',
                    'west' => 'Oeste',
                    default => 'Sin zona',
                },
                'operation_type' => $opType,
                'operation_label' => $opLabel,
                'exchange_quantity' => $exchangeQty,
                'exchange_pending' => $exchangePending,
                'fixed_quantity' => $fixedQty,
                'fixed_to_collect' => $fixedToCollect,
                'fixed_to_deliver' => $fixedToDeliver,
                'fixed_item_id' => $fixedItem?->id,
                'fixed_in_truck' => $fixedInTruck,
                'is_advance_paid' => $isAdvancePaid,
                'advance_paid_bottles' => $coveredRemaining,
                'unpaid_bottles' => $unpaidInTruck,
                'unpaid_amount' => $unpaidAmount,
                'unit_price' => $refillPrice,
                'total_bottles' => $exchangeQty + $fixedQty,
                'traffic_light' => $tl,
                'pending_debt_amount' => $debtAmount,
                'pending_debt_bottles' => $debtBottles,
                'action_type' => $actionType,
                'action_label' => $actionLabel,
                'can_execute' => $canExecute,
                'blocked_reason' => $blockedReason,
                'situation_label' => $situationLabel,
                'is_partial' => $isPartial,
                'breakdown' => $breakdown,
                'fixed_available_on_truck' => $fixedAvailableOnTruck,
                'created_at' => $order->created_at?->format('d/m H:i'),
                'stop_id' => $stop?->id,
                'stop_number' => $stop?->stop_number,
                'stop_status' => $stop?->status,
                'failure_reason' => $stop?->failure_reason,
                'completed_at' => $stop?->completed_at?->format('H:i'),
            ];
        };

        // Listados por pestaña
        // 1. Por recoger (cliente)
        $porRecogerList = $allOrders->filter(function ($order) {
            $fixedItem = $order->items->firstWhere('type', OrderItem::TYPE_FIXED);
            return $fixedItem && ($fixedItem->quantity > $fixedItem->collected_quantity);
        })->map(function ($order) use ($formatOrderCard, $pendingStopMap) {
            $stop = $pendingStopMap->get($order->id);
            return $formatOrderCard($order, $stop);
        })->values();

        // 2. Por recoger en Planta (listos tras lavado y llenado)
        $porRecogerPlantaList = $allOrders->filter(function ($order) {
            $fixedItem = $order->items->firstWhere('type', OrderItem::TYPE_FIXED);
            if (!$fixedItem) return false;
            $readyToLoad = max(0, (int)$fixedItem->prepared_quantity - (int)$fixedItem->loaded_quantity - (int)$fixedItem->damaged_quantity);
            return $readyToLoad > 0;
        })->map(function ($order) use ($formatOrderCard, $pendingStopMap) {
            $stop = $pendingStopMap->get($order->id);
            $card = $formatOrderCard($order, $stop);
            $fixedItem = $order->items->firstWhere('type', OrderItem::TYPE_FIXED);
            $readyToLoad = max(0, (int)$fixedItem->prepared_quantity - (int)$fixedItem->loaded_quantity - (int)$fixedItem->damaged_quantity);
            $card['fixed_ready_to_load'] = $readyToLoad;
            $card['fixed_quantity'] = $readyToLoad;
            $card['order_item_id'] = $fixedItem->id;
            return $card;
        })->values();

        // 3. Por entregar (en camión)
        $porEntregarList = $allOrders->filter(function ($order) use ($loadedFixedByCustomer, $deliveredFixedByCustomer) {
            $exchangeItem = $order->items->firstWhere('type', OrderItem::TYPE_EXCHANGE);
            $fixedItem = $order->items->firstWhere('type', OrderItem::TYPE_FIXED);
            $hasExchange = $exchangeItem && ($exchangeItem->quantity > $exchangeItem->delivered_quantity);
            $fixedInTruck = $fixedItem ? max(0, (int)$fixedItem->loaded_quantity - (int)$fixedItem->delivered_quantity) : 0;
            $custAvail = max(0, (int)($loadedFixedByCustomer[$order->customer_id] ?? 0) - (int)($deliveredFixedByCustomer[$order->customer_id] ?? 0));
            return $hasExchange || ($fixedInTruck > 0) || ($custAvail > 0);
        })->map(function ($order) use ($formatOrderCard, $pendingStopMap) {
            $stop = $pendingStopMap->get($order->id);
            return $formatOrderCard($order, $stop);
        })->values();

        $pendientesList = $porRecogerList;
        $enProcesoList = collect();
        $atendidosList = collect();

        // Conteo por pestañas
        $tabCounts = [
            'por_recoger' => $porRecogerList->count(),
            'por_recoger_planta' => $porRecogerPlantaList->count(),
            'por_entregar' => $porEntregarList->count(),
            'pendientes' => $allOrders->count(),
            'en_proceso' => 0,
            'atendidos' => 0,
        ];

        // Colección seleccionada
        $currentCollection = match ($activeTab) {
            'por_recoger_planta' => $porRecogerPlantaList,
            'por_entregar' => $porEntregarList,
            default => $porRecogerList,
        };

        // Conteo por zonas de la colección activa
        $zoneCounts = [
            'all' => $currentCollection->count(),
            'north' => $currentCollection->where('zone_key', 'north')->count(),
            'south' => $currentCollection->where('zone_key', 'south')->count(),
            'east' => $currentCollection->where('zone_key', 'east')->count(),
            'west' => $currentCollection->where('zone_key', 'west')->count(),
            'none' => $currentCollection->where('zone_key', 'none')->count(),
        ];

        // Elementos filtrados
        $filteredItems = $activeZone === 'all'
            ? $currentCollection->values()->all()
            : $currentCollection->where('zone_key', $activeZone)->values()->all();

        return Inertia::render('Repartidor/Reparto/Index', [
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
            ],
            'activeRoute' => $activeRoute ? [
                'id' => $activeRoute->id,
                'folio' => $activeRoute->folio,
                'status' => $activeRoute->status,
                'total_stops' => $activeRoute->stops->count(),
                'pending_stops' => $activeRoute->stops->where('status', RouteStop::STATUS_PENDING)->count(),
                'completed_stops' => $activeRoute->stops->where('status', RouteStop::STATUS_COMPLETED)->count(),
                'failed_stops' => $activeRoute->stops->where('status', RouteStop::STATUS_FAILED)->count(),
            ] : null,
            'journeyStatus' => $journeyStatus,
            'journeyStatusLabel' => $journeyStatusLabel,
            'truckState' => [
                'exchange_full_available' => $exchangeAvailable,
                'exchange_empty_collected' => $exchangeEmpty,
                'fixed_transporting_to_plant' => $fixedTransporting,
            ],
            'activeTab' => $activeTab,
            'activeZone' => $activeZone,
            'tabCounts' => $tabCounts,
            'zoneCounts' => $zoneCounts,
            'items' => $filteredItems,
            'lastUpdatedAt' => now('America/Merida')->format('d/m/Y H:i:s'),
        ]);
    }

    /**
     * Incorpora un pedido disponible a la ruta activa del repartidor.
     */
    public function addToRoute(Order $order, Request $request): RedirectResponse
    {
        $user = $request->user();
        $driver = $this->routeService->resolveSoleActiveDriver();

        $activeRoute = DeliveryRoute::where('driver_user_id', $driver->id)
            ->whereIn('status', [DeliveryRoute::STATUS_ASSIGNED, DeliveryRoute::STATUS_IN_PROGRESS])
            ->orderBy('id', 'desc')
            ->first();

        if (!$activeRoute) {
            try {
                $activeRoute = $this->routeService->startOrCreateSoleDriverRoute($user);
            } catch (OrderBusinessRuleException $e) {
                return back()->withErrors(['route' => $e->getMessage()]);
            }
        }

        try {
            $this->routeService->addOrderToActiveRoute($activeRoute, $order, $user);
            return back()->with('success', "Pedido {$order->folio} incorporado a tu ruta activa ({$activeRoute->folio}).");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['route' => $e->getMessage()]);
        }
    }

    /**
     * Atiende directamente un pedido: si la ruta activa existe y está en curso,
     * auto-incorpora el pedido y redirige inmediatamente a la pantalla táctil de atención de parada.
     */
    public function serveOrder(Order $order, Request $request): RedirectResponse
    {
        $user = $request->user();
        $driver = $this->routeService->resolveSoleActiveDriver();

        $activeRoute = DeliveryRoute::where('driver_user_id', $driver->id)
            ->whereIn('status', [DeliveryRoute::STATUS_ASSIGNED, DeliveryRoute::STATUS_IN_PROGRESS, DeliveryRoute::STATUS_INCIDENT])
            ->orderBy('id', 'desc')
            ->first();

        if (!$activeRoute) {
            try {
                $activeRoute = $this->routeService->startOrCreateSoleDriverRoute($user);
            } catch (OrderBusinessRuleException $e) {
                return back()->withErrors(['route' => $e->getMessage()]);
            }
        }

        if ($activeRoute->status === DeliveryRoute::STATUS_INCIDENT) {
            return back()->withErrors(['stop' => 'La ruta se encuentra en pausa por avería mecánica. Reanúdala para atender pedidos.']);
        }

        if ($activeRoute->status === DeliveryRoute::STATUS_ASSIGNED) {
            $activeRoute = $this->routeService->startRoute($activeRoute, $user);
        }

        try {
            $stop = $this->routeService->addOrderToActiveRoute($activeRoute, $order, $user);
            $redirectParams = ['stop' => $stop->id];
            if ($request->filled('tab')) {
                $redirectParams['return_tab'] = $request->input('tab');
            }
            if ($request->filled('zone')) {
                $redirectParams['return_zone'] = $request->input('zone');
            }
            return redirect()->route('repartidor.stops.serve', $redirectParams);
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['stop' => $e->getMessage()]);
        }
    }
}
