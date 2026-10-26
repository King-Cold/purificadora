<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    use HasFactory;

    /**
     * Esta tabla solo almacena la marca temporal de creación del evento.
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'order_status_histories';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'previous_status',
        'new_status',
        'user_id',
        'reason',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * Pedido al que pertenece el historial.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Usuario responsable de la transición o acción.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Etiqueta del estado anterior.
     */
    protected function previousStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->previous_status ? (Order::STATUSES[$this->previous_status] ?? $this->previous_status) : null
        );
    }

    /**
     * Etiqueta del nuevo estado.
     */
    protected function newStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => Order::STATUSES[$this->new_status] ?? $this->new_status
        );
    }
}
