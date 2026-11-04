<?php

namespace App\Http\Requests\Admin;

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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Configure the validator instance.
     * El sistema opera exclusivamente con botellones de 20 litros; no se permite agregar presentaciones adicionales.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $v->errors()->add('presentation', 'El sistema opera exclusivamente con botellones de 20 litros. No se permite agregar nuevas presentaciones al catálogo.');
        });
    }

    /**
     * Mensajes de validación personalizados.
     */
    public function messages(): array
    {
        return [];
    }
}
