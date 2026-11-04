<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantInventory extends Model
{
    use HasFactory;

    protected $table = 'plant_inventories';

    protected $fillable = [
        'exchange_empty_available',
        'exchange_washing_filling',
        'exchange_full_available',
        'exchange_full_reserved',
        'exchange_unlocated',
        'initial_count_completed_at',
        'initial_count_user_id',
        'initial_empty_count',
        'initial_full_count',
        'initial_count_notes',
    ];

    protected function casts(): array
    {
        return [
            'exchange_empty_available' => 'integer',
            'exchange_washing_filling' => 'integer',
            'exchange_full_available' => 'integer',
            'exchange_full_reserved' => 'integer',
            'exchange_unlocated' => 'integer',
            'initial_empty_count' => 'integer',
            'initial_full_count' => 'integer',
            'initial_count_completed_at' => 'datetime',
        ];
    }

    public function initialCountUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initial_count_user_id');
    }

    public function isInitialCountCompleted(): bool
    {
        return !is_null($this->initial_count_completed_at);
    }
}

