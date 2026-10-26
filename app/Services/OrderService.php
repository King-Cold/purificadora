<?php

namespace App\Services;

use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\OrderBusinessRuleException;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Inyección del servicio de horarios de la sucursal.
     */
    public function __construct(
        protected StoreScheduleService $scheduleService
    ) {}

    /**
     * Registra un nuevo pedido a domicilio aplicando todas las validaciones de negocio.
     *
     * @param User $creator Usuario autenticado que registra el pedido (Cliente o Administrador).
     * @param array $data Datos del pedido (items o cantidades, cliente_id para admin, domicilio, notas).
     * @param DateTimeInterface|string|null $atDateTime Fecha y hora a evaluar para horarios (permite simulación en pruebas).
     * @return Order
     * @throws OrderBusinessRuleException
     */
    public function createOrder(User $creator, array $data, $atDateTime = null): Order
    {
        // 1. REGLA DE HORARIO: La creación de pedidos debe respetar el horario configurado en StoreScheduleService
        $scheduleCheck = $this->scheduleService->checkOrderReception($atDateTime);
        if (!$scheduleCheck['can_receive']) {
            throw new OrderBusinessRuleException($scheduleCheck['message'], 'schedule', 422);
        }

        // 2. REGLA DE CLIENTES: Determinar y validar el cliente propietario
        $customer = $this->resolveCustomerForCreation($creator, $data);

        // 3. REGLA DE DOMICILIO: Obligatorio, perteneciente al cliente y activo
        $address = $this->resolveAddressForCreation($customer, $data);

        // 4. REGLA DE BOTELLONES Y CANTIDADES: Validar modalidades, límites y enteros positivos
        [$fixedQuantity, $exchangeQuantity] = $this->extractAndValidateQuantities($customer, $data);

        // 5. REGLA DE PEDIDO ACTIVO: Solo un pedido activo (pendiente o confirmado) por cliente
        $this->ensureCustomerHasNoActiveOrder($customer);

        // 6. PERSISTENCIA ATÓMICA CON CONCURRENCIA SEGURA
        return DB::transaction(function () use ($creator, $customer, $address, $fixedQuantity, $exchangeQuantity, $data) {
            // Bloqueo pesimista del cliente y verificación transaccional para evitar pedidos simultáneos
            Customer::where('id', $customer->id)->lockForUpdate()->first();
            $this->ensureCustomerHasNoActiveOrder($customer, true);

            // A. Generación segura del folio consecutivo (ej. PED-0001)
            $folio = OrderFolioGenerator::generate();

            // B. Copia histórica inmutable del domicilio y contacto
            $addressSnapshot = $this->buildAddressSnapshot($customer, $address);

            // C. Registro de la cabecera del pedido (siempre inicia en estado 'pending')
            $order = Order::create([
                'folio' => $folio,
                'customer_id' => $customer->id,
                'created_by_user_id' => $creator->id,
                'customer_address_id' => $address->id,
                'recipient_name' => $addressSnapshot['recipient_name'],
                'recipient_phone' => $addressSnapshot['recipient_phone'],
                'delivery_street' => $addressSnapshot['delivery_street'],
                'delivery_exterior_number' => $addressSnapshot['delivery_exterior_number'],
                'delivery_interior_number' => $addressSnapshot['delivery_interior_number'],
                'delivery_neighborhood' => $addressSnapshot['delivery_neighborhood'],
                'delivery_city' => $addressSnapshot['delivery_city'],
                'delivery_state' => $addressSnapshot['delivery_state'],
                'delivery_postal_code' => $addressSnapshot['delivery_postal_code'],
                'delivery_between_streets' => $addressSnapshot['delivery_between_streets'],
                'delivery_reference' => $addressSnapshot['delivery_reference'],
                'delivery_zone' => $addressSnapshot['delivery_zone'],
                'address_snapshot' => $addressSnapshot,
                'status' => Order::STATUS_PENDING,
                'notes' => !empty($data['notes']) ? trim((string) $data['notes']) : null,
            ]);

            // D. Registro de detalles por modalidad (sin guardar filas en cero)
            if ($fixedQuantity > 0) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'type' => OrderItem::TYPE_FIXED,
                    'quantity' => $fixedQuantity,
                ]);
            }

            if ($exchangeQuantity > 0) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'type' => OrderItem::TYPE_EXCHANGE,
                    'quantity' => $exchangeQuantity,
                ]);
            }

            // E. Registro del historial inicial de creación
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'previous_status' => null,
                'new_status' => Order::STATUS_PENDING,
                'user_id' => $creator->id,
                'reason' => 'Creación del pedido',
                'created_at' => now(),
            ]);

            return $order->load(['items', 'customer', 'creator', 'address', 'statusHistories']);
        });
    }

    /**
     * Confirma un pedido en estado Pendiente. Operación exclusiva de Administrador.
     *
     * @param Order $order
     * @param User $user Usuario responsable de la confirmación
     * @param string|null $comment Comentario u observación opcional
     * @return Order
     * @throws OrderBusinessRuleException|InvalidOrderTransitionException
     */
    public function confirmOrder(Order $order, User $user, ?string $comment = null): Order
    {
        // 1. Autorización: El cliente no puede confirmar pedidos
        if ($user->hasRole('Cliente')) {
            throw new OrderBusinessRuleException('El cliente no tiene autorización para confirmar pedidos.', 'authorization', 403);
        }

        if (!$user->hasRole('Administrador') && !$user->hasRole('Super Admin') && !$user->can('orders.confirm') && !$user->can('orders.update')) {
            throw new OrderBusinessRuleException('No tienes permisos para confirmar pedidos.', 'authorization', 403);
        }

        // 2. Transacción y bloqueo pesimista contra concurrencia
        return DB::transaction(function () use ($order, $user, $comment) {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);

            // Validar estado previo
            if ($lockedOrder->isConfirmed()) {
                throw new InvalidOrderTransitionException('El pedido ya se encuentra confirmado.');
            }

            if ($lockedOrder->isCancelled()) {
                throw new InvalidOrderTransitionException('No se puede confirmar un pedido que ha sido cancelado.');
            }

            if (!$lockedOrder->isPending()) {
                throw new InvalidOrderTransitionException("Transición inválida: no se puede confirmar un pedido en estado '{$lockedOrder->status}'.");
            }

            // Transición a Confirmado
            $lockedOrder->update(['status' => Order::STATUS_CONFIRMED]);

            // Registro en historial
            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'previous_status' => Order::STATUS_PENDING,
                'new_status' => Order::STATUS_CONFIRMED,
                'user_id' => $user->id,
                'reason' => !empty($comment) ? trim($comment) : 'Pedido confirmado por el administrador',
                'created_at' => now(),
            ]);

            return $lockedOrder->fresh(['items', 'customer', 'creator', 'statusHistories']);
        });
    }

    /**
     * Cancela un pedido registrando obligatoriamente el motivo.
     *
     * @param Order $order
     * @param User $user Usuario responsable de la cancelación
     * @param string $reason Motivo de la cancelación (obligatorio)
     * @return Order
     * @throws OrderBusinessRuleException|InvalidOrderTransitionException
     */
    public function cancelOrder(Order $order, User $user, string $reason): Order
    {
        $trimmedReason = trim($reason);
        if (empty($trimmedReason)) {
            throw new OrderBusinessRuleException('El motivo de cancelación es obligatorio.', 'reason', 422);
        }

        // Restricción a roles operativos ajenos
        if ($user->hasRole('Repartidor') || $user->hasRole('Operador Planta')) {
            throw new OrderBusinessRuleException('No tienes permisos para cancelar pedidos.', 'authorization', 403);
        }

        // Validar pertenencia si quien cancela es Cliente
        if ($user->hasRole('Cliente')) {
            $customer = $user->customer;
            if (!$customer || (int) $order->customer_id !== (int) $customer->id) {
                throw new OrderBusinessRuleException('No tienes autorización para cancelar este pedido.', 'authorization', 403);
            }
        }

        return DB::transaction(function () use ($order, $user, $trimmedReason) {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);

            // El estado cancelado es terminal
            if ($lockedOrder->isCancelled()) {
                throw new InvalidOrderTransitionException('El pedido ya se encuentra cancelado (estado terminal).');
            }

            // Si el pedido ya estaba Confirmado: únicamente Administrador puede cancelarlo
            if ($lockedOrder->isConfirmed()) {
                if ($user->hasRole('Cliente')) {
                    throw new OrderBusinessRuleException('El cliente no puede cancelar pedidos que ya han sido confirmados.', 'status', 403);
                }

                if (!$user->hasRole('Administrador') && !$user->hasRole('Super Admin') && !$user->can('orders.cancel')) {
                    throw new OrderBusinessRuleException('No tienes permisos para cancelar pedidos confirmados.', 'authorization', 403);
                }
            }

            $previousStatus = $lockedOrder->status;

            // Transición a Cancelado
            $lockedOrder->update(['status' => Order::STATUS_CANCELLED]);

            // Registro en historial
            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'previous_status' => $previousStatus,
                'new_status' => Order::STATUS_CANCELLED,
                'user_id' => $user->id,
                'reason' => $trimmedReason,
                'created_at' => now(),
            ]);

            return $lockedOrder->fresh(['items', 'customer', 'creator', 'statusHistories']);
        });
    }

    /**
     * Resuelve el cliente según el rol del usuario autenticado.
     */
    protected function resolveCustomerForCreation(User $creator, array $data): Customer
    {
        if ($creator->hasRole('Cliente')) {
            $customer = $creator->customer;
            if (!$customer) {
                throw new OrderBusinessRuleException('Tu cuenta de usuario no tiene un perfil de cliente asociado.', 'customer', 403);
            }

            // No confiar en customer_id enviado por el navegador
            if (isset($data['customer_id']) && (int) $data['customer_id'] !== (int) $customer->id) {
                throw new OrderBusinessRuleException('No tienes autorización para registrar pedidos a nombre de otro cliente.', 'customer_id', 403);
            }

            if ($customer->trashed()) {
                throw new OrderBusinessRuleException('Tu cuenta de cliente se encuentra inhabilitada.', 'customer', 403);
            }

            return $customer;
        }

        // Para Administrador / Super Admin
        if (empty($data['customer_id'])) {
            throw new OrderBusinessRuleException('Debes seleccionar un cliente para registrar el pedido administrativo.', 'customer_id', 422);
        }

        $customer = Customer::find($data['customer_id']);
        if (!$customer) {
            throw new OrderBusinessRuleException('El cliente seleccionado no existe.', 'customer_id', 404);
        }

        if ($customer->trashed()) {
            throw new OrderBusinessRuleException('No se pueden registrar pedidos para un cliente inactivo o eliminado.', 'customer_id', 422);
        }

        // REGLA: Los pedidos administrativos son exclusivos para clientes sin cuenta vinculada
        if ($customer->user_id !== null) {
            throw new OrderBusinessRuleException('Los pedidos administrativos solo pueden registrarse para clientes particulares sin cuenta.', 'customer_id', 422);
        }

        return $customer;
    }

    /**
     * Resuelve y valida el domicilio del cliente para la entrega.
     */
    protected function resolveAddressForCreation(Customer $customer, array $data): CustomerAddress
    {
        if (!empty($data['customer_address_id'])) {
            $address = CustomerAddress::where('id', $data['customer_address_id'])
                ->where('customer_id', $customer->id)
                ->first();

            if (!$address) {
                throw new OrderBusinessRuleException('El domicilio especificado no existe o no pertenece al cliente.', 'customer_address_id', 422);
            }
        } else {
            $address = $customer->address;

            if (!$address) {
                throw new OrderBusinessRuleException('El cliente debe contar con un domicilio de entrega registrado para realizar pedidos.', 'address', 422);
            }
        }

        if ($address->trashed()) {
            throw new OrderBusinessRuleException('El domicilio indicado ha sido eliminado y no puede utilizarse.', 'address', 422);
        }

        return $address;
    }

    /**
     * Extrae y valida las cantidades de botellones fijos y de cambio.
     * Retorna array [int $fixedQuantity, int $exchangeQuantity].
     */
    protected function extractAndValidateQuantities(Customer $customer, array $data): array
    {
        $fixedQuantity = 0;
        $exchangeQuantity = 0;

        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                if (!is_array($item) || !isset($item['type'])) {
                    throw new OrderBusinessRuleException('El formato de los detalles del pedido es inválido.', 'items', 422);
                }

                $type = trim((string) $item['type']);
                if (!in_array($type, [OrderItem::TYPE_FIXED, OrderItem::TYPE_EXCHANGE], true)) {
                    throw new OrderBusinessRuleException("El tipo de botellón '{$type}' no es válido. Solo se admiten fijos o de cambio.", 'type', 422);
                }

                $rawQty = $item['quantity'] ?? null;
                $qty = $this->parsePositiveInteger($rawQty, "La cantidad para {$type}");

                if ($type === OrderItem::TYPE_FIXED) {
                    $fixedQuantity += $qty;
                } else {
                    $exchangeQuantity += $qty;
                }
            }
        } else {
            // Formato alternativo plano: fixed_quantity y/o exchange_quantity
            if (isset($data['fixed_quantity'])) {
                $fixedQuantity = $this->parsePositiveInteger($data['fixed_quantity'], 'La cantidad de botellones fijos');
            }

            if (isset($data['exchange_quantity'])) {
                $exchangeQuantity = $this->parsePositiveInteger($data['exchange_quantity'], 'La cantidad de botellones de cambio');
            }
        }

        // Al menos un botellón debe ser solicitado
        $total = $fixedQuantity + $exchangeQuantity;
        if ($total <= 0) {
            throw new OrderBusinessRuleException('Debe solicitarse al menos un botellón en el pedido.', 'quantity', 422);
        }

        // Límite de botellones fijos registrados del cliente
        $clientFixedLimit = (int) ($customer->fixed_bottles_count ?? 0);
        if ($fixedQuantity > $clientFixedLimit) {
            throw new OrderBusinessRuleException(
                "La cantidad de botellones fijos solicitada ({$fixedQuantity}) supera los botellones fijos registrados del cliente ({$clientFixedLimit}).",
                'fixed_quantity',
                422
            );
        }

        // Límite de botellones de cambio registrados del cliente
        $clientExchangeLimit = (int) ($customer->exchange_bottles_count ?? 0);
        if ($exchangeQuantity > $clientExchangeLimit) {
            throw new OrderBusinessRuleException(
                "La cantidad de botellones de cambio solicitada ({$exchangeQuantity}) supera los botellones de cambio registrados del cliente ({$clientExchangeLimit}).",
                'exchange_quantity',
                422
            );
        }

        return [$fixedQuantity, $exchangeQuantity];
    }

    /**
     * Valida que un valor sea un entero no negativo ni decimal o texto.
     */
    protected function parsePositiveInteger($value, string $fieldName): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        // Si es float con decimales o cadena no numérica
        if (is_float($value) && floor($value) != $value) {
            throw new OrderBusinessRuleException("{$fieldName} debe ser un número entero sin decimales.", 'quantity', 422);
        }

        if (!is_numeric($value)) {
            throw new OrderBusinessRuleException("{$fieldName} debe ser un valor numérico entero.", 'quantity', 422);
        }

        $intVal = (int) $value;
        if ($intVal < 0) {
            throw new OrderBusinessRuleException("{$fieldName} no puede ser un valor negativo.", 'quantity', 422);
        }

        return $intVal;
    }

    /**
     * Construye la copia histórica inmutable del domicilio y contacto.
     */
    protected function buildAddressSnapshot(Customer $customer, CustomerAddress $address): array
    {
        $recipientName = trim($customer->name ?: ($customer->nickname ?: 'Cliente'));
        $recipientPhone = trim($customer->phone ?: ($customer->secondary_phone ?: ''));

        return [
            'customer_id' => $customer->id,
            'customer_code' => $customer->code,
            'recipient_name' => $recipientName,
            'recipient_phone' => $recipientPhone ?: null,
            'delivery_street' => $address->street,
            'delivery_exterior_number' => $address->exterior_number,
            'delivery_interior_number' => $address->interior_number,
            'delivery_neighborhood' => $address->neighborhood,
            'delivery_city' => $address->city ?: 'Hocabá',
            'delivery_state' => $address->state ?: 'Yucatán',
            'delivery_postal_code' => $address->postal_code,
            'delivery_between_streets' => $address->between_streets,
            'delivery_reference' => $address->reference,
            'delivery_zone' => $address->delivery_zone,
            'delivery_zone_label' => $address->zone_label,
            'snapshot_created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Obtiene el pedido activo actual de un cliente (en estado Pendiente o Confirmado).
     */
    public function getActiveOrderForCustomer(Customer $customer, bool $lockForUpdate = false): ?Order
    {
        $query = Order::where('customer_id', $customer->id)
            ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CONFIRMED]);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Valida que el cliente no cuente con un pedido en proceso (activo).
     *
     * @throws OrderBusinessRuleException
     */
    public function ensureCustomerHasNoActiveOrder(Customer $customer, bool $lockForUpdate = false): void
    {
        $activeOrder = $this->getActiveOrderForCustomer($customer, $lockForUpdate);

        if ($activeOrder) {
            throw new OrderBusinessRuleException(
                "Ya cuentas con un pedido activo con folio {$activeOrder->folio} en estado {$activeOrder->status_label}.",
                'active_order',
                422
            );
        }
    }
}
