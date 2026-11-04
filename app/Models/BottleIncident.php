<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BottleIncident extends Model
{
    use HasFactory;

    // Stages
    public const STAGE_RECEPTION = 'recepcion';
    public const STAGE_EMPTY_STORAGE = 'almacen_vacio';
    public const STAGE_WASHING = 'lavado';
    public const STAGE_FILLING = 'llenado';
    public const STAGE_STORAGE = 'almacen';
    public const STAGE_PLANT_STORAGE = 'almacen_planta';
    public const STAGE_PREPARED = 'preparado';
    public const STAGE_LOADING = 'carga';
    public const STAGE_TRUCK_LOADING = 'carga_camion';
    public const STAGE_ON_TRUCK = 'en_camion';
    public const STAGE_ROUTE = 'ruta';

    // Damage types
    public const DAMAGE_BROKEN = 'rotura';
    public const DAMAGE_CRACKED = 'fisura';
    public const DAMAGE_CONTAMINATED = 'contaminacion';
    public const DAMAGE_DEFORMED = 'deformacion';
    public const DAMAGE_OTHER = 'otro';

    // Statuses
    public const STATUS_PENDING = 'pendiente_resolucion';
    public const STATUS_RESOLVED = 'resuelta';
    public const STATUS_DISMISSED = 'descartada';

    protected $table = 'bottle_incidents';

    protected $fillable = [
        'customer_id',
        'order_id',
        'order_item_id',
        'reported_by_user_id',
        'bottle_type',
        'bottle_condition',
        'quantity',
        'stage',
        'damage_type',
        'status',
        'notes',
        'resolved_by_user_id',
        'resolved_at',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function reportedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}

