<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteStop extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'route_stops';

    protected $fillable = [
        'delivery_route_id',
        'order_id',
        'stop_number',
        'status',
        'failure_reason',
        'arrived_at',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'stop_number' => 'integer',
            'arrived_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class, 'delivery_route_id');
    }

    public function deliveryRoute(): BelongsTo
    {
        return $this->route();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}

