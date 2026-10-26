<?php

namespace App\Http\Controllers\Cliente;

use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\OrderBusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cliente\CancelClientOrderRequest;
use App\Http\Requests\Cliente\StoreClientOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\StoreScheduleService;
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
     * Listado paginado de pedidos del cliente autenticado.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $customer = $user?->customer;

        if (!$customer) {
            abort(403, 'No tienes un perfil de cliente asociado.');
        }

        $status = $request->query('status', 'all');

        $query = Order::where('customer_id', $customer->id)
            ->with(['items'])
            ->latest('id');

        if (in_array($status, [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_CANCELLED], true)) {
            $query->where('status', $status);
        }

        $activeOrder = $this->orderService->getActiveOrderForCustomer($customer);
        $activeOrderId = $activeOrder?->id;

        $orders = $query->paginate(10)->withQueryString();

        $orders->through(function (Order $order) use ($activeOrderId) {
            $addressSnapshot = $order->address_snapshot ?: [];

            return [
                'id' => $order->id,
                'folio' => $order->folio,
                'status' => $order->status,
                'status_label' => $order->status_label,
                'is_active' => $order->id === $activeOrderId,
                'fixed_quantity' => $order->fixed_quantity,
                'exchange_quantity' => $order->exchange_quantity,
                'total_bottles' => $order->total_bottles,
                'delivery_street' => $order->delivery_street,
                'delivery_zone_label' => $addressSnapshot['delivery_zone_label'] ?? $order->delivery_zone,
                'created_at' => $order->created_at->format('d/m/Y H:i'),
                'can_cancel' => $order->isPending(),
            ];
        });

        return Inertia::render('Cliente/Orders/Index', [
            'orders' => $orders,
            'filters' => [
                'status' => $status,
            ],
            'activeOrder' => $activeOrder ? [
                'id' => $activeOrder->id,
                'folio' => $activeOrder->folio,
                'status' => $activeOrder->status,
                'status_label' => $activeOrder->status_label,
                'created_at' => $activeOrder->created_at->format('d/m/Y H:i'),
                'total_bottles' => $activeOrder->total_bottles,
            ] : null,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'nickname' => $customer->nickname,
                'code' => $customer->code,
            ],
        ]);
    }

    /**
     * Muestra la pantalla para registrar un nuevo pedido a domicilio.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();
        $customer = $user?->customer;
        $address = $customer?->address;

        $activeOrder = $customer ? $this->orderService->getActiveOrderForCustomer($customer) : null;
        $scheduleStatus = $this->scheduleService->checkOrderReception();
        $scheduleSummary = $this->scheduleService->getScheduleSummary();

        $addressData = null;
        if ($address && !$address->trashed()) {
            $addressData = [
                'id' => $address->id,
                'street' => $address->street,
                'exterior_number' => $address->exterior_number,
                'interior_number' => $address->interior_number,
                'neighborhood' => $address->neighborhood,
                'city' => $address->city ?: 'Hocabá',
                'delivery_zone' => $address->delivery_zone,
                'zone_label' => $address->zone_label ?: 'Sin zona asignada',
                'between_streets' => $address->between_streets,
                'reference' => $address->reference,
                'full_address' => $address->full_address,
            ];
        }

        $customerData = null;
        if ($customer && !$customer->trashed()) {
            $customerData = [
                'id' => $customer->id,
                'name' => $customer->name,
                'nickname' => $customer->nickname,
                'code' => $customer->code,
                'fixed_bottles_count' => (int) $customer->fixed_bottles_count,
                'exchange_bottles_count' => (int) $customer->exchange_bottles_count,
                'total_bottles' => (int) $customer->total_bottles,
            ];
        }

        $activeOrderData = null;
        if ($activeOrder) {
            $activeOrderData = [
                'id' => $activeOrder->id,
                'folio' => $activeOrder->folio,
                'status' => $activeOrder->status,
                'status_label' => $activeOrder->status_label,
                'created_at' => $activeOrder->created_at->format('d/m/Y H:i'),
                'total_bottles' => $activeOrder->total_bottles,
            ];
        }

        $blocks = [
            'no_customer' => is_null($customerData),
            'no_bottles' => !is_null($customerData) && ($customerData['total_bottles'] <= 0),
            'no_address' => is_null($addressData),
            'has_active_order' => !is_null($activeOrderData),
            'outside_schedule' => !$scheduleStatus['can_receive'],
        ];

        $canOrder = !in_array(true, $blocks, true);

        return Inertia::render('Cliente/Orders/Create', [
            'customer' => $customerData,
            'address' => $addressData,
            'activeOrder' => $activeOrderData,
            'scheduleStatus' => $scheduleStatus,
            'scheduleSummary' => $scheduleSummary,
            'blocks' => $blocks,
            'canOrder' => $canOrder,
        ]);
    }

    /**
     * Procesa y registra un nuevo pedido a domicilio.
     * Al guardar, redirige al detalle del pedido creado conservando la confirmación con folio.
     *
     * @throws ValidationException
     */
    public function store(StoreClientOrderRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Verificación de política de pedidos
        Gate::authorize('create', Order::class);

        try {
            $order = $this->orderService->createOrder($user, [
                'fixed_quantity' => $request->integer('fixed_quantity'),
                'exchange_quantity' => $request->integer('exchange_quantity'),
                'notes' => $request->input('notes'),
            ]);

            return redirect()->route('cliente.orders.show', $order->id)
                ->with('success', "¡Tu pedido con folio {$order->folio} fue registrado exitosamente! En breve comenzará su preparación.");
        } catch (OrderBusinessRuleException $e) {
            // Convierte excepciones de reglas comerciales a errores legibles en Inertia
            throw ValidationException::withMessages([
                $e->getErrorKey() => [$e->getMessage()],
            ]);
        }
    }

    /**
     * Muestra el detalle completo de un pedido del cliente con su histórico inmutable.
     */
    public function show(Request $request, Order $order): Response
    {
        // Autorización estricta por política (únicamente pedidos propios del cliente)
        Gate::authorize('view', $order);

        $order->load(['items', 'statusHistories']);

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

        // Historial cronológico con etiquetas en español y sin datos innecesarios de personal
        $history = $order->statusHistories->map(function ($h) {
            return [
                'id' => $h->id,
                'previous_status' => $h->previous_status,
                'previous_status_label' => $h->previous_status ? (Order::STATUSES[$h->previous_status] ?? $h->previous_status) : null,
                'new_status' => $h->new_status,
                'new_status_label' => Order::STATUSES[$h->new_status] ?? $h->new_status,
                'reason' => $h->reason,
                'created_at' => $h->created_at->format('d/m/Y H:i'),
            ];
        });

        // Motivo de cancelación si el pedido está cancelado
        $cancellationReason = null;
        if ($order->isCancelled()) {
            $cancelledHistory = $order->statusHistories->last(fn ($h) => $h->new_status === Order::STATUS_CANCELLED);
            $cancellationReason = $cancelledHistory?->reason ?? $order->notes;
        }

        $canCancel = Gate::forUser($request->user())->allows('cancel', $order);

        return Inertia::render('Cliente/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'folio' => $order->folio,
                'status' => $order->status,
                'status_label' => $order->status_label,
                'is_pending' => $order->isPending(),
                'is_confirmed' => $order->isConfirmed(),
                'is_cancelled' => $order->isCancelled(),
                'fixed_quantity' => $order->fixed_quantity,
                'exchange_quantity' => $order->exchange_quantity,
                'total_bottles' => $order->total_bottles,
                'notes' => $order->notes,
                'created_at' => $order->created_at->format('d/m/Y H:i'),
                'cancellation_reason' => $cancellationReason,
                'address' => $historicalAddress,
                'history' => $history,
                'can_cancel' => $canCancel,
            ],
        ]);
    }

    /**
     * Cancela un pedido pendiente del cliente registrando el motivo obligatorio.
     */
    public function cancel(CancelClientOrderRequest $request, Order $order): RedirectResponse
    {
        // Autorización estricta por política
        Gate::authorize('cancel', $order);

        $reason = (string) $request->input('reason');

        try {
            $this->orderService->cancelOrder($order, $request->user(), $reason);

            return redirect()->route('cliente.orders.show', $order->id)
                ->with('success', "El pedido con folio {$order->folio} ha sido cancelado exitosamente.");
        } catch (OrderBusinessRuleException|InvalidOrderTransitionException $e) {
            return redirect()->route('cliente.orders.show', $order->id)
                ->with('error', $e->getMessage());
        }
    }
}
