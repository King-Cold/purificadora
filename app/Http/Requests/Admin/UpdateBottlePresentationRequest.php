<?php

namespace App\Http\Requests\Admin;

use App\Models\BottlePresentation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateBottlePresentationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) && trim($this->name) !== '' ? trim($this->name) : 'Botellón 20 Litros',
            'unit' => 'Litros',
            'refill_price' => ($this->refill_price === '' || $this->refill_price === null) ? null : $this->refill_price,
            'store_price' => ($this->store_price === '' || $this->store_price === null) ? null : $this->store_price,
            'new_bottle_price' => ($this->new_bottle_price === '' || $this->new_bottle_price === null) ? null : $this->new_bottle_price,
            'image_path' => is_string($this->image_path) ? trim($this->image_path) : null,
            'is_available_for_delivery' => $this->boolean('is_available_for_delivery', true),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'numeric'],
            'unit' => ['nullable', 'string', 'max:20'],
            'refill_price' => ['nullable', 'numeric', 'gt:0', 'max:999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'store_price' => ['nullable', 'numeric', 'gt:0', 'max:999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'new_bottle_price' => ['nullable', 'numeric', 'gt:0', 'max:999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:4096'],
            'is_available_for_delivery' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            // Si el cliente intentó enviar una capacidad distinta de 20 litros, rechazar
            if ($this->has('capacity') && !is_null($this->input('capacity'))) {
                $rawCapacity = (float) $this->input('capacity');
                if (abs($rawCapacity - 20.0) >= 0.001) {
                    $v->errors()->add('capacity', 'El sistema opera exclusivamente con botellones de 20 litros.');
                }
            }
        });
    }

    /**
     * Nombres legibles para atributos.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre del botellón',
            'capacity' => 'capacidad',
            'refill_price' => 'precio del servicio a domicilio',
            'store_price' => 'precio de llenado en planta',
            'new_bottle_price' => 'precio del envase nuevo',
            'is_available_for_delivery' => 'disponible para entrega a domicilio',
            'is_active' => 'estado activo',
        ];
    }

    /**
     * Mensajes de validación personalizados.
     */
    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'gt' => 'El campo :attribute debe ser un valor mayor que cero.',
            'numeric' => 'El campo :attribute debe ser un número válido.',
            'regex' => 'El campo :attribute debe tener como máximo dos decimales.',
            'max' => 'El campo :attribute excede la longitud o valor máximo permitido.',
        ];
    }
}
