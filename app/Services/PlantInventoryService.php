<?php

namespace App\Services;

use App\Exceptions\OrderBusinessRuleException;
use App\Models\BottleCustodyMovement;
use App\Models\BottleIncident;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\PlantInventory;
use App\Models\PlantProductionBatch;
use App\Models\TruckInventory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PlantInventoryService
{
    /**
     * Obtiene o crea el registro singleton de existencias de planta.
     */
    public function getOrCreatePlantInventory(): PlantInventory
    {
        return PlantInventory::firstOrCreate(
            ['id' => 1],
            [
                'exchange_empty_available' => 0,
                'exchange_washing_filling' => 0,
                'exchange_full_available' => 0,
                'exchange_full_reserved' => 0,
                'initial_count_completed_at' => null,
                'initial_count_user_id' => null,
                'initial_empty_count' => null,
                'initial_full_count' => null,
                'initial_count_notes' => null,
            ]
        );
    }

    /**
     * Determina si el conteo inicial físico está pendiente de realizarse.
     */
    public function isInitialCountPending(): bool
    {
        $inv = $this->getOrCreatePlantInventory();
        return is_null($inv->initial_count_completed_at);
    }

    /**
     * Registra el conteo físico inicial de botellones de cambio de 20L presentes en Planta.
     * Define la línea base física real. Impide duplicidad y concilia sin borrar movimientos previos.
     */
    public function recordInitialCount(
        User $operator,
        int $emptyCount,
        int $fullCount,
        ?string $notes = null
    ): PlantInventory {
        if ($emptyCount < 0) {
            throw new OrderBusinessRuleException('La cantidad de botellones vacíos no puede ser negativa.', 'empty_count', 422);
        }

        if ($fullCount < 0) {
            throw new OrderBusinessRuleException('La cantidad de botellones llenos no puede ser negativa.', 'full_count', 422);
        }

        return DB::transaction(function () use ($operator, $emptyCount, $fullCount, $notes) {
            $inv = PlantInventory::lockForUpdate()->firstOrCreate(
                ['id' => 1],
                [
                    'exchange_empty_available' => 0,
                    'exchange_washing_filling' => 0,
                    'exchange_full_available' => 0,
                    'exchange_full_reserved' => 0,
                ]
            );

            // Validación estricta contra duplicidad
            if (!is_null($inv->initial_count_completed_at)) {
                $formattedDate = $inv->initial_count_completed_at->format('d/m/Y H:i');
                throw new OrderBusinessRuleException(
                    "El conteo inicial físico de planta ya fue registrado el {$formattedDate} y no puede aplicarse dos veces.",
                    'initial_count',
                    422
                );
            }

            // Comprobación de movimientos previos sin conciliar
            $priorMovementsCount = BottleCustodyMovement::where(function ($q) {
                $q->where('origin', 'plant')->orWhere('destination', 'plant');
            })->where('movement_type', '!=', BottleCustodyMovement::TYPE_PLANT_INITIAL_COUNT)->count();

            if ($priorMovementsCount > 0) {
                throw new OrderBusinessRuleException(
                    "Existen {$priorMovementsCount} movimiento(s) de custodia de planta previos sin conciliar. Debe auditar y conciliar los movimientos existentes con Administración antes de establecer un nuevo conteo inicial.",
                    'initial_count',
                    422
                );
            }

            // Conciliación de línea base física:
            // Los saldos en planta quedan establecidos con el conteo presencial verificado
            $inv->update([
                'exchange_empty_available' => $emptyCount,
                'exchange_washing_filling' => 0,
                'exchange_full_available' => $fullCount,
                'exchange_full_reserved' => 0,
                'initial_empty_count' => $emptyCount,
                'initial_full_count' => $fullCount,
                'initial_count_completed_at' => now(),
                'initial_count_user_id' => $operator->id,
                'initial_count_notes' => $notes ? trim($notes) : null,
            ]);

            // Registrar movimiento auditable en la bitácora de custodia
            $totalCounted = $emptyCount + $fullCount;
            $auditNotes = "Apertura de inventario físico de planta: {$emptyCount} vacíos, {$fullCount} llenos.";
            if (!empty($notes)) {
                $auditNotes .= ' Obs: ' . trim($notes);
            }

            BottleCustodyMovement::create([
                'user_id' => $operator->id,
                'movement_type' => BottleCustodyMovement::TYPE_PLANT_INITIAL_COUNT,
                'bottle_type' => OrderItem::TYPE_EXCHANGE,
                'bottle_condition' => BottleCustodyMovement::CONDITION_FULL,
                'quantity' => $totalCounted,
                'proposed_quantity' => $totalCounted,
                'origin' => 'physical_audit',
                'destination' => 'plant',
                'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                'confirmed_by_user_id' => $operator->id,
                'confirmed_at' => now(),
                'notes' => $auditNotes,
                'created_at' => now(),
            ]);

            return $inv->fresh('initialCountUser');
        });
    }

    /**
     * Consulta el estado consolidado de existencias, fijos por cliente, incidencias y movimientos.
     */
    public function getInventoryState(): array
    {
        $inv = $this->getOrCreatePlantInventory();
        $isPending = is_null($inv->initial_count_completed_at);

        // Fijos de clientes presentes en planta (fijos recibidos en planta no entregados aún al camión o con daño)
        $fixedItems = OrderItem::with(['order.customer'])
            ->where('type', OrderItem::TYPE_FIXED)
            ->where(function ($q) {
                $q->where('received_at_plant_quantity', '>', 0)
                  ->orWhere('damaged_quantity', '>', 0);
            })
            ->whereColumn('received_at_plant_quantity', '>', 'loaded_quantity')
            ->get();

        $fixedByCustomer = [];
        foreach ($fixedItems as $item) {
            $order = $item->order;
            $customer = $order?->customer;
            if (!$customer) {
                continue;
            }

            $received = (int) $item->received_at_plant_quantity;
            $prepared = (int) $item->prepared_quantity;
            $loaded = (int) $item->loaded_quantity;
            $damaged = (int) $item->damaged_quantity;

            // Cantidad en lavado / preparación: recibidos menos los ya preparados y menos los dañados
            $inWashing = max(0, $received - $prepared - $damaged);
            // Cantidad lista para entregar al camión: preparados menos los ya subidos al camión
            $readyFull = max(0, $prepared - $loaded);

            if ($inWashing > 0 || $readyFull > 0 || $damaged > 0) {
                $fixedByCustomer[] = [
                    'order_item_id' => $item->id,
                    'order_id' => $order->id,
                    'order_folio' => $order->folio,
                    'customer_id' => $customer->id,
                    'customer_code' => $customer->code ?? "CLI-{$customer->id}",
                    'customer_nickname' => $customer->nickname ?? '',
                    'customer_name' => $customer->name ?? '',
                    'received_at_plant' => $received,
                    'in_washing' => $inWashing,
                    'ready_full' => $readyFull,
                    'damaged' => $damaged,
                    'item_status' => $item->status,
                ];
            }
        }

        // Total de botellones de cambio con incidencias pendientes de resolución
        $exchangeDamagedTotal = (int) BottleIncident::where('bottle_type', OrderItem::TYPE_EXCHANGE)
            ->where('status', BottleIncident::STATUS_PENDING)
            ->sum('quantity');

        // Total de fijos con incidencias pendientes
        $fixedDamagedTotal = (int) BottleIncident::where('bottle_type', OrderItem::TYPE_FIXED)
            ->where('status', BottleIncident::STATUS_PENDING)
            ->sum('quantity');

        // Historial reciente de movimientos en planta (últimos 30)
        $recentMovements = BottleCustodyMovement::with(['customer', 'user', 'confirmedByUser'])
            ->where(function ($q) {
                $q->where('origin', 'plant')
                  ->orWhere('destination', 'plant')
                  ->orWhere('movement_type', BottleCustodyMovement::TYPE_PLANT_INITIAL_COUNT);
            })
            ->orderBy('id', 'desc')
            ->limit(30)
            ->get();

        // Incidencias recientes (últimas 30)
        $incidents = BottleIncident::with(['customer', 'order', 'reportedByUser', 'resolvedByUser'])
            ->orderBy('id', 'desc')
            ->limit(30)
            ->get();

        return [
            'initial_count_pending' => $isPending,
            'initial_count_info' => $isPending ? null : [
                'completed_at' => $inv->initial_count_completed_at?->format('d/m/Y H:i'),
                'operator_name' => $inv->initialCountUser?->name ?? 'Operador de Planta',
                'empty_count' => $inv->initial_empty_count,
                'full_count' => $inv->initial_full_count,
                'notes' => $inv->initial_count_notes,
            ],
            'balances' => [
                'exchange_empty_available' => (int) $inv->exchange_empty_available,
                'exchange_washing_filling' => (int) $inv->exchange_washing_filling,
                'exchange_full_available' => (int) $inv->exchange_full_available,
                'exchange_full_reserved' => (int) $inv->exchange_full_reserved,
                'exchange_damaged_total' => $exchangeDamagedTotal,
                'fixed_damaged_total' => $fixedDamagedTotal,
                'exchange_total_physical' => (int) ($inv->exchange_empty_available + $inv->exchange_washing_filling + $inv->exchange_full_available + $inv->exchange_full_reserved + $exchangeDamagedTotal),
            ],
            'fixed_in_plant_by_customer' => $fixedByCustomer,
            'incidents' => $incidents,
            'recent_movements' => $recentMovements,
        ];
    }

    /**
     * Registra una incidencia de daño físico en botellones (fijos o de cambio).
     *
     * REGLAS:
     * - Vincula cliente propietario, pedido, cantidad, etapa, responsable y fecha.
     * - Separa los dañados de los disponibles para preparar o entregar.
     * - NO descuenta automáticamente fixed_bottles_count del cliente.
     * - NO genera cobro de rellenado ni modifica cargos.
     * - NO marca como devuelto ni cierra el pedido.
     * - Resolución corresponde a Administración.
     */
    public function recordBottleIncident(User $reporter, array $data): BottleIncident
    {
        $bottleType = trim((string) ($data['bottle_type'] ?? ''));
        if (!in_array($bottleType, [OrderItem::TYPE_FIXED, OrderItem::TYPE_EXCHANGE], true)) {
            throw new OrderBusinessRuleException('Modalidad de envase inválida para registrar incidencia.', 'bottle_type', 422);
        }

        $quantity = (int) ($data['quantity'] ?? 0);
        if ($quantity <= 0) {
            throw new OrderBusinessRuleException('La cantidad dañada debe ser mayor a cero.', 'quantity', 422);
        }

        $stage = trim((string) ($data['stage'] ?? ''));
        $validStages = [
            BottleIncident::STAGE_RECEPTION,
            BottleIncident::STAGE_WASHING,
            BottleIncident::STAGE_FILLING,
            BottleIncident::STAGE_STORAGE,
            BottleIncident::STAGE_LOADING,
        ];
        if (!in_array($stage, $validStages, true)) {
            throw new OrderBusinessRuleException('Etapa operativa inválida.', 'stage', 422);
        }

        $damageType = trim((string) ($data['damage_type'] ?? ''));
        $validDamages = [
            BottleIncident::DAMAGE_BROKEN,
            BottleIncident::DAMAGE_CRACKED,
            BottleIncident::DAMAGE_CONTAMINATED,
            BottleIncident::DAMAGE_DEFORMED,
            BottleIncident::DAMAGE_OTHER,
        ];
        if (!in_array($damageType, $validDamages, true)) {
            throw new OrderBusinessRuleException('Tipo de daño inválido.', 'damage_type', 422);
        }

        $notes = trim((string) ($data['notes'] ?? ''));
        if (strlen($notes) < 5) {
            throw new OrderBusinessRuleException('Debe proporcionar una descripción del daño de al menos 5 caracteres.', 'notes', 422);
        }

        return DB::transaction(function () use ($reporter, $bottleType, $quantity, $stage, $damageType, $notes, $data) {
            $customerId = null;
            $orderId = null;
            $orderItemId = null;
            $condition = $data['bottle_condition'] ?? BottleCustodyMovement::CONDITION_EMPTY;

            if ($bottleType === OrderItem::TYPE_FIXED) {
                if (empty($data['customer_id'])) {
                    throw new OrderBusinessRuleException('Los envases fijos requieren especificar el cliente propietario.', 'customer_id', 422);
                }

                $customer = Customer::lockForUpdate()->find($data['customer_id']);
                if (!$customer) {
                    throw new OrderBusinessRuleException('El cliente propietario especificado no existe.', 'customer_id', 404);
                }
                $customerId = $customer->id;

                // Si se vinculó a un pedido / detalle
                if (!empty($data['order_item_id'])) {
                    $fixedItem = OrderItem::lockForUpdate()->find($data['order_item_id']);
                    if (!$fixedItem || $fixedItem->type !== OrderItem::TYPE_FIXED) {
                        throw new OrderBusinessRuleException('El detalle del pedido especificado no corresponde a botellones fijos.', 'order_item_id', 422);
                    }
                    $orderItemId = $fixedItem->id;
                    $orderId = $fixedItem->order_id;

                    // Validar existencias disponibles de este fijo en planta
                    $received = (int) $fixedItem->received_at_plant_quantity;
                    $damaged = (int) $fixedItem->damaged_quantity;
                    $prepared = (int) $fixedItem->prepared_quantity;
                    $loaded = (int) $fixedItem->loaded_quantity;

                    // Fijos en planta aptos (no cargados aún y no dañados previamente)
                    $availableInPlant = max(0, $received - $damaged - $loaded);
                    if ($quantity > $availableInPlant) {
                        throw new OrderBusinessRuleException(
                            "No se pueden registrar {$quantity} fijos dañados. Disponibles en planta para este pedido: {$availableInPlant}.",
                            'quantity',
                            422
                        );
                    }

                    // Separar los dañados de los disponibles:
                    $fixedItem->increment('damaged_quantity', $quantity);
                }
            } else {
                // Botellón de cambio genérico: descontar de las existencias de planta según la etapa
                $inv = PlantInventory::lockForUpdate()->firstOrCreate(['id' => 1]);

                if (in_array($stage, [BottleIncident::STAGE_RECEPTION, BottleIncident::STAGE_WASHING], true)) {
                    // Si estaba en lavado o recibido vacío
                    if ($stage === BottleIncident::STAGE_WASHING && $inv->exchange_washing_filling >= $quantity) {
                        $inv->decrement('exchange_washing_filling', $quantity);
                    } else {
                        if ($inv->exchange_empty_available < $quantity) {
                            throw new OrderBusinessRuleException(
                                "No hay suficientes botellones vacíos en planta para reportar merma. Disponibles: {$inv->exchange_empty_available}, reportados: {$quantity}.",
                                'quantity',
                                422
                            );
                        }
                        $inv->decrement('exchange_empty_available', $quantity);
                    }
                    $condition = BottleCustodyMovement::CONDITION_EMPTY;
                } else {
                    // En llenado, almacén o carga (estaba lleno)
                    if ($inv->exchange_full_available < $quantity) {
                        throw new OrderBusinessRuleException(
                            "No hay suficientes botellones llenos disponibles en planta para reportar merma. Disponibles: {$inv->exchange_full_available}, reportados: {$quantity}.",
                            'quantity',
                            422
                        );
                    }
                    $inv->decrement('exchange_full_available', $quantity);
                    $condition = BottleCustodyMovement::CONDITION_FULL;
                }
            }

            // Crear registro de incidencia
            $incident = BottleIncident::create([
                'customer_id' => $customerId,
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'reported_by_user_id' => $reporter->id,
                'bottle_type' => $bottleType,
                'bottle_condition' => $condition,
                'quantity' => $quantity,
                'stage' => $stage,
                'damage_type' => $damageType,
                'status' => BottleIncident::STATUS_PENDING,
                'notes' => $notes,
            ]);

            // Crear movimiento de custodia auditada
            BottleCustodyMovement::create([
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'customer_id' => $customerId,
                'user_id' => $reporter->id,
                'movement_type' => BottleCustodyMovement::TYPE_BOTTLE_INCIDENT,
                'bottle_type' => $bottleType,
                'bottle_condition' => $condition,
                'quantity' => $quantity,
                'proposed_quantity' => $quantity,
                'origin' => 'plant',
                'destination' => 'incident_quarantine',
                'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                'confirmed_by_user_id' => $reporter->id,
                'confirmed_at' => now(),
                'difference_reason' => "Incidencia [{$damageType}] en etapa [{$stage}]: {$notes}",
                'notes' => "Incidencia #{$incident->id} reportada por {$reporter->name}",
                'created_at' => now(),
            ]);

            return $incident->load(['customer', 'order', 'reportedByUser']);
        });
    }

    /**
     * Inicia el proceso de lavado/llenado para botellones de cambio genéricos:
     * vacíos disponibles -> en lavado/llenado.
     * No convierte directamente vacíos en llenos.
     */
    public function startExchangeWashing(User $operator, int $quantity): void
    {
        if ($quantity <= 0) {
            throw new OrderBusinessRuleException('La cantidad a lavar debe ser mayor a cero.', 'quantity', 422);
        }

        DB::transaction(function () use ($operator, $quantity) {
            $inv = PlantInventory::lockForUpdate()->firstOrCreate(['id' => 1]);

            if ($inv->exchange_empty_available < $quantity) {
                throw new OrderBusinessRuleException(
                    "No hay suficientes botellones vacíos disponibles. Disponibles: {$inv->exchange_empty_available}, solicitados: {$quantity}.",
                    'quantity',
                    422
                );
            }

            $inv->decrement('exchange_empty_available', $quantity);
            $inv->increment('exchange_washing_filling', $quantity);

            BottleCustodyMovement::create([
                'user_id' => $operator->id,
                'movement_type' => BottleCustodyMovement::TYPE_PLANT_WASHING_STARTED,
                'bottle_type' => OrderItem::TYPE_EXCHANGE,
                'bottle_condition' => BottleCustodyMovement::CONDITION_EMPTY,
                'quantity' => $quantity,
                'origin' => 'plant_empty_storage',
                'destination' => 'plant_washing_line',
                'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                'confirmed_by_user_id' => $operator->id,
                'confirmed_at' => now(),
                'notes' => "Lote de {$quantity} botellones ingresado a línea de lavado y purificación",
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Finaliza el proceso de lavado/llenado para botellones de cambio genéricos:
     * en lavado/llenado -> llenos disponibles.
     */
    public function finishExchangeWashing(User $operator, int $quantity): void
    {
        if ($quantity <= 0) {
            throw new OrderBusinessRuleException('La cantidad terminada debe ser mayor a cero.', 'quantity', 422);
        }

        DB::transaction(function () use ($operator, $quantity) {
            $inv = PlantInventory::lockForUpdate()->firstOrCreate(['id' => 1]);

            if ($inv->exchange_washing_filling < $quantity) {
                throw new OrderBusinessRuleException(
                    "No hay suficientes botellones en proceso de lavado. En proceso: {$inv->exchange_washing_filling}, solicitados: {$quantity}.",
                    'quantity',
                    422
                );
            }

            $inv->decrement('exchange_washing_filling', $quantity);
            $inv->increment('exchange_full_available', $quantity);

            BottleCustodyMovement::create([
                'user_id' => $operator->id,
                'movement_type' => BottleCustodyMovement::TYPE_PLANT_WASHING_FINISHED,
                'bottle_type' => OrderItem::TYPE_EXCHANGE,
                'bottle_condition' => BottleCustodyMovement::CONDITION_FULL,
                'quantity' => $quantity,
                'origin' => 'plant_washing_line',
                'destination' => 'plant_full_storage',
                'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                'confirmed_by_user_id' => $operator->id,
                'confirmed_at' => now(),
                'notes' => "Lote de {$quantity} botellones purificados, sellados y almacenados como llenos listos",
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Reserva botellones llenos para una propuesta de carga hacia el camión.
     */
    public function reserveExchangeForTruck(int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $inv = PlantInventory::lockForUpdate()->firstOrCreate(['id' => 1]);

        // Si ya completó conteo inicial, validamos existencias estrictamente
        if (!is_null($inv->initial_count_completed_at)) {
            if ($inv->exchange_full_available < $quantity) {
                throw new OrderBusinessRuleException(
                    "No hay suficientes botellones de cambio llenos en planta para transferir. Disponibles: {$inv->exchange_full_available}, requeridos: {$quantity}.",
                    'quantity',
                    422
                );
            }
            $inv->decrement('exchange_full_available', $quantity);
            $inv->increment('exchange_full_reserved', $quantity);
        }
    }

    /**
     * Libera una reserva de botellones llenos (por rechazo de carga o discrepancia).
     */
    public function releaseExchangeReservation(int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $inv = PlantInventory::lockForUpdate()->firstOrCreate(['id' => 1]);

        if (!is_null($inv->initial_count_completed_at)) {
            $toRelease = min($quantity, $inv->exchange_full_reserved);
            $inv->decrement('exchange_full_reserved', $toRelease);
            $inv->increment('exchange_full_available', $toRelease);
        }
    }

    /**
     * Confirma la salida definitiva de planta al camión (al ser aceptada por el repartidor).
     */
    public function commitExchangeTruckLoad(int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $inv = PlantInventory::lockForUpdate()->firstOrCreate(['id' => 1]);

        if (!is_null($inv->initial_count_completed_at)) {
            $toCommit = min($quantity, $inv->exchange_full_reserved);
            $inv->decrement('exchange_full_reserved', $toCommit);
        }
    }

    /**
     * Incrementa los botellones vacíos disponibles al recibir físicamente una descarga del camión.
     */
    public function receiveExchangeEmptyFromTruck(int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $inv = PlantInventory::lockForUpdate()->firstOrCreate(['id' => 1]);

        if (!is_null($inv->initial_count_completed_at)) {
            $inv->increment('exchange_empty_available', $quantity);
        }
    }

    /**
     * Consulta las propuestas de entrega y botellones pendientes de recepción física en Planta.
     */
    public function getPendingReceptions(): array
    {
        // 1. Fijos vacíos recogidos por el chofer pendientes de cotejo físico en planta
        $fixedItems = OrderItem::with(['order.customer', 'order.deliveryRoute.driver'])
            ->where('type', OrderItem::TYPE_FIXED)
            ->where('collected_quantity', '>', 0)
            ->whereColumn('collected_quantity', '>', 'received_at_plant_quantity')
            ->get();

        $fixedPending = [];
        foreach ($fixedItems as $item) {
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
                'collected_quantity' => (int) $item->collected_quantity,
                'received_at_plant_quantity' => (int) $item->received_at_plant_quantity,
                'damaged_quantity' => (int) $item->damaged_quantity,
                'pending_quantity' => $pendingToReceive,
                'status' => $item->status,
                'driver_name' => $order->deliveryRoute?->driver?->name ?? 'Repartidor',
                'created_at' => $order->created_at?->format('d/m/Y H:i'),
            ];
        }

        // 2. Cambios vacíos propuestos desde el camión hacia la planta
        $exchangeMovements = BottleCustodyMovement::with(['user'])
            ->where('bottle_type', OrderItem::TYPE_EXCHANGE)
            ->where('bottle_condition', BottleCustodyMovement::CONDITION_EMPTY)
            ->where('destination', 'plant')
            ->where('status', BottleCustodyMovement::STATUS_PROPOSED)
            ->orderBy('id', 'desc')
            ->get();

        $exchangePending = [];
        foreach ($exchangeMovements as $mov) {
            $exchangePending[] = [
                'movement_id' => $mov->id,
                'reference' => "TRF-{$mov->id}",
                'driver_id' => $mov->user_id,
                'driver_name' => $mov->user?->name ?? 'Repartidor',
                'quantity_declared' => (int) ($mov->proposed_quantity ?? $mov->quantity),
                'notes' => $mov->notes,
                'created_at' => $mov->created_at?->format('d/m/Y H:i'),
                'status' => $mov->status,
            ];
        }

        // 3. Recepciones recientes completadas o con discrepancia (últimas 25)
        $recentReceptions = BottleCustodyMovement::with(['customer', 'order', 'user', 'confirmedByUser'])
            ->where('destination', 'plant')
            ->whereIn('movement_type', [
                BottleCustodyMovement::TYPE_PLANT_RECEPTION_FIXED,
                BottleCustodyMovement::TYPE_TRUCK_UNLOAD_EXCHANGE,
            ])
            ->whereIn('status', [BottleCustodyMovement::STATUS_ACCEPTED, 'accepted_with_discrepancy'])
            ->orderBy('id', 'desc')
            ->limit(25)
            ->get();

        return [
            'fixed_pending' => $fixedPending,
            'exchange_pending' => $exchangePending,
            'recent_receptions' => $recentReceptions,
            'total_pending_count' => count($fixedPending) + count($exchangePending),
        ];
    }

    /**
     * Confirma la recepción física presencial de botellones fijos de un cliente en Planta.
     */
    public function confirmFixedReception(
        User $operator,
        OrderItem $item,
        int $receivedQuantity,
        ?string $differenceReason = null,
        bool $startWashing = false,
        int $damagedQuantity = 0,
        ?string $damageType = null,
        ?string $damageNotes = null
    ): array {
        if ($receivedQuantity < 0) {
            throw new OrderBusinessRuleException('La cantidad recibida no puede ser negativa.', 'received_quantity', 422);
        }

        if ($damagedQuantity < 0) {
            throw new OrderBusinessRuleException('La cantidad dañada no puede ser negativa.', 'damaged_quantity', 422);
        }

        return DB::transaction(function () use (
            $operator, $item, $receivedQuantity, $differenceReason, $startWashing,
            $damagedQuantity, $damageType, $damageNotes
        ) {
            $lockedItem = OrderItem::lockForUpdate()->findOrFail($item->id);
            $lockedOrder = Order::lockForUpdate()->findOrFail($lockedItem->order_id);

            $pendingToReceive = max(0, (int)$lockedItem->collected_quantity - (int)$lockedItem->received_at_plant_quantity - (int)$lockedItem->damaged_quantity);

            $totalHandled = $receivedQuantity + $damagedQuantity;
            if ($totalHandled > $pendingToReceive) {
                throw new OrderBusinessRuleException(
                    "La cantidad total procesada ({$totalHandled}) excede lo pendiente de recibir ({$pendingToReceive}).",
                    'received_quantity',
                    422
                );
            }

            // Si hay faltantes respecto al pendiente, exigir motivo
            $shortage = $pendingToReceive - $totalHandled;
            $trimmedReason = trim((string) $differenceReason);

            if ($shortage > 0 && strlen($trimmedReason) < 5) {
                throw new OrderBusinessRuleException(
                    "Debe especificar el motivo obligatorio de la diferencia ({$shortage} envase(s) faltante(s)).",
                    'difference_reason',
                    422
                );
            }

            // Si hay dañados detectados al recibir, registrar incidencia separada
            if ($damagedQuantity > 0) {
                $trimmedDamageNotes = trim((string) $damageNotes);
                if (strlen($trimmedDamageNotes) < 5) {
                    throw new OrderBusinessRuleException('Debe describir el daño físico detectado al recibir (mínimo 5 caracteres).', 'damage_notes', 422);
                }

                $validDamage = in_array($damageType, [
                    BottleIncident::DAMAGE_BROKEN,
                    BottleIncident::DAMAGE_CRACKED,
                    BottleIncident::DAMAGE_CONTAMINATED,
                    BottleIncident::DAMAGE_DEFORMED,
                    BottleIncident::DAMAGE_OTHER,
                ], true) ? $damageType : BottleIncident::DAMAGE_BROKEN;

                BottleIncident::create([
                    'customer_id' => $lockedOrder->customer_id,
                    'order_id' => $lockedOrder->id,
                    'order_item_id' => $lockedItem->id,
                    'reported_by_user_id' => $operator->id,
                    'bottle_type' => OrderItem::TYPE_FIXED,
                    'bottle_condition' => BottleCustodyMovement::CONDITION_EMPTY,
                    'quantity' => $damagedQuantity,
                    'stage' => BottleIncident::STAGE_RECEPTION,
                    'damage_type' => $validDamage,
                    'status' => BottleIncident::STATUS_PENDING,
                    'notes' => $trimmedDamageNotes,
                ]);

                $lockedItem->increment('damaged_quantity', $damagedQuantity);
            }

            // Actualizar fijos recibidos aptos
            if ($receivedQuantity > 0) {
                $lockedItem->increment('received_at_plant_quantity', $receivedQuantity);
            }

            // Determinar si entra a lavado
            if ($startWashing && $receivedQuantity > 0) {
                $lockedItem->increment('washing_quantity', $receivedQuantity);
                $lockedItem->update(['status' => OrderItem::STATUS_WASHING]);
            }

            $movementStatus = ($shortage === 0) ? BottleCustodyMovement::STATUS_ACCEPTED : 'accepted_with_discrepancy';

            // Auditoría física de custodia
            $notes = "Recepción física de fijos: {$receivedQuantity} aptos recibidos";
            if ($damagedQuantity > 0) {
                $notes .= ", {$damagedQuantity} dañados en recepción";
            }
            if ($shortage > 0) {
                $notes .= " | Discrepancia pendiente de aclaración ({$shortage} faltantes): {$trimmedReason}";
            }

            $movement = BottleCustodyMovement::create([
                'order_id' => $lockedOrder->id,
                'order_item_id' => $lockedItem->id,
                'customer_id' => $lockedOrder->customer_id,
                'user_id' => $operator->id,
                'movement_type' => BottleCustodyMovement::TYPE_PLANT_RECEPTION_FIXED,
                'bottle_type' => OrderItem::TYPE_FIXED,
                'bottle_condition' => BottleCustodyMovement::CONDITION_EMPTY,
                'quantity' => $receivedQuantity,
                'proposed_quantity' => $pendingToReceive,
                'origin' => 'truck',
                'destination' => 'plant',
                'status' => $movementStatus,
                'confirmed_by_user_id' => $operator->id,
                'confirmed_at' => now(),
                'difference_reason' => $shortage > 0 ? $trimmedReason : null,
                'notes' => $notes,
                'created_at' => now(),
            ]);

            // Asegurar que la orden mantenga estado En Proceso
            if ($lockedOrder->status !== Order::STATUS_IN_PROCESS && !$lockedOrder->isCancelled() && !$lockedOrder->isDelivered()) {
                $previousStatus = $lockedOrder->status;
                $lockedOrder->update(['status' => Order::STATUS_IN_PROCESS]);

                OrderStatusHistory::create([
                    'order_id' => $lockedOrder->id,
                    'previous_status' => $previousStatus,
                    'new_status' => Order::STATUS_IN_PROCESS,
                    'user_id' => $operator->id,
                    'reason' => "Fijos recibidos en planta ({$receivedQuantity} u.)" . ($startWashing ? " - Lavado iniciado" : ""),
                    'created_at' => now(),
                ]);
            }

            $customer = $lockedOrder->customer;
            $customerLabel = $customer ? ($customer->nickname ?: $customer->name) . " ({$customer->code})" : 'Cliente';

            return [
                'receipt_folio' => "REC-FIJ-{$movement->id}",
                'timestamp' => now()->format('d/m/Y H:i'),
                'operator_name' => $operator->name,
                'type' => 'fijo',
                'customer' => $customerLabel,
                'order_folio' => $lockedOrder->folio,
                'proposed_quantity' => $pendingToReceive,
                'received_quantity' => $receivedQuantity,
                'damaged_quantity' => $damagedQuantity,
                'shortage_quantity' => $shortage,
                'difference_reason' => $shortage > 0 ? $trimmedReason : null,
                'washing_started' => $startWashing && $receivedQuantity > 0,
                'status' => $movementStatus,
            ];
        });
    }

    /**
     * Confirma la recepción física presencial de botellones de cambio descargados por el chofer.
     */
    public function confirmExchangeReception(
        User $operator,
        BottleCustodyMovement $movement,
        int $receivedQuantity,
        ?string $differenceReason = null,
        int $damagedQuantity = 0,
        ?string $damageType = null,
        ?string $damageNotes = null
    ): array {
        if ($receivedQuantity < 0) {
            throw new OrderBusinessRuleException('La cantidad recibida no puede ser negativa.', 'received_quantity', 422);
        }

        if ($damagedQuantity < 0) {
            throw new OrderBusinessRuleException('La cantidad dañada no puede ser negativa.', 'damaged_quantity', 422);
        }

        // Restricción: el chofer no puede auto-confirmarse recepciones de planta a menos que sea Administrador
        if ($movement->user_id === $operator->id && !$operator->hasRole(['Administrador', 'Super Admin'])) {
            throw new OrderBusinessRuleException('El repartidor no puede confirmar recepciones en planta para sí mismo.', 'operator', 403);
        }

        return DB::transaction(function () use (
            $operator, $movement, $receivedQuantity, $differenceReason,
            $damagedQuantity, $damageType, $damageNotes
        ) {
            $lockedMovement = BottleCustodyMovement::lockForUpdate()->findOrFail($movement->id);

            if ($lockedMovement->status !== BottleCustodyMovement::STATUS_PROPOSED) {
                throw new OrderBusinessRuleException('Esta entrega ya ha sido procesada previamente.', 'status', 422);
            }

            if ($lockedMovement->destination !== 'plant') {
                throw new OrderBusinessRuleException('Solo se pueden confirmar transferencias con destino a planta.', 'destination', 422);
            }

            $proposed = (int) ($lockedMovement->proposed_quantity ?? $lockedMovement->quantity);
            $totalHandled = $receivedQuantity + $damagedQuantity;

            if ($totalHandled > $proposed) {
                throw new OrderBusinessRuleException(
                    "La cantidad total procesada ({$totalHandled}) excede lo declarado ({$proposed}).",
                    'received_quantity',
                    422
                );
            }

            $shortage = $proposed - $totalHandled;
            $trimmedReason = trim((string) $differenceReason);

            if ($shortage > 0 && strlen($trimmedReason) < 5) {
                throw new OrderBusinessRuleException(
                    "Debe especificar el motivo obligatorio de la diferencia ({$shortage} garrafón(es) faltante(s)).",
                    'difference_reason',
                    422
                );
            }

            // Registrar dañados en incidencia si los hubo
            if ($damagedQuantity > 0) {
                $trimmedDamageNotes = trim((string) $damageNotes);
                if (strlen($trimmedDamageNotes) < 5) {
                    throw new OrderBusinessRuleException('Debe describir el daño físico detectado al recibir (mínimo 5 caracteres).', 'damage_notes', 422);
                }

                $validDamage = in_array($damageType, [
                    BottleIncident::DAMAGE_BROKEN,
                    BottleIncident::DAMAGE_CRACKED,
                    BottleIncident::DAMAGE_CONTAMINATED,
                    BottleIncident::DAMAGE_DEFORMED,
                    BottleIncident::DAMAGE_OTHER,
                ], true) ? $damageType : BottleIncident::DAMAGE_BROKEN;

                BottleIncident::create([
                    'customer_id' => null,
                    'order_id' => null,
                    'order_item_id' => null,
                    'reported_by_user_id' => $operator->id,
                    'bottle_type' => OrderItem::TYPE_EXCHANGE,
                    'bottle_condition' => BottleCustodyMovement::CONDITION_EMPTY,
                    'quantity' => $damagedQuantity,
                    'stage' => BottleIncident::STAGE_RECEPTION,
                    'damage_type' => $validDamage,
                    'status' => BottleIncident::STATUS_PENDING,
                    'notes' => $trimmedDamageNotes,
                ]);
            }

            // Incrementar vacíos disponibles en planta únicamente por los aptos recibidos
            if ($receivedQuantity > 0) {
                $this->receiveExchangeEmptyFromTruck($receivedQuantity);
            }

            // Descontar del camión del chofer lo que físicamente bajó a planta (recibidos aptos + dañados)
            if ($lockedMovement->user_id) {
                $driverUser = User::find($lockedMovement->user_id);
                if ($driverUser) {
                    $truckInv = TruckInventory::lockForUpdate()->firstOrCreate(
                        ['driver_user_id' => $driverUser->id],
                        [
                            'exchange_full_available' => 0,
                            'exchange_full_initial' => 0,
                            'exchange_full_delivered' => 0,
                            'exchange_empty_collected' => 0,
                        ]
                    );

                    $toDeductFromTruck = min($totalHandled, $truckInv->exchange_empty_collected);
                    if ($toDeductFromTruck > 0) {
                        $truckInv->decrement('exchange_empty_collected', $toDeductFromTruck);
                    }
                }
            }

            $movementStatus = ($shortage === 0) ? BottleCustodyMovement::STATUS_ACCEPTED : 'accepted_with_discrepancy';

            $notes = $lockedMovement->notes . " | Recibidos físicamente: {$receivedQuantity} aptos";
            if ($damagedQuantity > 0) {
                $notes .= ", {$damagedQuantity} dañados separados";
            }
            if ($shortage > 0) {
                $notes .= " | Discrepancia pendiente de aclaración ({$shortage} faltantes): {$trimmedReason}";
            }

            $lockedMovement->update([
                'quantity' => $receivedQuantity,
                'status' => $movementStatus,
                'confirmed_by_user_id' => $operator->id,
                'confirmed_at' => now(),
                'difference_reason' => $shortage > 0 ? $trimmedReason : null,
                'notes' => $notes,
            ]);

            return [
                'receipt_folio' => "REC-CAM-{$lockedMovement->id}",
                'timestamp' => now()->format('d/m/Y H:i'),
                'operator_name' => $operator->name,
                'type' => 'cambio',
                'customer' => 'Fondo General de Planta',
                'order_folio' => null,
                'proposed_quantity' => $proposed,
                'received_quantity' => $receivedQuantity,
                'damaged_quantity' => $damagedQuantity,
                'shortage_quantity' => $shortage,
                'difference_reason' => $shortage > 0 ? $trimmedReason : null,
                'status' => $movementStatus,
            ];
        });
    }

    /**
     * Obtiene el estado consolidado de producción (Lavado y Llenado).
     */
    public function getProductionState(): array
    {
        $inv = $this->getOrCreatePlantInventory();

        // 1. Fijos pendientes de preparar (recibidos aptos aún no ingresados a lavado)
        $fixedPendingPrepare = OrderItem::with(['order.customer'])
            ->whereHas('order', function ($q) {
                $q->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_DELIVERED]);
            })
            ->where('received_at_plant_quantity', '>', 0)
            ->whereRaw('(received_at_plant_quantity - washing_quantity) > 0')
            ->get()
            ->map(function ($item) {
                $availableToWash = $item->getPendingToWashQuantity();
                return [
                    'item_id' => $item->id,
                    'order_id' => $item->order_id,
                    'order_folio' => $item->order ? $item->order->folio : "ORD-{$item->order_id}",
                    'customer_id' => $item->order?->customer_id,
                    'customer_name' => $item->order?->customer?->name ?? 'Cliente',
                    'customer_nickname' => $item->order?->customer?->nickname,
                    'customer_code' => $item->order?->customer?->code,
                    'presentation' => $item->presentation,
                    'received_at_plant_quantity' => (int)$item->received_at_plant_quantity,
                    'washing_quantity' => (int)$item->washing_quantity,
                    'prepared_quantity' => (int)$item->prepared_quantity,
                    'damaged_quantity' => (int)$item->damaged_quantity,
                    'pending_to_wash_quantity' => $availableToWash,
                    'status' => $item->status,
                ];
            })
            ->filter(fn ($i) => $i['pending_to_wash_quantity'] > 0)
            ->values();

        // 2. Fijos en lavado y llenado (en túnel/línea)
        $fixedInWashing = OrderItem::with(['order.customer'])
            ->whereHas('order', function ($q) {
                $q->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_DELIVERED]);
            })
            ->where('washing_quantity', '>', 0)
            ->get()
            ->map(function ($item) {
                $inWashing = $item->getInWashingQuantity();
                return [
                    'item_id' => $item->id,
                    'order_id' => $item->order_id,
                    'order_folio' => $item->order ? $item->order->folio : "ORD-{$item->order_id}",
                    'customer_id' => $item->order?->customer_id,
                    'customer_name' => $item->order?->customer?->name ?? 'Cliente',
                    'customer_nickname' => $item->order?->customer?->nickname,
                    'customer_code' => $item->order?->customer?->code,
                    'presentation' => $item->presentation,
                    'in_washing_quantity' => $inWashing,
                    'washing_quantity' => (int)$item->washing_quantity,
                    'prepared_quantity' => (int)$item->prepared_quantity,
                    'damaged_quantity' => (int)$item->damaged_quantity,
                    'status' => $item->status,
                ];
            })
            ->filter(fn ($i) => $i['in_washing_quantity'] > 0)
            ->values();

        // 3. Fijos preparados (listos para reparto, no cargados al camión todavía)
        $fixedPrepared = OrderItem::with(['order.customer'])
            ->whereHas('order', function ($q) {
                $q->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_DELIVERED]);
            })
            ->where('prepared_quantity', '>', 0)
            ->get()
            ->map(function ($item) {
                $readyToLoad = $item->getReadyToLoadQuantity();
                return [
                    'item_id' => $item->id,
                    'order_id' => $item->order_id,
                    'order_folio' => $item->order ? $item->order->folio : "ORD-{$item->order_id}",
                    'customer_id' => $item->order?->customer_id,
                    'customer_name' => $item->order?->customer?->name ?? 'Cliente',
                    'customer_nickname' => $item->order?->customer?->nickname,
                    'customer_code' => $item->order?->customer?->code,
                    'presentation' => $item->presentation,
                    'prepared_quantity' => (int)$item->prepared_quantity,
                    'loaded_quantity' => (int)$item->loaded_quantity,
                    'reserved_quantity' => (int)$item->reserved_quantity,
                    'ready_to_load_quantity' => $readyToLoad,
                    'status' => $item->status,
                ];
            })
            ->filter(fn ($i) => $i['ready_to_load_quantity'] > 0 || $i['reserved_quantity'] > 0)
            ->values();

        // Lotes de cambio abiertos en proceso
        $exchangeBatchesInProcess = PlantProductionBatch::where('status', PlantProductionBatch::STATUS_IN_PROCESS)
            ->with('operator')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'operator_name' => $batch->operator?->name ?? 'Operador',
                    'initial_quantity' => (int)$batch->initial_quantity,
                    'finished_quantity' => (int)$batch->finished_quantity,
                    'damaged_quantity' => (int)$batch->damaged_quantity,
                    'in_process_quantity' => $batch->getInProcessQuantity(),
                    'status' => $batch->status,
                    'started_at' => $batch->started_at ? $batch->started_at->format('d/m/Y H:i') : null,
                    'notes' => $batch->notes,
                ];
            });

        // Lotes recientes
        $recentBatches = PlantProductionBatch::with('operator')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'operator_name' => $batch->operator?->name ?? 'Operador',
                    'initial_quantity' => (int)$batch->initial_quantity,
                    'finished_quantity' => (int)$batch->finished_quantity,
                    'damaged_quantity' => (int)$batch->damaged_quantity,
                    'status' => $batch->status,
                    'started_at' => $batch->started_at ? $batch->started_at->format('d/m/Y H:i') : null,
                    'completed_at' => $batch->completed_at ? $batch->completed_at->format('d/m/Y H:i') : null,
                    'notes' => $batch->notes,
                ];
            });

        return [
            'plant_inventory' => [
                'exchange_empty_available' => (int)$inv->exchange_empty_available,
                'exchange_washing_filling' => (int)$inv->exchange_washing_filling,
                'exchange_full_available' => (int)$inv->exchange_full_available,
                'exchange_full_reserved' => (int)$inv->exchange_full_reserved,
            ],
            'initial_count_pending' => is_null($inv->initial_count_completed_at),
            'fixed_pending_prepare' => $fixedPendingPrepare,
            'fixed_in_washing' => $fixedInWashing,
            'fixed_prepared' => $fixedPrepared,
            'exchange_batches_in_process' => $exchangeBatchesInProcess,
            'recent_batches' => $recentBatches,
        ];
    }

    /**
     * Inicia el lavado de botellones fijos pertenecientes a un cliente/pedido.
     */
    public function startFixedWashing(User $operator, OrderItem $item, int $quantity): OrderItem
    {
        if ($quantity <= 0) {
            throw new OrderBusinessRuleException('La cantidad a ingresar a lavado debe ser mayor a cero.', 'quantity', 422);
        }

        return DB::transaction(function () use ($operator, $item, $quantity) {
            $lockedItem = OrderItem::lockForUpdate()->find($item->id);
            if (!$lockedItem) {
                throw new OrderBusinessRuleException('El renglón del pedido no existe.', 'item', 404);
            }

            $order = $lockedItem->order;
            if (!$order) {
                throw new OrderBusinessRuleException('El pedido asociado no existe.', 'order', 404);
            }

            $pendingToWash = max(0, (int)$lockedItem->received_at_plant_quantity - (int)$lockedItem->washing_quantity);
            if ($quantity > $pendingToWash) {
                throw new OrderBusinessRuleException(
                    "No se pueden lavar {$quantity} botellones fijos; solo hay {$pendingToWash} pendientes de preparar para este pedido.",
                    'quantity',
                    422
                );
            }

            $lockedItem->increment('washing_quantity', $quantity);
            $lockedItem->update(['status' => OrderItem::STATUS_WASHING]);

            BottleCustodyMovement::create([
                'order_id' => $order->id,
                'order_item_id' => $lockedItem->id,
                'customer_id' => $order->customer_id,
                'user_id' => $operator->id,
                'movement_type' => BottleCustodyMovement::TYPE_PLANT_WASHING_STARTED,
                'bottle_type' => OrderItem::TYPE_FIXED,
                'bottle_condition' => BottleCustodyMovement::CONDITION_EMPTY,
                'quantity' => $quantity,
                'origin' => 'plant_reception_area',
                'destination' => 'plant_washing_line',
                'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                'confirmed_by_user_id' => $operator->id,
                'confirmed_at' => now(),
                'notes' => "Inicio de lavado de {$quantity} botellones fijos de {$order->customer?->name}",
                'created_at' => now(),
            ]);

            return $lockedItem->fresh(['order.customer']);
        });
    }

    /**
     * Finaliza la preparación (lavado, llenado y sellado) de botellones fijos.
     */
    public function finishFixedPreparation(User $operator, OrderItem $item, int $quantity): OrderItem
    {
        if ($quantity <= 0) {
            throw new OrderBusinessRuleException('La cantidad preparada debe ser mayor a cero.', 'quantity', 422);
        }

        return DB::transaction(function () use ($operator, $item, $quantity) {
            $lockedItem = OrderItem::lockForUpdate()->find($item->id);
            if (!$lockedItem) {
                throw new OrderBusinessRuleException('El renglón del pedido no existe.', 'item', 404);
            }

            $order = $lockedItem->order;
            if (!$order) {
                throw new OrderBusinessRuleException('El pedido asociado no existe.', 'order', 404);
            }

            $inWashing = $lockedItem->getInWashingQuantity();
            if ($quantity > $inWashing) {
                throw new OrderBusinessRuleException(
                    "No se pueden marcar {$quantity} botellones como preparados; solo hay {$inWashing} en proceso de lavado.",
                    'quantity',
                    422
                );
            }

            $lockedItem->increment('prepared_quantity', $quantity);

            // Si se prepararon todos los fijos aptos recibidos (descontando dañados en lavado)
            $aptosRecepcion = max(0, (int)$lockedItem->received_at_plant_quantity - $lockedItem->getDamagedInWashingQuantity());
            if ($aptosRecepcion > 0 && $lockedItem->prepared_quantity >= $aptosRecepcion) {
                $lockedItem->update(['status' => OrderItem::STATUS_READY_FOR_DELIVERY]);
            }

            BottleCustodyMovement::create([
                'order_id' => $order->id,
                'order_item_id' => $lockedItem->id,
                'customer_id' => $order->customer_id,
                'user_id' => $operator->id,
                'movement_type' => BottleCustodyMovement::TYPE_PLANT_PREPARED_FIXED,
                'bottle_type' => OrderItem::TYPE_FIXED,
                'bottle_condition' => BottleCustodyMovement::CONDITION_FULL,
                'quantity' => $quantity,
                'origin' => 'plant_washing_line',
                'destination' => 'plant_prepared_storage',
                'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                'confirmed_by_user_id' => $operator->id,
                'confirmed_at' => now(),
                'notes' => "Preparación finalizada para {$quantity} botellones fijos de {$order->customer?->name}",
                'created_at' => now(),
            ]);

            return $lockedItem->fresh(['order.customer']);
        });
    }

    /**
     * Reporta daño en botellones fijos durante el proceso de lavado/llenado.
     */
    public function reportFixedWashingDamage(
        User $operator,
        OrderItem $item,
        int $quantity,
        string $damageType,
        ?string $notes = null
    ): BottleIncident {
        if ($quantity <= 0) {
            throw new OrderBusinessRuleException('La cantidad de botellones dañados debe ser mayor a cero.', 'quantity', 422);
        }

        return DB::transaction(function () use ($operator, $item, $quantity, $damageType, $notes) {
            $lockedItem = OrderItem::lockForUpdate()->find($item->id);
            if (!$lockedItem) {
                throw new OrderBusinessRuleException('El renglón del pedido no existe.', 'item', 404);
            }

            $order = $lockedItem->order;
            if (!$order) {
                throw new OrderBusinessRuleException('El pedido asociado no existe.', 'order', 404);
            }

            $inWashing = $lockedItem->getInWashingQuantity();
            if ($quantity > $inWashing) {
                throw new OrderBusinessRuleException(
                    "No se pueden reportar {$quantity} botellones dañados; solo hay {$inWashing} en lavado.",
                    'quantity',
                    422
                );
            }

            // Preservar acumulados históricos intactos: incrementar damaged_quantity
            $lockedItem->increment('damaged_quantity', $quantity);

            $incident = BottleIncident::create([
                'order_id' => $order->id,
                'order_item_id' => $lockedItem->id,
                'customer_id' => $order->customer_id,
                'bottle_type' => OrderItem::TYPE_FIXED,
                'stage' => 'lavado',
                'damage_type' => $damageType,
                'quantity' => $quantity,
                'reported_by_user_id' => $operator->id,
                'responsible_user_id' => $operator->id,
                'status' => BottleIncident::STATUS_PENDING,
                'notes' => $notes ?? 'Envase fijo dañado durante el proceso de lavado/llenado.',
            ]);

            $aptosRecepcion = max(0, (int)$lockedItem->received_at_plant_quantity - $lockedItem->getDamagedInWashingQuantity());
            if ($aptosRecepcion > 0 && $lockedItem->prepared_quantity >= $aptosRecepcion) {
                $lockedItem->update(['status' => OrderItem::STATUS_READY_FOR_DELIVERY]);
            }

            BottleCustodyMovement::create([
                'order_id' => $order->id,
                'order_item_id' => $lockedItem->id,
                'customer_id' => $order->customer_id,
                'user_id' => $operator->id,
                'movement_type' => BottleCustodyMovement::TYPE_BOTTLE_INCIDENT,
                'bottle_type' => OrderItem::TYPE_FIXED,
                'bottle_condition' => BottleCustodyMovement::CONDITION_EMPTY,
                'quantity' => $quantity,
                'origin' => 'plant_washing_line',
                'destination' => 'quarantine_damaged',
                'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                'confirmed_by_user_id' => $operator->id,
                'confirmed_at' => now(),
                'notes' => "Envase fijo dañado en lavado ({$quantity} unidades). Incidencia #{$incident->id}",
                'created_at' => now(),
            ]);

            return $incident;
        });
    }

    /**
     * Inicia un lote de producción de botellones de cambio genéricos.
     */
    public function startExchangeBatch(User $operator, int $quantity, ?string $notes = null): PlantProductionBatch
    {
        if ($quantity <= 0) {
            throw new OrderBusinessRuleException('La cantidad a ingresar a lote debe ser mayor a cero.', 'quantity', 422);
        }

        return DB::transaction(function () use ($operator, $quantity, $notes) {
            $inv = PlantInventory::lockForUpdate()->firstOrCreate(['id' => 1]);

            if ($inv->exchange_empty_available < $quantity) {
                throw new OrderBusinessRuleException(
                    "No hay suficientes botellones vacíos disponibles. Disponibles: {$inv->exchange_empty_available}, solicitados: {$quantity}.",
                    'quantity',
                    422
                );
            }

            $inv->decrement('exchange_empty_available', $quantity);
            $inv->increment('exchange_washing_filling', $quantity);

            $batch = PlantProductionBatch::create([
                'batch_number' => PlantProductionBatch::generateBatchNumber(),
                'operator_user_id' => $operator->id,
                'initial_quantity' => $quantity,
                'finished_quantity' => 0,
                'damaged_quantity' => 0,
                'status' => PlantProductionBatch::STATUS_IN_PROCESS,
                'started_at' => now(),
                'notes' => $notes,
            ]);

            BottleCustodyMovement::create([
                'user_id' => $operator->id,
                'movement_type' => BottleCustodyMovement::TYPE_PLANT_WASHING_STARTED,
                'bottle_type' => OrderItem::TYPE_EXCHANGE,
                'bottle_condition' => BottleCustodyMovement::CONDITION_EMPTY,
                'quantity' => $quantity,
                'origin' => 'plant_empty_storage',
                'destination' => 'plant_washing_line',
                'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                'confirmed_by_user_id' => $operator->id,
                'confirmed_at' => now(),
                'notes' => "Lote {$batch->batch_number} de {$quantity} botellones iniciado por {$operator->name}",
                'created_at' => now(),
            ]);

            return $batch;
        });
    }

    /**
     * Finaliza (total o parcialmente) un lote de producción de botellones de cambio genéricos.
     */
    public function finishExchangeBatch(User $operator, PlantProductionBatch $batch, int $quantity): PlantProductionBatch
    {
        if ($quantity <= 0) {
            throw new OrderBusinessRuleException('La cantidad finalizada debe ser mayor a cero.', 'quantity', 422);
        }

        return DB::transaction(function () use ($operator, $batch, $quantity) {
            $lockedBatch = PlantProductionBatch::lockForUpdate()->find($batch->id);
            if (!$lockedBatch) {
                throw new OrderBusinessRuleException('El lote de producción no existe.', 'batch', 404);
            }

            if ($lockedBatch->status !== PlantProductionBatch::STATUS_IN_PROCESS) {
                throw new OrderBusinessRuleException('El lote seleccionado ya no está en proceso.', 'status', 422);
            }

            $inProcess = $lockedBatch->getInProcessQuantity();
            if ($quantity > $inProcess) {
                throw new OrderBusinessRuleException(
                    "No se pueden finalizar {$quantity} botellones; el lote solo tiene {$inProcess} pendientes de terminar.",
                    'quantity',
                    422
                );
            }

            $inv = PlantInventory::lockForUpdate()->firstOrCreate(['id' => 1]);
            $inv->decrement('exchange_washing_filling', $quantity);
            $inv->increment('exchange_full_available', $quantity);

            $lockedBatch->increment('finished_quantity', $quantity);
            if ($lockedBatch->getInProcessQuantity() === 0) {
                $lockedBatch->update([
                    'status' => PlantProductionBatch::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);
            }

            BottleCustodyMovement::create([
                'user_id' => $operator->id,
                'movement_type' => BottleCustodyMovement::TYPE_PLANT_WASHING_FINISHED,
                'bottle_type' => OrderItem::TYPE_EXCHANGE,
                'bottle_condition' => BottleCustodyMovement::CONDITION_FULL,
                'quantity' => $quantity,
                'origin' => 'plant_washing_line',
                'destination' => 'plant_full_storage',
                'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                'confirmed_by_user_id' => $operator->id,
                'confirmed_at' => now(),
                'notes' => "Lote {$lockedBatch->batch_number}: finalizados {$quantity} botellones listos",
                'created_at' => now(),
            ]);

            return $lockedBatch->fresh(['operator']);
        });
    }

    /**
     * Reporta daño en botellones de cambio durante el lote de lavado/llenado.
     */
    public function reportBatchDamage(
        User $operator,
        PlantProductionBatch $batch,
        int $quantity,
        string $damageType,
        ?string $notes = null
    ): BottleIncident {
        if ($quantity <= 0) {
            throw new OrderBusinessRuleException('La cantidad de botellones dañados debe ser mayor a cero.', 'quantity', 422);
        }

        return DB::transaction(function () use ($operator, $batch, $quantity, $damageType, $notes) {
            $lockedBatch = PlantProductionBatch::lockForUpdate()->find($batch->id);
            if (!$lockedBatch) {
                throw new OrderBusinessRuleException('El lote de producción no existe.', 'batch', 404);
            }

            if ($lockedBatch->status !== PlantProductionBatch::STATUS_IN_PROCESS) {
                throw new OrderBusinessRuleException('El lote seleccionado ya no está en proceso.', 'status', 422);
            }

            $inProcess = $lockedBatch->getInProcessQuantity();
            if ($quantity > $inProcess) {
                throw new OrderBusinessRuleException(
                    "No se pueden reportar {$quantity} botellones dañados; el lote solo tiene {$inProcess} en proceso.",
                    'quantity',
                    422
                );
            }

            $inv = PlantInventory::lockForUpdate()->firstOrCreate(['id' => 1]);
            $inv->decrement('exchange_washing_filling', $quantity);

            $lockedBatch->increment('damaged_quantity', $quantity);
            if ($lockedBatch->getInProcessQuantity() === 0) {
                $lockedBatch->update([
                    'status' => PlantProductionBatch::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);
            }

            $incident = BottleIncident::create([
                'bottle_type' => OrderItem::TYPE_EXCHANGE,
                'stage' => 'lavado',
                'damage_type' => $damageType,
                'quantity' => $quantity,
                'reported_by_user_id' => $operator->id,
                'responsible_user_id' => $operator->id,
                'status' => BottleIncident::STATUS_PENDING,
                'notes' => "Envase dañado en lote {$lockedBatch->batch_number}: " . ($notes ?? 'Detectado durante lavado/llenado'),
            ]);

            BottleCustodyMovement::create([
                'user_id' => $operator->id,
                'movement_type' => BottleCustodyMovement::TYPE_BOTTLE_INCIDENT,
                'bottle_type' => OrderItem::TYPE_EXCHANGE,
                'bottle_condition' => BottleCustodyMovement::CONDITION_EMPTY,
                'quantity' => $quantity,
                'origin' => 'plant_washing_line',
                'destination' => 'quarantine_damaged',
                'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                'confirmed_by_user_id' => $operator->id,
                'confirmed_at' => now(),
                'notes' => "Envase de cambio dañado en lote {$lockedBatch->batch_number} ({$quantity} unidades). Incidencia #{$incident->id}",
                'created_at' => now(),
            ]);

            return $incident;
        });
    }
}

