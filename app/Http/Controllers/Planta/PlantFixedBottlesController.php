<?php

namespace App\Http\Controllers\Planta;

use App\Http\Controllers\Controller;
use App\Models\BottleCustodyMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\PlantInventoryService;
use App\Services\TruckInventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlantFixedBottlesController extends Controller
{
    public function __construct(
        protected PlantInventoryService $plantInventoryService,
        protected TruckInventoryService $truckInventoryService
    ) {}

    /**
     * Pantalla unificada de Botellones Fijos del Repartidor.
     * Integra:
     * - Pestaña 1: Por recibir (cotejo físico de fijos declarados por chofer)
     * - Pestaña 2: Por llenar (lavado, llenado y sellado de fijos aptos)
     * - Pestaña 3: Listos para enviar (propuesta de fijos listos hacia el camión)
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (!$user->hasRole(['Operador Planta', 'Administrador', 'Super Admin']) && !$user->can('bottles.manage')) {
            abort(403, 'No autorizado para acceder a la gestión de botellones fijos.');
        }

        // Pestaña solicitada (por-recibir, por-llenar, listos-para-enviar)
        $tab = $request->query('tab', 'por-recibir');
        if (!in_array($tab, ['por-recibir', 'por-llenar', 'listos-para-enviar'], true)) {
            $tab = 'por-recibir';
        }

        // 1. Datos para "Por recibir"
        $receptionsData = $this->plantInventoryService->getPendingReceptions();
        $fixedPending = $receptionsData['fixed_pending'];

        // 2. Datos para "Por llenar"
        $productionState = $this->plantInventoryService->getProductionState();
        $fixedPendingPrepare = $productionState['fixed_pending_prepare'];
        $fixedInWashing = $productionState['fixed_in_washing'];
        $fixedPrepared = $productionState['fixed_prepared'];

        // 3. Datos para "Listos para enviar"
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

        // Propuestas de fijos pendientes de aceptación por el chofer
        $pendingFixedProposals = BottleCustodyMovement::with(['customer', 'user', 'order'])
            ->where('movement_type', BottleCustodyMovement::TYPE_TRUCK_LOAD_FIXED)
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

        return Inertia::render('Planta/Fijos/Index', [
            'activeTab' => $tab,
            'fixedPending' => $fixedPending,
            'fixedPendingCount' => count($fixedPending),
            'fixedPendingPrepare' => $fixedPendingPrepare,
            'fixedInWashing' => $fixedInWashing,
            'fixedPrepared' => $fixedPrepared,
            'fixedReadyToLoad' => $fixedReadyToLoad,
            'pendingFixedProposals' => $pendingFixedProposals,
            'isOperator' => $user->hasRole('Operador Planta') || $user->hasRole('Super Admin'),
            'isAdmin' => $user->hasRole('Administrador'),
        ]);
    }
}
