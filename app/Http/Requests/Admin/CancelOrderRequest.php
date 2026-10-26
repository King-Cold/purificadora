<?php

namespace App\Http\Requests\Admin;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class CancelOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
     * Get the validation rules that apply to the request.
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
