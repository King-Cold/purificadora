<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentChargeAllocation extends Model
{
    public $timestamps = false;

    protected $table = 'payment_charge_allocations';

    protected $fillable = [
        'order_payment_id',
        'order_charge_id',
        'amount_applied',
        'bottles_count',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_applied' => 'decimal:2',
            'bottles_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(OrderPayment::class, 'order_payment_id');
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(OrderCharge::class, 'order_charge_id');
    }
}

