<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPromotion extends Model
{
    use HasFactory;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_REDEEMED = 'redeemed';
    public const STATUS_EXPIRED = 'expired';

    protected $table = 'customer_promotions';

    protected $fillable = [
        'customer_id',
        'week_identifier',
        'week_start_date',
        'week_end_date',
        'eligible_at',
        'expires_at',
        'status',
        'redeemed_order_id',
        'redeemed_order_item_id',
        'redeemed_at',
    ];

    protected function casts(): array
    {
        return [
            'week_start_date' => 'date',
            'week_end_date' => 'date',
            'eligible_at' => 'datetime',
            'expires_at' => 'datetime',
            'redeemed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function redeemedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'redeemed_order_id');
    }

    public function redeemedOrderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'redeemed_order_item_id');
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE && now()->setTimezone('America/Merida')->lessThanOrEqualTo($this->expires_at);
    }
}

