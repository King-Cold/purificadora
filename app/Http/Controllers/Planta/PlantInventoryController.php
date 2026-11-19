<?php

namespace App\Http\Controllers\Planta;

use App\Exceptions\OrderBusinessRuleException;
use App\Http\Controllers\Controller;
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
     * Pantalla 1: Existencias físicas de Planta y Camión.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Planta/Inventory/Index', $this->sharedPlantaProps());
    }

    /**
     * Pantalla 2: Incidencias y envases dañados / sin localizar.
     */
    public function incidents(Request $request): Response
    {
        return Inertia::render('Planta/Incidents/Index', $this->sharedPlantaProps());
    }

    /**
     * Pantalla 3: Historial de movimientos de custodia en Planta (con filtros).
     */
    public function history(Request $request): Response
    {
        $typeFilter = trim((string) $request->query('type', 'all'));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $query = \App\Models\BottleCustodyMovement::with(['customer:id,name,nickname', 'user:id,name', 'confirmedByUser:id,name'])
            ->where(function ($q) {
                $q->where('origin', 'plant')
                    ->orWhere('destination', 'plant')
                    ->orWhere('origin', 'physical_audit')
                    ->orWhere('origin', 'external_purchase')
                    ->orWhere('origin', 'incident_quarantine')
                    ->orWhere('movement_type', \App\Models\BottleCustodyMovement::TYPE_PLANT_INITIAL_COUNT)
                    ->orWhere('movement_type', \App\Models\BottleCustodyMovement::TYPE_PLANT_PHYSICAL_RECOUNT);
            });

        $typeMap = [
            'ajustes' => [
                \App\Models\BottleCustodyMovement::TYPE_PLANT_INITIAL_COUNT,
                \App\Models\BottleCustodyMovement::TYPE_PLANT_PHYSICAL_RECOUNT,
                \App\Models\BottleCustodyMovement::TYPE_EXCHANGE_NEW_ENTRY,
            ],
            'cargas' => [
                \App\Models\BottleCustodyMovement::TYPE_TRUCK_LOAD_EXCHANGE,
                \App\Models\BottleCustodyMovement::TYPE_TRUCK_LOAD_FIXED,
            ],
            'descargas' => [
                \App\Models\BottleCustodyMovement::TYPE_TRUCK_UNLOAD_EXCHANGE,
                \App\Models\BottleCustodyMovement::TYPE_PLANT_RECEPTION_FIXED,
            ],
            'llenado' => [
                \App\Models\BottleCustodyMovement::TYPE_PLANT_WASHING_FINISHED,
                \App\Models\BottleCustodyMovement::TYPE_PLANT_PREPARED_FIXED,
            ],
            'incidencias' => [
                \App\Models\BottleCustodyMovement::TYPE_BOTTLE_INCIDENT,
                \App\Models\BottleCustodyMovement::TYPE_EXCHANGE_INCONVENIENCE_REPORTED,
                \App\Models\BottleCustodyMovement::TYPE_EXCHANGE_WRITE_OFF,
                \App\Models\BottleCustodyMovement::TYPE_EXCHANGE_RECOVERY,
            ],
        ];

        if ($typeFilter !== '' && $typeFilter !== 'all' && isset($typeMap[$typeFilter])) {
            $query->whereIn('movement_type', $typeMap[$typeFilter]);
        }

        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $movements = $query
            ->orderBy('id', 'desc')
            ->limit(80)
            ->get();

        return Inertia::render('Planta/History/Index', [
            'movements' => $movements,
            'filters' => [
                'type' => $typeFilter ?: 'all',
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    private function sharedPlantaProps(): array
    {
        $state = $this->plantInventoryService->getInventoryState();

        $customersWithFixed = Customer::select('id', 'nickname', 'name')
            ->whereHas('orders.items', function ($q) {
                $q->where('type', OrderItem::TYPE_FIXED)
                  ->where('received_at_plant_quantity', '>', 0);
            })
            ->orderBy('nickname')
            ->get();

        return [
            'inventoryState' => $state,
            'customersWithFixed' => $customersWithFixed,
            'canRecordInitialCount' => auth()->user()->hasRole(['Operador Planta', 'Administrador', 'Super Admin']),
            'canReportIncident' => auth()->user()->hasRole(['Operador Planta', 'Administrador', 'Super Admin']),
            'canEditTarget' => auth()->user()->hasRole(['Administrador', 'Super Admin']),
        ];
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
     * Permite recontar y editar físicamente los botellones de cambio de 20L en Planta.
     */
    public function reconcileCount(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user->hasRole(['Operador Planta', 'Administrador', 'Super Admin'])) {
            abort(403, 'No autorizado para recontar existencias de Planta.');
        }

        $validated = $request->validate([
            'empty_count' => ['required', 'integer', 'min:0', 'max:10000'],
            'washing_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'full_count' => ['required', 'integer', 'min:0', 'max:10000'],
            'truck_full_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'truck_empty_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'reason' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
            'sync_target' => ['nullable', 'boolean'],
        ], [
            'empty_count.required' => 'La cantidad de vacíos en Planta es obligatoria.',
            'full_count.required' => 'La cantidad de llenos en Planta es obligatoria.',
            'reason.required' => 'Indica el motivo del ajuste o reinicio físico.',
        ]);

        try {
            $canSyncTarget = $user->hasRole(['Administrador', 'Super Admin']);
            $result = $this->plantInventoryService->reconcilePhysicalCount(
                $user,
                (int) $validated['empty_count'],
                (int) ($validated['washing_count'] ?? 0),
                (int) $validated['full_count'],
                $validated['reason'],
                $validated['notes'] ?? null,
                $canSyncTarget && $request->boolean('sync_target', false),
                isset($validated['truck_full_count']) ? (int) $validated['truck_full_count'] : null,
                isset($validated['truck_empty_count']) ? (int) $validated['truck_empty_count'] : null
            );

            return back()->with(
                'success',
                "Ajuste actualizado: Planta {$result['plant_after']} u. · Total circuito {$result['active_after']} u."
            );
        } catch (OrderBusinessRuleException $e) {
            return back()->withErrors([$e->getErrorKey() => $e->getMessage()]);
        }
    }

    /**
     * Registra una incidencia unificada de botellones (daño físico o sin localizar).
     */
    public function storeIncident(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bottle_type' => ['required', 'string', 'in:fijo,cambio'],
            'customer_id' => ['nullable', 'required_if:bottle_type,fijo', 'exists:customers,id'],
            'order_item_id' => ['nullable', 'exists:order_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'stage' => ['required', 'string', 'in:recepcion,almacen_vacio,lavado,llenado,almacen,almacen_planta,preparado,carga,carga_camion,en_camion,ruta'],
            'bottle_condition' => ['nullable', 'string', 'in:empty,full'],
            'location_status' => ['nullable', 'string', 'in:separado,sin_localizar'],
            'damage_type' => ['required', 'string', 'in:rotura,fisura,contaminacion,deformacion,otro'],
            'notes' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'bottle_type.required' => 'El tipo de botellón es obligatorio.',
            'bottle_type.in' => 'El tipo de botellón debe ser fijo o de cambio.',
            'customer_id.required_if' => 'Debe seleccionar el cliente propietario de los botellones fijos.',
            'quantity.required' => 'La cantidad con daño es obligatoria.',
            'quantity.integer' => 'La cantidad debe ser un número entero.',
            'quantity.min' => 'La cantidad debe ser mayor a cero.',
            'stage.required' => 'Debe indicar dónde ocurrió la incidencia.',
            'damage_type.required' => 'Debe clasificar el tipo de daño detectado.',
            'notes.required' => 'Debe describir brevemente qué ocurrió.',
            'notes.min' => 'La descripción debe tener al menos 3 caracteres.',
        ]);

        try {
            if (($validated['bottle_type'] ?? '') === 'cambio' && ($validated['location_status'] ?? 'separado') === 'sin_localizar') {
                $cond = $validated['bottle_condition'] ?? 'empty';
                $originStage = in_array($validated['stage'], ['en_camion', 'ruta'], true)
                    ? ($cond === 'full' ? 'truck_full' : 'truck_empty')
                    : ($cond === 'full' ? 'plant_full' : 'plant_empty');

                $this->plantInventoryService->reportExchangeInconvenience($request->user(), [
                    'quantity' => (int) $validated['quantity'],
                    'origin_stage' => $originStage,
                    'reason' => $validated['damage_type'],
                    'location_status' => 'sin_localizar',
                    'notes' => $validated['notes'],
                ]);
            } else {
                $this->plantInventoryService->recordBottleIncident($request->user(), $validated);
            }

            return redirect()->route('planta.incidents.index')->with('success', 'Incidencia registrada correctamente.');
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

