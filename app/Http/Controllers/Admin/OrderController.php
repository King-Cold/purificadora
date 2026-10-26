<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\OrderBusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelOrderRequest;
use App\Http\Requests\Admin\ConfirmOrderRequest;
use App\Http\Requests\Admin\StoreAdminOrderRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\StoreScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected StoreScheduleService $scheduleService
    ) {}

    /**
     * Listado global de pedidos de clientes con y sin cuenta.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Order::class);

        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', 'all');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = Order::with(['customer', 'items'])
            ->latest('id');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('folio', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('nickname', 'like', "%{$search}%")
                         ->orWhere('code', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        if (in_array($status, [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_CANCELLED], true)) {
            $query->where('status', $status);
        }

        if (!empty($dateFrom)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $orders = $query->paginate(15)->withQueryString();

        $orders->through(function (Order $order) {
            $customer = $order->customer;
            $addressSnapshot = $order->address_snapshot ?: [];

            return [
                'id' => $order->id,
                'folio' => $order->folio,
                'status' => $order->status,
                'status_label' => $order->status_label,
                'is_pending' => $order->isPending(),
                'is_confirmed' => $order->isConfirmed(),
                'is_cancelled' => $order->isCancelled(),
                'created_at' => $order->created_at->format('d/m/Y H:i'),
                'fixed_quantity' => $order->fixed_quantity,
                'exchange_quantity' => $order->exchange_quantity,
                'total_bottles' => $order->total_bottles,
                'delivery_zone_label' => $addressSnapshot['delivery_zone_label'] ?? $order->delivery_zone,
                'customer' => $customer ? [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'nickname' => $customer->nickname,
                    'code' => $customer->code,
                    'phone' => $customer->phone,
                    'has_account' => $customer->user_id !== null,
                ] : null,
            ];
        });

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Búsqueda en tiempo real de clientes sin cuenta vinculada para selección en registro.
     */
    public function searchCustomers(Request $request): JsonResponse
    {
        Gate::authorize('create', Order::class);

        $q = trim((string) $request->input('search', ''));

        $query = Customer::whereNull('user_id')
            ->with('address');

        if (!empty($q)) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('nickname', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        $customers = $query->take(20)->get()->map(function (Customer $customer) {
            $activeOrder = $this->orderService->getActiveOrderForCustomer($customer);
            $address = $customer->address;

            return [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'nickname' => $customer->nickname,
                'phone' => $customer->phone,
                'fixed_bottles_count' => (int) $customer->fixed_bottles_count,
                'exchange_bottles_count' => (int) $customer->exchange_bottles_count,
                'total_bottles' => (int) $customer->total_bottles,
                'has_address' => $address !== null && !$address->trashed(),
                'address' => $address && !$address->trashed() ? [
                    'id' => $address->id,
                    'full_address' => $address->full_address,
                    'zone_label' => $address->zone_label,
                ] : null,
                'active_order' => $activeOrder ? [
                    'id' => $activeOrder->id,
                    'folio' => $activeOrder->folio,
                    'status_label' => $activeOrder->status_label,
                ] : null,
            ];
        });

        return response()->json($customers);
    }

    /**
     * Formulario para registrar un pedido administrativo para un cliente sin cuenta.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Order::class);

        $scheduleStatus = $this->scheduleService->checkOrderReception();
        $scheduleSummary = $this->scheduleService->getScheduleSummary();

        $selectedCustomerData = null;
        if ($customerId = $request->query('customer_id')) {
            $customer = Customer::whereNull('user_id')
                ->with('address')
                ->find($customerId);

            if ($customer && !$customer->trashed()) {
                $activeOrder = $this->orderService->getActiveOrderForCustomer($customer);
                $address = $customer->address;

                $selectedCustomerData = [
                    'id' => $customer->id,
                    'code' => $customer->code,
                    'name' => $customer->name,
                    'nickname' => $customer->nickname,
                    'phone' => $customer->phone,
                    'fixed_bottles_count' => (int) $customer->fixed_bottles_count,
                    'exchange_bottles_count' => (int) $customer->exchange_bottles_count,
                    'total_bottles' => (int) $customer->total_bottles,
                    'has_address' => $address !== null && !$address->trashed(),
                    'address' => $address && !$address->trashed() ? [
                        'id' => $address->id,
                        'full_address' => $address->full_address,
                        'zone_label' => $address->zone_label,
                    ] : null,
                    'active_order' => $activeOrder ? [
                        'id' => $activeOrder->id,
                        'folio' => $activeOrder->folio,
                        'status_label' => $activeOrder->status_label,
                    ] : null,
                ];
            }
        }

        return Inertia::render('Admin/Orders/Create', [
            'scheduleStatus' => $scheduleStatus,
            'scheduleSummary' => $scheduleSummary,
            'preselectedCustomer' => $selectedCustomerData,
        ]);
    }

    /**
     * Procesa y registra un nuevo pedido administrativo.
     *
     * @throws ValidationException
     */
    public function store(StoreAdminOrderRequest $request): RedirectResponse
    {
        Gate::authorize('create', Order::class);

        try {
            $order = $this->orderService->createOrder($request->user(), [
                'customer_id' => $request->integer('customer_id'),
                'fixed_quantity' => $request->integer('fixed_quantity'),
                'exchange_quantity' => $request->integer('exchange_quantity'),
                'notes' => $request->input('notes'),
            ]);

            return redirect()->route('admin.orders.show', $order->id)
                ->with('success', "¡Pedido con folio {$order->folio} registrado exitosamente!");
        } catch (OrderBusinessRuleException $e) {
            throw ValidationException::withMessages([
                $e->getErrorKey() => [$e->getMessage()],
            ]);
        }
    }

    /**
     * Muestra el detalle administrativo de un pedido.
     */
    public function show(Request $request, Order $order): Response
    {
        Gate::authorize('view', $order);

        $order->load(['items', 'customer.address', 'creator.roles', 'statusHistories.user.roles']);

        $customer = $order->customer;
        $addressSnapshot = $order->address_snapshot ?: [];

        $historicalAddress = [
            'recipient_name' => $order->recipient_name,
            'recipient_phone' => $order->recipient_phone,
            'street' => $order->delivery_street,
            'exterior_number' => $order->delivery_exterior_number,
            'interior_number' => $order->delivery_interior_number,
            'neighborhood' => $order->delivery_neighborhood,
            'city' => $order->delivery_city ?: 'Hocabá',
            'state' => $order->delivery_state ?: 'Yucatán',
            'postal_code' => $order->delivery_postal_code,
            'between_streets' => $order->delivery_between_streets,
            'reference' => $order->delivery_reference,
            'zone' => $order->delivery_zone,
            'zone_label' => $addressSnapshot['delivery_zone_label'] ?? $order->delivery_zone,
            'full_address' => $order->full_delivery_address,
        ];

        $history = $order->statusHistories->map(function ($h) {
            return [
                'id' => $h->id,
                'previous_status' => $h->previous_status,
                'previous_status_label' => $h->previous_status ? (Order::STATUSES[$h->previous_status] ?? $h->previous_status) : null,
                'new_status' => $h->new_status,
                'new_status_label' => Order::STATUSES[$h->new_status] ?? $h->new_status,
                'user_name' => $h->user?->name ?? 'Sistema',
                'user_role' => $h->user?->roles->first()?->name ?? 'Usuario',
                'reason' => $h->reason,
                'created_at' => $h->created_at->format('d/m/Y H:i'),
            ];
        });

        $creator = $order->creator ? [
            'name' => $order->creator->name,
            'role' => $order->creator->roles->first()?->name ?? 'Usuario',
        ] : null;

        $cancellationReason = null;
        if ($order->isCancelled()) {
            $cancelledHistory = $order->statusHistories->last(fn ($h) => $h->new_status === Order::STATUS_CANCELLED);
            $cancellationReason = $cancelledHistory?->reason ?? $order->notes;
        }

        return Inertia::render('Admin/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'folio' => $order->folio,
                'status' => $order->status,
                'status_label' => $order->status_label,
                'is_pending' => $order->isPending(),
                'is_confirmed' => $order->isConfirmed(),
                'is_cancelled' => $order->isCancelled(),
                'can_confirm' => Gate::forUser($request->user())->allows('confirm', $order),
                'can_cancel' => Gate::forUser($request->user())->allows('cancel', $order),
                'fixed_quantity' => $order->fixed_quantity,
                'exchange_quantity' => $order->exchange_quantity,
                'total_bottles' => $order->total_bottles,
                'notes' => $order->notes,
                'created_at' => $order->created_at->format('d/m/Y H:i'),
                'cancellation_reason' => $cancellationReason,
                'address' => $historicalAddress,
                'history' => $history,
                'creator' => $creator,
                'customer' => $customer ? [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'nickname' => $customer->nickname,
                    'code' => $customer->code,
                    'phone' => $customer->phone,
                    'has_account' => $customer->user_id !== null,
                ] : null,
            ],
        ]);
    }

    /**
     * Confirma un pedido en estado Pendiente.
     */
    public function confirm(ConfirmOrderRequest $request, Order $order): RedirectResponse
    {
        Gate::authorize('confirm', $order);

        try {
            $this->orderService->confirmOrder($order, $request->user(), $request->input('comment'));

            return redirect()->route('admin.orders.show', $order->id)
                ->with('success', "El pedido {$order->folio} ha sido confirmado exitosamente.");
        } catch (OrderBusinessRuleException|InvalidOrderTransitionException $e) {
            return redirect()->route('admin.orders.show', $order->id)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Cancela un pedido en estado Pendiente o Confirmado registrando motivo obligatorio.
     */
    public function cancel(CancelOrderRequest $request, Order $order): RedirectResponse
    {
        Gate::authorize('cancel', $order);

        $reason = (string) $request->input('reason');

        try {
            $this->orderService->cancelOrder($order, $request->user(), $reason);

            return redirect()->route('admin.orders.show', $order->id)
                ->with('success', "El pedido {$order->folio} ha sido cancelado exitosamente.");
        } catch (OrderBusinessRuleException|InvalidOrderTransitionException $e) {
            return redirect()->route('admin.orders.show', $order->id)
                ->with('error', $e->getMessage());
        }
    }
}
