<?php

namespace App\Http\Controllers\Planta;

use App\Exceptions\OrderBusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\BottleCustodyMovement;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\PlantInventoryService;
use App\Services\TruckInventoryService;
use Illuminate\Http\RedirectResponse;
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
     * Pantalla principal simplificada de Cambios (Opción 1: flujo directo de 3 acciones).
     * Contabiliza en tiempo real Planta + Camión = Total esperado.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (!$user->hasRole(['Operador Planta', 'Administrador', 'Super Admin']) && !$user->can('bottles.manage')) {
            abort(403, 'No autorizado para acceder al intercambio de cambios.');
        }

        $invState = $this->plantInventoryService->getInventoryState();
        $supervision = $this->plantInventoryService->getExchangeSupervisionState();
        $inv = $this->plantInventoryService->getOrCreatePlantInventory();

        // Único repartidor activo del circuito
        $soleDriver = User::role('Repartidor')
            ->where('status', 'active')
            ->orderBy('id')
            ->first()
            ?? User::role('Repartidor')->orderBy('id')->first();

        $truckInv = $soleDriver ? $this->truckInventoryService->getOrCreateTruckInventory($soleDriver) : null;

        // Últimos movimientos de cambios ya confirmados
        $recentExchangeMovements = BottleCustodyMovement::with(['user', 'confirmedByUser'])
            ->where('bottle_type', OrderItem::TYPE_EXCHANGE)
            ->whereIn('movement_type', [
                BottleCustodyMovement::TYPE_TRUCK_UNLOAD_EXCHANGE,
                BottleCustodyMovement::TYPE_TRUCK_LOAD_EXCHANGE,
                BottleCustodyMovement::TYPE_PLANT_WASHING_STARTED,
                BottleCustodyMovement::TYPE_PLANT_WASHING_FINISHED,
                BottleCustodyMovement::TYPE_PLANT_PHYSICAL_RECOUNT,
                BottleCustodyMovement::TYPE_EXCHANGE_NEW_ENTRY,
            ])
            ->whereIn('status', [BottleCustodyMovement::STATUS_ACCEPTED, 'accepted_with_discrepancy'])
            ->orderBy('id', 'desc')
            ->limit(15)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->movement_type,
                'quantity' => (int) $m->quantity,
                'condition' => $m->bottle_condition,
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
                'exchange_unlocated' => (int) ($inv->exchange_unlocated ?? 0),
                'exchange_damaged_total' => (int) ($invState['balances']['exchange_damaged_total'] ?? 0),
            ],
            'truckSummary' => [
                'driver_id' => $soleDriver?->id,
                'driver_name' => $soleDriver?->name ?? 'Repartidor',
                'exchange_full_available' => (int) ($truckInv?->exchange_full_available ?? 0),
                'exchange_empty_collected' => (int) ($truckInv?->exchange_empty_collected ?? 0),
            ],
            'supervisionSummary' => $supervision['summary'],
            'initialCountPending' => is_null($inv->initial_count_completed_at),
            'initialCountInfo' => $invState['initial_count_info'] ?? null,
            'recentExchangeMovements' => $recentExchangeMovements,
            'isOperator' => $user->hasRole('Operador Planta') || $user->hasRole('Super Admin'),
            'isAdmin' => $user->hasRole('Administrador') || $user->hasRole('Super Admin'),
        ]);
    }

    /**
     * Acción 1: Recibir vacíos del camión hacia Planta en 1 clic.
     */
    public function receiveEmpties(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'driver_id' => ['nullable', 'exists:users,id'],
        ]);

        try {
            $driver = !empty($validated['driver_id']) ? User::find($validated['driver_id']) : null;
            $qty = (int) $validated['quantity'];

            $this->plantInventoryService->receiveTruckEmptyDirect($request->user(), $qty, $driver);

            return back()->with('success', "Se recibieron {$qty} botellón(es) vacíos del camión en Planta.");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors([$e->getErrorKey() => $e->getMessage()]);
        }
    }

    /**
     * Acción 2: Marcar vacíos de Planta como llenos en 1 clic.
     */
    public function fill(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        try {
            $qty = (int) $validated['quantity'];
            $this->plantInventoryService->fillExchangeDirect($request->user(), $qty);

            return back()->with('success', "Se llenaron {$qty} botellón(es) de cambio en Planta.");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors([$e->getErrorKey() => $e->getMessage()]);
        }
    }

    /**
     * Acción 3: Subir llenos de Planta al camión en 1 clic.
     */
    public function loadTruck(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'driver_id' => ['nullable', 'exists:users,id'],
        ]);

        try {
            $driver = !empty($validated['driver_id']) ? User::find($validated['driver_id']) : null;
            $qty = (int) $validated['quantity'];

            $this->plantInventoryService->sendFullToTruckDirect($request->user(), $qty, $driver);

            return back()->with('success', "Se subieron {$qty} botellón(es) llenos al camión.");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors([$e->getErrorKey() => $e->getMessage()]);
        }
    }

    /**
     * Reporta un inconveniente de botellones de cambio desde Planta → Cambios.
     */
    public function reportInconvenience(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'origin_stage' => ['required', 'string', 'in:plant_empty,plant_preparation,plant_full,truck_full,truck_empty'],
            'reason' => ['required', 'string', 'max:100'],
            'location_status' => ['required', 'string', 'in:separado,sin_localizar'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->plantInventoryService->reportExchangeInconvenience($request->user(), $validated);

            return back()->with('success', 'Inconveniente reportado. Las unidades afectadas pasaron a «En revisión».');
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors([$e->getErrorKey() => $e->getMessage()]);
        }
    }
}
