<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BottlePresentation extends Model
{
    use HasFactory;

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
     * Capacidad formateada de forma legible (ej. 20 L, 3.78 L).
     */
    protected function formattedCapacity(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $val = (float) ($this->capacity ?? 0);
                $formattedVal = (fmod($val, 1.0) == 0.0) ? (string) (int) $val : rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.');
                return "{$formattedVal} {$this->unit}";
            }
        );
    }

    /**
     * Comprueba si tiene precio de rellenado configurado.
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
     * Comprueba si tiene precio en sucursal configurado.
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
        $normalizedUnit = mb_strtolower(trim($unit));
        $numericCapacity = round((float) $capacity, 2);

        // 1. Validar duplicado por nombre normalizado
        $nameQuery = static::query()->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName]);
        if ($ignoreId) {
            $nameQuery->where('id', '!=', $ignoreId);
        }
        if ($nameQuery->exists()) {
            return 'name';
        }

        // 2. Validar duplicado por capacidad y unidad de medida
        $capacityQuery = static::query()
            ->where('capacity', $numericCapacity)
            ->whereRaw('LOWER(TRIM(unit)) = ?', [$normalizedUnit]);
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
            get: fn (): bool => self::isEquivalentToTwentyLiters($this->capacity, $this->unit ?? '')
        );
    }
}
