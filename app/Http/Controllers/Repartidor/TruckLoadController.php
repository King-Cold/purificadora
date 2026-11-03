<?php

namespace App\Http\Controllers\Repartidor;

use App\Exceptions\OrderBusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\BottleCustodyMovement;
use App\Services\DeliveryRouteService;
use App\Services\TruckInventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TruckLoadController extends Controller
{
    public function __construct(
        protected TruckInventoryService $truckInventoryService,
        protected DeliveryRouteService $routeService
    ) {}

    /**
     * Pantalla "Mi Carga": Muestra existencias continuas del camión, desglose de fijos y transferencias.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Determinar chofer a consultar (Repartidor logueado o el único chofer activo si es Admin)
        if ($user->hasRole('Repartidor')) {
            $driver = $user;
        } elseif ($user->hasRole(['Administrador', 'Super Admin'])) {
            $driver = $this->routeService->resolveSoleActiveDriver();
        } else {
            abort(403, 'No autorizado para consultar existencias del camión.');
        }

        $state = $this->truckInventoryService->getTruckInventoryState($driver);

        return Inertia::render('Repartidor/TruckLoad/Index', [
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
            ],
            'truckInventory' => $state['truck_inventory'],
            'fixedByCustomer' => $state['fixed_by_customer'],
            'pendingTransfers' => $state['pending_transfers'],
            'recentMovements' => $state['recent_movements'],
            'canManage' => $user->hasRole('Repartidor') || $user->can('bottles.manage'),
        ]);
    }

    /**
     * Aceptación física de una transferencia propuesta desde planta al camión.
     * Solo el Repartidor puede confirmar físicamente lo recibido en su camión.
     */
    public function acceptTransfer(Request $request, BottleCustodyMovement $movement)
    {
        $user = $request->user();
        if (!$user->hasRole('Repartidor')) {
            abort(403, 'Solo el Repartidor puede confirmar la recepción física y aceptar la carga de su unidad.');
        }

        $validated = $request->validate([
            'confirmed_quantity' => ['nullable', 'integer', 'min:0'],
            'difference_reason' => ['nullable', 'string', 'max:500'],
            'retained_in_plant' => ['nullable', 'boolean'],
            'retained_in_plant_quantity' => ['nullable', 'integer', 'min:0'],
            'damaged_quantity' => ['nullable', 'integer', 'min:0'],
            'damage_type' => ['nullable', 'string', 'max:50'],
            'damage_notes' => ['nullable', 'string', 'max:500'],
            'unlocated_quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $driver = $user;
            $confirmedQty = isset($validated['confirmed_quantity']) ? (int) $validated['confirmed_quantity'] : null;
            $diffReason = $validated['difference_reason'] ?? null;
            $retainedInPlant = (bool) ($validated['retained_in_plant'] ?? false);
            $retainedInPlantQty = (int) ($validated['retained_in_plant_quantity'] ?? 0);
            $damagedQty = (int) ($validated['damaged_quantity'] ?? 0);
            $damageType = $validated['damage_type'] ?? null;
            $damageNotes = $validated['damage_notes'] ?? null;
            $unlocatedQty = (int) ($validated['unlocated_quantity'] ?? 0);

            $this->truckInventoryService->acceptTransfer(
                $movement,
                $driver,
                $confirmedQty,
                $diffReason,
                $retainedInPlant,
                $retainedInPlantQty,
                $damagedQty,
                $damageType,
                $damageNotes,
                $unlocatedQty
            );

            return back()->with('success', 'Transferencia aceptada y sumada a las existencias del camión correctamente.');
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }
    }

    /**
     * Rechazo de una transferencia propuesta.
     */
    public function rejectTransfer(Request $request, BottleCustodyMovement $movement)
    {
        $user = $request->user();
        if (!$user->hasRole('Repartidor')) {
            abort(403, 'Solo el Repartidor puede rechazar transferencias de carga de su unidad.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'retained_in_plant_quantity' => ['nullable', 'integer', 'min:0'],
            'damaged_quantity' => ['nullable', 'integer', 'min:0'],
            'damage_type' => ['nullable', 'string', 'max:50'],
            'damage_notes' => ['nullable', 'string', 'max:500'],
            'unlocated_quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $driver = $user;
            $retainedInPlantQty = (int) ($validated['retained_in_plant_quantity'] ?? 0);
            $damagedQty = (int) ($validated['damaged_quantity'] ?? 0);
            $damageType = $validated['damage_type'] ?? null;
            $damageNotes = $validated['damage_notes'] ?? null;
            $unlocatedQty = (int) ($validated['unlocated_quantity'] ?? 0);

            $this->truckInventoryService->rejectTransfer(
                $movement,
                $driver,
                $validated['reason'],
                $retainedInPlantQty,
                $damagedQty,
                $damageType,
                $damageNotes,
                $unlocatedQty
            );

            return back()->with('success', 'Transferencia rechazada.');
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }
    }

    /**
     * Propuesta de descarga desde el camión a la planta (vacíos o devoluciones).
     */
    public function proposeUnload(Request $request)
    {
        $user = $request->user();
        if (!$user->hasRole('Repartidor') && !$user->can('bottles.manage')) {
            abort(403, 'No autorizado para registrar descargas.');
        }

        $validated = $request->validate([
            'bottle_type' => ['required', 'in:cambio,fijo'],
            'bottle_condition' => ['required', 'in:empty,full'],
            'quantity' => ['required', 'integer', 'min:1'],
            'customer_id' => ['nullable', 'required_if:bottle_type,fijo', 'exists:customers,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $driver = $user->hasRole('Repartidor') ? $user : $this->routeService->resolveSoleActiveDriver();
            $customerId = isset($validated['customer_id']) ? (int) $validated['customer_id'] : null;

            $this->truckInventoryService->proposeTruckToPlantUnload(
                $driver,
                $validated['bottle_type'],
                $validated['bottle_condition'],
                (int) $validated['quantity'],
                $customerId,
                $validated['notes'] ?? null
            );

            return back()->with('success', 'Descarga registrada y enviada a planta para su confirmación física.');
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['unload' => $e->getMessage()]);
        }
    }
}

