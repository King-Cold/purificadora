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

    public const ZONE_NORTH = 'north';
    public const ZONE_SOUTH = 'south';
    public const ZONE_EAST = 'east';
    public const ZONE_WEST = 'west';

    public const ZONES = [
        self::ZONE_NORTH => 'Norte',
        self::ZONE_SOUTH => 'Sur',
        self::ZONE_EAST => 'Este',
        self::ZONE_WEST => 'Oeste',
    ];

    /**
     * Cliente al que pertenece la dirección.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Accesor para el nombre legible de la zona de reparto.
     */
    protected function zoneLabel(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->delivery_zone) {
                    $name = self::ZONES[$this->delivery_zone] ?? $this->delivery_zone;
                    return str_starts_with($name, 'Zona') ? $name : "Zona {$name}";
                }

                $isHocaba = empty($this->city) || in_array(strtolower(trim($this->city)), ['hocaba', 'hocabá', 'hocabá, yucatán', 'hocaba, yucatan']);
                if (!$isHocaba) {
                    return 'Otro municipio';
                }

                return 'Sin zona asignada';
            }
        );
    }

    /**
     * Alias para municipio (almacenado en la columna city).
     */
    protected function municipality(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->city,
            set: fn ($value) => ['city' => $value],
        );
    }

    /**
     * Accesor para obtener la dirección completa formateada.
     */
    protected function fullAddress(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $parts = [];

                if (!empty($this->street)) {
                    $streetPart = $this->street;
                    if (!empty($this->exterior_number) && $this->exterior_number !== 'S/N' && !str_contains($this->street, (string)$this->exterior_number)) {
                        $streetPart .= ' #' . $this->exterior_number;
                    }
                    $parts[] = $streetPart;
                }

                if (!empty($this->city)) {
                    $parts[] = $this->city;
                }

                return implode(', ', $parts);
            }
        );
    }
}
