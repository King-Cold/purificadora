<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerAddress extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'customer_addresses';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'label',
        'street',
        'exterior_number',
        'interior_number',
        'neighborhood',
        'postal_code',
        'city',
        'state',
        'between_streets',
        'reference',
        'latitude',
        'longitude',
        'delivery_zone',
        'preferred_time_window',
        'is_default',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    /**
     * Cliente al que pertenece la dirección.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Accesor para obtener la dirección completa formateada.
     */
    protected function fullAddress(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $parts = [
                    $this->street . ' #' . $this->exterior_number,
                ];

                if (!empty($this->interior_number)) {
                    $parts[0] .= ' Int. ' . $this->interior_number;
                }

                if (!empty($this->neighborhood)) {
                    $parts[] = 'Col. ' . $this->neighborhood;
                }

                if (!empty($this->postal_code)) {
                    $parts[] = 'C.P. ' . $this->postal_code;
                }

                if (!empty($this->city)) {
                    $parts[] = $this->city;
                }

                return implode(', ', $parts);
            }
        );
    }
}
