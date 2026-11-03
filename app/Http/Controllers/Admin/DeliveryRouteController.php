<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\OrderBusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\DeliveryRoute;
use App\Models\RouteStop;
use App\Services\DeliveryRouteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryRouteController extends Controller
{
    public function __construct(
        protected DeliveryRouteService $routeService
    ) {}

    /**
     * Listado general de hojas de ruta.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', DeliveryRoute::class);

        $query = DeliveryRoute::with(['driver', 'stops'])
            ->withCount('stops');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date')) {
            $query->whereDate('date', $request->input('date'));
        }

        $routes = $query->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($route) => [
                'id' => $route->id,
                'folio' => $route->folio,
                'date' => $route->date?->format('Y-m-d'),
                'status' => $route->status,
                'status_label' => match ($route->status) {
                    DeliveryRoute::STATUS_ASSIGNED => 'Asignada',
                    DeliveryRoute::STATUS_IN_PROGRESS => 'En curso',
                    DeliveryRoute::STATUS_COMPLETED => 'Completada',
                    DeliveryRoute::STATUS_CANCELLED => 'Cancelada',
                    DeliveryRoute::STATUS_INCIDENT => 'Avería / Incidencia',
                    default => ucfirst($route->status),
                },
                'driver_name' => $route->driver?->name ?? 'Sin chofer',
                'stops_count' => $route->stops_count,
                'pending_stops_count' => $route->stops->where('status', RouteStop::STATUS_PENDING)->count(),
                'completed_stops_count' => $route->stops->where('status', RouteStop::STATUS_COMPLETED)->count(),
                'failed_stops_count' => $route->stops->where('status', RouteStop::STATUS_FAILED)->count(),
                'started_at' => $route->started_at?->format('Y-m-d H:i'),
                'completed_at' => $route->completed_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Admin/DeliveryRoutes/Index', [
            'routes' => $routes,
            'filters' => [
                'status' => $request->input('status', ''),
                'date' => $request->input('date', ''),
            ],
        ]);
    }

    /**
     * Pantalla de creación y armado de ruta con pedidos elegibles y ordenamiento manual.
     */
    public function create(): Response
    {
        Gate::authorize('create', DeliveryRoute::class);

        $driver = null;
        $configError = null;

        try {
            $soleDriver = $this->routeService->resolveSoleActiveDriver();
            $driver = [
                'id' => $soleDriver->id,
                'name' => $soleDriver->name,
                'email' => $soleDriver->email,
            ];
        } catch (OrderBusinessRuleException $e) {
            $configError = $e->getMessage();
        }

        $eligibleOrders = $this->routeService->getEligibleOrdersForRoute();

        return Inertia::render('Admin/DeliveryRoutes/Create', [
            'driver' => $driver,
            'configError' => $configError,
            'eligibleOrders' => $eligibleOrders,
            'defaultDate' => now()->toDateString(),
        ]);
    }

    /**
     * Guarda la nueva hoja de ruta vinculándola automáticamente al único repartidor.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', DeliveryRoute::class);

        $validated = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['required', 'integer', 'exists:orders,id'],
            'date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $route = $this->routeService->createRoute(
                $request->user(),
                null,
                $validated['date'] ?? now(),
                $validated['order_ids'],
                $validated['notes'] ?? null
            );

            return redirect()->route('admin.routes.show', $route->id)
                ->with('success', "Hoja de ruta {$route->folio} creada con éxito.");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['route' => $e->getMessage()]);
        }
    }

    /**
     * Detalle completo de la hoja de ruta administrativa.
     */
    public function show(DeliveryRoute $route): Response
    {
        Gate::authorize('view', $route);

        $route->load([
            'driver',
            'stops' => fn ($q) => $q->orderBy('stop_number', 'asc')->with([
                'order.customer',
                'order.items',
            ]),
        ]);

        $stops = $route->stops->map(function ($stop) {
            $order = $stop->order;
            $customer = $order?->customer;

            $exchangeItem = $order?->items->firstWhere('type', 'cambio');
            $fixedItem = $order?->items->firstWhere('type', 'fijo');

            $exchangeQty = (int) ($exchangeItem?->quantity ?? 0);
            $fixedQty = (int) ($fixedItem?->quantity ?? 0);

            // Operación
            $opType = 'intercambio';
            $opLabel = 'Intercambio de botellones de cambio';

            if ($exchangeQty > 0 && $fixedQty > 0) {
                $opType = 'mixto';
                $opLabel = 'Operación mixta (Cambio + Fijos)';
            } elseif ($fixedQty > 0) {
                if ($fixedItem->prepared_quantity > 0) {
                    $opType = 'devolucion_fijos';
                    $opLabel = 'Devolución de fijos preparados';
                } else {
                    $opType = 'recoleccion_fijos';
                    $opLabel = 'Recolección de fijos vacíos';
                }
            }

            $address = $order?->delivery_street 
                ? ($order->delivery_street . ($order->delivery_exterior_number ? ' #' . $order->delivery_exterior_number : '') . ($order->delivery_neighborhood ? ', ' . $order->delivery_neighborhood : ''))
                : 'Sin ubicación registrada';

            return [
                'id' => $stop->id,
                'stop_number' => $stop->stop_number,
                'status' => $stop->status,
                'status_label' => match ($stop->status) {
                    RouteStop::STATUS_PENDING => 'Pendiente',
                    RouteStop::STATUS_COMPLETED => 'Completada',
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
                'customer_phone' => $order?->recipient_phone ?: $customer?->phone,
                'delivery_zone' => $order?->delivery_zone ?: 'Sin zona',
                'address_summary' => $address,
                'operation_type' => $opType,
                'operation_label' => $opLabel,
                'exchange_quantity' => $exchangeQty,
                'fixed_quantity' => $fixedQty,
                'total_bottles' => $exchangeQty + $fixedQty,
            ];
        });

        return Inertia::render('Admin/DeliveryRoutes/Show', [
            'route' => [
                'id' => $route->id,
                'folio' => $route->folio,
                'date' => $route->date?->format('Y-m-d'),
                'status' => $route->status,
                'status_label' => match ($route->status) {
                    DeliveryRoute::STATUS_ASSIGNED => 'Asignada',
                    DeliveryRoute::STATUS_IN_PROGRESS => 'En curso',
                    DeliveryRoute::STATUS_COMPLETED => 'Completada',
                    DeliveryRoute::STATUS_CANCELLED => 'Cancelada',
                    DeliveryRoute::STATUS_INCIDENT => 'Avería / Incidencia',
                    default => ucfirst($route->status),
                },
                'notes' => $route->notes,
                'started_at' => $route->started_at?->format('Y-m-d H:i'),
                'completed_at' => $route->completed_at?->format('Y-m-d H:i'),
                'driver' => [
                    'id' => $route->driver?->id,
                    'name' => $route->driver?->name,
                    'email' => $route->driver?->email,
                ],
                'stops' => $stops,
            ],
        ]);
    }

    /**
     * Reanuda una ruta que se encontraba en avería o incidencia.
     */
    public function resume(DeliveryRoute $route, Request $request): RedirectResponse
    {
        Gate::authorize('deliver', $route);

        try {
            $this->routeService->resumeRoute($route, $request->user(), $request->input('notes'));
            return back()->with('success', 'Ruta reanudada correctamente tras resolver la incidencia.');
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['route' => $e->getMessage()]);
        }
    }
}
