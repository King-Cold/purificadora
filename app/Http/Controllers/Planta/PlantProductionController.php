<?php

namespace App\Http\Controllers\Planta;

use App\Exceptions\OrderBusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\PlantProductionBatch;
use App\Services\PlantInventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlantProductionController extends Controller
{
    public function __construct(
        protected PlantInventoryService $plantInventoryService
    ) {}

    /**
     * Asegura que el usuario tenga rol de Operador de Planta para operaciones físicas.
     */
    protected function ensureIsPhysicalOperator(Request $request): void
    {
        $user = $request->user();

        if ($user->hasRole('Administrador')) {
            abort(403, 'El Administrador puede consultar y supervisar el inventario, pero las operaciones físicas de lavado, llenado y producción corresponden exclusivamente al Operador de Planta.');
        }

        if (!$user->hasRole('Operador Planta') && !$user->hasRole('Super Admin')) {
            abort(403, 'Solo el Operador de Planta puede ejecutar operaciones de producción.');
        }
    }

    /**
     * Pantalla principal de Lavado y Llenado (Mesa de Trabajo de Producción).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (!$user->hasRole('Operador Planta') && !$user->hasRole('Administrador') && !$user->can('bottles.manage')) {
            abort(403, 'No autorizado para acceder a la mesa de trabajo de producción.');
        }

        $productionState = $this->plantInventoryService->getProductionState();

        return Inertia::render('Planta/Production/Index', [
            'state' => $productionState,
            'isOperator' => $user->hasRole('Operador Planta') || $user->hasRole('Super Admin'),
            'isAdmin' => $user->hasRole('Administrador'),
        ]);
    }

    /**
     * Inicia el lavado de botellones fijos para un cliente/pedido.
     */
    public function startFixed(Request $request)
    {
        $this->ensureIsPhysicalOperator($request);

        $validated = $request->validate([
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $item = OrderItem::findOrFail($validated['order_item_id']);

        try {
            $this->plantInventoryService->startFixedWashing(
                $request->user(),
                $item,
                (int)$validated['quantity']
            );

            return back()->with('success', "Se ingresaron {$validated['quantity']} botellones fijos al túnel de lavado.");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['fixed' => $e->getMessage()]);
        }
    }

    /**
     * Finaliza la preparación de botellones fijos (lavado, llenado y sellado).
     */
    public function finishFixed(Request $request)
    {
        $this->ensureIsPhysicalOperator($request);

        $validated = $request->validate([
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $item = OrderItem::findOrFail($validated['order_item_id']);

        try {
            $this->plantInventoryService->finishFixedPreparation(
                $request->user(),
                $item,
                (int)$validated['quantity']
            );

            return back()->with('success', "Se marcaron {$validated['quantity']} botellones fijos como preparados y listos para reparto.");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['fixed' => $e->getMessage()]);
        }
    }

    /**
     * Reporta daño en botellones fijos durante el proceso de lavado/llenado.
     */
    public function damageFixed(Request $request)
    {
        $this->ensureIsPhysicalOperator($request);

        $validated = $request->validate([
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'damage_type' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $item = OrderItem::findOrFail($validated['order_item_id']);

        try {
            $this->plantInventoryService->reportFixedWashingDamage(
                $request->user(),
                $item,
                (int)$validated['quantity'],
                $validated['damage_type'],
                $validated['notes'] ?? null
            );

            return back()->with('success', "Incidencia de {$validated['quantity']} botellón(es) fijos dañados registrada correctamente.");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['damage' => $e->getMessage()]);
        }
    }

    /**
     * Inicia un lote de botellones de cambio genéricos.
     */
    public function startExchangeBatch(Request $request)
    {
        $this->ensureIsPhysicalOperator($request);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $batch = $this->plantInventoryService->startExchangeBatch(
                $request->user(),
                (int)$validated['quantity'],
                $validated['notes'] ?? null
            );

            return back()->with('success', "Lote {$batch->batch_number} iniciado con {$validated['quantity']} botellones de cambio.");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['batch' => $e->getMessage()]);
        }
    }

    /**
     * Finaliza un lote de botellones de cambio genéricos (total o parcialmente).
     */
    public function finishExchangeBatch(Request $request)
    {
        $this->ensureIsPhysicalOperator($request);

        $validated = $request->validate([
            'batch_id' => ['required', 'integer', 'exists:plant_production_batches,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $batch = PlantProductionBatch::findOrFail($validated['batch_id']);

        try {
            $this->plantInventoryService->finishExchangeBatch(
                $request->user(),
                $batch,
                (int)$validated['quantity']
            );

            return back()->with('success', "Se purificaron y sellaron {$validated['quantity']} botellones del lote {$batch->batch_number}.");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['batch' => $e->getMessage()]);
        }
    }

    /**
     * Reporta envases dañados dentro de un lote de botellones de cambio.
     */
    public function damageExchangeBatch(Request $request)
    {
        $this->ensureIsPhysicalOperator($request);

        $validated = $request->validate([
            'batch_id' => ['required', 'integer', 'exists:plant_production_batches,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'damage_type' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $batch = PlantProductionBatch::findOrFail($validated['batch_id']);

        try {
            $this->plantInventoryService->reportBatchDamage(
                $request->user(),
                $batch,
                (int)$validated['quantity'],
                $validated['damage_type'],
                $validated['notes'] ?? null
            );

            return back()->with('success', "Incidencia de {$validated['quantity']} envase(s) dañados en lote {$batch->batch_number} registrada correctamente.");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors(['batch_damage' => $e->getMessage()]);
        }
    }
}

