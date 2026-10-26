<?php

namespace App\Http\Requests\Cliente;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class CancelClientOrderRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para cancelar este pedido.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        $order = $this->route('order');
        if (!$order instanceof Order) {
            return false;
        }

        return $user->can('cancel', $order);
    }

    /**
     * Reglas de validación para la cancelación.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * Nombres de atributos personalizados.
     */
    public function attributes(): array
    {
        return [
            'reason' => 'motivo de cancelación',
        ];
    }

    /**
     * Mensajes de error personalizados.
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'El motivo de cancelación es obligatorio.',
            'reason.min' => 'El motivo de cancelación debe contener al menos 3 caracteres.',
            'reason.max' => 'El motivo de cancelación no puede exceder 500 caracteres.',
        ];
    }
}
