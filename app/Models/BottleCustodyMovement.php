<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BottleCustodyMovement extends Model
{
    use HasFactory;

    public $timestamps = false;

    // Statuses
    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    // Bottle conditions
    public const CONDITION_FULL = 'full';
    public const CONDITION_EMPTY = 'empty';

    // Movement types
    public const TYPE_TRUCK_LOAD_EXCHANGE = 'truck_load_exchange';
    public const TYPE_TRUCK_UNLOAD_EXCHANGE = 'truck_unload_exchange';
    public const TYPE_COLLECTION_FIXED_FROM_CUSTOMER = 'collection_fixed_from_customer';
    public const TYPE_COLLECTION_EXCHANGE_EMPTY = 'collection_exchange_empty';
    public const TYPE_PLANT_RECEPTION_FIXED = 'plant_reception_fixed';
    public const TYPE_PLANT_PREPARED_FIXED = 'plant_prepared_fixed';
    public const TYPE_TRUCK_LOAD_FIXED = 'truck_load_fixed';
    public const TYPE_DELIVERY_REFILL_TO_CUSTOMER = 'delivery_refill_to_customer';
    public const TYPE_RETURN_EMPTY_FIXED_TO_CUSTOMER = 'return_empty_fixed_to_customer';

    protected $table = 'bottle_custody_movements';

    protected $fillable = [
        'order_id',
        'order_item_id',
        'customer_id',
        'user_id',
        'movement_type',
        'bottle_type',
        'bottle_condition',
        'quantity',
        'proposed_quantity',
        'origin',
        'destination',
        'status',
        'confirmed_by_user_id',
        'confirmed_at',
        'difference_reason',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'proposed_quantity' => 'integer',
            'confirmed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }
}

