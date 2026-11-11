<?php

namespace App\Services;

use App\Exceptions\OrderBusinessRuleException;
use App\Models\BottleCustodyMovement;
use App\Models\BottlePresentation;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\OrderCharge;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\RouteStop;
use App\Models\TruckInventory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DeliveryRouteService
{
    public function __construct(
        protected OrderService $orderService,
        protected DebtAndCreditService $debtService,
        protected WeeklyPromotionService $promotionService,
        protected OrderCustodyService $custodyService,
        protected TruckInventoryService $truckInventoryService
    ) {}

    /**
     * Resuelve de forma estricta el único repartidor activo del sistema.
     * Si no hay repartidor activo o hay más de uno, muestra un error de configuración operativa.
     */
    public function resolveSoleActiveDriver(): User
    {
        $activeDrivers = User::role('Repartidor')
            ->where('status', 'active')
            ->get();

        if ($activeDrivers->isEmpty()) {
            throw new OrderBusinessRuleException(
                'Error de configuración operativa: No hay ningún repartidor activo registrado en el sistema.',
                'driver',
                422
            );
        }

        if ($activeDrivers->count() > 1) {
            throw new OrderBusinessRuleException(
                "Error de configuración operativa: Se encontraron {$activeDrivers->count()} repartidores activos. La operación requiere exactamente un único repartidor activo.",
                'driver',
                422
            );
        }

        return $activeDrivers->first();
    }

    /**
     * Inicia la jornada de reparto del único repartidor activo:
     * - Si ya tiene una ruta en curso, la retorna.
     * - Si tiene una ruta asignada para hoy (o pendiente), la inicia formalmente.
     * - Si tiene una ruta en pausa por avería, la reanuda.
     * - Si no tiene ninguna ruta activa para hoy, crea la ruta de hoy y la inicia automáticamente sin exigir asignación previa.
     */
    public function startOrCreateSoleDriverRoute(User $user): DeliveryRoute
    {
        $soleDriver = $this->resolveSoleActiveDriver();

        if ($user->hasRole('Repartidor') && (int) $user->id !== (int) $soleDriver->id) {
            throw new OrderBusinessRuleException(
                "El repartidor indicado no coincide con el único repartidor activo ({$soleDriver->name}).",
                'driver',
                422
            );
        }

        $driver = $soleDriver;

        // 1. ¿Tiene ruta en curso?
        $inProgress = DeliveryRoute::where('driver_user_id', $driver->id)
            ->where('status', DeliveryRoute::STATUS_IN_PROGRESS)
            ->orderBy('id', 'desc')
            ->first();
        if ($inProgress) {
            return $inProgress;
        }

        // 2. ¿Tiene ruta asignada lista para iniciar?
        $assigned = DeliveryRoute::where('driver_user_id', $driver->id)
            ->where('status', DeliveryRoute::STATUS_ASSIGNED)
            ->orderBy('id', 'desc')
            ->first();
        if ($assigned) {
            return $this->startRoute($assigned, $driver);
        }

        // 3. ¿Tiene ruta en avería/incidencia?
        $incident = DeliveryRoute::where('driver_user_id', $driver->id)
            ->where('status', DeliveryRoute::STATUS_INCIDENT)
            ->orderBy('id', 'desc')
            ->first();
        if ($incident) {
            return $this->resumeRoute($incident, $driver);
        }

        // 4. Crear nueva ruta de hoy con pedidos confirmados disponibles y arrancar
        $eligibleData = $this->getEligibleOrdersForRoute();
        $orderIds = array_column($eligibleData, 'id');

        $newRoute = $this->createRoute($user, $driver, now(), $orderIds, 'Jornada iniciada directamente por repartidor');
        return $this->startRoute($newRoute, $driver);
    }

    /**
     * Crea una nueva hoja de ruta de reparto y asigna pedidos confirmados ordenados por parada.
     * Vincula automáticamente las rutas al único repartidor activo sin exigir selección manual.
     */
    public function createRoute(
        User $creator,
        ?User $driver = null,
        $date = null,
        array $orderIds = [],
        ?string $notes = null
    ): DeliveryRoute {
        return DB::transaction(function () use ($creator, $driver, $date, $orderIds, $notes) {
            $soleDriver = $this->resolveSoleActiveDriver();

            if ($driver !== null && (int) $driver->id !== (int) $soleDriver->id) {
                throw new OrderBusinessRuleException(
                    "El repartidor indicado no coincide con el único repartidor activo ({$soleDriver->name}).",
                    'driver',
                    422
                );
            }

            $driverToUse = $soleDriver;
            $parsedDate = $date instanceof Carbon ? $date : ($date ? Carbon::parse($date) : now());
            $dateString = $parsedDate->format('Ymd');

            // Folio consecutivo para la fecha ej. RUT-20261102-01
            $routeCount = DeliveryRoute::whereDate('date', $parsedDate->toDateString())->count();
            $consecutive = str_pad((string) ($routeCount + 1), 2, '0', STR_PAD_LEFT);
            $folio = "RUT-{$dateString}-{$consecutive}";

            $route = DeliveryRoute::create([
                'folio' => $folio,
                'driver_user_id' => $driverToUse->id,
                'date' => $parsedDate->toDateString(),
                'status' => DeliveryRoute::STATUS_ASSIGNED,
                'notes' => $notes,
            ]);

            // Conservar y vincular inventario continuo de camión para la ruta del chofer
            $truckInv = $this->truckInventoryService->getOrCreateTruckInventory($driverToUse);
            $truckInv->update(['delivery_route_id' => $route->id]);

            // Evitar duplicados en la lista de paradas
            if (count($orderIds) !== count(array_unique($orderIds))) {
                throw new OrderBusinessRuleException('No se permiten pedidos duplicados en la misma hoja de ruta.', 'order_ids', 422);
            }

            $stopNumber = 1;
            foreach ($orderIds as $orderId) {
                $order = Order::lockForUpdate()->findOrFail($orderId);

                // Validar que el pedido no esté ya asignado a una ruta activa
                if ($order->delivery_route_id) {
                    $existingRoute = DeliveryRoute::find($order->delivery_route_id);
                    if ($existingRoute && in_array($existingRoute->status, [DeliveryRoute::STATUS_ASSIGNED, DeliveryRoute::STATUS_IN_PROGRESS, DeliveryRoute::STATUS_INCIDENT], true)) {
                        throw new OrderBusinessRuleException("El pedido {$order->folio} ya está asignado a la ruta activa {$existingRoute->folio}.", 'order', 422);
                    }
                }

                if (!$order->isPending() && !$order->isConfirmed() && !$order->isInProcess()) {
                    throw new OrderBusinessRuleException("El pedido {$order->folio} no se encuentra elegible para asignación a ruta.", 'status', 422);
                }

                $prevStatus = $order->status;

                $order->update([
                    'delivery_route_id' => $route->id,
                    'status' => Order::STATUS_IN_ROUTE,
                ]);

                RouteStop::create([
                    'delivery_route_id' => $route->id,
                    'order_id' => $order->id,
                    'stop_number' => $stopNumber++,
                    'status' => RouteStop::STATUS_PENDING,
                ]);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'previous_status' => $prevStatus,
                    'new_status' => Order::STATUS_IN_ROUTE,
                    'user_id' => $creator->id,
                    'reason' => "Pedido asignado a hoja de ruta {$folio}",
                    'created_at' => now(),
                ]);
            }

            return $route->load('stops.order');
        });
    }

    /**
     * Inicia formalmente el recorrido de la ruta.
     */
    public function startRoute(DeliveryRoute $route, User $driver): DeliveryRoute
    {
        if ($route->status !== DeliveryRoute::STATUS_ASSIGNED) {
            throw new OrderBusinessRuleException('Solo se pueden iniciar rutas en estado asignada.', 'status', 422);
        }

        $route->update([
            'status' => DeliveryRoute::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        return $route;
    }

    /**
     * Completa formalmente la hoja de ruta.
     * Libera para reprogramación los pedidos con paradas fallidas sin alterar su historial ni duplicar datos.
     */
    public function completeRoute(DeliveryRoute $route, User $driver): DeliveryRoute
    {
        return DB::transaction(function () use ($route, $driver) {
            $lockedRoute = DeliveryRoute::lockForUpdate()->findOrFail($route->id);

            $hasPendingStops = $lockedRoute->stops()->where('status', RouteStop::STATUS_PENDING)->exists();
            if ($hasPendingStops) {
                throw new OrderBusinessRuleException('No se puede completar la ruta porque aún tiene paradas pendientes de atención.', 'status', 422);
            }

            // Liberar pedidos con paradas fallidas para permitir su reprogramación en una nueva ruta
            $failedStops = $lockedRoute->stops()->where('status', RouteStop::STATUS_FAILED)->get();
            foreach ($failedStops as $failedStop) {
                $order = Order::lockForUpdate()->find($failedStop->order_id);
                if ($order && !$order->isDelivered() && !$order->isCancelled()) {
                    $revertedStatus = $order->hasCollectedFixedCustody() ? Order::STATUS_IN_PROCESS : Order::STATUS_CONFIRMED;
                    $previousStatus = $order->status;

                    $order->update([
                        'status' => $revertedStatus,
                        'delivery_route_id' => null,
                    ]);

                    OrderStatusHistory::create([
                        'order_id' => $order->id,
                        'previous_status' => $previousStatus,
                        'new_status' => $revertedStatus,
                        'user_id' => $driver->id,
                        'reason' => "Ruta {$lockedRoute->folio} completada con visita fallida. Pedido liberado para reprogramación.",
                        'created_at' => now(),
                    ]);
                }
            }

            $lockedRoute->update([
                'status' => DeliveryRoute::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            $route->refresh();

            return $route;
        });
    }

    /**
     * Incorpora de forma transaccional un pedido pendiente a una hoja de ruta activa.
     */
    public function addOrderToActiveRoute(DeliveryRoute $route, Order $order, User $actor): RouteStop
    {
        return DB::transaction(function () use ($route, $order, $actor) {
            $lockedRoute = DeliveryRoute::lockForUpdate()->findOrFail($route->id);
            if (!in_array($lockedRoute->status, [DeliveryRoute::STATUS_ASSIGNED, DeliveryRoute::STATUS_IN_PROGRESS], true)) {
                throw new OrderBusinessRuleException('Solo se pueden incorporar pedidos a rutas asignadas o en curso.', 'route', 422);
            }

            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);

            // Si ya pertenece a esta ruta, regresar la parada existente
            $existingStop = RouteStop::where('delivery_route_id', $lockedRoute->id)
                ->where('order_id', $lockedOrder->id)
                ->first();
            if ($existingStop) {
                return $existingStop;
            }

            // Si está asignado a otra ruta activa, lanzar error
            if ($lockedOrder->delivery_route_id && (int) $lockedOrder->delivery_route_id !== (int) $lockedRoute->id) {
                $otherRoute = DeliveryRoute::find($lockedOrder->delivery_route_id);
                if ($otherRoute && in_array($otherRoute->status, [DeliveryRoute::STATUS_ASSIGNED, DeliveryRoute::STATUS_IN_PROGRESS, DeliveryRoute::STATUS_INCIDENT], true)) {
                    throw new OrderBusinessRuleException("El pedido {$lockedOrder->folio} ya está asignado a otra ruta activa ({$otherRoute->folio}).", 'order', 422);
                }
            }

            if (!$lockedOrder->isPending() && !$lockedOrder->isConfirmed() && !$lockedOrder->isInProcess()) {
                throw new OrderBusinessRuleException("El pedido {$lockedOrder->folio} no se encuentra elegible para asignación a ruta.", 'status', 422);
            }

            $prevStatus = $lockedOrder->status;
            $nextStopNumber = ($lockedRoute->stops()->max('stop_number') ?? 0) + 1;

            $lockedOrder->update([
                'delivery_route_id' => $lockedRoute->id,
                'status' => Order::STATUS_IN_ROUTE,
            ]);

            $stop = RouteStop::create([
                'delivery_route_id' => $lockedRoute->id,
                'order_id' => $lockedOrder->id,
                'stop_number' => $nextStopNumber,
                'status' => RouteStop::STATUS_PENDING,
            ]);

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'previous_status' => $prevStatus,
                'new_status' => Order::STATUS_IN_ROUTE,
                'user_id' => $actor->id,
                'reason' => "Pedido incorporado a la hoja de ruta activa {$lockedRoute->folio}",
                'created_at' => now(),
            ]);

            return $stop;
        });
    }

    /**
     * Atiende con éxito una parada de ruta registrando entregas, recolecciones, cancelaciones parciales y cobro.
     */
    public function recordStopCompleted(
        RouteStop $stop,
        array $executionData,
        User $driver
    ): void {
        DB::transaction(function () use ($stop, $executionData, $driver) {
            $lockedStop = RouteStop::lockForUpdate()->findOrFail($stop->id);

            // Una visita completada nunca debe pasar a fallida ni re-atenderse
            if ($lockedStop->status !== RouteStop::STATUS_PENDING) {
                throw new OrderBusinessRuleException('Esta parada ya ha sido procesada previamente y no puede modificarse.', 'status', 422);
            }

            $order = Order::lockForUpdate()->findOrFail($lockedStop->order_id);
            $customer = Customer::lockForUpdate()->findOrFail($order->customer_id);

            $refillPrice = (float) (BottlePresentation::where('capacity', 20.00)
                ->where('is_available_for_delivery', true)
                ->first()?->refill_price ?? 15.00);

            // 1. GESTIÓN DE MODALIDAD CAMBIO
            $exchangeItem = $order->items()->where('type', OrderItem::TYPE_EXCHANGE)->first();
            if ($exchangeItem) {
                $requestedExchange = (int) $exchangeItem->quantity;
                $emptyReceived = isset($executionData['exchange_empty_received']) 
                    ? (int) $executionData['exchange_empty_received'] 
                    : $requestedExchange;

                $acceptedByCustomer = isset($executionData['exchange_delivered']) 
                    ? (int) $executionData['exchange_delivered'] 
                    : $emptyReceived;

                // Regla confirmada: Se entrega únicamente lo respaldado por vacíos recibidos
                $deliveredExchange = min($emptyReceived, $acceptedByCustomer);
                $cancelledExchange = max(0, $requestedExchange - $deliveredExchange);

                $cancelReason = null;
                if ($cancelledExchange > 0) {
                    if (!empty($executionData['cancellation_reason'])) {
                        $cancelReason = trim($executionData['cancellation_reason']);
                    } elseif ($emptyReceived < $requestedExchange) {
                        $cancelReason = 'Falta de envase vacío';
                    } else {
                        $cancelReason = 'Cancelación parcial por el cliente';
                    }
                }

                $exchangeItem->update([
                    'delivered_quantity' => $deliveredExchange,
                    'cancelled_quantity' => $cancelledExchange,
                    'cancellation_reason' => $cancelReason,
                    'unit_price' => $refillPrice,
                    'collected_quantity' => $emptyReceived,
                    'status' => $deliveredExchange > 0 ? OrderItem::STATUS_DELIVERED : OrderItem::STATUS_CANCELLED,
                ]);

                // Registrar movimientos de custodia
                if ($emptyReceived > 0) {
                    BottleCustodyMovement::create([
                        'order_id' => $order->id,
                        'order_item_id' => $exchangeItem->id,
                        'customer_id' => $customer->id,
                        'user_id' => $driver->id,
                        'movement_type' => BottleCustodyMovement::TYPE_COLLECTION_EXCHANGE_EMPTY,
                        'bottle_type' => OrderItem::TYPE_EXCHANGE,
                        'bottle_condition' => BottleCustodyMovement::CONDITION_EMPTY,
                        'quantity' => $emptyReceived,
                        'origin' => 'customer',
                        'destination' => 'truck',
                        'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                        'notes' => 'Recolección de vacíos de cambio en domicilio',
                        'created_at' => now(),
                    ]);
                }

                if ($deliveredExchange > 0) {
                    BottleCustodyMovement::create([
                        'order_id' => $order->id,
                        'order_item_id' => $exchangeItem->id,
                        'customer_id' => $customer->id,
                        'user_id' => $driver->id,
                        'movement_type' => BottleCustodyMovement::TYPE_DELIVERY_REFILL_TO_CUSTOMER,
                        'bottle_type' => OrderItem::TYPE_EXCHANGE,
                        'bottle_condition' => BottleCustodyMovement::CONDITION_FULL,
                        'quantity' => $deliveredExchange,
                        'origin' => 'truck',
                        'destination' => 'customer',
                        'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                        'notes' => 'Entrega de botellones de cambio llenos',
                        'created_at' => now(),
                    ]);

                    // Actualizar inventario continuo de camión y validar existencias
                    if ($deliveredExchange > 0) {
                        $this->truckInventoryService->decrementExchangeFullForDelivery($driver, $deliveredExchange);
                    }
                    if ($emptyReceived > 0) {
                        $this->truckInventoryService->incrementExchangeEmptyCollected($driver, $emptyReceived);
                    }

                    // Procesamiento de Cargos, Promoción y Cobro en Efectivo
                    $this->processChargesAndPayments(
                        $order,
                        $exchangeItem,
                        $customer,
                        $deliveredExchange,
                        $refillPrice,
                        $executionData,
                        $driver
                    );
                }
            }

            // 2. GESTIÓN DE MODALIDAD FIJOS
            $fixedItem = $order->items()->where('type', OrderItem::TYPE_FIXED)->first();
            if ($fixedItem) {
                // Si la parada incluye o reporta recolección de fijos
                if (array_key_exists('fixed_collected', $executionData)) {
                    $fixedCollected = max(0, (int) $executionData['fixed_collected']);
                    $pendingToCollect = max(0, (int) $fixedItem->quantity - (int) $fixedItem->collected_quantity - (int) $fixedItem->cancelled_quantity);
                    $toCollect = min($fixedCollected, $pendingToCollect);
                    $uncollected = max(0, $pendingToCollect - $toCollect);

                    if ($toCollect > 0) {
                        $this->custodyService->recordFixedBottlesCollected($order, $toCollect, $driver);
                    }

                    if ($uncollected > 0) {
                        $cancelReason = !empty($executionData['fixed_uncollected_cancelled_reason'])
                            ? trim($executionData['fixed_uncollected_cancelled_reason'])
                            : (!empty($executionData['fixed_cancellation_reason'])
                                ? trim($executionData['fixed_cancellation_reason'])
                                : (!empty($executionData['cancellation_reason'])
                                    ? trim($executionData['cancellation_reason'])
                                    : 'Envases fijos no entregados por el cliente'));

                        $newStatus = ($toCollect > 0 || $fixedItem->collected_quantity > 0)
                            ? OrderItem::STATUS_TRANSPORTING_TO_PLANT
                            : OrderItem::STATUS_CANCELLED;

                        $fixedItem->update([
                            'cancelled_quantity' => $fixedItem->cancelled_quantity + $uncollected,
                            'cancellation_reason' => $cancelReason,
                            'status' => $newStatus,
                        ]);
                    }
                }

                // Si la parada es de entrega de fijos ya lavados y llenados
                if (!empty($executionData['fixed_delivered']) && (int) $executionData['fixed_delivered'] > 0) {
                    $fixedDelivered = (int) $executionData['fixed_delivered'];

                    // Validar existencias de fijos cargados en el camión para este cliente propietario
                    $loadedFixed = (int) BottleCustodyMovement::where('customer_id', $customer->id)
                        ->where('bottle_type', OrderItem::TYPE_FIXED)
                        ->where('movement_type', BottleCustodyMovement::TYPE_TRUCK_LOAD_FIXED)
                        ->where('destination', 'truck')
                        ->whereIn('status', [BottleCustodyMovement::STATUS_ACCEPTED, BottleCustodyMovement::STATUS_ACCEPTED_WITH_DISCREPANCY])
                        ->sum('quantity');

                    $alreadyDeliveredFixed = (int) BottleCustodyMovement::where('customer_id', $customer->id)
                        ->where('bottle_type', OrderItem::TYPE_FIXED)
                        ->where('movement_type', BottleCustodyMovement::TYPE_DELIVERY_REFILL_TO_CUSTOMER)
                        ->where('origin', 'truck')
                        ->whereIn('status', [BottleCustodyMovement::STATUS_ACCEPTED, BottleCustodyMovement::STATUS_ACCEPTED_WITH_DISCREPANCY])
                        ->sum('quantity');

                    $availableFixedOnTruck = max(0, $loadedFixed - $alreadyDeliveredFixed);

                    if ($fixedDelivered > $availableFixedOnTruck) {
                        throw new OrderBusinessRuleException(
                            "No se pueden entregar {$fixedDelivered} botellón(es) fijo(s). Solo hay {$availableFixedOnTruck} disponible(s) en el camión para este cliente.",
                            'fixed_delivered',
                            422
                        );
                    }

                    $newDelivered = $fixedItem->delivered_quantity + $fixedDelivered;
                    $adjustedCancelled = (int) $fixedItem->cancelled_quantity;
                    if ($fixedItem->collected_quantity > 0 && $newDelivered >= $fixedItem->collected_quantity && ($newDelivered + $adjustedCancelled) < $fixedItem->quantity) {
                        $adjustedCancelled = max(0, (int) $fixedItem->quantity - $newDelivered);
                    }
                    $isLineCompleted = ($newDelivered + $adjustedCancelled) >= $fixedItem->quantity;
                    $fixedItem->update([
                        'delivered_quantity' => $newDelivered,
                        'cancelled_quantity' => $adjustedCancelled,
                        'cancellation_reason' => $adjustedCancelled > (int) $fixedItem->cancelled_quantity
                            ? ($fixedItem->cancellation_reason ?: 'Ajuste de cantidad al recoger fijos')
                            : $fixedItem->cancellation_reason,
                        'unit_price' => $refillPrice,
                        'status' => $isLineCompleted ? OrderItem::STATUS_DELIVERED : OrderItem::STATUS_OUT_FOR_DELIVERY,
                    ]);

                    BottleCustodyMovement::create([
                        'order_id' => $order->id,
                        'order_item_id' => $fixedItem->id,
                        'customer_id' => $customer->id,
                        'user_id' => $driver->id,
                        'movement_type' => BottleCustodyMovement::TYPE_DELIVERY_REFILL_TO_CUSTOMER,
                        'bottle_type' => OrderItem::TYPE_FIXED,
                        'bottle_condition' => BottleCustodyMovement::CONDITION_FULL,
                        'quantity' => $fixedDelivered,
                        'origin' => 'truck',
                        'destination' => 'customer',
                        'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                        'notes' => 'Devolución de fijos llenados al cliente propietario',
                        'created_at' => now(),
                    ]);

                    $this->processChargesAndPayments(
                        $order,
                        $fixedItem,
                        $customer,
                        $fixedDelivered,
                        $refillPrice,
                        $executionData,
                        $driver
                    );
                }
            }

            // 3. DETERMINACIÓN DEL ESTADO FINAL DEL PEDIDO
            $order->refresh();
            $allUnitsResolved = true;
            foreach ($order->items as $it) {
                if ($it->quantity !== ($it->delivered_quantity + $it->cancelled_quantity)) {
                    $allUnitsResolved = false;
                    break;
                }
            }

            // Si todas las unidades están resueltas Y no hay fijos bajo custodia
            if ($allUnitsResolved && !$order->hasCollectedFixedCustody()) {
                $totalDelivered = (int) $order->items->sum('delivered_quantity');
                $totalCancelled = (int) $order->items->sum('cancelled_quantity');
                $totalQuantity = (int) $order->items->sum('quantity');

                if ($totalDelivered === 0 && $totalCancelled === $totalQuantity) {
                    // Si todas las unidades se cancelan y no hubo entrega ni custodia pendiente: CANCELADO
                    $order->update(['status' => Order::STATUS_CANCELLED]);
                    OrderStatusHistory::create([
                        'order_id' => $order->id,
                        'previous_status' => Order::STATUS_IN_ROUTE,
                        'new_status' => Order::STATUS_CANCELLED,
                        'user_id' => $driver->id,
                        'reason' => 'Pedido cancelado al no concretarse entregas en la parada',
                        'created_at' => now(),
                    ]);
                } else {
                    // Si hubo entrega (completa o parcial con resto cancelado): ENTREGADO
                    $order->update(['status' => Order::STATUS_DELIVERED]);
                    OrderStatusHistory::create([
                        'order_id' => $order->id,
                        'previous_status' => Order::STATUS_IN_ROUTE,
                        'new_status' => Order::STATUS_DELIVERED,
                        'user_id' => $driver->id,
                        'reason' => $totalCancelled > 0 
                            ? "Pedido completado con entrega parcial ({$totalDelivered} u. entregadas, {$totalCancelled} u. canceladas)"
                            : 'Pedido completado y entregado al 100%',
                        'created_at' => now(),
                    ]);
                }
            } elseif ($order->hasCollectedFixedCustody() || !$allUnitsResolved) {
                $order->update(['status' => Order::STATUS_IN_PROCESS]);
            }

            $lockedStop->update([
                'status' => RouteStop::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        });
    }

    /**
     * Marca una parada como fallida registrando obligatoriamente el motivo.
     */
    public function recordStopFailed(
        RouteStop $stop,
        string $reason,
        ?string $notes,
        User $driver
    ): void {
        $trimmedReason = trim($reason);
        if (empty($trimmedReason)) {
            throw new OrderBusinessRuleException('El motivo de la visita fallida es obligatorio.', 'failure_reason', 422);
        }

        DB::transaction(function () use ($stop, $trimmedReason, $notes, $driver) {
            $lockedStop = RouteStop::lockForUpdate()->findOrFail($stop->id);

            if ($lockedStop->status !== RouteStop::STATUS_PENDING) {
                throw new OrderBusinessRuleException('Esta parada ya ha sido procesada previamente y no puede marcarse como fallida.', 'status', 422);
            }

            $order = Order::lockForUpdate()->findOrFail($lockedStop->order_id);

            $lockedStop->update([
                'status' => RouteStop::STATUS_FAILED,
                'failure_reason' => $trimmedReason,
                'notes' => $notes,
                'completed_at' => now(),
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'previous_status' => $order->status,
                'new_status' => $order->status,
                'user_id' => $driver->id,
                'reason' => "Visita en ruta fallida: {$trimmedReason}",
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Registra una avería o incidencia en la hoja de ruta del único repartidor.
     * Conserva las paradas no atendidas como pendientes para su reanudación o reprogramación.
     */
    public function recordRouteBreakdown(
        DeliveryRoute $route,
        string $incidentReason,
        User $user
    ): DeliveryRoute {
        $trimmedReason = trim($incidentReason);
        if (empty($trimmedReason)) {
            throw new OrderBusinessRuleException('El motivo de la avería o incidencia es obligatorio.', 'reason', 422);
        }

        return DB::transaction(function () use ($route, $trimmedReason, $user) {
            $lockedRoute = DeliveryRoute::lockForUpdate()->findOrFail($route->id);

            if ($lockedRoute->status !== DeliveryRoute::STATUS_IN_PROGRESS && $lockedRoute->status !== DeliveryRoute::STATUS_ASSIGNED) {
                throw new OrderBusinessRuleException('Solo se pueden registrar incidencias en rutas activas o asignadas.', 'status', 422);
            }

            $pendingStopsCount = $lockedRoute->stops()->where('status', RouteStop::STATUS_PENDING)->count();
            $existingNotes = $lockedRoute->notes ? $lockedRoute->notes . ' | ' : '';
            $lockedRoute->update([
                'status' => DeliveryRoute::STATUS_INCIDENT,
                'notes' => $existingNotes . "Incidencia / Avería de unidad: {$trimmedReason} ({$pendingStopsCount} paradas conservadas pendientes)",
            ]);

            // Notificar a los pedidos asociados conservando sus paradas pendientes
            $pendingOrderIds = $lockedRoute->stops()->where('status', RouteStop::STATUS_PENDING)->pluck('order_id');
            $inRouteOrders = Order::whereIn('id', $pendingOrderIds)->get();
            foreach ($inRouteOrders as $ord) {
                OrderStatusHistory::create([
                    'order_id' => $ord->id,
                    'previous_status' => $ord->status,
                    'new_status' => $ord->status,
                    'user_id' => $user->id,
                    'reason' => "Avería de unidad en ruta: {$trimmedReason}. Parada conservada para reanudación.",
                    'created_at' => now(),
                ]);
            }

            return $lockedRoute->fresh('stops');
        });
    }

    /**
     * Reanuda formalmente una hoja de ruta que se encontraba en estado de avería o incidencia.
     */
    public function resumeRoute(DeliveryRoute $route, User $user, ?string $notes = null): DeliveryRoute
    {
        return DB::transaction(function () use ($route, $user, $notes) {
            $lockedRoute = DeliveryRoute::lockForUpdate()->findOrFail($route->id);

            if ($lockedRoute->status !== DeliveryRoute::STATUS_INCIDENT) {
                throw new OrderBusinessRuleException('Solo se pueden reanudar rutas que se encuentren en estado de incidencia o avería.', 'status', 422);
            }

            $newStatus = $lockedRoute->started_at ? DeliveryRoute::STATUS_IN_PROGRESS : DeliveryRoute::STATUS_ASSIGNED;
            $resumeNote = "Ruta reanudada tras resolver avería/incidencia" . ($notes ? ": {$notes}" : "");
            $existingNotes = $lockedRoute->notes ? $lockedRoute->notes . ' | ' : '';

            $lockedRoute->update([
                'status' => $newStatus,
                'notes' => $existingNotes . $resumeNote,
            ]);

            $pendingOrderIds = $lockedRoute->stops()->where('status', RouteStop::STATUS_PENDING)->pluck('order_id');
            $inRouteOrders = Order::whereIn('id', $pendingOrderIds)->get();
            foreach ($inRouteOrders as $ord) {
                OrderStatusHistory::create([
                    'order_id' => $ord->id,
                    'previous_status' => $ord->status,
                    'new_status' => $ord->status,
                    'user_id' => $user->id,
                    'reason' => "Ruta reanudada tras resolver avería. Parada lista para atención.",
                    'created_at' => now(),
                ]);
            }

            return $lockedRoute->fresh('stops');
        });
    }

    /**
     * Obtiene los pedidos elegibles para ser asignados a una nueva hoja de ruta.
     */
    public function getEligibleOrdersForRoute(): array
    {
        $activeRouteIds = DeliveryRoute::whereIn('status', [
            DeliveryRoute::STATUS_ASSIGNED,
            DeliveryRoute::STATUS_IN_PROGRESS,
            DeliveryRoute::STATUS_INCIDENT,
        ])->pluck('id');

        $orders = Order::with(['customer', 'items'])
            ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_IN_PROCESS])
            ->where(function ($q) use ($activeRouteIds) {
                $q->whereNull('delivery_route_id')
                  ->orWhereNotIn('delivery_route_id', $activeRouteIds);
            })
            ->orderBy('id', 'asc')
            ->get();

        $eligible = [];
        foreach ($orders as $order) {
            $exchangeItem = $order->items->firstWhere('type', OrderItem::TYPE_EXCHANGE);
            $fixedItem = $order->items->firstWhere('type', OrderItem::TYPE_FIXED);

            $exchangeQty = (int) ($exchangeItem?->quantity ?? 0);
            $fixedQty = (int) ($fixedItem?->quantity ?? 0);

            // Determinar tipo de operación
            $opType = 'intercambio';
            $opLabel = 'Intercambio de botellones de cambio';

            if ($exchangeQty > 0 && $fixedQty > 0) {
                $opType = 'mixto';
                $opLabel = 'Operación mixta (Cambio + Fijos)';
            } elseif ($fixedQty > 0) {
                if ($fixedItem->prepared_quantity > 0) {
                    $opType = 'devolucion_fijos';
                    $opLabel = 'Devolución de fijos preparados';
                } else {
                    $opType = 'recoleccion_fijos';
                    $opLabel = 'Recolección de fijos vacíos';
                }
            }

            $address = $order->delivery_street 
                ? ($order->delivery_street . ($order->delivery_exterior_number ? ' #' . $order->delivery_exterior_number : '') . ($order->delivery_neighborhood ? ', ' . $order->delivery_neighborhood : ''))
                : 'Sin ubicación registrada';

            $eligible[] = [
                'id' => $order->id,
                'folio' => $order->folio,
                'status' => $order->status,
                'customer_id' => $order->customer_id,
                'customer_code' => $order->customer?->code ?? "CLI-{$order->customer_id}",
                'customer_name' => $order->customer?->name,
                'customer_nickname' => $order->customer?->nickname,
                'customer_phone' => $order->recipient_phone ?: $order->customer?->phone,
                'delivery_zone' => $order->delivery_zone ?: 'Sin zona',
                'address_summary' => $address,
                'operation_type' => $opType,
                'operation_label' => $opLabel,
                'exchange_quantity' => $exchangeQty,
                'fixed_quantity' => $fixedQty,
                'total_bottles' => $exchangeQty + $fixedQty,
                'created_at' => $order->created_at?->format('Y-m-d H:i'),
            ];
        }

        return $eligible;
    }

    /**
     * Procesa cargos, evaluación de promoción semanal 9+1 y pagos en efectivo de la entrega.
     */
    protected function processChargesAndPayments(
        Order $order,
        OrderItem $item,
        Customer $customer,
        int $unitsDelivered,
        float $unitPrice,
        array $executionData,
        User $driver
    ): void {
        $unitsToCharge = $unitsDelivered;
        $unitsWaived = 0;

        // Comprobar si aplica el 10.º botellón gratuito en esta entrega
        $promo = $this->promotionService->checkOrGenerateWeeklyPromotion($customer);
        if ($promo && $promo->isAvailable() && $unitsToCharge > 0) {
            // Canjear 1 botellón gratuito
            $this->promotionService->redeemPromotion($promo, $order, $item);
            $this->debtService->recordCharge($order, $item, 1, $unitPrice, true); // waived promotion
            $unitsToCharge -= 1;
            $unitsWaived += 1;
        }

        if ($unitsToCharge > 0) {
            $paidUnits = isset($executionData['units_paid_cash']) 
                ? min($unitsToCharge, max(0, (int) $executionData['units_paid_cash'])) 
                : $unitsToCharge;

            $deferredUnits = $unitsToCharge - $paidUnits;

            // Validación de límite de pendientes de pago
            if ($deferredUnits > 0 && !$this->debtService->canChargeDebt($customer, $deferredUnits)) {
                $limit = app(SettingService::class)->getMaxPendingPaymentBottles();
                $pending = $this->debtService->getPendingDebtBottles($customer);
                throw new OrderBusinessRuleException(
                    "No se pueden fiar {$deferredUnits} botellones. El cliente cuenta con {$pending} pendientes y el límite es de {$limit}.",
                    'debt_limit',
                    422
                );
            }

            // Registrar cargo por las unidades no gratuitas
            $charge = $this->debtService->recordCharge($order, $item, $unitsToCharge, $unitPrice, false);

            // Registrar pago en efectivo si se pagaron unidades al momento
            if ($paidUnits > 0) {
                $cashTendered = isset($executionData['cash_tendered']) 
                    ? (float) $executionData['cash_tendered'] 
                    : (float) round($paidUnits * $unitPrice, 2);

                $this->debtService->recordPayment(
                    $customer,
                    $cashTendered,
                    $driver,
                    $order,
                    'Pago en efectivo recibido en ruta',
                    $paidUnits
                );

                // Si al pagar alcanza las 9 unidades semanales y tenía otra unidad pendiente en la misma entrega:
                if ($deferredUnits > 0) {
                    $newPromo = $this->promotionService->checkOrGenerateWeeklyPromotion($customer);
                    if ($newPromo && $newPromo->isAvailable()) {
                        // El cliente acaba de ganar el décimo gratis en la misma entrega
                        $this->promotionService->redeemPromotion($newPromo, $order, $item);

                        // Crear cargo de promoción para la unidad absuelta
                        $this->debtService->recordCharge($order, $item, 1, $unitPrice, true);

                        // Reducir la unidad fiada del cargo original
                        $charge->refresh();
                        $remainingUnits = $unitsToCharge - 1;
                        $charge->update([
                            'quantity_delivered' => $remainingUnits,
                            'total_amount' => round($remainingUnits * $unitPrice, 2),
                            'status' => ($charge->paid_amount >= round($remainingUnits * $unitPrice, 2)) 
                                ? OrderCharge::STATUS_PAID 
                                : OrderCharge::STATUS_PARTIALLY_PAID,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Registra un cliente nuevo sin cuenta directamente durante la ruta de reparto.
     * Apodo obligatorio, nombre opcional, sin exigir teléfono, correo ni domicilio.
     */
    public function registerDirectRouteCustomer(User $driver, array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $nickname = !empty($data['nickname']) ? trim((string)$data['nickname']) : null;
            if (empty($nickname)) {
                throw new OrderBusinessRuleException('El apodo es obligatorio para el registro rápido de cliente en ruta.', 'nickname', 422);
            }

            $name = !empty($data['name']) ? trim((string)$data['name']) : null;
            $phone = !empty($data['phone']) ? trim((string)$data['phone']) : null;

            $customer = Customer::create([
                'user_id' => null,
                'name' => $name,
                'nickname' => $nickname,
                'phone' => $phone,
                'fixed_bottles_count' => max(0, (int) ($data['fixed_bottles_count'] ?? 0)),
                'exchange_bottles_count' => max(0, (int) ($data['exchange_bottles_count'] ?? 0)),
                'notes' => !empty($data['notes']) ? trim((string)$data['notes']) : null,
            ]);

            // Domicilio es estrictamente opcional. Solo se registra si el cliente proporciona calle real o zona de reparto.
            $hasStreet = !empty($data['street']) && trim((string)$data['street']) !== '';
            $hasZone = !empty($data['delivery_zone']) && trim((string)$data['delivery_zone']) !== '';

            if ($hasStreet || $hasZone) {
                CustomerAddress::create([
                    'customer_id' => $customer->id,
                    'street' => $hasStreet ? trim((string)$data['street']) : 'Domicilio conocido',
                    'exterior_number' => !empty($data['exterior_number']) ? trim((string)$data['exterior_number']) : 'S/N',
                    'neighborhood' => !empty($data['neighborhood']) ? trim((string)$data['neighborhood']) : 'Centro',
                    'city' => !empty($data['city']) ? trim((string)$data['city']) : 'Hocabá',
                    'state' => 'Yucatán',
                    'delivery_zone' => $hasZone ? trim((string)$data['delivery_zone']) : null,
                    'between_streets' => !empty($data['between_streets']) ? trim((string)$data['between_streets']) : null,
                    'reference' => !empty($data['reference']) ? trim((string)$data['reference']) : null,
                    'is_default' => true,
                ]);
            }

            return $customer->fresh('address');
        });
    }

    /**
     * Registra un pedido directo solicitado en ruta.
     * No bloquea el servicio por falta de domicilio registrado.
     * Para Fijos: registra pedido y recogida de forma atómica iniciando en 'Llevando a planta' (in_process).
     * Para Cambio: registra pedido, intercambio y cobro en la misma operación.
     */
    public function registerDirectRouteOrder(
        User $driver,
        Customer $customer,
        array $orderData,
        ?DeliveryRoute $route = null
    ): Order {
        return DB::transaction(function () use ($driver, $customer, $orderData, $route) {
            $this->orderService->ensureCustomerHasNoActiveOrder($customer, true);

            $fixedQty = (int) ($orderData['fixed_quantity'] ?? 0);
            $exchangeQty = (int) ($orderData['exchange_quantity'] ?? 0);

            if (($fixedQty + $exchangeQty) <= 0) {
                throw new OrderBusinessRuleException('Debe solicitarse al menos un botellón en el pedido.', 'quantity', 422);
            }

            // Validar que las cantidades no superen las registradas del cliente
            if ($fixedQty > (int) $customer->fixed_bottles_count) {
                throw new OrderBusinessRuleException(
                    "La cantidad de botellones fijos solicitada ({$fixedQty}) supera los fijos registrados del cliente ({$customer->fixed_bottles_count}).",
                    'fixed_quantity',
                    422
                );
            }

            if ($exchangeQty > (int) $customer->exchange_bottles_count) {
                throw new OrderBusinessRuleException(
                    "La cantidad de botellones de cambio solicitada ({$exchangeQty}) supera los botellones de cambio registrados del cliente ({$customer->exchange_bottles_count}).",
                    'exchange_quantity',
                    422
                );
            }

            $address = $customer->address;
            $recipientName = $customer->nickname ?: ($customer->name ?: 'Cliente en ruta');
            $recipientPhone = $customer->phone;
            $requestedZone = !empty($orderData['delivery_zone']) ? trim((string)$orderData['delivery_zone']) : null;
            $deliveryZone = $requestedZone ?: $address?->delivery_zone;
            $zoneLabel = $deliveryZone ? (CustomerAddress::ZONES[$deliveryZone] ?? (str_starts_with($deliveryZone, 'Zona') ? $deliveryZone : "Zona {$deliveryZone}")) : 'Sin zona asignada';

            if ($address) {
                $addressSnapshot = [
                    'customer_id' => $customer->id,
                    'customer_code' => $customer->code,
                    'recipient_name' => $recipientName,
                    'recipient_phone' => $recipientPhone,
                    'delivery_street' => $address->street,
                    'delivery_exterior_number' => $address->exterior_number,
                    'delivery_neighborhood' => $address->neighborhood,
                    'delivery_city' => $address->city ?: 'Hocabá',
                    'delivery_state' => 'Yucatán',
                    'delivery_zone' => $deliveryZone,
                    'delivery_zone_label' => $zoneLabel,
                ];
                $addressId = $address->id;
                $deliveryStreet = $address->street;
                $deliveryExt = $address->exterior_number;
                $deliveryNeigh = $address->neighborhood;
                $deliveryCity = $address->city ?: 'Hocabá';
                $deliveryState = 'Yucatán';
            } else {
                $addressSnapshot = [
                    'customer_id' => $customer->id,
                    'customer_code' => $customer->code,
                    'recipient_name' => $recipientName,
                    'recipient_phone' => $recipientPhone,
                    'delivery_street' => null,
                    'delivery_exterior_number' => null,
                    'delivery_neighborhood' => null,
                    'delivery_city' => null,
                    'delivery_state' => null,
                    'delivery_zone' => $deliveryZone,
                    'delivery_zone_label' => $deliveryZone ? $zoneLabel : 'Sin domicilio registrado (Servicio directo en ruta)',
                ];
                $addressId = null;
                $deliveryStreet = null;
                $deliveryExt = null;
                $deliveryNeigh = null;
                $deliveryCity = null;
                $deliveryState = null;
            }

            $folio = OrderFolioGenerator::generate();

            $order = Order::create([
                'folio' => $folio,
                'customer_id' => $customer->id,
                'created_by_user_id' => $driver->id,
                'customer_address_id' => $addressId,
                'delivery_route_id' => $route?->id,
                'recipient_name' => $recipientName,
                'recipient_phone' => $recipientPhone,
                'delivery_street' => $deliveryStreet,
                'delivery_exterior_number' => $deliveryExt,
                'delivery_neighborhood' => $deliveryNeigh,
                'delivery_city' => $deliveryCity,
                'delivery_state' => $deliveryState,
                'delivery_zone' => $deliveryZone,
                'address_snapshot' => $addressSnapshot,
                'status' => $fixedQty > 0 ? Order::STATUS_IN_PROCESS : Order::STATUS_DELIVERED,
                'notes' => !empty($orderData['notes']) ? trim($orderData['notes']) : 'Servicio directo solicitado en ruta',
            ]);

            $refillPrice = (float) (BottlePresentation::where('capacity', 20.00)
                ->where('is_available_for_delivery', true)
                ->first()?->refill_price ?? 15.00);

            // Crear y ejecutar cambio
            if ($exchangeQty > 0) {
                $emptyReceived = isset($orderData['exchange_empty_received']) ? (int) $orderData['exchange_empty_received'] : $exchangeQty;
                $deliveredChange = min($emptyReceived, $exchangeQty);
                $cancelledChange = max(0, $exchangeQty - $deliveredChange);

                $changeItem = OrderItem::create([
                    'order_id' => $order->id,
                    'type' => OrderItem::TYPE_EXCHANGE,
                    'quantity' => $exchangeQty,
                    'delivered_quantity' => $deliveredChange,
                    'cancelled_quantity' => $cancelledChange,
                    'cancellation_reason' => $cancelledChange > 0 ? 'Falta de envase vacío' : null,
                    'unit_price' => $refillPrice,
                    'collected_quantity' => $emptyReceived,
                    'status' => $deliveredChange > 0 ? OrderItem::STATUS_DELIVERED : OrderItem::STATUS_CANCELLED,
                ]);

                if ($emptyReceived > 0) {
                    BottleCustodyMovement::create([
                        'order_id' => $order->id,
                        'order_item_id' => $changeItem->id,
                        'customer_id' => $customer->id,
                        'user_id' => $driver->id,
                        'movement_type' => BottleCustodyMovement::TYPE_COLLECTION_EXCHANGE_EMPTY,
                        'bottle_type' => OrderItem::TYPE_EXCHANGE,
                        'bottle_condition' => BottleCustodyMovement::CONDITION_EMPTY,
                        'quantity' => $emptyReceived,
                        'origin' => 'customer',
                        'destination' => 'truck',
                        'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                        'created_at' => now(),
                    ]);
                }

                if ($deliveredChange > 0) {
                    $this->truckInventoryService->decrementExchangeFullForDelivery($driver, $deliveredChange);

                    BottleCustodyMovement::create([
                        'order_id' => $order->id,
                        'order_item_id' => $changeItem->id,
                        'customer_id' => $customer->id,
                        'user_id' => $driver->id,
                        'movement_type' => BottleCustodyMovement::TYPE_DELIVERY_REFILL_TO_CUSTOMER,
                        'bottle_type' => OrderItem::TYPE_EXCHANGE,
                        'bottle_condition' => BottleCustodyMovement::CONDITION_FULL,
                        'quantity' => $deliveredChange,
                        'origin' => 'truck',
                        'destination' => 'customer',
                        'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                        'created_at' => now(),
                    ]);

                    $this->processChargesAndPayments($order, $changeItem, $customer, $deliveredChange, $refillPrice, $orderData, $driver);
                } elseif ($fixedQty === 0) {
                    $order->update(['status' => Order::STATUS_CANCELLED]);
                }

                if ($emptyReceived > 0) {
                    $this->truckInventoryService->incrementExchangeEmptyCollected($driver, $emptyReceived);
                }
            }

            // Crear y ejecutar recolección atómica de fijos
            if ($fixedQty > 0) {
                $fixedItem = OrderItem::create([
                    'order_id' => $order->id,
                    'type' => OrderItem::TYPE_FIXED,
                    'quantity' => $fixedQty,
                    'delivered_quantity' => 0,
                    'cancelled_quantity' => 0,
                    'unit_price' => $refillPrice,
                    'collected_quantity' => $fixedQty,
                    'status' => OrderItem::STATUS_TRANSPORTING_TO_PLANT,
                ]);

                BottleCustodyMovement::create([
                    'order_id' => $order->id,
                    'order_item_id' => $fixedItem->id,
                    'customer_id' => $customer->id,
                    'user_id' => $driver->id,
                    'movement_type' => BottleCustodyMovement::TYPE_COLLECTION_FIXED_FROM_CUSTOMER,
                    'bottle_type' => OrderItem::TYPE_FIXED,
                    'bottle_condition' => BottleCustodyMovement::CONDITION_EMPTY,
                    'quantity' => $fixedQty,
                    'origin' => 'customer',
                    'destination' => 'truck',
                    'status' => BottleCustodyMovement::STATUS_ACCEPTED,
                    'notes' => 'Recolección atómica en ruta - Llevando a planta',
                    'created_at' => now(),
                ]);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'previous_status' => null,
                'new_status' => $order->status,
                'user_id' => $driver->id,
                'reason' => 'Servicio directo registrado en ruta por repartidor',
                'created_at' => now(),
            ]);

            return $order->fresh(['items', 'customer', 'statusHistories']);
        });
    }
}
