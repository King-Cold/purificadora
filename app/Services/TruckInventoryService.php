<?php

namespace App\Services;

use App\Exceptions\OrderBusinessRuleException;
use App\Models\BottleCustodyMovement;
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
            ->where('status', BottleCustodyMovement::STATUS_ACCEPTED)
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
                'fixed_empty_collected_total' => $totalFixedEmpty,
                'fixed_full_to_deliver_total' => $totalFixedFull,
            ],
            'fixed_by_customer' => $fixedByCustomer,
            'pending_transfers' => $pendingTransfers,
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
        ?string $notes = null
    ): BottleCustodyMovement {
        if ($quantity <= 0) {
            throw new OrderBusinessRuleException('La cantidad a transferir debe ser mayor a cero.', 'quantity', 422);
        }

        if (!in_array($bottleType, [OrderItem::TYPE_EXCHANGE, OrderItem::TYPE_FIXED], true)) {
            throw new OrderBusinessRuleException('Modalidad de envase inválida.', 'bottle_type', 422);
        }

        if ($bottleType === OrderItem::TYPE_FIXED && !$customerId) {
            throw new OrderBusinessRuleException('Los envases fijos requieren especificar el cliente propietario.', 'customer_id', 422);
        }

        return DB::transaction(function () use ($initiator, $bottleType, $quantity, $customerId, $orderId, $notes) {
            $movementType = $bottleType === OrderItem::TYPE_EXCHANGE
                ? BottleCustodyMovement::TYPE_TRUCK_LOAD_EXCHANGE
                : BottleCustodyMovement::TYPE_TRUCK_LOAD_FIXED;

            return BottleCustodyMovement::create([
                'order_id' => $orderId,
                'customer_id' => $customerId,
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
     * Aceptación física de la carga por parte del Repartidor.
     * Solo tras esta confirmación se suman formalmente las existencias al camión.
     */
    public function acceptTransfer(
        BottleCustodyMovement $movement,
        User $driver,
        ?int $confirmedQuantity = null,
        ?string $differenceReason = null
    ): BottleCustodyMovement {
        return DB::transaction(function () use ($movement, $driver, $confirmedQuantity, $differenceReason) {
            $lockedMovement = BottleCustodyMovement::lockForUpdate()->findOrFail($movement->id);

            if ($lockedMovement->status !== BottleCustodyMovement::STATUS_PROPOSED) {
                throw new OrderBusinessRuleException('Esta transferencia ya ha sido procesada previamente.', 'status', 422);
            }

            if ($lockedMovement->destination !== 'truck') {
                throw new OrderBusinessRuleException('Solo se pueden aceptar recepciones con destino al camión.', 'destination', 422);
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
                        throw new OrderBusinessRuleException('Debe especificar el motivo de la diferencia entre la cantidad propuesta y la recibida.', 'difference_reason', 422);
                    }
                    $diffReason = $trimmedReason;
                    $finalQty = $confirmedQuantity;
                }
            }

            // Actualizar movimiento
            $lockedMovement->update([
                'quantity' => $finalQty,
                'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                'confirmed_by_user_id' => $driver->id,
                'confirmed_at' => now(),
                'difference_reason' => $diffReason,
                'notes' => $diffReason ? ($lockedMovement->notes . " | Diferencia aceptada: {$diffReason}") : $lockedMovement->notes,
            ]);

            // Actualizar existencias del camión
            $truckInv = TruckInventory::lockForUpdate()->firstOrCreate(
                ['driver_user_id' => $driver->id],
                [
                    'exchange_full_available' => 0,
                    'exchange_full_initial' => 0,
                    'exchange_full_delivered' => 0,
                    'exchange_empty_collected' => 0,
                ]
            );

            if ($lockedMovement->bottle_type === OrderItem::TYPE_EXCHANGE && $lockedMovement->bottle_condition === BottleCustodyMovement::CONDITION_FULL) {
                $truckInv->increment('exchange_full_available', $finalQty);
                $truckInv->increment('exchange_full_initial', $finalQty);
            }

            // Si es fijo y tiene pedido asociado, actualizar orden
            if ($lockedMovement->bottle_type === OrderItem::TYPE_FIXED && $lockedMovement->order_id) {
                $orderItem = OrderItem::where('order_id', $lockedMovement->order_id)
                    ->where('type', OrderItem::TYPE_FIXED)
                    ->lockForUpdate()
                    ->first();

                if ($orderItem) {
                    $orderItem->update([
                        'loaded_quantity' => $orderItem->loaded_quantity + $finalQty,
                        'status' => OrderItem::STATUS_OUT_FOR_DELIVERY,
                    ]);
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
        string $reason
    ): BottleCustodyMovement {
        $trimmedReason = trim($reason);
        if (empty($trimmedReason)) {
            throw new OrderBusinessRuleException('El motivo de rechazo es obligatorio.', 'reason', 422);
        }

        return DB::transaction(function () use ($movement, $driver, $trimmedReason) {
            $lockedMovement = BottleCustodyMovement::lockForUpdate()->findOrFail($movement->id);

            if ($lockedMovement->status !== BottleCustodyMovement::STATUS_PROPOSED) {
                throw new OrderBusinessRuleException('Esta transferencia ya ha sido procesada previamente.', 'status', 422);
            }

            $lockedMovement->update([
                'status' => BottleCustodyMovement::STATUS_REJECTED,
                'difference_reason' => $trimmedReason,
                'confirmed_by_user_id' => $driver->id,
                'confirmed_at' => now(),
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

            // Validar que el camión no quede con existencias negativas
            if ($bottleType === OrderItem::TYPE_EXCHANGE) {
                if ($bottleCondition === BottleCustodyMovement::CONDITION_EMPTY) {
                    if ($truckInv->exchange_empty_collected < $quantity) {
                        throw new OrderBusinessRuleException(
                            "No se pueden descargar {$quantity} vacíos. Disponibles en camión: {$truckInv->exchange_empty_collected}.",
                            'quantity',
                            422
                        );
                    }
                } elseif ($bottleCondition === BottleCustodyMovement::CONDITION_FULL) {
                    if ($truckInv->exchange_full_available < $quantity) {
                        throw new OrderBusinessRuleException(
                            "No se pueden devolver {$quantity} llenos. Disponibles en camión: {$truckInv->exchange_full_available}.",
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

