<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantProductionBatch extends Model
{
    use HasFactory;

    public const STATUS_IN_PROCESS = 'in_process';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'plant_production_batches';

    protected $fillable = [
        'batch_number',
        'operator_user_id',
        'initial_quantity',
        'finished_quantity',
        'damaged_quantity',
        'status',
        'started_at',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'initial_quantity' => 'integer',
            'finished_quantity' => 'integer',
            'damaged_quantity' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_user_id');
    }

    public function getInProcessQuantity(): int
    {
        return max(0, (int)$this->initial_quantity - (int)$this->finished_quantity - (int)$this->damaged_quantity);
    }

    public static function generateBatchNumber(): string
    {
        $prefix = 'LOT-' . date('Ymd') . '-';
        $todayCount = static::whereDate('created_at', today())->count() + 1;
        return $prefix . str_pad((string)$todayCount, 4, '0', STR_PAD_LEFT);
    }
}
