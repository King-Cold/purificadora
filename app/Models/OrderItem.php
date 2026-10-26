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
        ];
    }

    /**
     * Pedido al que pertenece el detalle.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
