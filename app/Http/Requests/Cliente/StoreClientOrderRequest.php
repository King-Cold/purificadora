<?php

namespace App\Http\Requests\Cliente;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use App\Services\StoreScheduleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreClientOrderRequest extends FormRequest
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

        return $user->can('create', Order::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fixed_quantity' => ['nullable', 'integer', 'min:0'],
            'exchange_quantity' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Nombres de atributos personalizados para los mensajes de error.
     */
    public function attributes(): array
    {
        return [
            'fixed_quantity' => 'botellones fijos (20L)',
            'exchange_quantity' => 'botellones de cambio (20L)',
            'notes' => 'observaciones',
        ];
    }

    /**
     * Mensajes de error personalizados.
     */
    public function messages(): array
    {
        return [
            'fixed_quantity.integer' => 'La cantidad de botellones fijos debe ser un número entero.',
            'fixed_quantity.min' => 'La cantidad de botellones fijos no puede ser negativa.',
            'exchange_quantity.integer' => 'La cantidad de botellones de cambio debe ser un número entero.',
            'exchange_quantity.min' => 'La cantidad de botellones de cambio no puede ser negativa.',
            'notes.max' => 'Las observaciones no pueden exceder los 500 caracteres.',
        ];
    }

    /**
     * Configurar el validador con las reglas comerciales específicas.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->user();
            $customer = $user?->customer;

            // 1. Cliente vinculado
            if (!$customer) {
                $validator->errors()->add('customer', 'Tu cuenta de usuario no tiene una ficha de cliente vinculada.');
                return;
            }

            if ($customer->trashed()) {
                $validator->errors()->add('customer', 'Tu cuenta de cliente se encuentra inhabilitada.');
                return;
            }

            // 2. Horario de recepción
            $scheduleService = app(StoreScheduleService::class);
            $scheduleCheck = $scheduleService->checkOrderReception();
            if (!$scheduleCheck['can_receive']) {
                $validator->errors()->add('schedule', $scheduleCheck['message']);
            }

            // 3. Domicilio activo
            $address = $customer->address;
            if (!$address || $address->trashed()) {
                $validator->errors()->add('address', 'Debes registrar tu domicilio de entrega para poder realizar pedidos.');
            }

            // 4. Ausencia de otro pedido activo
            $orderService = app(OrderService::class);
            $activeOrder = $orderService->getActiveOrderForCustomer($customer);
            if ($activeOrder) {
                $validator->errors()->add(
                    'active_order',
                    "Ya cuentas con un pedido activo con folio {$activeOrder->folio} en estado {$activeOrder->status_label}."
                );
            }

            // 5. Validación de botellones registrados y cantidades
            $fixedQty = (int) $this->input('fixed_quantity', 0);
            $exchangeQty = (int) $this->input('exchange_quantity', 0);
            $totalQty = $fixedQty + $exchangeQty;

            $registeredFixed = (int) ($customer->fixed_bottles_count ?? 0);
            $registeredExchange = (int) ($customer->exchange_bottles_count ?? 0);
            $totalRegistered = $registeredFixed + $registeredExchange;

            if ($totalRegistered <= 0) {
                $validator->errors()->add(
                    'quantity',
                    'No tienes botellones de 20 litros registrados en tu cuenta.'
                );
            } elseif ($totalQty <= 0) {
                $validator->errors()->add(
                    'quantity',
                    'Debes solicitar al menos un botellón de 20 litros en tu pedido.'
                );
            }

            if ($fixedQty > $registeredFixed) {
                $validator->errors()->add(
                    'fixed_quantity',
                    "La cantidad de botellones fijos solicitada ({$fixedQty}) supera tus botellones fijos registrados ({$registeredFixed})."
                );
            }

            if ($exchangeQty > $registeredExchange) {
                $validator->errors()->add(
                    'exchange_quantity',
                    "La cantidad de botellones de cambio solicitada ({$exchangeQty}) supera tus botellones de cambio registrados ({$registeredExchange})."
                );
            }

            if ($registeredFixed === 0 && $fixedQty > 0) {
                $validator->errors()->add(
                    'fixed_quantity',
                    'No tienes botellones fijos de 20L registrados en tu cuenta.'
                );
            }

            if ($registeredExchange === 0 && $exchangeQty > 0) {
                $validator->errors()->add(
                    'exchange_quantity',
                    'No tienes botellones de cambio de 20L registrados en tu cuenta.'
                );
            }
        });
    }
}
