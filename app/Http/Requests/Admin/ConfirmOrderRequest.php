<?php

namespace App\Http\Requests\Admin;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmOrderRequest extends FormRequest
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

        return $user->can('confirm', $order);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Nombres de atributos personalizados.
     */
    public function attributes(): array
    {
        return [
            'comment' => 'comentario de confirmación',
        ];
    }

    /**
     * Mensajes de error personalizados.
     */
    public function messages(): array
    {
        return [
            'comment.max' => 'El comentario de confirmación no puede exceder 500 caracteres.',
        ];
    }
}
