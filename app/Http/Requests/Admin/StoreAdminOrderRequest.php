<?php

namespace App\Http\Requests\Admin;

use App\Models\Customer;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\StoreScheduleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAdminOrderRequest extends FormRequest
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
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
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
            'customer_id' => 'cliente',
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
            'customer_id.required' => 'Debes seleccionar un cliente para registrar el pedido.',
            'customer_id.exists' => 'El cliente seleccionado no existe en el sistema.',
            'fixed_quantity.integer' => 'La cantidad de botellones fijos debe ser un número entero.',
            'fixed_quantity.min' => 'La cantidad de botellones fijos no puede ser negativa.',
            'exchange_quantity.integer' => 'La cantidad de botellones de cambio debe ser un número entero.',
            'exchange_quantity.min' => 'La cantidad de botellones de cambio no puede ser negativa.',
            'notes.max' => 'Las observaciones no pueden exceder los 500 caracteres.',
        ];
    }

    /**
     * Validaciones comerciales específicas para pedidos administrativos.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('customer_id')) {
                return;
            }

            $customer = Customer::find($this->input('customer_id'));
            if (!$customer) {
                $validator->errors()->add('customer_id', 'El cliente seleccionado no existe.');
                return;
            }

            if ($customer->trashed()) {
                $validator->errors()->add('customer_id', 'No se pueden registrar pedidos para un cliente inactivo o eliminado.');
                return;
            }

            // 1. REGLA: Exclusivamente para clientes SIN cuenta vinculada (user_id null)
            if ($customer->user_id !== null) {
                $validator->errors()->add('customer_id', 'Los pedidos administrativos solo pueden registrarse para clientes particulares sin cuenta.');
                return;
            }

            // 2. REGLA: Horario de recepción de pedidos
            $scheduleService = app(StoreScheduleService::class);
            $scheduleCheck = $scheduleService->checkOrderReception();
            if (!$scheduleCheck['can_receive']) {
                $validator->errors()->add('schedule', $scheduleCheck['message']);
            }

            // 3. REGLA: Domicilio activo obligatorio y perteneciente al cliente
            $address = $customer->address;
            if (!$address || $address->trashed()) {
                $validator->errors()->add('customer_id', 'El cliente no tiene un domicilio de entrega registrado.');
            }

            // 4. REGLA: Un solo pedido activo por cliente
            $orderService = app(OrderService::class);
            $activeOrder = $orderService->getActiveOrderForCustomer($customer);
            if ($activeOrder) {
                $validator->errors()->add(
                    'active_order',
                    "El cliente ya cuenta con un pedido activo con folio {$activeOrder->folio} en estado {$activeOrder->status_label}."
                );
            }

            // 5. REGLA: Cantidades de botellones de 20L
            $fixedQty = (int) $this->input('fixed_quantity', 0);
            $exchangeQty = (int) $this->input('exchange_quantity', 0);
            $totalQty = $fixedQty + $exchangeQty;

            $registeredFixed = (int) ($customer->fixed_bottles_count ?? 0);
            $registeredExchange = (int) ($customer->exchange_bottles_count ?? 0);
            $totalRegistered = $registeredFixed + $registeredExchange;

            if ($totalRegistered <= 0) {
                $validator->errors()->add('quantity', 'El cliente no tiene botellones de 20 litros registrados en su inventario.');
            } elseif ($totalQty <= 0) {
                $validator->errors()->add('quantity', 'Debe solicitarse al menos un botellón de 20 litros en el pedido.');
            }

            if ($fixedQty > $registeredFixed) {
                $validator->errors()->add(
                    'fixed_quantity',
                    "La cantidad solicitada ({$fixedQty}) supera los botellones fijos registrados del cliente ({$registeredFixed})."
                );
            }

            if ($exchangeQty > $registeredExchange) {
                $validator->errors()->add(
                    'exchange_quantity',
                    "La cantidad solicitada ({$exchangeQty}) supera los botellones de cambio registrados del cliente ({$registeredExchange})."
                );
            }
        });
    }
}
