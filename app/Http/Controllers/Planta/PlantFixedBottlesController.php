<?php

namespace App\Http\Controllers\Planta;

use App\Exceptions\OrderBusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\BottleCustodyMovement;
use App\Models\BottleIncident;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\PlantInventoryService;
use App\Services\TruckInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlantFixedBottlesController extends Controller
{
    public function __construct(
        protected PlantInventoryService $plantInventoryService,
        protected TruckInventoryService $truckInventoryService
    ) {}

    /**
     * Pantalla unificada de Botellones Fijos del Repartidor en Planta.
     * Tablas compactas:
     * - Pestaña 1: Por recibir (cotejo físico de fijos que llegaron de ruta)
     * - Pestaña 2: Por llenar (lavado y llenado físico de fijos aptos)
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (!$user->hasRole(['Operador Planta', 'Administrador', 'Super Admin']) && !$user->can('bottles.manage')) {
            abort(403, 'No autorizado para acceder a la gestión de botellones fijos.');
        }

        $tab = $request->query('tab', 'por-recibir');
        if (!in_array($tab, ['por-recibir', 'por-llenar'], true)) {
            $tab = 'por-recibir';
        }

        // 1. Datos para "Por recibir": fijos recogidos en camión pendientes de recepcionar
        $fixedPendingItems = OrderItem::with(['order.customer', 'order.deliveryRoute.driver'])
            ->where('type', OrderItem::TYPE_FIXED)
            ->where('collected_quantity', '>', 0)
            ->whereColumn('collected_quantity', '>', 'received_at_plant_quantity')
            ->get();

        $fixedPending = [];
        foreach ($fixedPendingItems as $item) {
            $order = $item->order;
            $customer = $order?->customer;
            if (!$customer) {
                continue;
            }

            $pendingToReceive = max(0, (int)$item->collected_quantity - (int)$item->received_at_plant_quantity - (int)$item->damaged_quantity);
            if ($pendingToReceive <= 0) {
                continue;
            }

            $fixedPending[] = [
                'order_item_id' => $item->id,
                'order_id' => $order->id,
                'order_folio' => $order->folio,
                'customer_id' => $customer->id,
                'customer_code' => $customer->code ?? "CLI-{$customer->id}",
                'customer_nickname' => $customer->nickname ?? '',
                'customer_name' => $customer->name ?? '',
                'expected_quantity' => $pendingToReceive,
                'collected_quantity' => (int) $item->collected_quantity,
                'received_at_plant_quantity' => (int) $item->received_at_plant_quantity,
                'damaged_quantity' => (int) $item->damaged_quantity,
                'status' => $item->status,
                'driver_name' => $order->deliveryRoute?->driver?->name ?? 'Repartidor',
                'created_at' => $order->created_at?->format('d/m/Y H:i'),
            ];
        }

        // 2. Datos para "Por llenar": fijos recibidos aptos en planta pendientes de llenarse
        $fixedToFillItems = OrderItem::with(['order.customer'])
            ->where('type', OrderItem::TYPE_FIXED)
            ->where('received_at_plant_quantity', '>', 0)
            ->whereRaw('(received_at_plant_quantity - prepared_quantity - damaged_quantity) > 0')
            ->whereHas('order', function ($q) {
                $q->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_DELIVERED]);
            })
            ->get();

        $fixedToFill = [];
        foreach ($fixedToFillItems as $item) {
            $order = $item->order;
            $customer = $order?->customer;
            if (!$customer) {
                continue;
            }

            $pendingToFill = max(0, (int)$item->received_at_plant_quantity - (int)$item->prepared_quantity - (int)$item->damaged_quantity);
            if ($pendingToFill <= 0) {
                continue;
            }

            $fixedToFill[] = [
                'order_item_id' => $item->id,
                'order_id' => $order->id,
                'order_folio' => $order->folio,
                'customer_id' => $customer->id,
                'customer_code' => $customer->code ?? "CLI-{$customer->id}",
                'customer_nickname' => $customer->nickname ?? '',
                'customer_name' => $customer->name ?? '',
                'pending_quantity' => $pendingToFill,
                'received_total' => (int) $item->received_at_plant_quantity,
                'prepared_total' => (int) $item->prepared_quantity,
                'damaged_total' => (int) $item->damaged_quantity,
                'status' => $item->status,
            ];
        }

        return Inertia::render('Planta/Fijos/Index', [
            'activeTab' => $tab,
            'fixedPending' => $fixedPending,
            'fixedPendingCount' => count($fixedPending),
            'fixedToFill' => $fixedToFill,
            'fixedToFillCount' => count($fixedToFill),
            // Compatibilidad hacia atrás para pruebas legacy
            'fixedPendingPrepare' => $fixedToFill,
            'fixedInWashing' => [],
            'fixedReadyToLoad' => [],
            'isOperator' => $user->hasRole('Operador Planta') || $user->hasRole('Super Admin'),
            'isAdmin' => $user->hasRole('Administrador'),
        ]);
    }

    /**
     * Confirma el llenado físico de botellones fijos (individual, total o parcial).
     */
    public function markFilled(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $item = OrderItem::findOrFail($validated['order_item_id']);
        $pendingToFill = max(0, (int)$item->received_at_plant_quantity - (int)$item->prepared_quantity - (int)$item->damaged_quantity);
        $qty = (int) $validated['quantity'];

        if ($qty > $pendingToFill) {
            return back()->withErrors(['fill' => "No se pueden marcar {$qty} como llenados; solo hay {$pendingToFill} pendientes."]);
        }

        DB::transaction(function () use ($item, $qty, $request) {
            $order = $item->order;
            $operator = $request->user();

            $newWashing = max((int)$item->washing_quantity, (int)$item->prepared_quantity + $qty);
            $newPrepared = (int)$item->prepared_quantity + $qty;
            $isFinished = ($newPrepared + (int)$item->damaged_quantity) >= (int)$item->received_at_plant_quantity;

            $item->update([
                'washing_quantity' => $newWashing,
                'prepared_quantity' => $newPrepared,
                'status' => $isFinished ? OrderItem::STATUS_READY_FOR_DELIVERY : OrderItem::STATUS_WASHING,
            ]);

            BottleCustodyMovement::create([
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'customer_id' => $order->customer_id,
                'user_id' => $operator->id,
                'movement_type' => BottleCustodyMovement::TYPE_PLANT_PREPARED_FIXED,
                'bottle_type' => OrderItem::TYPE_FIXED,
                'bottle_condition' => BottleCustodyMovement::CONDITION_FULL,
                'quantity' => $qty,
                'origin' => 'plant',
                'destination' => 'plant',
                'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                'notes' => "Llenado finalizado en Planta ({$qty} u.) - Listos para cargar",
                'confirmed_by_user_id' => $operator->id,
                'confirmed_at' => now(),
                'created_at' => now(),
            ]);
        });

        return back()->with('success', "{$qty} botellón(es) fijos marcados como llenados. Aparecen listos para cargar en Reparto.");
    }

    /**
     * Marca como llenados todos los botellones fijos elegibles visibles en Planta.
     */
    public function markAllFilled(Request $request): RedirectResponse
    {
        $items = OrderItem::with('order')
            ->where('type', OrderItem::TYPE_FIXED)
            ->where('received_at_plant_quantity', '>', 0)
            ->whereRaw('(received_at_plant_quantity - prepared_quantity - damaged_quantity) > 0')
            ->get();

        $confirmedOrders = 0;
        $confirmedBottles = 0;

        DB::transaction(function () use ($items, $request, &$confirmedOrders, &$confirmedBottles) {
            foreach ($items as $item) {
                $pending = max(0, (int)$item->received_at_plant_quantity - (int)$item->prepared_quantity - (int)$item->damaged_quantity);
                if ($pending <= 0) {
                    continue;
                }

                $newWashing = max((int)$item->washing_quantity, (int)$item->prepared_quantity + $pending);
                $newPrepared = (int)$item->prepared_quantity + $pending;

                $item->update([
                    'washing_quantity' => $newWashing,
                    'prepared_quantity' => $newPrepared,
                    'status' => OrderItem::STATUS_READY_FOR_DELIVERY,
                ]);

                BottleCustodyMovement::create([
                    'order_id' => $item->order_id,
                    'order_item_id' => $item->id,
                    'customer_id' => $item->order->customer_id,
                    'user_id' => $request->user()->id,
                    'movement_type' => BottleCustodyMovement::TYPE_PLANT_PREPARED_FIXED,
                    'bottle_type' => OrderItem::TYPE_FIXED,
                    'bottle_condition' => BottleCustodyMovement::CONDITION_FULL,
                    'quantity' => $pending,
                    'origin' => 'plant',
                    'destination' => 'plant',
                    'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                    'notes' => "Llenado masivo en Planta ({$pending} u.) - Listos para cargar",
                    'confirmed_by_user_id' => $request->user()->id,
                    'confirmed_at' => now(),
                    'created_at' => now(),
                ]);

                $confirmedOrders++;
                $confirmedBottles += $pending;
            }
        });

        return back()->with('success', "{$confirmedBottles} fijos marcados como llenados ({$confirmedOrders} pedidos). Disponibles en Reparto.");
    }

    /**
     * Reporta daño o rotura en botellones fijos durante el proceso de lavado/llenado.
     */
    public function reportIncident(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'damage_type' => ['required', 'string', 'in:rotura,fisura,contaminacion,deformacion,otro'],
            'notes' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $item = OrderItem::findOrFail($validated['order_item_id']);
        $qty = (int) $validated['quantity'];

        DB::transaction(function () use ($item, $qty, $validated, $request) {
            $item->increment('damaged_quantity', $qty);

            BottleIncident::create([
                'customer_id' => $item->order->customer_id,
                'order_id' => $item->order_id,
                'order_item_id' => $item->id,
                'reported_by_user_id' => $request->user()->id,
                'bottle_type' => OrderItem::TYPE_FIXED,
                'bottle_condition' => BottleCustodyMovement::CONDITION_EMPTY,
                'quantity' => $qty,
                'stage' => BottleIncident::STAGE_PRODUCTION,
                'damage_type' => $validated['damage_type'],
                'status' => BottleIncident::STATUS_PENDING,
                'notes' => $validated['notes'],
            ]);
        });

        return back()->with('success', "Incidencia registrada: {$qty} botellón(es) fijos dañados en producción.");
    }
}
