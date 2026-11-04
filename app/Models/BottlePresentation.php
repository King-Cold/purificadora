<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class BottlePresentation extends Model
{
    use HasFactory;

    /**
     * Capacidad estándar única del sistema en litros.
     */
    public const CAPACITY_LITERS = 20.00;
    public const UNIT_NAME = 'Litros';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'bottle_presentations';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'capacity',
        'unit',
        'refill_price',
        'store_price',
        'new_bottle_price',
        'image_path',
        'is_available_for_delivery',
        'is_active',
    ];

    /**
     * The "booted" method of the model.
     * Garantiza que la capacidad siempre sea exactamente 20 litros y previene su eliminación.
     */
    protected static function booted(): void
    {
        static::saving(function (BottlePresentation $model) {
            $model->capacity = self::CAPACITY_LITERS;
            $model->unit = self::UNIT_NAME;
        });

        static::deleting(function (BottlePresentation $model) {
            throw new LogicException('El botellón de 20 litros es la base operativa del sistema y no puede eliminarse.');
        });
    }

    /**
     * Obtiene la instancia única configurada del botellón de 20 litros.
     */
    public static function getTwentyLiters(): self
    {
        $pres = static::where('capacity', self::CAPACITY_LITERS)->first();
        if (!$pres) {
            $pres = static::first();
        }

        if (!$pres) {
            $pres = static::create([
                'name' => 'Botellón 20 Litros',
                'capacity' => self::CAPACITY_LITERS,
                'unit' => self::UNIT_NAME,
                'refill_price' => 15.00,
                'store_price' => 10.00,
                'new_bottle_price' => 95.00,
                'image_path' => '/images/garrafon-20l.png',
                'is_available_for_delivery' => true,
                'is_active' => true,
            ]);
        }

        return $pres;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'decimal:2',
            'refill_price' => 'decimal:2',
            'store_price' => 'decimal:2',
            'new_bottle_price' => 'decimal:2',
            'is_available_for_delivery' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope para filtrar presentaciones activas.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para filtrar presentaciones disponibles a domicilio.
     */
    public function scopeAvailableForDelivery(Builder $query): Builder
    {
        return $query->where('is_available_for_delivery', true);
    }

    /**
     * Capacidad formateada de forma legible (ej. 20 Litros).
     */
    protected function formattedCapacity(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                return '20 Litros';
            }
        );
    }

    /**
     * Comprueba si tiene precio de rellenado a domicilio configurado.
     */
    protected function hasRefillPrice(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => !is_null($this->refill_price) && (float) $this->refill_price > 0
        );
    }

    /**
     * Comprueba si tiene precio de envase nuevo configurado.
     */
    protected function hasNewBottlePrice(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => !is_null($this->new_bottle_price) && (float) $this->new_bottle_price > 0
        );
    }

    /**
     * Comprueba si tiene precio de llenado en planta configurado.
     */
    protected function hasStorePrice(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => !is_null($this->store_price) && (float) $this->store_price > 0
        );
    }

    /**
     * URL o ruta pública de la imagen del envase.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if (!$this->image_path) {
                    return null;
                }
                if (str_starts_with($this->image_path, 'http') || str_starts_with($this->image_path, '/')) {
                    return $this->image_path;
                }
                return '/storage/' . $this->image_path;
            }
        );
    }

    /**
     * Comprueba si existe una presentación duplicada considerando mayúsculas/minúsculas y espacios.
     */
    public static function checkDuplicate(string $name, $capacity, string $unit, ?int $ignoreId = null): ?string
    {
        $normalizedName = mb_strtolower(trim($name));

        $nameQuery = static::query()->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName]);
        if ($ignoreId) {
            $nameQuery->where('id', '!=', $ignoreId);
        }
        if ($nameQuery->exists()) {
            return 'name';
        }

        $capacityQuery = static::query()->where('capacity', self::CAPACITY_LITERS);
        if ($ignoreId) {
            $capacityQuery->where('id', '!=', $ignoreId);
        }
        if ($capacityQuery->exists()) {
            return 'capacity_unit';
        }

        return null;
    }

    /**
     * Evalúa si una capacidad y unidad de medida equivalen exactamente a 20 litros.
     */
    public static function isEquivalentToTwentyLiters($capacity, string $unit): bool
    {
        $numeric = (float) $capacity;
        $normalizedUnit = mb_strtolower(trim($unit));

        // Litros
        if (in_array($normalizedUnit, ['l', 'lt', 'lts', 'litro', 'litros'])) {
            return abs($numeric - 20.0) < 0.001;
        }

        // Mililitros
        if (in_array($normalizedUnit, ['ml', 'mililitros', 'mililitro'])) {
            return abs($numeric - 20000.0) < 0.001;
        }

        return false;
    }

    /**
     * Accesor para saber si la presentación equivale a 20 litros.
     */
    protected function isTwentyLiters(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => true
        );
    }
}
