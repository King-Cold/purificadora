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
        'contact_name',
        'phone',
        'secondary_phone',
        'email',
        'tax_id',
        'customer_type',
        'price_tier',
        'credit_limit',
        'credit_days',
        'has_deposit_bottles',
        'status',
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
            'credit_limit' => 'decimal:2',
            'credit_days' => 'integer',
            'has_deposit_bottles' => 'boolean',
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
            'name' => ['required', 'string', 'max:150'],
            'nickname' => ['nullable', 'string', 'max:100'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:25'],
            'secondary_phone' => ['nullable', 'string', 'max:25'],
            'email' => ['nullable', 'email', 'max:120'],
            'tax_id' => ['nullable', 'string', 'max:25'],
            'customer_type' => ['required', Rule::in(['particular', 'comercio', 'empresa'])],
            'price_tier' => ['required', Rule::in(['general', 'mayoreo', 'especial'])],
            'credit_limit' => ['numeric', 'min:0'],
            'credit_days' => ['integer', 'min:0'],
            'has_deposit_bottles' => ['boolean'],
            'status' => ['required', Rule::in(['active', 'suspended', 'inactive'])],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Relación con el usuario del sistema (opcional: 1 a 1).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Todos los domicilios asociados al cliente.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * Domicilio predeterminado del cliente.
     */
    public function defaultAddress(): HasOne
    {
        return $this->hasOne(CustomerAddress::class)->where('is_default', true);
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
     * Scope para filtrar únicamente clientes activos.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
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
                ->orWhere('contact_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('secondary_phone', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        });
    }
}
