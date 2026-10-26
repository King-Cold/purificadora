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
     * Estados comerciales iniciales del ciclo de vida del pedido.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pendiente',
        self::STATUS_CONFIRMED => 'Confirmado',
        self::STATUS_CANCELLED => 'Cancelado',
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
     * Comprueba si el pedido ha sido Cancelado (estado terminal).
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Determina si el pedido puede ser confirmado (solo cuando está en estado Pendiente).
     */
    public function canBeConfirmed(): bool
    {
        return $this->isPending();
    }

    /**
     * Determina si el pedido puede ser cancelado (cuando está Pendiente o Confirmado).
     */
    public function canBeCancelled(): bool
    {
        return $this->isPending() || $this->isConfirmed();
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

                return implode(', ', $parts);
            }
        );
    }
}
