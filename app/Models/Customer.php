<?php

namespace App\Models;

use App\Services\CustomerCodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'customers';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'code',
        'name',
        'nickname',
        'phone',
        'secondary_phone',
        'email',
        // Cantidad de botellones en posesión del cliente
        'fixed_bottles_count',
        'exchange_bottles_count',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fixed_bottles_count' => 'integer',
            'exchange_bottles_count' => 'integer',
        ];
    }

    /**
     * Boot del modelo para validaciones y generación automática de código.
     */
    protected static function booted(): void
    {
        static::creating(function (Customer $customer): void {
            // Generación de código si no fue provisto
            if (empty($customer->code)) {
                $customer->code = CustomerCodeGenerator::generate();
            }
        });

        static::saving(function (Customer $customer): void {
            // Protección contra vinculación múltiple de una misma cuenta User
            if (!empty($customer->user_id)) {
                $query = static::where('user_id', $customer->user_id);

                if ($customer->exists) {
                    $query->where('id', '!=', $customer->id);
                }

                if ($query->exists()) {
                    throw ValidationException::withMessages([
                        'user_id' => 'Esta cuenta de usuario ya se encuentra vinculada a otro perfil de cliente.',
                    ]);
                }
            }
        });
    }

    /**
     * Reglas de validación estándar para controladores y FormRequests.
     */
    public static function validationRules(?int $customerId = null): array
    {
        return [
            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                Rule::unique('customers', 'user_id')->ignore($customerId),
            ],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('customers', 'code')->ignore($customerId),
            ],
            'name' => ['nullable', 'string', 'max:150'],
            'nickname' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:25'],
            'secondary_phone' => ['nullable', 'string', 'max:25'],
            'email' => ['nullable', 'email', 'max:120'],
            'fixed_bottles_count' => ['required', 'integer', 'min:0'],
            'exchange_bottles_count' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Accesor informativo: Total de botellones en posesión del cliente (Fijos + Cambio).
     */
    public function getTotalBottlesAttribute(): int
    {
        return (int) (($this->fixed_bottles_count ?? 0) + ($this->exchange_bottles_count ?? 0));
    }

    /**
     * Relación con el usuario del sistema (opcional: 1 a 1).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Domicilio único del cliente particular.
     */
    public function address(): HasOne
    {
        return $this->hasOne(CustomerAddress::class)->whereNull('deleted_at');
    }

    /**
     * Domicilios asociados al cliente (relación histórica conservada).
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * Domicilio predeterminado del cliente (compatibilidad).
     */
    public function defaultAddress(): HasOne
    {
        return $this->hasOne(CustomerAddress::class)->whereNull('deleted_at');
    }

    /**
     * Determina si el cliente está habilitado para realizar nuevos pedidos.
     * Toda cuenta registrada permanece habilitada mientras no esté eliminada.
     */
    public function canPlaceOrders(): bool
    {
        return is_null($this->deleted_at);
    }

    /**
     * Accesor para leer el email del usuario vinculado si el cliente no tiene email comercial propio.
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ?: $this->user?->email,
            set: fn (?string $value) => $value
        );
    }

    /**
     * Scope para búsquedas rápidas por nombre, código, teléfono o correo.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($term) {
            $subQuery->where('name', 'like', "%{$term}%")
                ->orWhere('nickname', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('secondary_phone', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        });
    }
}
