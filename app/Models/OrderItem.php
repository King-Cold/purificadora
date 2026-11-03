<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    /**
     * Modalidades de botellones admitidas en los detalles de un pedido.
     */
    public const TYPE_FIXED = 'fijo';
    public const TYPE_EXCHANGE = 'cambio';

    public const TYPES = [
        self::TYPE_FIXED => 'Botellón Fijo',
        self::TYPE_EXCHANGE => 'Botellón de Cambio',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_TRANSPORTING_TO_PLANT = 'transporting_to_plant';
    public const STATUS_WASHING = 'washing';
    public const STATUS_READY_FOR_DELIVERY = 'ready_for_delivery';
    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_RETURNED_EMPTY = 'returned_empty';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'order_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'type',
        'quantity',
        'delivered_quantity',
        'cancelled_quantity',
        'cancellation_reason',
        'unit_price',
        'collected_quantity',
        'received_at_plant_quantity',
        'prepared_quantity',
        'loaded_quantity',
        'returned_empty_quantity',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'delivered_quantity' => 'integer',
            'cancelled_quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'collected_quantity' => 'integer',
            'received_at_plant_quantity' => 'integer',
            'prepared_quantity' => 'integer',
            'loaded_quantity' => 'integer',
            'returned_empty_quantity' => 'integer',
        ];
    }

    /**
     * Pedido al que pertenece el detalle.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function charge(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OrderCharge::class);
    }

    public function custodyMovements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BottleCustodyMovement::class);
    }

    /**
     * Comprueba si el detalle corresponde a botellones fijos.
     */
    public function isFixed(): bool
    {
        return $this->type === self::TYPE_FIXED;
    }

    /**
     * Comprueba si el detalle corresponde a botellones de cambio.
     */
    public function isExchange(): bool
    {
        return $this->type === self::TYPE_EXCHANGE;
    }

    /**
     * Etiqueta descriptiva del tipo de botellón.
     */
    protected function typeLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => self::TYPES[$this->type] ?? $this->type
        );
    }
}
