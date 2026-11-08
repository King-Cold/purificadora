<?php

namespace App\Http\Controllers\Planta;

use App\Http\Controllers\Controller;
use App\Models\BottleCustodyMovement;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\PlantInventoryService;
use App\Services\TruckInventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlantCambiosController extends Controller
{
    public function __construct(
        protected PlantInventoryService $plantInventoryService,
        protected TruckInventoryService $truckInventoryService
    ) {}

    /**
     * Pantalla principal de Cambios (Intercambio con el Camión y Preparación de Existencias).
     * Muestra saldos útiles arriba y dos acciones independientes:
     * 1. Recibir vacíos (cotejo de descarga del chofer)
     * 2. Enviar llenos (propuesta de carga al camión)
     * Acción secundaria: Preparar cambios (lavado/llenado interno de stock)
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (!$user->hasRole(['Operador Planta', 'Administrador', 'Super Admin']) && !$user->can('bottles.manage')) {
            abort(403, 'No autorizado para acceder al intercambio de cambios.');
        }

        $invState = $this->plantInventoryService->getInventoryState();
        $inv = $this->plantInventoryService->getOrCreatePlantInventory();
        $receptionsData = $this->plantInventoryService->getPendingReceptions();

        // 1. Entregas de vacíos declaradas por choferes pendientes de cotejo en Planta
        $pendingExchangeReceptions = $receptionsData['exchange_pending'];

        // 2. Propuestas de llenos enviadas al camión pendientes de confirmación por el chofer
        $pendingExchangeProposals = BottleCustodyMovement::with(['user', 'confirmedByUser'])
            ->where('bottle_type', OrderItem::TYPE_EXCHANGE)
            ->where('movement_type', BottleCustodyMovement::TYPE_TRUCK_LOAD_EXCHANGE)
            ->where('status', BottleCustodyMovement::STATUS_PROPOSED)
            ->where('destination', 'truck')
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'quantity' => (int) $m->quantity,
                'driver_id' => $m->user_id,
                'driver_name' => $m->confirmedByUser?->name ?? 'Repartidor',
                'initiator_name' => $m->user?->name ?? 'Operador',
                'notes' => $m->notes,
                'status' => $m->status,
                'created_at' => $m->created_at ? date('d/m/Y H:i', strtotime($m->created_at)) : null,
            ]);

        // 3. Choferes disponibles para seleccionar en envíos
        $drivers = User::role('Repartidor')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // 4. Movimientos recientes de cambios (recepciones de vacíos y envíos de llenos separados)
        $recentExchangeMovements = BottleCustodyMovement::with(['user', 'confirmedByUser'])
            ->where('bottle_type', OrderItem::TYPE_EXCHANGE)
            ->whereIn('movement_type', [
                BottleCustodyMovement::TYPE_TRUCK_UNLOAD_EXCHANGE,
                BottleCustodyMovement::TYPE_TRUCK_LOAD_EXCHANGE,
                BottleCustodyMovement::TYPE_PLANT_WASHING_STARTED,
                BottleCustodyMovement::TYPE_PLANT_WASHING_FINISHED,
            ])
            ->whereIn('status', [BottleCustodyMovement::STATUS_ACCEPTED, 'accepted_with_discrepancy'])
            ->orderBy('id', 'desc')
            ->limit(15)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->movement_type,
                'quantity' => (int) $m->quantity,
                'proposed_quantity' => (int) ($m->proposed_quantity ?? $m->quantity),
                'condition' => $m->bottle_condition,
                'difference_reason' => $m->difference_reason,
                'driver_name' => $m->confirmedByUser?->name ?? $m->user?->name ?? 'Repartidor',
                'operator_name' => $m->user?->name ?? 'Operador',
                'date' => $m->created_at ? date('d/m/Y H:i', strtotime($m->created_at)) : null,
                'notes' => $m->notes,
            ]);

        return Inertia::render('Planta/Cambios/Index', [
            'balances' => [
                'exchange_empty_available' => (int) $inv->exchange_empty_available,
                'exchange_washing_filling' => (int) $inv->exchange_washing_filling,
                'exchange_full_ready' => (int) $inv->exchange_full_available,
                'exchange_full_reserved' => (int) $inv->exchange_full_reserved,
            ],
            'initialCountPending' => is_null($inv->initial_count_completed_at),
            'initialCountInfo' => $invState['initial_count_info'] ?? null,
            'pendingExchangeReceptions' => $pendingExchangeReceptions,
            'pendingExchangeProposals' => $pendingExchangeProposals,
            'recentExchangeMovements' => $recentExchangeMovements,
            'drivers' => $drivers,
            'isOperator' => $user->hasRole('Operador Planta') || $user->hasRole('Super Admin'),
            'isAdmin' => $user->hasRole('Administrador'),
        ]);
    }
}
