<?php

namespace App\Http\Controllers\Planta;

use App\Exceptions\OrderBusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\BottleCustodyMovement;
use App\Models\OrderItem;
use App\Services\PlantInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlantReceptionController extends Controller
{
    public function __construct(
        protected PlantInventoryService $plantInventoryService
    ) {}

    /**
     * Pantalla "Por Recibir": muestra fijos y cambios pendientes de cotejo físico en Planta.
     */
    public function index(): Response
    {
        $data = $this->plantInventoryService->getPendingReceptions();

        return Inertia::render('Planta/Reception/Index', [
            'pendingReceptions' => $data,
            'canConfirmReception' => auth()->user()->hasRole(['Operador Planta', 'Administrador', 'Super Admin']),
        ]);
    }

    /**
     * Confirma la recepción física presencial de botellones fijos de un cliente.
     */
    public function confirmFixed(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_item_id' => ['required', 'exists:order_items,id'],
            'received_quantity' => ['required', 'integer', 'min:0'],
            'start_washing' => ['nullable', 'boolean'],
            'damaged_quantity' => ['nullable', 'integer', 'min:0'],
            'damage_type' => ['nullable', 'string', 'in:rotura,fisura,contaminacion,deformacion,otro'],
            'damage_notes' => ['nullable', 'string', 'max:500'],
            'difference_reason' => ['nullable', 'string', 'max:500'],
        ], [
            'order_item_id.required' => 'El detalle del pedido es obligatorio.',
            'received_quantity.required' => 'La cantidad recibida es obligatoria.',
            'received_quantity.integer' => 'La cantidad debe ser un número entero.',
            'received_quantity.min' => 'La cantidad no puede ser negativa.',
            'damaged_quantity.min' => 'La cantidad dañada no puede ser negativa.',
        ]);

        $item = OrderItem::findOrFail($validated['order_item_id']);

        try {
            $receipt = $this->plantInventoryService->confirmFixedReception(
                $request->user(),
                $item,
                (int) $validated['received_quantity'],
                $validated['difference_reason'] ?? null,
                (bool) ($validated['start_washing'] ?? false),
                (int) ($validated['damaged_quantity'] ?? 0),
                $validated['damage_type'] ?? null,
                $validated['damage_notes'] ?? null
            );

            return back()
                ->with('success', 'Recepción de envases fijos confirmada exitosamente.')
                ->with('receipt', $receipt);
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors([$e->getErrorKey() => $e->getMessage()]);
        }
    }

    /**
     * Confirmación masiva de botellones fijos aptos recibidos en Planta.
     */
    public function confirmAllFixed(Request $request): RedirectResponse
    {
        $items = OrderItem::with(['order.customer'])
            ->where('type', OrderItem::TYPE_FIXED)
            ->where('collected_quantity', '>', 0)
            ->whereColumn('collected_quantity', '>', 'received_at_plant_quantity')
            ->get();

        $confirmedOrders = 0;
        $confirmedBottles = 0;
        $excludedCount = 0;

        \Illuminate\Support\Facades\DB::transaction(function () use ($items, $request, &$confirmedOrders, &$confirmedBottles, &$excludedCount) {
            foreach ($items as $item) {
                $pending = max(0, (int)$item->collected_quantity - (int)$item->received_at_plant_quantity - (int)$item->damaged_quantity);
                if ($pending <= 0) {
                    continue;
                }

                if ($item->damaged_quantity > 0) {
                    $excludedCount++;
                    continue;
                }

                $this->plantInventoryService->confirmFixedReception(
                    $request->user(),
                    $item,
                    $pending,
                    null,
                    false,
                    0,
                    null,
                    null
                );
                $confirmedOrders++;
                $confirmedBottles += $pending;
            }
        });

        $msg = "{$confirmedBottles} fijos recibidos en Planta ({$confirmedOrders} pedidos).";
        if ($excludedCount > 0) {
            $msg .= " Se excluyeron {$excludedCount} pedidos con incidencias previas.";
        }

        return back()->with('success', $msg);
    }

    /**
     * Confirma la recepción física presencial de botellones de cambio descargados por el chofer.
     */
    public function confirmExchange(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'movement_id' => ['required', 'exists:bottle_custody_movements,id'],
            'received_quantity' => ['required', 'integer', 'min:0'],
            'damaged_quantity' => ['nullable', 'integer', 'min:0'],
            'damage_type' => ['nullable', 'string', 'in:rotura,fisura,contaminacion,deformacion,otro'],
            'damage_notes' => ['nullable', 'string', 'max:500'],
            'unlocated_quantity' => ['nullable', 'integer', 'min:0'],
            'difference_reason' => ['nullable', 'string', 'max:500'],
        ], [
            'movement_id.required' => 'La transferencia de descarga es obligatoria.',
            'received_quantity.required' => 'La cantidad recibida es obligatoria.',
            'received_quantity.integer' => 'La cantidad debe ser un número entero.',
            'received_quantity.min' => 'La cantidad no puede ser negativa.',
            'damaged_quantity.min' => 'La cantidad dañada no puede ser negativa.',
        ]);

        $movement = BottleCustodyMovement::findOrFail($validated['movement_id']);

        try {
            $receipt = $this->plantInventoryService->confirmExchangeReception(
                $request->user(),
                $movement,
                (int) $validated['received_quantity'],
                $validated['difference_reason'] ?? null,
                (int) ($validated['damaged_quantity'] ?? 0),
                $validated['damage_type'] ?? null,
                $validated['damage_notes'] ?? null,
                (int) ($validated['unlocated_quantity'] ?? 0)
            );

            return back()
                ->with('success', 'Recepción de garrafones de cambio confirmada exitosamente.')
                ->with('receipt', $receipt);
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors([$e->getErrorKey() => $e->getMessage()]);
        }
    }
}

