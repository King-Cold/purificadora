<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\OrderBusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\BottleIncident;
use App\Services\PlantInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeInventoryController extends Controller
{
    public function __construct(
        protected PlantInventoryService $plantInventoryService
    ) {}

    /**
     * Administración → Supervisión → Inventario de cambios
     * Control general de botellones de cambio de 20L en el circuito (Planta + Camión + En revisión).
     */
    public function index(Request $request): Response
    {
        $filters = $request->only(['movement_type', 'date_from', 'date_to']);
        $state = $this->plantInventoryService->getExchangeSupervisionState($filters);

        return Inertia::render('Admin/ExchangeInventory/Index', [
            'summary' => $state['summary'],
            'breakdown' => $state['breakdown'],
            'pendingIncidents' => $state['pending_incidents'],
            'resolvedIncidents' => $state['resolved_incidents'],
            'movements' => $state['movements'],
            'filters' => $state['filters'],
        ]);
    }

    /**
     * Editar la meta de botellones de cambio de 20L.
     */
    public function updateTarget(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'target_quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'target_quantity.required' => 'Indica la meta de botellones de cambio.',
            'target_quantity.integer' => 'La meta debe ser un número entero.',
            'target_quantity.min' => 'La meta debe ser de al menos 1 botellón.',
        ]);

        try {
            $this->plantInventoryService->updateExchangeTarget(
                $request->user(),
                (int) $validated['target_quantity'],
                $validated['notes'] ?? null
            );

            return back()->with('success', "Meta de botellones de cambio actualizada a {$validated['target_quantity']} unidades.");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors([$e->getErrorKey() => $e->getMessage()]);
        }
    }

    /**
     * Registrar botellones nuevos (alta física en inventario de cambios).
     */
    public function storeNewBottles(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:5000'],
            'bottle_condition' => ['required', 'string', 'in:empty,full'],
            'reason' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'quantity.required' => 'La cantidad de botellones nuevos es obligatoria.',
            'quantity.integer' => 'La cantidad debe ser un número entero positivo.',
            'quantity.min' => 'La cantidad debe ser al menos 1 botellón.',
            'bottle_condition.required' => 'Indica si los botellones nuevos entran vacíos o llenos.',
            'reason.required' => 'Selecciona o indica el motivo del alta (compra, reposición o incorporación).',
        ]);

        try {
            $this->plantInventoryService->registerNewExchangeBottles(
                $request->user(),
                (int) $validated['quantity'],
                $validated['bottle_condition'],
                $validated['reason'],
                $validated['notes'] ?? null
            );

            $condLabel = $validated['bottle_condition'] === 'empty' ? 'vacíos aptos' : 'llenos libres';

            return back()->with('success', "Alta registrada: {$validated['quantity']} botellón(es) nuevos de 20L incorporados como {$condLabel} en Planta.");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors([$e->getErrorKey() => $e->getMessage()]);
        }
    }

    /**
     * Resolver una incidencia de botellón de cambio en revisión (Confirmar baja o Registrar recuperación).
     */
    public function resolveIncident(Request $request, BottleIncident $incident): RedirectResponse
    {
        $validated = $request->validate([
            'resolution_type' => ['required', 'string', 'in:baja,recuperacion'],
            'recovery_location' => ['nullable', 'string', 'in:plant_empty,plant_full,truck_full,truck_empty'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'resolution_type.required' => 'Selecciona si deseas confirmar la baja o registrar una recuperación.',
        ]);

        try {
            $this->plantInventoryService->resolveExchangeIncident(
                $request->user(),
                $incident,
                $validated['resolution_type'],
                $validated['recovery_location'] ?? null,
                $validated['notes'] ?? null
            );

            $msg = $validated['resolution_type'] === 'baja'
                ? 'Baja confirmada en el historial sin descontar nuevamente de los activos.'
                : 'Recuperación registrada: las unidades volvieron a estar disponibles en el circuito.';

            return back()->with('success', $msg);
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors([$e->getErrorKey() => $e->getMessage()]);
        }
    }

    /**
     * Resolver unidades sin localizar (Recuperación, Localizado con daño o Baja).
     */
    public function resolveUnlocated(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'resolution_type' => ['required', 'string', 'in:recuperacion,danado,baja'],
            'recovery_location' => ['nullable', 'string', 'in:plant_empty,plant_full,truck_full,truck_empty'],
            'damage_type' => ['nullable', 'string', 'in:rotura,fisura,contaminacion,deformacion,otro'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'quantity.required' => 'Indica cuántas unidades sin localizar deseas resolver.',
            'quantity.min' => 'La cantidad debe ser al menos 1.',
            'resolution_type.required' => 'Selecciona el resultado de la revisión.',
        ]);

        try {
            $this->plantInventoryService->resolveUnlocatedExchange(
                $request->user(),
                (int) $validated['quantity'],
                $validated['resolution_type'],
                $validated['recovery_location'] ?? null,
                $validated['damage_type'] ?? null,
                $validated['notes'] ?? null
            );

            return back()->with('success', 'Unidades sin localizar actualizadas correctamente.');
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors([$e->getErrorKey() => $e->getMessage()]);
        }
    }

    /**
     * Reportar un inconveniente (dañado o sin localizar) directamente desde el panel de supervisión.
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

            return back()->with('success', 'Inconveniente reportado. Las unidades pasaron a «En revisión» para su seguimiento.');
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors([$e->getErrorKey() => $e->getMessage()]);
        }
    }
}

