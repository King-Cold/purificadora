<?php

namespace App\Services;

use App\Exceptions\OrderBusinessRuleException;
use App\Models\BottleCustodyMovement;
use App\Models\BottleIncident;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\TruckInventory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TruckInventoryService
{
    /**
     * Obtiene o crea el registro de inventario continuo del camión para el chofer.
     */
    public function getOrCreateTruckInventory(User $driver): TruckInventory
    {
        return TruckInventory::firstOrCreate(
            ['driver_user_id' => $driver->id],
            [
                'exchange_full_available' => 0,
                'exchange_full_initial' => 0,
                'exchange_full_delivered' => 0,
                'exchange_empty_collected' => 0,
            ]
        );
    }

    /**
     * Consulta el estado consolidado de existencias, fijos por cliente, transferencias y movimientos.
     */
    public function getTruckInventoryState(User $driver): array
    {
        $truckInv = $this->getOrCreateTruckInventory($driver);

        // Fijos en el camión agrupados por cliente
        $fixedMovements = BottleCustodyMovement::with('customer')
            ->whereNotNull('customer_id')
            ->where('bottle_type', OrderItem::TYPE_FIXED)
            ->whereIn('status', [BottleCustodyMovement::STATUS_ACCEPTED, BottleCustodyMovement::STATUS_ACCEPTED_WITH_DISCREPANCY])
            ->get();

        $customersMap = [];
        foreach ($fixedMovements as $mov) {
            $cId = $mov->customer_id;
            if (!isset($customersMap[$cId])) {
                $customersMap[$cId] = [
                    'customer_id' => $cId,
                    'customer_code' => $mov->customer?->code ?? "CLI-{$cId}",
                    'customer_name' => $mov->customer?->name ?? 'Cliente sin nombre',
                    'customer_nickname' => $mov->customer?->nickname ?? '',
                    'fixed_empty_collected' => 0,
                    'fixed_full_to_deliver' => 0,
                ];
            }

            // Fijos vacíos en camión: recolectados a camión - entregados a planta - devueltos al cliente
            if ($mov->movement_type === BottleCustodyMovement::TYPE_COLLECTION_FIXED_FROM_CUSTOMER && $mov->destination === 'truck') {
                $customersMap[$cId]['fixed_empty_collected'] += $mov->quantity;
            } elseif ($mov->movement_type === BottleCustodyMovement::TYPE_PLANT_RECEPTION_FIXED && $mov->origin === 'truck') {
                $customersMap[$cId]['fixed_empty_collected'] -= $mov->quantity;
            } elseif ($mov->movement_type === BottleCustodyMovement::TYPE_RETURN_EMPTY_FIXED_TO_CUSTOMER && $mov->origin === 'truck') {
                $customersMap[$cId]['fixed_empty_collected'] -= $mov->quantity;
            }

            // Fijos llenos en camión: cargados de planta a camión - entregados al cliente
            if ($mov->movement_type === BottleCustodyMovement::TYPE_TRUCK_LOAD_FIXED && $mov->destination === 'truck') {
                $customersMap[$cId]['fixed_full_to_deliver'] += $mov->quantity;
            } elseif ($mov->movement_type === BottleCustodyMovement::TYPE_DELIVERY_REFILL_TO_CUSTOMER && $mov->origin === 'truck') {
                $customersMap[$cId]['fixed_full_to_deliver'] -= $mov->quantity;
            }
        }

        // Filtrar clientes que tengan al menos 1 fijo en el camión
        $fixedByCustomer = [];
        $totalFixedEmpty = 0;
        $totalFixedFull = 0;
        foreach ($customersMap as $item) {
            $empty = max(0, $item['fixed_empty_collected']);
            $full = max(0, $item['fixed_full_to_deliver']);
            if ($empty > 0 || $full > 0) {
                $item['fixed_empty_collected'] = $empty;
                $item['fixed_full_to_deliver'] = $full;
                $fixedByCustomer[] = $item;
                $totalFixedEmpty += $empty;
                $totalFixedFull += $full;
            }
        }

        // Transferencias pendientes de aceptar por el repartidor (origen planta -> destino truck)
        $pendingTransfers = BottleCustodyMovement::with(['customer', 'user', 'order'])
            ->where('destination', 'truck')
            ->where('status', BottleCustodyMovement::STATUS_PROPOSED)
            ->orderBy('id', 'desc')
            ->get();

        // Descargas propuestas por el repartidor pendientes de confirmar en Planta (origen truck -> destino plant)
        $pendingUnloads = BottleCustodyMovement::with(['customer', 'user'])
            ->where('origin', 'truck')
            ->where('destination', 'plant')
            ->where('status', BottleCustodyMovement::STATUS_PROPOSED)
            ->orderBy('id', 'desc')
            ->get();

        $exchangeEmptyCommitted = (int) $pendingUnloads
            ->where('bottle_type', OrderItem::TYPE_EXCHANGE)
            ->where('bottle_condition', BottleCustodyMovement::CONDITION_EMPTY)
            ->sum('quantity');

        // Historial reciente de movimientos (últimos 25)
        $recentMovements = BottleCustodyMovement::with(['customer', 'user', 'confirmedByUser'])
            ->where(function ($q) {
                $q->where('origin', 'truck')
                  ->orWhere('destination', 'truck');
            })
            ->orderBy('id', 'desc')
            ->limit(25)
            ->get();

        return [
            'truck_inventory' => [
                'exchange_full_available' => (int) $truckInv->exchange_full_available,
                'exchange_empty_collected' => (int) $truckInv->exchange_empty_collected,
                'exchange_empty_committed_in_unload' => $exchangeEmptyCommitted,
                'exchange_empty_uncommitted' => max(0, (int) $truckInv->exchange_empty_collected - $exchangeEmptyCommitted),
                'fixed_empty_collected_total' => $totalFixedEmpty,
                'fixed_full_to_deliver_total' => $totalFixedFull,
            ],
            'fixed_by_customer' => $fixedByCustomer,
            'pending_transfers' => $pendingTransfers,
            'pending_unloads' => $pendingUnloads,
            'recent_movements' => $recentMovements,
        ];
    }

    /**
     * Propone una carga desde planta al camión (solicitada por Operador de Planta o Administrador).
     * El movimiento queda en estado 'proposed' y NO se suma a las existencias hasta su aceptación física.
     */
    public function proposePlantToTruckTransfer(
        User $initiator,
        string $bottleType,
        int $quantity,
        ?int $customerId = null,
        ?int $orderId = null,
        ?string $notes = null,
        ?int $orderItemId = null
    ): BottleCustodyMovement {
        if ($quantity <= 0) {
            throw new OrderBusinessRuleException('La cantidad a transferir debe ser mayor a cero.', 'quantity', 422);
        }

        if (!in_array($bottleType, [OrderItem::TYPE_EXCHANGE, OrderItem::TYPE_FIXED], true)) {
            throw new OrderBusinessRuleException('Modalidad de envase inválida.', 'bottle_type', 422);
        }

        if ($bottleType === OrderItem::TYPE_FIXED && !$customerId && !$orderItemId && !$orderId) {
            throw new OrderBusinessRuleException('Los envases fijos requieren especificar el cliente o pedido asociado.', 'customer_id', 422);
        }

        return DB::transaction(function () use ($initiator, $bottleType, $quantity, $customerId, $orderId, $notes, $orderItemId) {
            $resolvedOrderItemId = $orderItemId;
            $resolvedOrderId = $orderId;
            $resolvedCustomerId = $customerId;

            if ($bottleType === OrderItem::TYPE_EXCHANGE) {
                app(PlantInventoryService::class)->reserveExchangeForTruck($quantity);
            } else {
                $orderItem = null;
                if ($orderItemId) {
                    $orderItem = OrderItem::lockForUpdate()->find($orderItemId);
                } elseif ($orderId) {
                    $orderItem = OrderItem::where('order_id', $orderId)
                        ->where('type', OrderItem::TYPE_FIXED)
                        ->lockForUpdate()
                        ->first();
                }

                if (!$orderItem) {
                    throw new OrderBusinessRuleException('El renglón de botellón fijo no fue encontrado.', 'order_item_id', 404);
                }

                $availableToLoad = $orderItem->getReadyToLoadQuantity();
                if ($quantity > $availableToLoad) {
                    throw new OrderBusinessRuleException(
                        "No se pueden proponer {$quantity} botellones fijos; solo hay {$availableToLoad} preparados disponibles sin reservar para este cliente.",
                        'quantity',
                        422
                    );
                }

                $orderItem->increment('reserved_quantity', $quantity);
                $resolvedOrderItemId = $orderItem->id;
                $resolvedOrderId = $orderItem->order_id;
                $resolvedCustomerId = $orderItem->order?->customer_id ?? $customerId;
            }

            $movementType = $bottleType === OrderItem::TYPE_EXCHANGE
                ? BottleCustodyMovement::TYPE_TRUCK_LOAD_EXCHANGE
                : BottleCustodyMovement::TYPE_TRUCK_LOAD_FIXED;

            return BottleCustodyMovement::create([
                'order_id' => $resolvedOrderId,
                'order_item_id' => $resolvedOrderItemId,
                'customer_id' => $resolvedCustomerId,
                'user_id' => $initiator->id,
                'movement_type' => $movementType,
                'bottle_type' => $bottleType,
                'bottle_condition' => BottleCustodyMovement::CONDITION_FULL,
                'quantity' => $quantity,
                'proposed_quantity' => $quantity,
                'origin' => 'plant',
                'destination' => 'truck',
                'status' => BottleCustodyMovement::STATUS_PROPOSED,
                'notes' => $notes ?: 'Propuesta de carga autorizada en planta',
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Retira/cancela una propuesta de carga antes de que el repartidor la acepte.
     * Libera las reservas de forma segura garantizando que no hubo traspaso físico.
     */
    /**
     * Retira/cancela una propuesta de carga antes de que el repartidor la acepte.
     * Libera las reservas de forma segura garantizando que no hubo traspaso físico.
     * Permite confirmar físicamente el desglose de unidades retenidas aptas en planta, dañadas o sin localizar.
     */
    public function withdrawTransferProposal(
        BottleCustodyMovement $movement,
        User $operator,
        int $retainedInPlantQuantity = 0,
        int $damagedQuantity = 0,
        ?string $damageType = null,
        ?string $damageNotes = null,
        int $unlocatedQuantity = 0
    ): BottleCustodyMovement {
        return DB::transaction(function () use (
            $movement,
            $operator,
            $retainedInPlantQuantity,
            $damagedQuantity,
            $damageType,
            $damageNotes,
            $unlocatedQuantity
        ) {
            $lockedMovement = BottleCustodyMovement::lockForUpdate()->findOrFail($movement->id);

            if ($lockedMovement->status !== BottleCustodyMovement::STATUS_PROPOSED) {
                throw new OrderBusinessRuleException('Solo se pueden retirar propuestas que estén en estado pendiente de aceptación.', 'status', 422);
            }

            if ($lockedMovement->destination !== 'truck') {
                throw new OrderBusinessRuleException('Solo se pueden retirar transferencias de carga hacia el camión.', 'destination', 422);
            }

            $proposedQty = (int)$lockedMovement->quantity;
            $rQty = max(0, $retainedInPlantQuantity);
            $dQty = max(0, $damagedQuantity);
            $lQty = max(0, $unlocatedQuantity);

            if ($rQty + $dQty + $lQty === 0) {
                $rQty = $proposedQty;
            } elseif (($rQty + $dQty + $lQty) !== $proposedQty) {
                throw new OrderBusinessRuleException(
                    "El desglose de unidades al retirar propuesta (Retenidas: {$rQty}, Dañadas: {$dQty}, Sin localizar: {$lQty}) debe sumar la cantidad propuesta ({$proposedQty}).",
                    'withdraw_breakdown',
                    422
                );
            }

            $plantService = app(PlantInventoryService::class);

            if ($lockedMovement->bottle_type === OrderItem::TYPE_EXCHANGE) {
                if ($rQty > 0) {
                    $plantService->releaseExchangeReservation($rQty);
                }
                if ($dQty > 0) {
                    $plantService->commitExchangeTruckLoad($dQty);
                    $incident = BottleIncident::create([
                        'bottle_type' => OrderItem::TYPE_EXCHANGE,
                        'stage' => 'almacen_planta',
                        'damage_type' => $damageType ?: 'rotura',
                        'quantity' => $dQty,
                        'reported_by_user_id' => $operator->id,
                        'responsible_user_id' => $operator->id,
                        'status' => BottleIncident::STATUS_PENDING,
                        'notes' => 'Envase de cambio dañado al retirar propuesta en planta: ' . ($damageNotes ?: 'Detectado durante maniobra'),
                    ]);

                    BottleCustodyMovement::create([
                        'user_id' => $operator->id,
                        'movement_type' => BottleCustodyMovement::TYPE_BOTTLE_INCIDENT,
                        'bottle_type' => OrderItem::TYPE_EXCHANGE,
                        'bottle_condition' => BottleCustodyMovement::CONDITION_FULL,
                        'quantity' => $dQty,
                        'origin' => 'plant_full_storage',
                        'destination' => 'quarantine_damaged',
                        'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                        'confirmed_by_user_id' => $operator->id,
                        'confirmed_at' => now(),
                        'notes' => "Envase de cambio dañado al retirar propuesta ({$dQty} u.). Incidencia #{$incident->id}",
                        'created_at' => now(),
                    ]);
                }
                if ($lQty > 0) {
                    $plantService->commitExchangeUnlocated($lQty);
                }
            } elseif ($lockedMovement->bottle_type === OrderItem::TYPE_FIXED) {
                $orderItem = null;
                if ($lockedMovement->order_item_id) {
                    $orderItem = OrderItem::lockForUpdate()->find($lockedMovement->order_item_id);
                } elseif ($lockedMovement->order_id) {
                    $orderItem = OrderItem::where('order_id', $lockedMovement->order_id)
                        ->where('type', OrderItem::TYPE_FIXED)
                        ->lockForUpdate()
                        ->first();
                }

                if ($orderItem) {
                    $toRelease = min($proposedQty, (int)$orderItem->reserved_quantity);
                    if ($toRelease > 0) {
                        $orderItem->decrement('reserved_quantity', $toRelease);
                    }

                    if ($dQty > 0) {
                        $orderItem->increment('damaged_quantity', $dQty);
                        $incident = BottleIncident::create([
                            'order_id' => $lockedMovement->order_id,
                            'order_item_id' => $orderItem->id,
                            'customer_id' => $lockedMovement->customer_id,
                            'bottle_type' => OrderItem::TYPE_FIXED,
                            'stage' => 'almacen_planta',
                            'damage_type' => $damageType ?: 'rotura',
                            'quantity' => $dQty,
                            'reported_by_user_id' => $operator->id,
                            'responsible_user_id' => $operator->id,
                            'status' => BottleIncident::STATUS_PENDING,
                            'notes' => 'Envase fijo dañado al retirar propuesta en planta: ' . ($damageNotes ?: 'Detectado durante maniobra'),
                        ]);

                        BottleCustodyMovement::create([
                            'order_id' => $lockedMovement->order_id,
                            'order_item_id' => $orderItem->id,
                            'customer_id' => $lockedMovement->customer_id,
                            'user_id' => $operator->id,
                            'movement_type' => BottleCustodyMovement::TYPE_BOTTLE_INCIDENT,
                            'bottle_type' => OrderItem::TYPE_FIXED,
                            'bottle_condition' => BottleCustodyMovement::CONDITION_FULL,
                            'quantity' => $dQty,
                            'origin' => 'plant_full_storage',
                            'destination' => 'quarantine_damaged',
                            'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                            'confirmed_by_user_id' => $operator->id,
                            'confirmed_at' => now(),
                            'notes' => "Envase fijo dañado al retirar propuesta ({$dQty} u.). Incidencia #{$incident->id}",
                            'created_at' => now(),
                        ]);
                    }

                    if ($lQty > 0) {
                        $orderItem->increment('unlocated_quantity', $lQty);
                    }
                }
            }

            $notes = $lockedMovement->notes . ' | Propuesta retirada por Planta antes de su aceptación física';
            if ($rQty > 0) {
                $notes .= " ({$rQty} retenidos aptos en planta)";
            }
            if ($dQty > 0) {
                $notes .= " ({$dQty} dañados)";
            }
            if ($lQty > 0) {
                $notes .= " ({$lQty} sin localizar)";
            }

            $lockedMovement->update([
                'status' => BottleCustodyMovement::STATUS_CANCELLED,
                'confirmed_by_user_id' => $operator->id,
                'confirmed_at' => now(),
                'notes' => $notes,
            ]);

            return $lockedMovement;
        });
    }

    /**
     * Aceptación física de la carga por parte del Repartidor.
     * Solo tras esta confirmación se suman formalmente las existencias al camión.
     */
    public function acceptTransfer(
        BottleCustodyMovement $movement,
        User $driver,
        ?int $confirmedQuantity = null,
        ?string $differenceReason = null,
        bool $retainedInPlant = false,
        int $retainedInPlantQuantity = 0,
        int $damagedQuantity = 0,
        ?string $damageType = null,
        ?string $damageNotes = null,
        int $unlocatedQuantity = 0
    ): BottleCustodyMovement {
        return DB::transaction(function () use (
            $movement,
            $driver,
            $confirmedQuantity,
            $differenceReason,
            $retainedInPlant,
            $retainedInPlantQuantity,
            $damagedQuantity,
            $damageType,
            $damageNotes,
            $unlocatedQuantity
        ) {
            $lockedMovement = BottleCustodyMovement::lockForUpdate()->findOrFail($movement->id);

            if ($lockedMovement->status !== BottleCustodyMovement::STATUS_PROPOSED) {
                throw new OrderBusinessRuleException('Esta transferencia ya ha sido procesada previamente.', 'status', 422);
            }

            if ($lockedMovement->destination !== 'truck') {
                throw new OrderBusinessRuleException('Solo se pueden aceptar recepciones con destino al camión.', 'destination', 422);
            }

            $proposedQty = (int)$lockedMovement->quantity;
            $confirmedQty = $confirmedQuantity !== null ? (int)$confirmedQuantity : $proposedQty;

            if ($confirmedQty < 0) {
                throw new OrderBusinessRuleException('La cantidad confirmada no puede ser negativa.', 'confirmed_quantity', 422);
            }

            if ($confirmedQty > $proposedQty) {
                throw new OrderBusinessRuleException("La cantidad confirmada ({$confirmedQty}) no puede exceder la propuesta ({$proposedQty}).", 'confirmed_quantity', 422);
            }

            $unacceptedQty = $proposedQty - $confirmedQty;

            $rQty = max(0, $retainedInPlantQuantity);
            $dQty = max(0, $damagedQuantity);
            $lQty = max(0, $unlocatedQuantity);

            // Si hay diferencia pero no se especificó desglose detallado
            if ($unacceptedQty > 0 && ($rQty + $dQty + $lQty === 0)) {
                if ($retainedInPlant) {
                    $rQty = $unacceptedQty;
                } else {
                    $lQty = $unacceptedQty;
                }
            }

            if ($unacceptedQty > 0) {
                if (($rQty + $dQty + $lQty) !== $unacceptedQty) {
                    throw new OrderBusinessRuleException(
                        "El desglose de unidades no aceptadas (Retenidas: {$rQty}, Dañadas: {$dQty}, Faltantes: {$lQty}) debe sumar exactamente la diferencia ({$unacceptedQty}).",
                        'difference_breakdown',
                        422
                    );
                }

                $trimmedReason = trim((string)$differenceReason);
                if (empty($trimmedReason)) {
                    throw new OrderBusinessRuleException('Debe especificar el motivo de la diferencia entre la cantidad propuesta y la recibida.', 'difference_reason', 422);
                }
            }

            $diffReason = $unacceptedQty > 0 ? trim((string)$differenceReason) : null;

            $movementStatus = ($unacceptedQty === 0) ? BottleCustodyMovement::STATUS_ACCEPTED : 'accepted_with_discrepancy';
            $notes = $lockedMovement->notes . " | Aceptados en camión: {$confirmedQty}";
            if ($rQty > 0) {
                $notes .= ", {$rQty} retenidos en planta aptos";
            }
            if ($dQty > 0) {
                $notes .= ", {$dQty} dañados durante la carga";
            }
            if ($lQty > 0) {
                $notes .= ", {$lQty} sin localizar / discrepancia pendiente de aclaración";
            }
            if ($diffReason) {
                $notes .= " | Motivo: {$diffReason}";
            }

            $lockedMovement->update([
                'quantity' => $confirmedQty,
                'status' => $movementStatus,
                'confirmed_by_user_id' => $driver->id,
                'confirmed_at' => now(),
                'difference_reason' => $diffReason,
                'notes' => $notes,
            ]);

            $truckInv = TruckInventory::lockForUpdate()->firstOrCreate(
                ['driver_user_id' => $driver->id],
                [
                    'exchange_full_available' => 0,
                    'exchange_full_initial' => 0,
                    'exchange_full_delivered' => 0,
                    'exchange_empty_collected' => 0,
                ]
            );

            $plantService = app(PlantInventoryService::class);

            if ($lockedMovement->bottle_type === OrderItem::TYPE_EXCHANGE && $lockedMovement->bottle_condition === BottleCustodyMovement::CONDITION_FULL) {
                if ($confirmedQty > 0) {
                    $truckInv->increment('exchange_full_available', $confirmedQty);
                    $truckInv->increment('exchange_full_initial', $confirmedQty);
                    $plantService->commitExchangeTruckLoad($confirmedQty);
                }

                if ($rQty > 0) {
                    $plantService->releaseExchangeReservation($rQty);
                }

                if ($dQty > 0) {
                    $plantService->commitExchangeTruckLoad($dQty);
                    $incident = BottleIncident::create([
                        'bottle_type' => OrderItem::TYPE_EXCHANGE,
                        'stage' => 'carga_camion',
                        'damage_type' => $damageType ?: 'fuga_cuerpo',
                        'quantity' => $dQty,
                        'reported_by_user_id' => $driver->id,
                        'responsible_user_id' => $driver->id,
                        'status' => BottleIncident::STATUS_PENDING,
                        'notes' => 'Envase de cambio dañado al cargar: ' . ($damageNotes ?: $diffReason),
                    ]);

                    BottleCustodyMovement::create([
                        'user_id' => $driver->id,
                        'movement_type' => BottleCustodyMovement::TYPE_BOTTLE_INCIDENT,
                        'bottle_type' => OrderItem::TYPE_EXCHANGE,
                        'bottle_condition' => BottleCustodyMovement::CONDITION_FULL,
                        'quantity' => $dQty,
                        'origin' => 'truck_load_process',
                        'destination' => 'quarantine_damaged',
                        'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                        'confirmed_by_user_id' => $driver->id,
                        'confirmed_at' => now(),
                        'notes' => "Envase de cambio dañado en carga ({$dQty} u.). Incidencia #{$incident->id}",
                        'created_at' => now(),
                    ]);
                }

                if ($lQty > 0) {
                    $plantService->commitExchangeUnlocated($lQty);
                }
            } elseif ($lockedMovement->bottle_type === OrderItem::TYPE_FIXED) {
                $orderItem = null;
                if ($lockedMovement->order_item_id) {
                    $orderItem = OrderItem::lockForUpdate()->find($lockedMovement->order_item_id);
                } elseif ($lockedMovement->order_id) {
                    $orderItem = OrderItem::where('order_id', $lockedMovement->order_id)
                        ->where('type', OrderItem::TYPE_FIXED)
                        ->lockForUpdate()
                        ->first();
                }

                if ($orderItem) {
                    $toReleaseReservation = min($proposedQty, (int)$orderItem->reserved_quantity);
                    if ($toReleaseReservation > 0) {
                        $orderItem->decrement('reserved_quantity', $toReleaseReservation);
                    }

                    if ($confirmedQty > 0) {
                        $orderItem->increment('loaded_quantity', $confirmedQty);
                        $orderItem->update(['status' => OrderItem::STATUS_OUT_FOR_DELIVERY]);
                    }

                    if ($dQty > 0) {
                        $orderItem->increment('damaged_quantity', $dQty);
                        $incident = BottleIncident::create([
                            'order_id' => $lockedMovement->order_id,
                            'order_item_id' => $orderItem->id,
                            'customer_id' => $lockedMovement->customer_id,
                            'bottle_type' => OrderItem::TYPE_FIXED,
                            'stage' => 'carga_camion',
                            'damage_type' => $damageType ?: 'fuga_cuerpo',
                            'quantity' => $dQty,
                            'reported_by_user_id' => $driver->id,
                            'responsible_user_id' => $driver->id,
                            'status' => BottleIncident::STATUS_PENDING,
                            'notes' => 'Envase fijo dañado al cargar: ' . ($damageNotes ?: $diffReason),
                        ]);

                        BottleCustodyMovement::create([
                            'order_id' => $lockedMovement->order_id,
                            'order_item_id' => $orderItem->id,
                            'customer_id' => $lockedMovement->customer_id,
                            'user_id' => $driver->id,
                            'movement_type' => BottleCustodyMovement::TYPE_BOTTLE_INCIDENT,
                            'bottle_type' => OrderItem::TYPE_FIXED,
                            'bottle_condition' => BottleCustodyMovement::CONDITION_FULL,
                            'quantity' => $dQty,
                            'origin' => 'truck_load_process',
                            'destination' => 'quarantine_damaged',
                            'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                            'confirmed_by_user_id' => $driver->id,
                            'confirmed_at' => now(),
                            'notes' => "Envase fijo dañado en carga ({$dQty} u.). Incidencia #{$incident->id}",
                            'created_at' => now(),
                        ]);
                    }

                    if ($lQty > 0) {
                        $orderItem->increment('unlocated_quantity', $lQty);
                    }
                }
            }

            return $lockedMovement->fresh(['customer', 'user', 'confirmedByUser']);
        });
    }

    /**
     * Rechazo de una transferencia propuesta.
     */
    public function rejectTransfer(
        BottleCustodyMovement $movement,
        User $driver,
        string $reason,
        int $retainedInPlantQuantity = 0,
        int $damagedQuantity = 0,
        ?string $damageType = null,
        ?string $damageNotes = null,
        int $unlocatedQuantity = 0
    ): BottleCustodyMovement {
        $trimmedReason = trim($reason);
        if (empty($trimmedReason)) {
            throw new OrderBusinessRuleException('El motivo de rechazo es obligatorio.', 'reason', 422);
        }

        return DB::transaction(function () use (
            $movement,
            $driver,
            $trimmedReason,
            $retainedInPlantQuantity,
            $damagedQuantity,
            $damageType,
            $damageNotes,
            $unlocatedQuantity
        ) {
            $lockedMovement = BottleCustodyMovement::lockForUpdate()->findOrFail($movement->id);

            if ($lockedMovement->status !== BottleCustodyMovement::STATUS_PROPOSED) {
                throw new OrderBusinessRuleException('Esta transferencia ya ha sido procesada previamente.', 'status', 422);
            }

            $proposedQty = (int)$lockedMovement->quantity;
            $rQty = max(0, $retainedInPlantQuantity);
            $dQty = max(0, $damagedQuantity);
            $lQty = max(0, $unlocatedQuantity);

            if ($rQty + $dQty + $lQty === 0) {
                $rQty = $proposedQty;
            } elseif (($rQty + $dQty + $lQty) !== $proposedQty) {
                throw new OrderBusinessRuleException(
                    "El desglose de unidades rechazadas (Retenidas: {$rQty}, Dañadas: {$dQty}, Faltantes: {$lQty}) debe sumar la cantidad propuesta ({$proposedQty}).",
                    'reject_breakdown',
                    422
                );
            }

            $plantService = app(PlantInventoryService::class);

            if ($lockedMovement->bottle_type === OrderItem::TYPE_EXCHANGE && $lockedMovement->bottle_condition === BottleCustodyMovement::CONDITION_FULL) {
                if ($rQty > 0) {
                    $plantService->releaseExchangeReservation($rQty);
                }
                if ($dQty > 0) {
                    $plantService->commitExchangeTruckLoad($dQty);
                    BottleIncident::create([
                        'bottle_type' => OrderItem::TYPE_EXCHANGE,
                        'stage' => 'carga_camion',
                        'damage_type' => $damageType ?: 'fuga_cuerpo',
                        'quantity' => $dQty,
                        'reported_by_user_id' => $driver->id,
                        'responsible_user_id' => $driver->id,
                        'status' => BottleIncident::STATUS_PENDING,
                        'notes' => 'Envase de cambio dañado en propuesta rechazada: ' . ($damageNotes ?: $trimmedReason),
                    ]);
                }
                if ($lQty > 0) {
                    $plantService->commitExchangeUnlocated($lQty);
                }
            } elseif ($lockedMovement->bottle_type === OrderItem::TYPE_FIXED) {
                $orderItem = null;
                if ($lockedMovement->order_item_id) {
                    $orderItem = OrderItem::lockForUpdate()->find($lockedMovement->order_item_id);
                } elseif ($lockedMovement->order_id) {
                    $orderItem = OrderItem::where('order_id', $lockedMovement->order_id)
                        ->where('type', OrderItem::TYPE_FIXED)
                        ->lockForUpdate()
                        ->first();
                }

                if ($orderItem) {
                    $toRelease = min($proposedQty, (int)$orderItem->reserved_quantity);
                    if ($toRelease > 0) {
                        $orderItem->decrement('reserved_quantity', $toRelease);
                    }

                    if ($dQty > 0) {
                        $orderItem->increment('damaged_quantity', $dQty);
                        BottleIncident::create([
                            'order_id' => $lockedMovement->order_id,
                            'order_item_id' => $orderItem->id,
                            'customer_id' => $lockedMovement->customer_id,
                            'bottle_type' => OrderItem::TYPE_FIXED,
                            'stage' => 'carga_camion',
                            'damage_type' => $damageType ?: 'fuga_cuerpo',
                            'quantity' => $dQty,
                            'reported_by_user_id' => $driver->id,
                            'responsible_user_id' => $driver->id,
                            'status' => BottleIncident::STATUS_PENDING,
                            'notes' => 'Envase fijo dañado en propuesta rechazada: ' . ($damageNotes ?: $trimmedReason),
                        ]);
                    }

                    if ($lQty > 0) {
                        $orderItem->increment('unlocated_quantity', $lQty);
                    }
                }
            }

            $notes = $lockedMovement->notes . " | Transferencia rechazada por el repartidor: {$trimmedReason}";
            if ($rQty > 0) {
                $notes .= " ({$rQty} retenidos aptos en planta)";
            }
            if ($dQty > 0) {
                $notes .= " ({$dQty} dañados)";
            }
            if ($lQty > 0) {
                $notes .= " ({$lQty} sin localizar)";
            }

            $lockedMovement->update([
                'status' => BottleCustodyMovement::STATUS_REJECTED,
                'difference_reason' => $trimmedReason,
                'confirmed_by_user_id' => $driver->id,
                'confirmed_at' => now(),
                'notes' => $notes,
            ]);

            return $lockedMovement;
        });
    }

    /**
     * Propuesta de descarga desde el camión a la planta (e.g. vacíos de cambio o fijos vacíos recogidos).
     */
    public function proposeTruckToPlantUnload(
        User $driver,
        string $bottleType,
        string $bottleCondition,
        int $quantity,
        ?int $customerId = null,
        ?string $notes = null
    ): BottleCustodyMovement {
        if ($quantity <= 0) {
            throw new OrderBusinessRuleException('La cantidad a descargar debe ser mayor a cero.', 'quantity', 422);
        }

        return DB::transaction(function () use ($driver, $bottleType, $bottleCondition, $quantity, $customerId, $notes) {
            $truckInv = TruckInventory::lockForUpdate()->firstOrCreate(
                ['driver_user_id' => $driver->id],
                [
                    'exchange_full_available' => 0,
                    'exchange_full_initial' => 0,
                    'exchange_full_delivered' => 0,
                    'exchange_empty_collected' => 0,
                ]
            );

            // Validar que el camión no quede con existencias negativas ni proponga dos veces las mismas unidades
            if ($bottleType === OrderItem::TYPE_EXCHANGE) {
                if ($bottleCondition === BottleCustodyMovement::CONDITION_EMPTY) {
                    $committedEmpty = (int) BottleCustodyMovement::where('user_id', $driver->id)
                        ->where('bottle_type', OrderItem::TYPE_EXCHANGE)
                        ->where('movement_type', BottleCustodyMovement::TYPE_TRUCK_UNLOAD_EXCHANGE)
                        ->where('bottle_condition', BottleCustodyMovement::CONDITION_EMPTY)
                        ->where('status', BottleCustodyMovement::STATUS_PROPOSED)
                        ->sum('quantity');
                    $availableUncommitted = max(0, (int) $truckInv->exchange_empty_collected - $committedEmpty);

                    if ($availableUncommitted < $quantity) {
                        throw new OrderBusinessRuleException(
                            "No se pueden descargar {$quantity} vacíos. Disponibles en camión sin entrega pendiente: {$availableUncommitted}.",
                            'quantity',
                            422
                        );
                    }
                } elseif ($bottleCondition === BottleCustodyMovement::CONDITION_FULL) {
                    $committedFull = (int) BottleCustodyMovement::where('user_id', $driver->id)
                        ->where('bottle_type', OrderItem::TYPE_EXCHANGE)
                        ->where('movement_type', BottleCustodyMovement::TYPE_TRUCK_UNLOAD_EXCHANGE)
                        ->where('bottle_condition', BottleCustodyMovement::CONDITION_FULL)
                        ->where('status', BottleCustodyMovement::STATUS_PROPOSED)
                        ->sum('quantity');
                    $availableFullUncommitted = max(0, (int) $truckInv->exchange_full_available - $committedFull);

                    if ($availableFullUncommitted < $quantity) {
                        throw new OrderBusinessRuleException(
                            "No se pueden devolver {$quantity} llenos. Disponibles en camión: {$availableFullUncommitted}.",
                            'quantity',
                            422
                        );
                    }
                }
            } elseif ($bottleType === OrderItem::TYPE_FIXED) {
                if (!$customerId) {
                    throw new OrderBusinessRuleException('Los envases fijos requieren especificar el cliente propietario.', 'customer_id', 422);
                }

                // Validar fijos del cliente en camión
                $state = $this->getTruckInventoryState($driver);
                $customerFixed = collect($state['fixed_by_customer'])->firstWhere('customer_id', $customerId);
                $availableEmpty = $customerFixed['fixed_empty_collected'] ?? 0;

                if ($availableEmpty < $quantity) {
                    throw new OrderBusinessRuleException(
                        "No se pueden descargar {$quantity} fijos del cliente. Disponibles en camión: {$availableEmpty}.",
                        'quantity',
                        422
                    );
                }
            }

            return BottleCustodyMovement::create([
                'customer_id' => $customerId,
                'user_id' => $driver->id,
                'movement_type' => $bottleType === OrderItem::TYPE_EXCHANGE
                    ? BottleCustodyMovement::TYPE_TRUCK_UNLOAD_EXCHANGE
                    : BottleCustodyMovement::TYPE_PLANT_RECEPTION_FIXED,
                'bottle_type' => $bottleType,
                'bottle_condition' => $bottleCondition,
                'quantity' => $quantity,
                'proposed_quantity' => $quantity,
                'origin' => 'truck',
                'destination' => 'plant',
                'status' => BottleCustodyMovement::STATUS_PROPOSED,
                'notes' => $notes ?: 'Descarga de envases en planta',
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Confirmación física de recepción en planta por parte del Operador de Planta o Administrador.
     * El repartidor NO puede confirmarse a sí mismo recepciones de planta.
     */
    public function confirmPlantReception(
        BottleCustodyMovement $movement,
        User $plantOperator,
        ?int $confirmedQuantity = null,
        ?string $differenceReason = null
    ): BottleCustodyMovement {
        // Regla: El repartidor no puede atribuir una recepción al llenador ni marcar recepción de planta para sí mismo
        if ($movement->user_id === $plantOperator->id && !$plantOperator->hasRole(['Administrador', 'Super Admin', 'Operador Planta'])) {
            throw new OrderBusinessRuleException('El repartidor no puede confirmar recepciones en planta.', 'user', 403);
        }

        return DB::transaction(function () use ($movement, $plantOperator, $confirmedQuantity, $differenceReason) {
            $lockedMovement = BottleCustodyMovement::lockForUpdate()->findOrFail($movement->id);

            if ($lockedMovement->status !== BottleCustodyMovement::STATUS_PROPOSED) {
                throw new OrderBusinessRuleException('Esta descarga ya ha sido procesada previamente.', 'status', 422);
            }

            if ($lockedMovement->destination !== 'plant') {
                throw new OrderBusinessRuleException('Solo se pueden confirmar recepciones con destino a planta.', 'destination', 422);
            }

            $finalQty = $lockedMovement->quantity;
            $diffReason = null;

            if ($confirmedQuantity !== null) {
                if ($confirmedQuantity < 0) {
                    throw new OrderBusinessRuleException('La cantidad confirmada no puede ser negativa.', 'confirmed_quantity', 422);
                }

                if ($confirmedQuantity !== $lockedMovement->quantity) {
                    $trimmedReason = trim((string) $differenceReason);
                    if (empty($trimmedReason)) {
                        throw new OrderBusinessRuleException('Debe especificar el motivo de diferencia.', 'difference_reason', 422);
                    }
                    $diffReason = $trimmedReason;
                    $finalQty = $confirmedQuantity;
                }
            }

            $lockedMovement->update([
                'quantity' => $finalQty,
                'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                'confirmed_by_user_id' => $plantOperator->id,
                'confirmed_at' => now(),
                'difference_reason' => $diffReason,
            ]);

            // Descontar del camión del chofer responsable
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

                    if ($lockedMovement->bottle_type === OrderItem::TYPE_EXCHANGE) {
                        if ($lockedMovement->bottle_condition === BottleCustodyMovement::CONDITION_EMPTY) {
                            $truckInv->decrement('exchange_empty_collected', min($finalQty, $truckInv->exchange_empty_collected));
                            app(PlantInventoryService::class)->receiveExchangeEmptyFromTruck($finalQty);
                        } elseif ($lockedMovement->bottle_condition === BottleCustodyMovement::CONDITION_FULL) {
                            $truckInv->decrement('exchange_full_available', min($finalQty, $truckInv->exchange_full_available));
                        }
                    }
                }
            }

            return $lockedMovement->fresh(['customer', 'user', 'confirmedByUser']);
        });
    }

    /**
     * Descuenta botellones de cambio llenos del camión durante la entrega en ruta.
     * Lanza excepción si las existencias en el camión son insuficientes.
     */
    public function decrementExchangeFullForDelivery(User $driver, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $truckInv = TruckInventory::lockForUpdate()->firstOrCreate(
            ['driver_user_id' => $driver->id],
            [
                'exchange_full_available' => 0,
                'exchange_full_initial' => 0,
                'exchange_full_delivered' => 0,
                'exchange_empty_collected' => 0,
            ]
        );

        if ($truckInv->exchange_full_available < $quantity) {
            throw new OrderBusinessRuleException(
                "No hay suficientes botellones de cambio llenos en el camión. Disponibles: {$truckInv->exchange_full_available}, requeridos para entrega: {$quantity}.",
                'exchange_delivered',
                422
            );
        }

        $truckInv->decrement('exchange_full_available', $quantity);
        $truckInv->increment('exchange_full_delivered', $quantity);
    }

    /**
     * Incrementa botellones de cambio vacíos recogidos en el camión.
     */
    public function incrementExchangeEmptyCollected(User $driver, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $truckInv = TruckInventory::lockForUpdate()->firstOrCreate(
            ['driver_user_id' => $driver->id],
            [
                'exchange_full_available' => 0,
                'exchange_full_initial' => 0,
                'exchange_full_delivered' => 0,
                'exchange_empty_collected' => 0,
            ]
        );

        $truckInv->increment('exchange_empty_collected', $quantity);
    }
}

