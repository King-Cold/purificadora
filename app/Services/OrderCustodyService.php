<?php

namespace App\Services;

use App\Exceptions\OrderBusinessRuleException;
use App\Models\BottleCustodyMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderCustodyService
{
    /**
     * Registra la recolección física de botellones fijos vacíos en el domicilio del cliente.
     */
    public function recordFixedBottlesCollected(
        Order $order,
        int $quantity,
        User $driver,
        ?string $notes = null
    ): void {
        if ($quantity <= 0) {
            throw new OrderBusinessRuleException('La cantidad de botellones fijos a recolectar debe ser mayor a cero.', 'quantity', 422);
        }

        DB::transaction(function () use ($order, $quantity, $driver, $notes) {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);
            $fixedItem = $lockedOrder->items()->where('type', OrderItem::TYPE_FIXED)->lockForUpdate()->first();

            if (!$fixedItem) {
                throw new OrderBusinessRuleException('El pedido no incluye botellones fijos para recolección.', 'type', 422);
            }

            $pendingToCollect = $fixedItem->quantity - $fixedItem->collected_quantity - $fixedItem->cancelled_quantity;
            $customerMax = max($pendingToCollect, (int) ($lockedOrder->customer?->fixed_bottles_count ?? $pendingToCollect));
            if ($quantity > $customerMax) {
                throw new OrderBusinessRuleException("No se pueden recolectar {$quantity} fijos. Máximo permitido del cliente: {$customerMax}.", 'quantity', 422);
            }

            $newCollected = (int) $fixedItem->collected_quantity + $quantity;
            $newQuantity = max((int) $fixedItem->quantity, $newCollected);

            $fixedItem->update([
                'quantity' => $newQuantity,
                'collected_quantity' => $newCollected,
                'status' => OrderItem::STATUS_TRANSPORTING_TO_PLANT,
            ]);

            BottleCustodyMovement::create([
                'order_id' => $lockedOrder->id,
                'order_item_id' => $fixedItem->id,
                'customer_id' => $lockedOrder->customer_id,
                'user_id' => $driver->id,
                'movement_type' => BottleCustodyMovement::TYPE_COLLECTION_FIXED_FROM_CUSTOMER,
                'bottle_type' => OrderItem::TYPE_FIXED,
                'bottle_condition' => BottleCustodyMovement::CONDITION_EMPTY,
                'quantity' => $quantity,
                'origin' => 'customer',
                'destination' => 'truck',
                'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                'notes' => $notes ?: 'Recolección en domicilio - Llevando a planta',
                'created_at' => now(),
            ]);

            // Transición a En Proceso para fijos / mixto
            if ($lockedOrder->status !== Order::STATUS_IN_PROCESS) {
                $previousStatus = $lockedOrder->status;
                $lockedOrder->update(['status' => Order::STATUS_IN_PROCESS]);

                OrderStatusHistory::create([
                    'order_id' => $lockedOrder->id,
                    'previous_status' => $previousStatus,
                    'new_status' => Order::STATUS_IN_PROCESS,
                    'user_id' => $driver->id,
                    'reason' => "Botellones fijos recogidos ({$quantity} u.) - Llevando a planta",
                    'created_at' => now(),
                ]);
            }
        });
    }

    /**
     * Recepción física en planta confirmada por el Operador de Planta.
     */
    public function recordFixedBottlesReceivedInPlant(
        Order $order,
        int $quantity,
        User $operator,
        ?string $notes = null
    ): void {
        if ($quantity <= 0) {
            throw new OrderBusinessRuleException('La cantidad debe ser mayor a cero.', 'quantity', 422);
        }

        DB::transaction(function () use ($order, $quantity, $operator, $notes) {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);
            $fixedItem = $lockedOrder->items()->where('type', OrderItem::TYPE_FIXED)->lockForUpdate()->firstOrFail();

            $pendingToReceive = $fixedItem->collected_quantity - $fixedItem->received_at_plant_quantity;
            if ($quantity > $pendingToReceive) {
                throw new OrderBusinessRuleException("Cantidad excede lo pendiente de recibir en planta ({$pendingToReceive}).", 'quantity', 422);
            }

            $fixedItem->update([
                'received_at_plant_quantity' => $fixedItem->received_at_plant_quantity + $quantity,
                'status' => OrderItem::STATUS_WASHING,
            ]);

            BottleCustodyMovement::create([
                'order_id' => $lockedOrder->id,
                'order_item_id' => $fixedItem->id,
                'customer_id' => $lockedOrder->customer_id,
                'user_id' => $operator->id,
                'movement_type' => BottleCustodyMovement::TYPE_PLANT_RECEPTION_FIXED,
                'bottle_type' => OrderItem::TYPE_FIXED,
                'bottle_condition' => BottleCustodyMovement::CONDITION_EMPTY,
                'quantity' => $quantity,
                'origin' => 'truck',
                'destination' => 'plant',
                'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                'notes' => $notes ?: 'Recepción y validación de fijos en planta - Lavando',
                'created_at' => now(),
            ]);

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'previous_status' => $lockedOrder->status,
                'new_status' => $lockedOrder->status,
                'user_id' => $operator->id,
                'reason' => "Fijos recibidos en planta ({$quantity} u.) - Proceso de lavado iniciado",
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Registro de lavado y llenado concluido en planta por cliente.
     */
    public function recordFixedBottlesPrepared(
        Order $order,
        int $quantity,
        User $operator,
        ?string $notes = null
    ): void {
        if ($quantity <= 0) {
            throw new OrderBusinessRuleException('La cantidad debe ser mayor a cero.', 'quantity', 422);
        }

        DB::transaction(function () use ($order, $quantity, $operator, $notes) {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);
            $fixedItem = $lockedOrder->items()->where('type', OrderItem::TYPE_FIXED)->lockForUpdate()->firstOrFail();

            $pendingToPrepare = $fixedItem->received_at_plant_quantity - $fixedItem->prepared_quantity - $fixedItem->damaged_quantity;
            if ($quantity > $pendingToPrepare) {
                throw new OrderBusinessRuleException("Cantidad excede lo pendiente de preparar ({$pendingToPrepare}).", 'quantity', 422);
            }

            $newPreparedQty = $fixedItem->prepared_quantity + $quantity;
            $allEligiblePrepared = $newPreparedQty >= ($fixedItem->received_at_plant_quantity - $fixedItem->damaged_quantity);

            $fixedItem->update([
                'prepared_quantity' => $newPreparedQty,
                'status' => $allEligiblePrepared ? OrderItem::STATUS_READY_FOR_DELIVERY : OrderItem::STATUS_WASHING,
            ]);

            BottleCustodyMovement::create([
                'order_id' => $lockedOrder->id,
                'order_item_id' => $fixedItem->id,
                'customer_id' => $lockedOrder->customer_id,
                'user_id' => $operator->id,
                'movement_type' => BottleCustodyMovement::TYPE_PLANT_PREPARED_FIXED,
                'bottle_type' => OrderItem::TYPE_FIXED,
                'bottle_condition' => BottleCustodyMovement::CONDITION_FULL,
                'quantity' => $quantity,
                'origin' => 'plant',
                'destination' => 'plant',
                'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                'notes' => $notes ?: 'Llenado finalizado - Listo para entregar',
                'created_at' => now(),
            ]);

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'previous_status' => $lockedOrder->status,
                'new_status' => $lockedOrder->status,
                'user_id' => $operator->id,
                'reason' => "Fijos preparados y llenados ({$quantity} u.) - Listos para entrega",
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Registro de carga al camión por el repartidor para segunda vuelta.
     */
    public function recordFixedBottlesLoadedOnTruck(
        Order $order,
        int $quantity,
        User $driver,
        ?string $notes = null
    ): void {
        if ($quantity <= 0) {
            throw new OrderBusinessRuleException('La cantidad debe ser mayor a cero.', 'quantity', 422);
        }

        DB::transaction(function () use ($order, $quantity, $driver, $notes) {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);
            $fixedItem = $lockedOrder->items()->where('type', OrderItem::TYPE_FIXED)->lockForUpdate()->firstOrFail();

            $fixedItem->update([
                'loaded_quantity' => $fixedItem->loaded_quantity + $quantity,
                'status' => OrderItem::STATUS_OUT_FOR_DELIVERY,
            ]);

            BottleCustodyMovement::create([
                'order_id' => $lockedOrder->id,
                'order_item_id' => $fixedItem->id,
                'customer_id' => $lockedOrder->customer_id,
                'user_id' => $driver->id,
                'movement_type' => BottleCustodyMovement::TYPE_TRUCK_LOAD_FIXED,
                'bottle_type' => OrderItem::TYPE_FIXED,
                'bottle_condition' => BottleCustodyMovement::CONDITION_FULL,
                'quantity' => $quantity,
                'origin' => 'plant',
                'destination' => 'truck',
                'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                'notes' => $notes ?: 'Fijos llenos cargados al camión - En repartición',
                'created_at' => now(),
            ]);

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'previous_status' => $lockedOrder->status,
                'new_status' => $lockedOrder->status,
                'user_id' => $driver->id,
                'reason' => "Fijos llenos cargados en camión ({$quantity} u.) - En repartición",
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Registro de devolución física de envases fijos vacíos por interrupción de servicio/incidencia.
     * No genera cargo de rellenado.
     */
    public function recordFixedBottlesReturnedEmpty(
        Order $order,
        int $quantity,
        User $driver,
        string $incidentReason,
        ?string $notes = null
    ): void {
        if ($quantity <= 0) {
            throw new OrderBusinessRuleException('La cantidad debe ser mayor a cero.', 'quantity', 422);
        }

        $trimmedReason = trim($incidentReason);
        if (empty($trimmedReason)) {
            throw new OrderBusinessRuleException('El motivo de la incidencia es obligatorio para devolver fijos vacíos.', 'incident_reason', 422);
        }

        DB::transaction(function () use ($order, $quantity, $driver, $trimmedReason, $notes) {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);
            $fixedItem = $lockedOrder->items()->where('type', OrderItem::TYPE_FIXED)->lockForUpdate()->firstOrFail();

            $custodyRemaining = $fixedItem->collected_quantity - $fixedItem->delivered_quantity - $fixedItem->returned_empty_quantity;
            if ($quantity > $custodyRemaining) {
                throw new OrderBusinessRuleException("La cantidad ({$quantity}) excede los fijos bajo custodia pendientes de devolución ({$custodyRemaining}).", 'quantity', 422);
            }

            $newReturned = $fixedItem->returned_empty_quantity + $quantity;
            $newCancelled = $fixedItem->cancelled_quantity + $quantity;

            $fixedItem->update([
                'returned_empty_quantity' => $newReturned,
                'cancelled_quantity' => $newCancelled,
                'cancellation_reason' => $trimmedReason,
                'status' => OrderItem::STATUS_RETURNED_EMPTY,
            ]);

            BottleCustodyMovement::create([
                'order_id' => $lockedOrder->id,
                'order_item_id' => $fixedItem->id,
                'customer_id' => $lockedOrder->customer_id,
                'user_id' => $driver->id,
                'movement_type' => BottleCustodyMovement::TYPE_RETURN_EMPTY_FIXED_TO_CUSTOMER,
                'bottle_type' => OrderItem::TYPE_FIXED,
                'bottle_condition' => BottleCustodyMovement::CONDITION_EMPTY,
                'quantity' => $quantity,
                'origin' => 'truck',
                'destination' => 'customer',
                'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                'notes' => "Devolución de envases fijos vacíos sin servicio por incidencia: {$trimmedReason}",
                'created_at' => now(),
            ]);

            // Si ya no quedan fijos bajo custodia, se marca el pedido como Incidencia concluida
            $hasRemainingCustody = ($fixedItem->collected_quantity > ($fixedItem->delivered_quantity + $newReturned));
            $newStatus = $hasRemainingCustody ? Order::STATUS_INCIDENT : Order::STATUS_INCIDENT;

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'previous_status' => $lockedOrder->status,
                'new_status' => $newStatus,
                'user_id' => $driver->id,
                'reason' => "Devolución de {$quantity} fijos vacíos al propietario por incidencia: {$trimmedReason}",
                'created_at' => now(),
            ]);

            $lockedOrder->update(['status' => $newStatus]);
        });
    }
}

