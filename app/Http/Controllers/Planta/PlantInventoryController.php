<?php

namespace App\Http\Controllers\Planta;

use App\Exceptions\OrderBusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\BottleIncident;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Services\PlantInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlantInventoryController extends Controller
{
    public function __construct(
        protected PlantInventoryService $plantInventoryService
    ) {}

    /**
     * Muestra la pantalla principal de Existencias, Conteo Inicial e Incidencias en Planta.
     */
    public function index(): Response
    {
        $state = $this->plantInventoryService->getInventoryState();

        // Lista de clientes con botellones fijos activos en planta para el formulario de incidencias
        // Solo apodo, código CLI-XXXX y nombre opcional (sin datos sensibles)
        $customersWithFixed = Customer::select('id', 'code', 'nickname', 'name')
            ->whereHas('orders.items', function ($q) {
                $q->where('type', OrderItem::TYPE_FIXED)
                  ->where('received_at_plant_quantity', '>', 0);
            })
            ->orderBy('nickname')
            ->get();

        return Inertia::render('Planta/Inventory/Index', [
            'inventoryState' => $state,
            'customersWithFixed' => $customersWithFixed,
            'canRecordInitialCount' => auth()->user()->hasRole(['Operador Planta', 'Administrador', 'Super Admin']),
            'canReportIncident' => auth()->user()->hasRole(['Operador Planta', 'Administrador', 'Super Admin']),
        ]);
    }

    /**
     * Registra el conteo físico inicial de botellones de 20L en Planta.
     */
    public function storeInitialCount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'empty_count' => ['required', 'integer', 'min:0'],
            'full_count' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'empty_count.required' => 'La cantidad de botellones vacíos es obligatoria.',
            'empty_count.integer' => 'La cantidad de botellones vacíos debe ser un número entero.',
            'empty_count.min' => 'La cantidad de botellones vacíos no puede ser negativa.',
            'full_count.required' => 'La cantidad de botellones llenos es obligatoria.',
            'full_count.integer' => 'La cantidad de botellones llenos debe ser un número entero.',
            'full_count.min' => 'La cantidad de botellones llenos no puede ser negativa.',
            'notes.max' => 'Las observaciones no pueden exceder los 500 caracteres.',
        ]);

        try {
            $this->plantInventoryService->recordInitialCount(
                $request->user(),
                (int) $validated['empty_count'],
                (int) $validated['full_count'],
                $validated['notes'] ?? null
            );

            return redirect()->route('planta.inventory.index')->with('success', 'Conteo inicial de existencias registrado exitosamente. La línea base física de planta ha quedado establecida.');
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors([$e->getErrorKey() => $e->getMessage()]);
        }
    }

    /**
     * Registra una incidencia de daño físico en botellones.
     */
    public function storeIncident(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bottle_type' => ['required', 'string', 'in:fijo,cambio'],
            'customer_id' => ['nullable', 'required_if:bottle_type,fijo', 'exists:customers,id'],
            'order_item_id' => ['nullable', 'exists:order_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'stage' => ['required', 'string', 'in:recepcion,lavado,llenado,almacen,carga'],
            'damage_type' => ['required', 'string', 'in:rotura,fisura,contaminacion,deformacion,otro'],
            'notes' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'bottle_type.required' => 'El tipo de botellón es obligatorio.',
            'bottle_type.in' => 'El tipo de botellón debe ser fijo o de cambio.',
            'customer_id.required_if' => 'Debe seleccionar el cliente propietario de los botellones fijos.',
            'quantity.required' => 'La cantidad con daño es obligatoria.',
            'quantity.integer' => 'La cantidad debe ser un número entero.',
            'quantity.min' => 'La cantidad debe ser mayor a cero.',
            'stage.required' => 'Debe indicar la etapa operativa donde ocurrió la incidencia.',
            'damage_type.required' => 'Debe clasificar el tipo de daño detectado.',
            'notes.required' => 'Debe describir la incidencia y causa del daño.',
            'notes.min' => 'La descripción debe tener al menos 5 caracteres.',
        ]);

        try {
            $this->plantInventoryService->recordBottleIncident($request->user(), $validated);

            return redirect()->route('planta.inventory.index')->with('success', 'Incidencia de daño registrada correctamente. Los envases fueron separados de los disponibles.');
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors([$e->getErrorKey() => $e->getMessage()]);
        }
    }

    /**
     * Inicia lote de lavado y llenado para botellones de cambio (vacíos -> en proceso).
     */
    public function startWashing(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ], [
            'quantity.required' => 'La cantidad a ingresar a lavado es obligatoria.',
            'quantity.integer' => 'La cantidad debe ser un número entero.',
            'quantity.min' => 'La cantidad debe ser al menos 1 garrafón.',
        ]);

        try {
            $this->plantInventoryService->startExchangeWashing($request->user(), (int) $validated['quantity']);

            return redirect()->route('planta.inventory.index')->with('success', "Lote de {$validated['quantity']} botellones ingresado a línea de lavado y purificación.");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors([$e->getErrorKey() => $e->getMessage()]);
        }
    }

    /**
     * Finaliza lote de lavado y llenado para botellones de cambio (en proceso -> llenos listos).
     */
    public function finishWashing(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ], [
            'quantity.required' => 'La cantidad de botellones llenos a ingresar a almacén es obligatoria.',
            'quantity.integer' => 'La cantidad debe ser un número entero.',
            'quantity.min' => 'La cantidad debe ser al menos 1 garrafón.',
        ]);

        try {
            $this->plantInventoryService->finishExchangeWashing($request->user(), (int) $validated['quantity']);

            return redirect()->route('planta.inventory.index')->with('success', "Lote de {$validated['quantity']} botellones purificados y sellados disponible en almacén de llenos.");
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors([$e->getErrorKey() => $e->getMessage()]);
        }
    }
}

