<?php

namespace App\Http\Controllers\Repartidor;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DeliveryRoute;
use App\Models\RouteStop;
use App\Services\DebtAndCreditService;
use App\Services\DeliveryRouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryOperationController extends Controller
{
    public function __construct(
        protected DeliveryRouteService $deliveryRouteService,
        protected DebtAndCreditService $debtService
    ) {}

    /**
     * Búsqueda ágil de clientes en ruta por código, nombre, apodo o teléfono.
     */
    public function searchCustomers(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $termLower = mb_strtolower($term, 'UTF-8');

        $orderService = app(\App\Services\OrderService::class);
        $promoService = app(\App\Services\WeeklyPromotionService::class);

        $customers = Customer::query()
            ->where(function ($q) use ($termLower) {
                $q->whereRaw('LOWER(code) LIKE ?', ["%{$termLower}%"])
                    ->orWhereRaw('LOWER(name) LIKE ?', ["%{$termLower}%"])
                    ->orWhereRaw('LOWER(nickname) LIKE ?', ["%{$termLower}%"])
                    ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$termLower}%"]);
            })
            ->with('address')
            ->limit(10)
            ->get()
            ->map(function (Customer $customer) use ($orderService, $promoService) {
                $trafficLight = $this->debtService->getTrafficLight($customer);
                $activeOrder = $orderService->getActiveOrderForCustomer($customer);
                $weeklyPromo = $promoService->getWeeklyProgress($customer);

                return [
                    'id' => $customer->id,
                    'code' => $customer->code,
                    'name' => $customer->name,
                    'nickname' => $customer->nickname,
                    'phone' => $customer->phone,
                    'fixed_bottles_count' => (int) $customer->fixed_bottles_count,
                    'exchange_bottles_count' => (int) $customer->exchange_bottles_count,
                    'address' => $customer->address ? [
                        'street' => $customer->address->street,
                        'exterior_number' => $customer->address->exterior_number,
                        'zone_label' => $customer->address->zone_label,
                        'reference' => $customer->address->reference,
                    ] : null,
                    'traffic_light' => $trafficLight,
                    'active_order' => $activeOrder ? [
                        'id' => $activeOrder->id,
                        'folio' => $activeOrder->folio,
                        'status' => $activeOrder->status,
                        'status_label' => $activeOrder->status_label,
                        'stop_id' => $activeOrder->stops()->whereHas('deliveryRoute', fn ($r) => $r->where('status', \App\Models\DeliveryRoute::STATUS_IN_PROGRESS))->first()?->id
                            ?: $activeOrder->stops()->first()?->id,
                        'is_fixed' => $activeOrder->items->contains('type', \App\Models\OrderItem::TYPE_FIXED),
                        'is_exchange' => $activeOrder->items->contains('type', \App\Models\OrderItem::TYPE_EXCHANGE),
                    ] : null,
                    'weekly_promo' => $weeklyPromo,
                    'pending_debt' => [
                        'bottles' => $this->debtService->getPendingDebtBottles($customer),
                        'amount' => $this->debtService->getPendingDebtAmount($customer),
                    ],
                ];
            });

        return response()->json($customers);
    }

    /**
     * Obtiene el semáforo y saldo de un cliente.
     */
    public function trafficLight(Customer $customer): JsonResponse
    {
        return response()->json($this->debtService->getTrafficLight($customer));
    }

    /**
     * Registra un cliente nuevo sin cuenta durante la ruta.
     */
    public function storeCustomer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nickname' => ['required', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:25'],
            'fixed_bottles_count' => ['required', 'integer', 'min:0'],
            'exchange_bottles_count' => ['required', 'integer', 'min:0'],
            'street' => ['nullable', 'string', 'max:150'],
            'exterior_number' => ['nullable', 'string', 'max:20'],
            'neighborhood' => ['nullable', 'string', 'max:100'],
            'delivery_zone' => ['nullable', 'string', 'in:north,south,east,west'],
            'between_streets' => ['nullable', 'string', 'max:200'],
            'reference' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $customer = $this->deliveryRouteService->registerDirectRouteCustomer($request->user(), $validated);

        return response()->json([
            'message' => 'Cliente registrado exitosamente.',
            'customer' => $customer,
        ], 201);
    }

    /**
     * Registra un servicio directo en ruta.
     */
    public function storeDirectOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'fixed_quantity' => ['nullable', 'integer', 'min:0'],
            'exchange_quantity' => ['nullable', 'integer', 'min:0'],
            'exchange_empty_received' => ['nullable', 'integer', 'min:0'],
            'units_paid_cash' => ['nullable', 'integer', 'min:0'],
            'delivery_route_id' => ['nullable', 'exists:delivery_routes,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);
        $route = !empty($validated['delivery_route_id']) ? DeliveryRoute::find($validated['delivery_route_id']) : null;

        $order = $this->deliveryRouteService->registerDirectRouteOrder($request->user(), $customer, $validated, $route);

        return response()->json([
            'message' => 'Servicio en ruta registrado exitosamente.',
            'order' => $order,
        ], 201);
    }

    /**
     * Atiende con éxito una parada de ruta.
     */
    public function completeStop(Request $request, RouteStop $stop): JsonResponse
    {
        $validated = $request->validate([
            'exchange_delivered' => ['nullable', 'integer', 'min:0'],
            'exchange_empty_received' => ['nullable', 'integer', 'min:0'],
            'fixed_collected' => ['nullable', 'integer', 'min:0'],
            'fixed_delivered' => ['nullable', 'integer', 'min:0'],
            'units_paid_cash' => ['nullable', 'integer', 'min:0'],
            'cash_tendered' => ['nullable', 'numeric', 'min:0'],
            'cancellation_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->deliveryRouteService->recordStopCompleted($stop, $validated, $request->user());

        return response()->json(['message' => 'Parada completada exitosamente.']);
    }

    /**
     * Registra una parada fallida.
     */
    public function failStop(Request $request, RouteStop $stop): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->deliveryRouteService->recordStopFailed($stop, $validated['reason'], $validated['notes'] ?? null, $request->user());

        return response()->json(['message' => 'Incidencia de parada registrada exitosamente.']);
    }
}

