<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TruckInventory extends Model
{
    use HasFactory;

    protected $table = 'truck_inventories';

    protected $fillable = [
        'delivery_route_id',
        'driver_user_id',
        'exchange_full_available',
        'exchange_full_initial',
        'exchange_full_delivered',
        'exchange_empty_collected',
    ];

    protected function casts(): array
    {
        return [
            'exchange_full_available' => 'integer',
            'exchange_full_initial' => 'integer',
            'exchange_full_delivered' => 'integer',
            'exchange_empty_collected' => 'integer',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class, 'delivery_route_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_user_id');
    }

    public function getAvailableExchangeFullAttribute(): int
    {
        return max(0, (int) $this->exchange_full_available);
    }
}

