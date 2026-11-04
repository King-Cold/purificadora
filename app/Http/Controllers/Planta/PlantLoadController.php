<?php

namespace App\Http\Controllers\Planta;

use App\Exceptions\OrderBusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\BottleCustodyMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlantInventory;
use App\Services\PlantInventoryService;
use App\Services\TruckInventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlantLoadController extends Controller
{
    public function __construct(
        protected PlantInventoryService $plantInventoryService,
        protected TruckInventoryService $truckInventoryService
    ) {}

    /**
     * Asegura que el usuario tenga rol de Operador de Planta para preparar y proponer cargas.
     */
    protected function ensureIsPhysicalOperator(Request $request): void
    {
        $user = $request->user();

        if ($user->hasRole('Administrador')) {
            abort(403, 'El Administrador puede consultar y supervisar las cargas, pero la preparación y propuesta física corresponde exclusivamente al Operador de Planta.');
        }

        if (!$user->hasRole('Operador Planta') && !$user->hasRole('Super Admin')) {
            abort(403, 'Solo el Operador de Planta puede proponer cargas al camión.');
        }
    }

    /**
     * Pantalla: Cargas al Repartidor.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (!$user->hasRole('Operador Planta') && !$user->hasRole('Administrador') && !$user->can('bottles.manage')) {
            abort(403, 'No autorizado para acceder a la gestión de cargas.');
        }

        $inv = $this->plantInventoryService->getOrCreatePlantInventory();

        // Fijos preparados listos para proponer en carga
        $fixedReadyToLoad = OrderItem::with(['order.customer'])
            ->whereHas('order', function ($q) {
                $q->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_DELIVERED]);
            })
            ->where('prepared_quantity', '>', 0)
            ->get()
            ->map(function ($item) {
                $readyQty = $item->getReadyToLoadQuantity();
                return [
                    'item_id' => $item->id,
                    'order_id' => $item->order_id,
                    'order_folio' => $item->order ? $item->order->folio : "ORD-{$item->order_id}",
                    'customer_id' => $item->order?->customer_id,
                    'customer_name' => $item->order?->customer?->name ?? 'Cliente',
                    'customer_nickname' => $item->order?->customer?->nickname,
                    'customer_code' => $item->order?->customer?->code,
                    'presentation' => $item->presentation,
                    'prepared_quantity' => (int)$item->prepared_quantity,
                    'loaded_quantity' => (int)$item->loaded_quantity,
                    'reserved_quantity' => (int)$item->reserved_quantity,
                    'ready_to_load_quantity' => $readyQty,
                    'status' => $item->status,
                ];
            })
            ->filter(fn ($i) => $i['ready_to_load_quantity'] > 0)
            ->values();

        // Propuestas de carga pendientes de aceptación por el chofer
        $pendingProposals = BottleCustodyMovement::with(['customer', 'user', 'order'])
            ->whereIn('movement_type', [
                BottleCustodyMovement::TYPE_TRUCK_LOAD_EXCHANGE,
                BottleCustodyMovement::TYPE_TRUCK_LOAD_FIXED,
            ])
            ->where('status', BottleCustodyMovement::STATUS_PROPOSED)
            ->where('destination', 'truck')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($m) {
                return [
                    'id' => $m->id,
                    'order_id' => $m->order_id,
                    'order_item_id' => $m->order_item_id,
                    'order_folio' => $m->order?->folio,
                    'customer_id' => $m->customer_id,
                    'customer_name' => $m->customer?->name,
                    'customer_nickname' => $m->customer?->nickname,
                    'customer_code' => $m->customer?->code,
                    'bottle_type' => $m->bottle_type,
                    'quantity' => (int)$m->quantity,
                    'notes' => $m->notes,
                    'initiator_name' => $m->user?->name ?? 'Operador',
                    'created_at' => $m->created_at ? date('d/m/Y H:i', strtotime($m->created_at)) : null,
                ];
            });

        // Historial de transferencias procesadas (aceptadas, rechazadas o retiradas)
        $transferHistory = BottleCustodyMovement::with(['customer', 'user', 'confirmedByUser', 'order'])
            ->whereIn('movement_type', [
                BottleCustodyMovement::TYPE_TRUCK_LOAD_EXCHANGE,
                BottleCustodyMovement::TYPE_TRUCK_LOAD_FIXED,
            ])
            ->whereIn('status', [
                BottleCustodyMovement::STATUS_ACCEPTED,
                'accepted_with_discrepancy',
                BottleCustodyMovement::STATUS_REJECTED,
                BottleCustodyMovement::STATUS_CANCELLED,
            ])
            ->where('destination', 'truck')
            ->orderBy('id', 'desc')
            ->limit(30)
            ->get()
            ->map(function ($m) {
                return [
                    'id' => $m->id,
                    'order_folio' => $m->order?->folio,
                    'customer_name' => $m->customer?->nickname ?: $m->customer?->name,
                    'customer_code' => $m->customer?->code,
                    'bottle_type' => $m->bottle_type,
                    'proposed_quantity' => (int)$m->proposed_quantity,
                    'accepted_quantity' => (int)$m->quantity,
                    'status' => $m->status,
                    'difference_reason' => $m->difference_reason,
                    'notes' => $m->notes,
                    'initiator_name' => $m->user?->name ?? 'Operador',
                    'driver_name' => $m->confirmedByUser?->name ?? 'Repartidor',
                    'created_at' => $m->created_at ? date('d/m/Y H:i', strtotime($m->created_at)) : null,
                    'confirmed_at' => $m->confirmed_at ? date('d/m/Y H:i', strtotime($m->confirmed_at)) : null,
                ];
            });

        return Inertia::render('Planta/Loads/Index', [
            'balances' => [
                'exchange_full_available' => (int)$inv->exchange_full_available,
                'exchange_full_reserved' => (int)$inv->exchange_full_reserved,
            ],
            'fixed_ready_to_load' => $fixedReadyToLoad,
            'pending_proposals' => $pendingProposals,
            'transfer_history' => $transferHistory,
            'isOperator' => $user->hasRole('Operador Planta') || $user->hasRole('Super Admin'),
            'isAdmin' => $user->hasRole('Administrador'),
        ]);
    }

    /**
     * Propone una carga hacia la unidad del Repartidor.
     */
    public function store(Request $request)
    {
        $this->ensureIsPhysicalOperator($request);

        $validated = $request->validate([
            'bottle_type' => ['required', 'in:cambio,fijo'],
            'quantity' => ['required', 'integer', 'min:1'],
            'order_item_id' => ['nullable', 'required_if:bottle_type,fijo', 'exists:order_items,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $orderItemId = isset($validated['order_item_id']) ? (int)$validated['order_item_id'] : null;

            $this->truckInventoryService->proposePlantToTruckTransfer(
                $request->user(),
                $validated['bottle_type'],
                (int)$validated['quantity'],
                null,
                null,
                $validated['notes'] ?? null,
                $orderItemId
            );

            return back()->with('success', 'Propuesta de carga enviada al repartidor para su confirmación física.');
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['load' => $e->getMessage()]);
        }
    }

    /**
     * Retira una propuesta de carga antes de su confirmación física por el repartidor.
     */
    public function withdraw(Request $request, BottleCustodyMovement $movement)
    {
        $this->ensureIsPhysicalOperator($request);

        $validated = $request->validate([
            'retained_quantity' => ['nullable', 'integer', 'min:0'],
            'damaged_quantity' => ['nullable', 'integer', 'min:0'],
            'damage_type' => ['nullable', 'string', 'max:50'],
            'damage_notes' => ['nullable', 'string', 'max:255'],
            'unlocated_quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $this->truckInventoryService->withdrawTransferProposal(
                $movement,
                $request->user(),
                (int) ($validated['retained_quantity'] ?? 0),
                (int) ($validated['damaged_quantity'] ?? 0),
                $validated['damage_type'] ?? null,
                $validated['damage_notes'] ?? null,
                (int) ($validated['unlocated_quantity'] ?? 0)
            );

            return back()->with('success', 'Propuesta de carga retirada y reservas conciliadas correctamente.');
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['withdraw' => $e->getMessage()]);
        }
    }
}

