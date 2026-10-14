<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerAddressRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Preparar los datos para la validación normalizando entradas comunes.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('municipality') && !$this->has('municipality_type')) {
            $mun = trim((string)$this->input('municipality'));
            if (in_array(strtolower($mun), ['hocaba', 'hocabá', 'hocabá, yucatán', 'hocaba, yucatan'])) {
                $this->merge(['municipality_type' => 'hocaba']);
            } else {
                $this->merge([
                    'municipality_type' => 'other',
                    'other_municipality' => $mun,
                ]);
            }
        }

        if (!$this->has('street_and_number') && $this->has('street')) {
            $ext = $this->input('exterior_number');
            $num = (!empty($ext) && $ext !== 'S/N') ? ' #' . $ext : '';
            $this->merge([
                'street_and_number' => trim($this->input('street') . $num),
            ]);
        }
    }

    /**
     * Reglas de validación para el formulario simplificado de domicilio único.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'municipality_type' => ['required', 'in:hocaba,other'],
            'other_municipality' => ['required_if:municipality_type,other', 'nullable', 'string', 'max:100'],
            'delivery_zone' => [
                'required_if:municipality_type,hocaba',
                'nullable',
                'in:north,south,east,west',
            ],
            'street_and_number' => ['required', 'string', 'max:200'],
            'reference' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Mensajes claros y personalizados en español.
     */
    public function messages(): array
    {
        return [
            'municipality_type.required' => 'Debe seleccionar el municipio.',
            'municipality_type.in' => 'El tipo de municipio seleccionado no es válido.',
            'other_municipality.required_if' => 'Debe ingresar el nombre del municipio cuando selecciona "Otro municipio".',
            'other_municipality.max' => 'El municipio no debe exceder los 100 caracteres.',
            'delivery_zone.required_if' => 'Para Hocabá es obligatorio seleccionar la zona de reparto (Norte, Sur, Este u Oeste).',
            'delivery_zone.in' => 'La zona de reparto debe ser Norte, Sur, Este u Oeste.',
            'street_and_number.required' => 'La calle y número de la casa son obligatorios.',
            'street_and_number.max' => 'La calle y número no deben exceder los 200 caracteres.',
            'reference.max' => 'Las referencias para el repartidor no deben exceder los 500 caracteres.',
        ];
    }
}
