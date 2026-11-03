<?php

namespace App\Http\Controllers\Repartidor;

use App\Exceptions\OrderBusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\BottleCustodyMovement;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryRoute;
use App\Models\OrderItem;
use App\Models\RouteStop;
use App\Services\DebtAndCreditService;
use App\Services\DeliveryRouteService;
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
        protected SettingService $settingService
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

        return Inertia::render('Repartidor/Dashboard', [
            'driver' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'formattedDate' => $formattedDate,
            'activeRoute' => $formattedActiveRoute,
            'availableRoutes' => $availableRoutes,
            'truckInventory' => [
                'exchange_full_available' => (int) $truckState['truck_inventory']['exchange_full_available'],
                'exchange_empty_collected' => (int) $truckState['truck_inventory']['exchange_empty_collected'],
                'total_fixed_in_truck' => $totalFixedInTruck,
                'fixed_custody' => $fixedCustody,
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
            ->where('status', BottleCustodyMovement::STATUS_ACCEPTED)
            ->sum('quantity');

        $deliveredFixed = (int) BottleCustodyMovement::where('customer_id', $customer->id)
            ->where('bottle_type', OrderItem::TYPE_FIXED)
            ->where('movement_type', BottleCustodyMovement::TYPE_DELIVERY_REFILL_TO_CUSTOMER)
            ->where('origin', 'truck')
            ->where('status', BottleCustodyMovement::STATUS_ACCEPTED)
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

            return redirect()->route('repartidor.route.index')->with('success', "Servicio directo registrado exitosamente (Folio {$order->folio}).");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['direct' => $e->getMessage()]);
        }
    }
}
