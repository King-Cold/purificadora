<?php

namespace App\Http\Requests\Admin;

use App\Models\BottlePresentation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBottlePresentationRequest extends FormRequest
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
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'unit' => is_string($this->unit) ? trim($this->unit) : $this->unit,
            'refill_price' => ($this->refill_price === '' || $this->refill_price === null) ? null : $this->refill_price,
            'store_price' => ($this->store_price === '' || $this->store_price === null) ? null : $this->store_price,
            'new_bottle_price' => ($this->new_bottle_price === '' || $this->new_bottle_price === null) ? null : $this->new_bottle_price,
            'image_path' => is_string($this->image_path) ? trim($this->image_path) : null,
            'is_available_for_delivery' => $this->boolean('is_available_for_delivery', false),
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
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'numeric', 'gt:0', 'max:9999.99'],
            'unit' => ['required', 'string', 'max:20'],
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
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            // REGLA: Rechazar habilitar a domicilio presentaciones cuya capacidad no equivalga a 20 litros
            if ($this->boolean('is_available_for_delivery')) {
                if (!BottlePresentation::isEquivalentToTwentyLiters($this->input('capacity'), (string) $this->input('unit'))) {
                    $v->errors()->add('is_available_for_delivery', 'El servicio a domicilio únicamente está disponible para presentaciones de 20 litros.');
                    $v->errors()->add('capacity', 'Solo los envases de 20 litros pueden estar disponibles para entrega a domicilio.');
                }
            }

            $duplicateType = BottlePresentation::checkDuplicate(
                (string) $this->input('name'),
                $this->input('capacity'),
                (string) $this->input('unit')
            );

            if ($duplicateType === 'name') {
                $v->errors()->add('name', 'Ya existe una presentación con este nombre (sin considerar mayúsculas o espacios adicionales).');
            } elseif ($duplicateType === 'capacity_unit') {
                $v->errors()->add('capacity', 'Ya existe una presentación con esta misma capacidad y unidad de medida.');
            }
        });
    }

    /**
     * Nombres legibles para atributos.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre del envase',
            'capacity' => 'capacidad',
            'unit' => 'unidad de medida',
            'refill_price' => 'precio de rellenado',
            'new_bottle_price' => 'precio del envase nuevo',
            'is_available_for_delivery' => 'disponible a domicilio',
            'is_active' => 'estado',
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
