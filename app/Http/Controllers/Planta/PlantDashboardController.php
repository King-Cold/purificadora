<?php

namespace App\Http\Controllers\Planta;

use App\Http\Controllers\Controller;
use App\Models\BottleCustodyMovement;
use App\Models\BottleIncident;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlantCounterRefill;
use App\Models\User;
use App\Services\PlantInventoryService;
use App\Services\SettingService;
use App\Services\StoreScheduleService;
use App\Services\TruckInventoryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlantDashboardController extends Controller
{
    public function __construct(
        protected PlantInventoryService $plantInventoryService,
        protected TruckInventoryService $truckInventoryService,
        protected SettingService $settingService,
        protected StoreScheduleService $scheduleService
    ) {}

    /**
     * Renderiza el Dashboard ejecutivo del Operador de Planta.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if (!$user->hasRole(['Operador Planta', 'Administrador', 'Super Admin']) && !$user->can('bottles.manage')) {
            abort(403, 'No autorizado para acceder al módulo de Planta.');
        }

        $tz = 'America/Merida';
        Carbon::setLocale('es');
        $now = Carbon::now($tz);

        $todayStartUtc = $now->copy()->startOfDay()->utc();
        $todayEndUtc = $now->copy()->endOfDay()->utc();

        $receptionStatus = $this->scheduleService->checkOrderReception($now);
        $scheduleSummary = $this->scheduleService->getScheduleSummary();

        // 1. Llenado rápido en mostrador (hoy)
        $unitPrice = $this->settingService->getCurrentStorePrice();
        $todayRefillsQuery = PlantCounterRefill::whereBetween('created_at', [$todayStartUtc, $todayEndUtc]);
        $todayBottlesRefilled = (int) (clone $todayRefillsQuery)->sum('quantity');
        $todayAmountRefilled = round((float) (clone $todayRefillsQuery)->sum('total_amount'), 2);
        $todayServicesCount = (int) (clone $todayRefillsQuery)->count();

        $recentRefills = PlantCounterRefill::with('operator:id,name')
            ->whereBetween('created_at', [$todayStartUtc, $todayEndUtc])
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'quantity' => (int) $r->quantity,
                'total_amount' => (float) $r->total_amount,
                'operator_name' => $r->operator?->name ?? 'Operador',
                'time' => $r->created_at?->copy()->setTimezone($tz)->format('H:i') ?? '—',
                'notes' => $r->notes,
            ]);

        // 2. Botellones Fijos en ciclo de Planta
        $pendingReceptionItems = OrderItem::where('type', OrderItem::TYPE_FIXED)
            ->where('collected_quantity', '>', 0)
            ->whereColumn('collected_quantity', '>', 'received_at_plant_quantity')
            ->get();

        $pendingReceptionBottles = 0;
        $pendingReceptionOrders = 0;
        foreach ($pendingReceptionItems as $item) {
            $qty = max(0, (int) $item->collected_quantity - (int) $item->received_at_plant_quantity - (int) $item->damaged_quantity);
            if ($qty > 0) {
                $pendingReceptionBottles += $qty;
                $pendingReceptionOrders++;
            }
        }

        $pendingFillItems = OrderItem::where('type', OrderItem::TYPE_FIXED)
            ->where('received_at_plant_quantity', '>', 0)
            ->whereRaw('(received_at_plant_quantity - prepared_quantity - damaged_quantity) > 0')
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_DELIVERED]))
            ->get();

        $pendingFillBottles = 0;
        $pendingFillOrders = 0;
        foreach ($pendingFillItems as $item) {
            $qty = max(0, (int) $item->received_at_plant_quantity - (int) $item->prepared_quantity - (int) $item->damaged_quantity);
            if ($qty > 0) {
                $pendingFillBottles += $qty;
                $pendingFillOrders++;
            }
        }

        $readyForTruckItems = OrderItem::where('type', OrderItem::TYPE_FIXED)
            ->where('prepared_quantity', '>', 0)
            ->whereRaw('(prepared_quantity - loaded_quantity - damaged_quantity) > 0')
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_DELIVERED]))
            ->get();

        $readyForTruckBottles = 0;
        $readyForTruckOrders = 0;
        foreach ($readyForTruckItems as $item) {
            $qty = max(0, (int) $item->prepared_quantity - (int) $item->loaded_quantity - (int) $item->damaged_quantity);
            if ($qty > 0) {
                $readyForTruckBottles += $qty;
                $readyForTruckOrders++;
            }
        }

        // 3. Inventario de Cambios (Planta + Camión)
        $invState = $this->plantInventoryService->getInventoryState();
        $inv = $this->plantInventoryService->getOrCreatePlantInventory();

        $soleDriver = User::role('Repartidor')
            ->where('status', 'active')
            ->orderBy('id')
            ->first()
            ?? User::role('Repartidor')->orderBy('id')->first();

        $truckInv = $soleDriver ? $this->truckInventoryService->getOrCreateTruckInventory($soleDriver) : null;

        $plantEmpty = (int) $inv->exchange_empty_available;
        $plantWashing = (int) $inv->exchange_washing_filling;
        $plantFull = (int) $inv->exchange_full_available;
        $plantReserved = (int) $inv->exchange_full_reserved;
        $plantUnlocated = (int) ($inv->exchange_unlocated ?? 0);
        $plantDamaged = (int) ($invState['balances']['exchange_damaged_total'] ?? 0);

        $truckFull = (int) ($truckInv?->exchange_full_available ?? 0);
        $truckEmpty = (int) ($truckInv?->exchange_empty_collected ?? 0);

        $targetQty = (int) ($invState['target_quantity'] ?? 0);
        $activeTotal = $plantEmpty + $plantWashing + $plantFull + $plantReserved + $truckFull + $truckEmpty;

        // 4. Incidencias abiertas y movimientos recientes
        $pendingIncidentsCount = (int) BottleIncident::where('status', BottleIncident::STATUS_PENDING)->count();

        $recentMovements = BottleCustodyMovement::with(['user:id,name', 'confirmedByUser:id,name'])
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(function (BottleCustodyMovement $m) use ($now, $tz) {
                $createdLocal = $m->created_at
                    ? Carbon::parse($m->created_at)->setTimezone($tz)
                    : null;
                $timeLabel = '—';
                if ($createdLocal) {
                    $timeLabel = $createdLocal->isSameDay($now)
                        ? $createdLocal->format('H:i')
                        : $createdLocal->format('d/m H:i');
                }

                return [
                    'id' => $m->id,
                    'type' => $m->movement_type,
                    'type_label' => $this->formatMovementLabel($m->movement_type),
                    'bottle_type' => $m->bottle_type === OrderItem::TYPE_FIXED ? 'Fijo' : 'Cambio',
                    'quantity' => (int) $m->quantity,
                    'actor_name' => $m->confirmedByUser?->name ?? $m->user?->name ?? 'Planta',
                    'time_label' => $timeLabel,
                    'notes' => $m->notes,
                ];
            });

        return Inertia::render('Planta/Dashboard', [
            'meta' => [
                'date_label' => ucfirst($now->translatedFormat('l, j \d\e F \d\e Y')),
                'is_store_open' => (bool) ($receptionStatus['open'] ?? false),
                'schedule_hours' => $scheduleSummary['formatted_hours'] ?? '07:00 – 19:00',
            ],
            'quickRefill' => [
                'unit_price' => (float) $unitPrice,
                'bottles_today' => $todayBottlesRefilled,
                'amount_today' => $todayAmountRefilled,
                'services_today' => $todayServicesCount,
                'recent_refills' => $recentRefills,
            ],
            'fixedPipeline' => [
                'pending_reception_orders' => $pendingReceptionOrders,
                'pending_reception_bottles' => $pendingReceptionBottles,
                'pending_fill_orders' => $pendingFillOrders,
                'pending_fill_bottles' => $pendingFillBottles,
                'ready_for_truck_orders' => $readyForTruckOrders,
                'ready_for_truck_bottles' => $readyForTruckBottles,
            ],
            'exchange' => [
                'plant_empty_available' => $plantEmpty,
                'plant_washing_filling' => $plantWashing,
                'plant_full_ready' => $plantFull,
                'plant_full_reserved' => $plantReserved,
                'plant_unlocated' => $plantUnlocated,
                'plant_damaged_total' => $plantDamaged,
                'truck_full_available' => $truckFull,
                'truck_empty_collected' => $truckEmpty,
                'driver_name' => $soleDriver?->name ?? 'Repartidor',
                'target_quantity' => $targetQty,
                'active_total' => $activeTotal,
                'initial_count_pending' => is_null($inv->initial_count_completed_at),
                'can_view_target' => $user->hasRole(['Administrador', 'Super Admin']),
            ],
            'alerts' => [
                'pending_incidents' => $pendingIncidentsCount,
                'total_action_required' => $pendingReceptionOrders + $pendingFillOrders + $pendingIncidentsCount,
            ],
            'recentMovements' => $recentMovements,
        ]);
    }

    private function formatMovementLabel(string $type): string
    {
        return match ($type) {
            BottleCustodyMovement::TYPE_TRUCK_UNLOAD_EXCHANGE => 'Bajada de camión (Cambios)',
            BottleCustodyMovement::TYPE_TRUCK_LOAD_EXCHANGE => 'Carga al camión (Cambios)',
            BottleCustodyMovement::TYPE_PLANT_WASHING_STARTED => 'Inicio de lavado',
            BottleCustodyMovement::TYPE_PLANT_WASHING_FINISHED => 'Lavado y llenado terminado',
            BottleCustodyMovement::TYPE_PLANT_RECEPTION_FIXED => 'Recepción de fijos en planta',
            BottleCustodyMovement::TYPE_PLANT_PREPARED_FIXED => 'Fijos lavados y llenados',
            BottleCustodyMovement::TYPE_PLANT_PHYSICAL_RECOUNT => 'Reconteo / ajuste físico en planta',
            BottleCustodyMovement::TYPE_TRUCK_LOAD_FIXED => 'Subida de fijos al camión',
            BottleCustodyMovement::TYPE_COLLECTION_FIXED_FROM_CUSTOMER => 'Recolección de fijos en ruta',
            BottleCustodyMovement::TYPE_COLLECTION_EXCHANGE_EMPTY => 'Recolección de cambios vacíos',
            BottleCustodyMovement::TYPE_DELIVERY_REFILL_TO_CUSTOMER => 'Entrega de pedido al cliente',
            BottleCustodyMovement::TYPE_RETURN_EMPTY_FIXED_TO_CUSTOMER => 'Devolución de fijo vacío',
            BottleCustodyMovement::TYPE_EXCHANGE_NEW_ENTRY => 'Ingreso de botellones de cambio',
            BottleCustodyMovement::TYPE_BOTTLE_INCIDENT => 'Incidencia reportada',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
