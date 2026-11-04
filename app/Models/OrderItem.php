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
        'washing_quantity',
        'damaged_quantity',
        'prepared_quantity',
        'reserved_quantity',
        'loaded_quantity',
        'unlocated_quantity',
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
            'washing_quantity' => 'integer',
            'damaged_quantity' => 'integer',
            'prepared_quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'loaded_quantity' => 'integer',
            'unlocated_quantity' => 'integer',
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

    public function incidents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BottleIncident::class);
    }

    /**
     * Cantidad de botellones fijos dañados tras recepción física y antes de iniciar lavado (en almacén vacío).
     */
    public function getDamagedBeforeWashQuantity(): int
    {
        return (int) $this->incidents()->whereIn('stage', ['recepcion', 'almacen_vacio'])->sum('quantity');
    }

    /**
     * Cantidad de botellones fijos recibidos aptos en planta aún pendientes de ingresar a lavado.
     */
    public function getPendingToWashQuantity(): int
    {
        return max(0, (int)$this->received_at_plant_quantity - (int)$this->washing_quantity - $this->getDamagedBeforeWashQuantity());
    }

    /**
     * Cantidad de botellones dañados en la etapa de lavado y llenado.
     */
    public function getDamagedInWashingQuantity(): int
    {
        return (int) $this->incidents()->whereIn('stage', ['lavado', 'llenado'])->sum('quantity');
    }

    /**
     * Cantidad de botellones fijos actualmente en proceso de lavado y llenado (en túnel).
     */
    public function getInWashingQuantity(): int
    {
        return max(0, (int)$this->washing_quantity - (int)$this->prepared_quantity - $this->getDamagedInWashingQuantity());
    }

    /**
     * Cantidad de botellones dañados tras preparación (almacén planta o durante maniobra de carga).
     */
    public function getDamagedAfterPrepQuantity(): int
    {
        return (int) $this->incidents()->whereIn('stage', ['preparado', 'almacen_planta', 'carga_camion', 'carga'])->sum('quantity');
    }

    /**
     * Alias retrocompatible para daños en almacenamiento de preparados.
     */
    public function getDamagedInStorageQuantity(): int
    {
        return $this->getDamagedAfterPrepQuantity();
    }

    /**
     * Cantidad de botellones fijos preparados y listos para proponer en carga al camión.
     * Excluye cargados, reservados en propuestas activas, sin localizar y dañados.
     */
    public function getReadyToLoadQuantity(): int
    {
        return max(0, (int)$this->prepared_quantity - (int)$this->loaded_quantity - (int)$this->reserved_quantity - (int)$this->unlocated_quantity - $this->getDamagedAfterPrepQuantity());
    }

    /**
     * Cantidad de botellones dañados en tránsito en el camión.
     */
    public function getDamagedInTruckQuantity(): int
    {
        return (int) $this->incidents()->whereIn('stage', ['en_camion', 'ruta'])->sum('quantity');
    }

    /**
     * Cantidad de botellones actualmente en el camión camino al cliente.
     */
    public function getInTruckQuantity(): int
    {
        return max(0, (int)$this->loaded_quantity - (int)$this->delivered_quantity - $this->getDamagedInTruckQuantity());
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
