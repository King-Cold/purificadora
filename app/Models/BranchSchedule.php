<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchSchedule extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'branch_schedules';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'opening_time',
        'break_start_time',
        'break_end_time',
        'closing_time',
        'order_cutoff_advance_minutes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_cutoff_advance_minutes' => 'integer',
        ];
    }

    /**
     * Obtiene la única instancia de configuración de horario (o crea una predeterminada).
     */
    public static function current(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'opening_time' => '08:00',
                'break_start_time' => '13:00',
                'break_end_time' => '15:00',
                'closing_time' => '19:00',
                'order_cutoff_advance_minutes' => 60,
            ]
        );
    }

    /**
     * Calcula la hora límite de recepción de pedidos (cierre - anticipación).
     */
    protected function orderCutoffTime(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $closing = Carbon::createFromFormat('H:i', $this->opening_time ? substr($this->closing_time, 0, 5) : '19:00');
                $cutoff = $closing->subMinutes($this->order_cutoff_advance_minutes ?? 60);
                return $cutoff->format('H:i');
            }
        );
    }
}
