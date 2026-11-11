<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    /**
     * Estados del ciclo de vida del pedido.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_IN_ROUTE = 'in_route';
    public const STATUS_IN_PROCESS = 'in_process';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_INCIDENT = 'incident';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pendiente',
        self::STATUS_CONFIRMED => 'Confirmado',
        self::STATUS_IN_ROUTE => 'En ruta',
        self::STATUS_IN_PROCESS => 'En proceso',
        self::STATUS_DELIVERED => 'Entregado',
        self::STATUS_CANCELLED => 'Cancelado',
        self::STATUS_INCIDENT => 'Incidencia',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'orders';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'customer_id',
        'created_by_user_id',
        'customer_address_id',
        'delivery_route_id',
        'recipient_name',
        'recipient_phone',
        'delivery_street',
        'delivery_exterior_number',
        'delivery_interior_number',
        'delivery_neighborhood',
        'delivery_city',
        'delivery_state',
        'delivery_postal_code',
        'delivery_between_streets',
        'delivery_reference',
        'delivery_zone',
        'address_snapshot',
        'status',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'address_snapshot' => 'array',
        ];
    }

    /**
     * Cliente propietario del pedido.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Usuario que registró el pedido (Cliente o Administrador).
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Domicilio de referencia utilizado al momento de crear el pedido.
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'customer_address_id');
    }

    /**
     * Detalles del pedido (cantidades solicitadas por modalidad).
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Historial de cambios de estado del pedido.
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id', 'asc');
    }

    /**
     * Ruta de reparto a la que está asignado.
     */
    public function deliveryRoute(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class, 'delivery_route_id');
    }

    /**
     * Paradas de ruta registradas.
     */
    public function routeStops(): HasMany
    {
        return $this->hasMany(RouteStop::class, 'order_id');
    }

    /**
     * Alias para compatibilidad con paradas de ruta.
     */
    public function stops(): HasMany
    {
        return $this->routeStops();
    }

    /**
     * Cargos por unidades entregadas.
     */
    public function charges(): HasMany
    {
        return $this->hasMany(OrderCharge::class);
    }

    /**
     * Pagos asociados al pedido.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    /**
     * Movimientos auditables de custodia de botellones.
     */
    public function custodyMovements(): HasMany
    {
        return $this->hasMany(BottleCustodyMovement::class);
    }

    /**
     * Comprueba si el pedido se encuentra en estado Pendiente.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Comprueba si el pedido se encuentra en estado Confirmado.
     */
    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /**
     * Comprueba si el pedido está asignado a ruta.
     */
    public function isInRoute(): bool
    {
        return $this->status === self::STATUS_IN_ROUTE;
    }

    /**
     * Comprueba si el pedido está en proceso con envases en planta o pendientes de retorno.
     */
    public function isInProcess(): bool
    {
        return $this->status === self::STATUS_IN_PROCESS;
    }

    /**
     * Comprueba si el pedido ha sido completado y entregado en su totalidad.
     */
    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    /**
     * Comprueba si el pedido ha sido Cancelado (estado terminal).
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Comprueba si el pedido tiene registrada una incidencia con fijos en custodia.
     */
    public function isIncident(): bool
    {
        return $this->status === self::STATUS_INCIDENT;
    }

    /**
     * Determina si el pedido se encuentra activo en el sistema.
     */
    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_IN_ROUTE,
            self::STATUS_IN_PROCESS,
            self::STATUS_INCIDENT,
        ], true);
    }

    /**
     * Determina si el pedido tiene botellones fijos recogidos bajo custodia pendientes de devolución.
     */
    public function hasCollectedFixedCustody(): bool
    {
        return $this->items()
            ->where('type', OrderItem::TYPE_FIXED)
            ->where('collected_quantity', '>', 0)
            ->whereRaw('(returned_empty_quantity + delivered_quantity) < collected_quantity')
            ->exists();
    }

    /**
     * Determina si el pedido ya inició atención física (entregas, recolecciones o cobros).
     */
    public function hasStartedPhysicalAttention(): bool
    {
        // 1. Si ya se entregó alguna unidad (cambio o fijos)
        if ($this->items()->where('delivered_quantity', '>', 0)->exists()) {
            return true;
        }

        // 2. Si ya se recolectaron botellones fijos
        if ($this->items()->where('type', OrderItem::TYPE_FIXED)->where('collected_quantity', '>', 0)->exists()) {
            return true;
        }

        // 3. Si tiene pagos registrados
        if ($this->charges()->where('status', OrderCharge::STATUS_PAID)->exists()) {
            return true;
        }

        // 4. Si la parada en ruta ya fue completada o fallida
        if ($this->routeStops()->whereIn('status', [RouteStop::STATUS_COMPLETED, RouteStop::STATUS_FAILED])->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Determina si el pedido puede ser confirmado (solo cuando está en estado Pendiente).
     */
    public function canBeConfirmed(): bool
    {
        return $this->isPending();
    }

    /**
     * Determina si el pedido puede ser cancelado normalmente.
     * Si ya se inició atención física o se recogieron envases fijos, la cancelación normal se bloquea por completo.
     */
    public function canBeCancelled(): bool
    {
        if ($this->isCancelled() || $this->isDelivered()) {
            return false;
        }

        if ($this->hasCollectedFixedCustody() || $this->hasStartedPhysicalAttention()) {
            return false;
        }

        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_IN_ROUTE], true);
    }

    /**
     * Etiqueta descriptiva del estado actual.
     */
    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => self::STATUSES[$this->status] ?? $this->status
        );
    }

    /**
     * Obtiene la cantidad de botellones fijos solicitados en el pedido.
     */
    public function getFixedQuantityAttribute(): int
    {
        return (int) ($this->items->firstWhere('type', OrderItem::TYPE_FIXED)?->quantity ?? 0);
    }

    /**
     * Obtiene la cantidad de botellones de cambio solicitados en el pedido.
     */
    public function getExchangeQuantityAttribute(): int
    {
        return (int) ($this->items->firstWhere('type', OrderItem::TYPE_EXCHANGE)?->quantity ?? 0);
    }

    /**
     * Suma total de botellones solicitados en el pedido.
     */
    public function getTotalBottlesAttribute(): int
    {
        return $this->fixed_quantity + $this->exchange_quantity;
    }

    /**
     * Accesor para la dirección completa inmutable a partir de los datos históricos.
     */
    protected function fullDeliveryAddress(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $parts = [];

                if (!empty($this->delivery_street)) {
                    $streetPart = $this->delivery_street;
                    if (!empty($this->delivery_exterior_number) && $this->delivery_exterior_number !== 'S/N' && !str_contains($this->delivery_street, (string) $this->delivery_exterior_number)) {
                        $streetPart .= ' #' . $this->delivery_exterior_number;
                    }
                    $parts[] = $streetPart;
                }

                if (!empty($this->delivery_city)) {
                    $parts[] = $this->delivery_city;
                }

                return empty($parts) ? 'Sin domicilio registrado (Servicio directo en ruta)' : implode(', ', $parts);
            }
        );
    }

    /**
     * Obtiene el avance operativo detallado e independiente para clientes y administradores.
     * Distingue claramente: Pendiente, Confirmado, Asignado a ruta, Llevando fijos a planta,
     * Lavando, Listo para entregar, En repartición, Entregado y Cancelado.
     */
    public function getOperationalProgress(): array
    {
        $this->loadMissing(['items', 'deliveryRoute']);

        $exchangeItem = $this->items->firstWhere('type', OrderItem::TYPE_EXCHANGE);
        $fixedItem = $this->items->firstWhere('type', OrderItem::TYPE_FIXED);

        $hasExchange = !is_null($exchangeItem) && $exchangeItem->quantity > 0;
        $hasFixed = !is_null($fixedItem) && $fixedItem->quantity > 0;
        $isMixed = $hasExchange && $hasFixed;

        $exchangeProgress = null;
        if ($hasExchange) {
            $stageKey = 'pendiente';
            $stageLabel = 'Pendiente';

            if ($this->isCancelled()) {
                $stageKey = 'cancelado';
                $stageLabel = 'Cancelado';
            } elseif ($exchangeItem->delivered_quantity > 0 && ($exchangeItem->delivered_quantity + $exchangeItem->cancelled_quantity) >= $exchangeItem->quantity) {
                $stageKey = 'entregado';
                $stageLabel = 'Entregado';
            } elseif ($exchangeItem->delivered_quantity === 0 && $exchangeItem->cancelled_quantity >= $exchangeItem->quantity) {
                $stageKey = 'cancelado';
                $stageLabel = 'Cancelado';
            } elseif ($this->status === self::STATUS_IN_ROUTE || ($this->deliveryRoute && $this->deliveryRoute->status === DeliveryRoute::STATUS_IN_PROGRESS)) {
                $stageKey = 'en_reparticion';
                $stageLabel = 'En repartición';
            } elseif ($this->delivery_route_id && $this->deliveryRoute && $this->deliveryRoute->status === DeliveryRoute::STATUS_ASSIGNED) {
                $stageKey = 'asignado_a_ruta';
                $stageLabel = 'Asignado a ruta';
            } elseif ($this->status === self::STATUS_CONFIRMED) {
                $stageKey = 'confirmado';
                $stageLabel = 'Confirmado';
            }

            $exchangeProgress = [
                'type' => 'cambio',
                'label' => 'Botellones de Cambio (20L)',
                'quantity_requested' => (int) $exchangeItem->quantity,
                'delivered_quantity' => (int) $exchangeItem->delivered_quantity,
                'cancelled_quantity' => (int) $exchangeItem->cancelled_quantity,
                'cancellation_reason' => $exchangeItem->cancellation_reason,
                'stage_key' => $stageKey,
                'stage_label' => $stageLabel,
                'is_completed' => ($exchangeItem->delivered_quantity + $exchangeItem->cancelled_quantity) >= $exchangeItem->quantity,
            ];
        }

        $fixedProgress = null;
        if ($hasFixed) {
            $stageKey = 'pendiente';
            $stageLabel = 'Pendiente de recolección';

            if ($this->isCancelled() || ($fixedItem->status === OrderItem::STATUS_CANCELLED && $fixedItem->delivered_quantity === 0 && $fixedItem->collected_quantity === 0)) {
                $stageKey = 'cancelado';
                $stageLabel = 'Cancelado';
            } elseif ($fixedItem->delivered_quantity > 0 && ($fixedItem->delivered_quantity + $fixedItem->cancelled_quantity) >= $fixedItem->quantity) {
                $stageKey = 'entregado';
                $stageLabel = 'Devuelto y entregado';
            } elseif ($fixedItem->status === OrderItem::STATUS_OUT_FOR_DELIVERY || ($fixedItem->loaded_quantity > $fixedItem->delivered_quantity)) {
                $stageKey = 'en_reparticion';
                $stageLabel = 'En repartición (llevando a tu domicilio)';
            } elseif ($fixedItem->status === OrderItem::STATUS_READY_FOR_DELIVERY || ($fixedItem->prepared_quantity > 0 && $fixedItem->prepared_quantity > $fixedItem->loaded_quantity)) {
                $stageKey = 'listo_para_entregar';
                $stageLabel = 'Listo para entregar (en planta)';
            } elseif ($fixedItem->status === OrderItem::STATUS_WASHING) {
                $stageKey = 'lavando';
                $stageLabel = 'Lavando y llenando (en planta)';
            } elseif ($fixedItem->status === OrderItem::STATUS_TRANSPORTING_TO_PLANT || ($fixedItem->collected_quantity > 0 && $fixedItem->received_at_plant_quantity === 0)) {
                $stageKey = 'llevando_a_planta';
                $stageLabel = 'Llevando fijos a planta';
            } elseif ($this->status === self::STATUS_IN_ROUTE || ($this->deliveryRoute && $this->deliveryRoute->status === DeliveryRoute::STATUS_IN_PROGRESS)) {
                $stageKey = 'en_reparticion';
                $stageLabel = 'En repartición (ruta activa)';
            } elseif ($this->delivery_route_id && $this->deliveryRoute && $this->deliveryRoute->status === DeliveryRoute::STATUS_ASSIGNED) {
                $stageKey = 'asignado_a_ruta';
                $stageLabel = 'Asignado a ruta para recolección';
            } elseif ($this->status === self::STATUS_CONFIRMED) {
                $stageKey = 'confirmado';
                $stageLabel = 'Confirmado';
            }

            $fixedProgress = [
                'type' => 'fijo',
                'label' => 'Botellones Fijos (Identificados)',
                'quantity_requested' => (int) $fixedItem->quantity,
                'collected_quantity' => (int) $fixedItem->collected_quantity,
                'received_at_plant_quantity' => (int) $fixedItem->received_at_plant_quantity,
                'prepared_quantity' => (int) $fixedItem->prepared_quantity,
                'loaded_quantity' => (int) $fixedItem->loaded_quantity,
                'delivered_quantity' => (int) $fixedItem->delivered_quantity,
                'cancelled_quantity' => (int) $fixedItem->cancelled_quantity,
                'cancellation_reason' => $fixedItem->cancellation_reason,
                'stage_key' => $stageKey,
                'stage_label' => $stageLabel,
                'is_completed' => ($fixedItem->delivered_quantity + $fixedItem->cancelled_quantity) >= $fixedItem->quantity,
            ];
        }

        // Determinar estado y explicación global
        $overallKey = 'pendiente';
        $overallLabel = 'Pendiente';
        $explanation = 'Tu pedido ha sido recibido y está pendiente de confirmación por la purificadora.';

        if ($this->isCancelled()) {
            $overallKey = 'cancelado';
            $overallLabel = 'Cancelado';
            $explanation = 'El pedido ha sido cancelado.';
        } elseif ($this->isDelivered()) {
            $overallKey = 'entregado';
            $overallLabel = 'Entregado';
            $explanation = 'Todos los botellones del pedido han sido entregados con éxito.';
        } elseif ($this->hasCollectedFixedCustody()) {
            // Regla: no presentar como entregado si hay fijos en custodia
            $overallKey = $fixedProgress['stage_key'] ?? 'llevando_a_planta';
            $overallLabel = $fixedProgress['stage_label'] ?? 'Llevando fijos a planta';

            if ($isMixed && $exchangeProgress && $exchangeProgress['is_completed']) {
                $explanation = "Botellones de cambio entregados ({$exchangeProgress['delivered_quantity']} de {$exchangeProgress['quantity_requested']}). Tus botellones fijos ({$fixedProgress['collected_quantity']} u.) están bajo custodia en proceso de planta y retorno.";
            } else {
                $explanation = "Tus botellones fijos ({$fixedProgress['collected_quantity']} u.) fueron recolectados y se encuentran en traslado o proceso de planta para su devolución.";
            }
        } elseif ($this->status === self::STATUS_IN_ROUTE || ($this->deliveryRoute && $this->deliveryRoute->status === DeliveryRoute::STATUS_IN_PROGRESS)) {
            $overallKey = 'en_reparticion';
            $overallLabel = 'En repartición';
            $explanation = 'La unidad de reparto se encuentra en recorrido activo atendiendo las paradas asignadas.';
        } elseif ($this->delivery_route_id && $this->deliveryRoute && $this->deliveryRoute->status === DeliveryRoute::STATUS_ASSIGNED) {
            $overallKey = 'asignado_a_ruta';
            $overallLabel = 'Asignado a ruta';
            $explanation = 'El pedido ha sido programado en una hoja de ruta de reparto.';
        } elseif ($this->isConfirmed()) {
            $overallKey = 'confirmado';
            $overallLabel = 'Confirmado';
            $explanation = 'Tu pedido ha sido confirmado por administración y está listo para asignarse a ruta.';
        }

        return [
            'is_mixed' => $isMixed,
            'overall_stage_key' => $overallKey,
            'overall_stage_label' => $overallLabel,
            'explanation' => $explanation,
            'exchange' => $exchangeProgress,
            'fixed' => $fixedProgress,
            'last_updated_at' => now('America/Merida')->format('d/m/Y H:i:s'),
        ];
    }
}
