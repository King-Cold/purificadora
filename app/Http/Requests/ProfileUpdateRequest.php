<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $isClient = $user && $user->hasRole('Cliente');

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user?->id),
            ],
            'phone' => $isClient ? ['required', 'string', 'max:25'] : ['nullable', 'string', 'max:25'],
            'nickname' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre completo es obligatorio.',
            'name.max' => 'El nombre no debe superar los 150 caracteres.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingrese una dirección de correo electrónico válida.',
            'email.unique' => 'Este correo electrónico ya se encuentra registrado por otro usuario.',
            'phone.required' => 'El número de teléfono es obligatorio para contactarte ante entregas.',
            'phone.max' => 'El teléfono no debe superar los 25 caracteres.',
            'nickname.max' => 'El apodo no debe superar los 100 caracteres.',
        ];
    }
}
