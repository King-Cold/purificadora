<?php

namespace Tests\Feature;

use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\OrderBusinessRuleException;
use App\Models\BranchSchedule;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\OrderFolioGenerator;
use App\Services\OrderService;
use App\Services\StoreScheduleService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class OrderBusinessLogicTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $clientUser;
    protected Customer $clientCustomer;
    protected CustomerAddress $clientAddress;

    protected User $otherClientUser;
    protected Customer $otherClientCustomer;
    protected CustomerAddress $otherClientAddress;

    protected User $customerWithoutAccountUser;
    protected Customer $customerWithoutAccount;
    protected CustomerAddress $customerWithoutAccountAddress;

    protected User $repartidorUser;
    protected User $plantaUser;

    protected OrderService $orderService;
    protected StoreScheduleService $scheduleService;

    // Horas de prueba en horario válido (10:00 en America/Merida)
    protected Carbon $validOrderTime;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->orderService = app(OrderService::class);
        $this->scheduleService = app(StoreScheduleService::class);

        // Hora válida en horario de atención (10:00 AM)
        $this->validOrderTime = Carbon::create(2026, 10, 26, 10, 0, 0, StoreScheduleService::TIMEZONE);

        // 1. Administrador
        $this->adminUser = User::factory()->create([
            'name' => 'Admin Principal',
            'email' => 'admin@purificadora.com',
            'status' => 'active',
        ]);
        $this->adminUser->assignRole('Administrador');

        // 2. Cliente 1 (con cuenta vinculada)
        $this->clientUser = User::factory()->create([
            'name' => 'María Pech',
            'email' => 'maria@example.com',
            'status' => 'active',
        ]);
        $this->clientUser->assignRole('Cliente');

        $this->clientCustomer = Customer::create([
            'user_id' => $this->clientUser->id,
            'code' => 'CLI-0001',
            'name' => 'María Pech',
            'phone' => '9991234567',
            'fixed_bottles_count' => 3,
            'exchange_bottles_count' => 2,
        ]);

        $this->clientAddress = CustomerAddress::create([
            'customer_id' => $this->clientCustomer->id,
            'street' => 'Calle 20',
            'exterior_number' => '102',
            'neighborhood' => 'Centro',
            'city' => 'Hocabá',
            'delivery_zone' => CustomerAddress::ZONE_NORTH,
            'is_default' => true,
        ]);

        // 3. Cliente 2 (otro cliente con cuenta)
        $this->otherClientUser = User::factory()->create([
            'name' => 'José Canché',
            'email' => 'jose@example.com',
            'status' => 'active',
        ]);
        $this->otherClientUser->assignRole('Cliente');

        $this->otherClientCustomer = Customer::create([
            'user_id' => $this->otherClientUser->id,
            'code' => 'CLI-0002',
            'name' => 'José Canché',
            'phone' => '9997654321',
            'fixed_bottles_count' => 1,
            'exchange_bottles_count' => 1,
        ]);

        $this->otherClientAddress = CustomerAddress::create([
            'customer_id' => $this->otherClientCustomer->id,
            'street' => 'Calle 15',
            'exterior_number' => '45',
            'neighborhood' => 'Centro',
            'city' => 'Hocabá',
            'delivery_zone' => CustomerAddress::ZONE_SOUTH,
            'is_default' => true,
        ]);

        // 4. Cliente sin cuenta (para pedidos administrativos)
        $this->customerWithoutAccount = Customer::create([
            'user_id' => null,
            'code' => 'CLI-0003',
            'name' => 'Don Chucho Sin Cuenta',
            'phone' => '9998889999',
            'fixed_bottles_count' => 4,
            'exchange_bottles_count' => 5,
        ]);

        $this->customerWithoutAccountAddress = CustomerAddress::create([
            'customer_id' => $this->customerWithoutAccount->id,
            'street' => 'Calle 28',
            'exterior_number' => '500',
            'neighborhood' => 'Centro',
            'city' => 'Hocabá',
            'delivery_zone' => CustomerAddress::ZONE_EAST,
            'is_default' => true,
        ]);

        // 5. Repartidor
        $this->repartidorUser = User::factory()->create(['name' => 'Repartidor', 'email' => 'repartidor@purificadora.com']);
        $this->repartidorUser->assignRole('Repartidor');

        // 6. Operador Planta
        $this->plantaUser = User::factory()->create(['name' => 'Llenador', 'email' => 'planta@purificadora.com']);
        $this->plantaUser->assignRole('Operador Planta');
    }

    /**
     * 1. Creación de pedido solo de fijos.
     */
    public function test_can_create_order_with_only_fixed_bottles(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, [
            'fixed_quantity' => 2,
        ], $this->validOrderTime);

        $this->assertEquals(Order::STATUS_PENDING, $order->status);
        $this->assertEquals(2, $order->fixed_quantity);
        $this->assertEquals(0, $order->exchange_quantity);
        $this->assertEquals(2, $order->total_bottles);
        $this->assertCount(1, $order->items);
        $this->assertEquals(OrderItem::TYPE_FIXED, $order->items->first()->type);
        $this->assertEquals(2, $order->items->first()->quantity);
    }

    /**
     * 2. Creación de pedido solo de cambio.
     */
    public function test_can_create_order_with_only_exchange_bottles(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, [
            'exchange_quantity' => 1,
        ], $this->validOrderTime);

        $this->assertEquals(Order::STATUS_PENDING, $order->status);
        $this->assertEquals(0, $order->fixed_quantity);
        $this->assertEquals(1, $order->exchange_quantity);
        $this->assertEquals(1, $order->total_bottles);
        $this->assertCount(1, $order->items);
        $this->assertEquals(OrderItem::TYPE_EXCHANGE, $order->items->first()->type);
        $this->assertEquals(1, $order->items->first()->quantity);
    }

    /**
     * 3. Creación de pedido mixto (fijos y de cambio).
     */
    public function test_can_create_order_with_mixed_bottles(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, [
            'fixed_quantity' => 2,
            'exchange_quantity' => 2,
        ], $this->validOrderTime);

        $this->assertEquals(Order::STATUS_PENDING, $order->status);
        $this->assertEquals(2, $order->fixed_quantity);
        $this->assertEquals(2, $order->exchange_quantity);
        $this->assertEquals(4, $order->total_bottles);
        $this->assertCount(2, $order->items);
    }

    /**
     * 4. Cantidades exactamente iguales al límite registrado del cliente.
     */
    public function test_can_order_exact_registered_limit_quantities(): void
    {
        // María tiene 3 fijos y 2 de cambio
        $order = $this->orderService->createOrder($this->clientUser, [
            'fixed_quantity' => 3,
            'exchange_quantity' => 2,
        ], $this->validOrderTime);

        $this->assertEquals(5, $order->total_bottles);
        $this->assertEquals(3, $order->fixed_quantity);
        $this->assertEquals(2, $order->exchange_quantity);
    }

    /**
     * 5. Rechazo de cantidades que superan los botellones fijos registrados.
     */
    public function test_rejects_quantities_exceeding_fixed_bottles_count(): void
    {
        $this->expectException(OrderBusinessRuleException::class);
        $this->expectExceptionMessage('supera los botellones fijos registrados');

        // María solo tiene 3 fijos, pide 4
        $this->orderService->createOrder($this->clientUser, [
            'fixed_quantity' => 4,
            'exchange_quantity' => 1,
        ], $this->validOrderTime);
    }

    /**
     * 6. Rechazo de cantidades que superan los botellones de cambio registrados.
     */
    public function test_rejects_quantities_exceeding_exchange_bottles_count(): void
    {
        $this->expectException(OrderBusinessRuleException::class);
        $this->expectExceptionMessage('supera los botellones de cambio registrados');

        // María solo tiene 2 de cambio, pide 3
        $this->orderService->createOrder($this->clientUser, [
            'fixed_quantity' => 1,
            'exchange_quantity' => 3,
        ], $this->validOrderTime);
    }

    /**
     * 7. Rechazo de pedido vacío (sin botellones solicitados o cantidad cero).
     */
    public function test_rejects_empty_order_and_zero_quantities(): void
    {
        $this->expectException(OrderBusinessRuleException::class);
        $this->expectExceptionMessage('Debe solicitarse al menos un botellón');

        $this->orderService->createOrder($this->clientUser, [
            'fixed_quantity' => 0,
            'exchange_quantity' => 0,
        ], $this->validOrderTime);
    }

    /**
     * 8. Rechazo de cantidades negativas, decimales o texto no numérico.
     */
    public function test_rejects_negative_quantities_decimals_and_non_numeric_text(): void
    {
        // 1. Negativo
        try {
            $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => -2], $this->validOrderTime);
            $this->fail('Debió rechazar cantidad negativa.');
        } catch (OrderBusinessRuleException $e) {
            $this->assertStringContainsString('no puede ser un valor negativo', $e->getMessage());
        }

        // 2. Decimal
        try {
            $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1.5], $this->validOrderTime);
            $this->fail('Debió rechazar cantidad con decimales.');
        } catch (OrderBusinessRuleException $e) {
            $this->assertStringContainsString('sin decimales', $e->getMessage());
        }

        // 3. Texto no numérico
        try {
            $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 'cinco'], $this->validOrderTime);
            $this->fail('Debió rechazar texto.');
        } catch (OrderBusinessRuleException $e) {
            $this->assertStringContainsString('valor numérico entero', $e->getMessage());
        }
    }

    /**
     * 9. Rechazo de tipos de botellones no registrados (diferentes a fijo o cambio).
     */
    public function test_rejects_unregistered_or_invalid_bottle_types(): void
    {
        $this->expectException(OrderBusinessRuleException::class);
        $this->expectExceptionMessage("El tipo de botellón 'galon_desechable' no es válido");

        $this->orderService->createOrder($this->clientUser, [
            'items' => [
                ['type' => 'galon_desechable', 'quantity' => 2],
            ],
        ], $this->validOrderTime);
    }

    /**
     * 10. Domicilio obligatorio y perteneciente al cliente.
     */
    public function test_requires_registered_delivery_address_belonging_to_customer(): void
    {
        // Caso A: Cliente sin domicilio registrado
        $clientWithoutAddress = Customer::create([
            'user_id' => null,
            'code' => 'CLI-0004',
            'name' => 'Sin Casa',
            'fixed_bottles_count' => 2,
            'exchange_bottles_count' => 2,
        ]);

        try {
            $this->orderService->createOrder($this->adminUser, [
                'customer_id' => $clientWithoutAddress->id,
                'fixed_quantity' => 1,
            ], $this->validOrderTime);
            $this->fail('Debió requerir domicilio.');
        } catch (OrderBusinessRuleException $e) {
            $this->assertStringContainsString('domicilio de entrega registrado', $e->getMessage());
        }

        // Caso B: Intentar asociar un domicilio que pertenece a OTRO cliente
        try {
            $this->orderService->createOrder($this->clientUser, [
                'customer_address_id' => $this->otherClientAddress->id, // Pertenece a José Canché
                'fixed_quantity' => 1,
            ], $this->validOrderTime);
            $this->fail('Debió rechazar domicilio ajeno.');
        } catch (OrderBusinessRuleException $e) {
            $this->assertStringContainsString('no pertenece al cliente', $e->getMessage());
        }
    }

    /**
     * 11. Conservación de la copia histórica inmutable del domicilio y contacto.
     */
    public function test_preserves_immutable_historical_snapshot_of_delivery_address_and_contact(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, [
            'fixed_quantity' => 1,
        ], $this->validOrderTime);

        // Validar que la cabecera y el JSON contienen la foto actual
        $this->assertEquals('María Pech', $order->recipient_name);
        $this->assertEquals('9991234567', $order->recipient_phone);
        $this->assertEquals('Calle 20', $order->delivery_street);
        $this->assertEquals('102', $order->delivery_exterior_number);
        $this->assertEquals('Centro', $order->delivery_neighborhood);
        $this->assertEquals('Hocabá', $order->delivery_city);
        $this->assertEquals(CustomerAddress::ZONE_NORTH, $order->delivery_zone);

        // Modificamos al cliente y a su dirección en el sistema
        $this->clientCustomer->update([
            'name' => 'María Pech de González',
            'phone' => '9990000000',
        ]);
        $this->clientAddress->update([
            'street' => 'Avenida Principal 50',
            'exterior_number' => '999',
            'delivery_zone' => CustomerAddress::ZONE_SOUTH,
        ]);

        // Verificamos que la orden conserve intacta su copia histórica
        $orderFresh = $order->fresh();
        $this->assertEquals('María Pech', $orderFresh->recipient_name);
        $this->assertEquals('9991234567', $orderFresh->recipient_phone);
        $this->assertEquals('Calle 20', $orderFresh->delivery_street);
        $this->assertEquals('102', $orderFresh->delivery_exterior_number);
        $this->assertEquals('Centro', $orderFresh->delivery_neighborhood);
        $this->assertEquals(CustomerAddress::ZONE_NORTH, $orderFresh->delivery_zone);
        $this->assertEquals('Calle 20', $orderFresh->address_snapshot['delivery_street']);
    }

    /**
     * 12. Creación permitida y bloqueada según el horario configurable (con motivos exactos).
     */
    public function test_order_creation_respects_configurable_store_schedule_and_rejection_reasons(): void
    {
        // 07:59 -> Antes de abrir
        try {
            $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1], '07:59');
            $this->fail('Debió rechazar antes de apertura.');
        } catch (OrderBusinessRuleException $e) {
            $this->assertEquals('La sucursal todavía no abre.', $e->getMessage());
        }

        // 08:00 -> Apertura: Permitido
        $order0800 = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1], '08:00');
        $this->assertNotNull($order0800);
        $this->orderService->cancelOrder($order0800, $this->adminUser, 'Completado');

        // 13:00 -> En descanso
        try {
            $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1], '13:00');
            $this->fail('Debió rechazar durante el descanso.');
        } catch (OrderBusinessRuleException $e) {
            $this->assertEquals('La sucursal está en descanso.', $e->getMessage());
        }

        // 14:59 -> En descanso
        try {
            $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1], '14:59');
            $this->fail('Debió rechazar durante el descanso.');
        } catch (OrderBusinessRuleException $e) {
            $this->assertEquals('La sucursal está en descanso.', $e->getMessage());
        }

        // 15:00 -> Fin descanso: Permitido
        $order1500 = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1], '15:00');
        $this->assertNotNull($order1500);

        // 18:00 -> Anticipación de cierre alcanzada (límite 18:00 para cierre 19:00)
        try {
            $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1], '18:00');
            $this->fail('Debió rechazar al alcanzar hora límite de pedidos.');
        } catch (OrderBusinessRuleException $e) {
            $this->assertEquals('La recepción de pedidos terminó por hoy.', $e->getMessage());
        }

        // 19:00 -> Sucursal cerrada
        try {
            $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1], '19:00');
            $this->fail('Debió rechazar por sucursal cerrada.');
        } catch (OrderBusinessRuleException $e) {
            $this->assertEquals('La sucursal está cerrada.', $e->getMessage());
        }
    }

    /**
     * 13. El bloqueo por horario aplica tanto al Cliente como al Administrador.
     */
    public function test_schedule_rejection_applies_to_both_client_and_admin_creations(): void
    {
        // Cliente a las 18:05
        try {
            $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1], '18:05');
            $this->fail('Debió bloquear al cliente.');
        } catch (OrderBusinessRuleException $e) {
            $this->assertEquals('La recepción de pedidos terminó por hoy.', $e->getMessage());
        }

        // Administrador a las 18:05
        try {
            $this->orderService->createOrder($this->adminUser, [
                'customer_id' => $this->customerWithoutAccount->id,
                'fixed_quantity' => 1,
            ], '18:05');
            $this->fail('Debió bloquear al administrador.');
        } catch (OrderBusinessRuleException $e) {
            $this->assertEquals('La recepción de pedidos terminó por hoy.', $e->getMessage());
        }
    }

    /**
     * 14. El horario limita la creación, pero no bloquea consultar, confirmar ni cancelar pedidos.
     */
    public function test_schedule_does_not_block_viewing_confirming_or_cancelling_orders(): void
    {
        // Creamos un pedido en horario válido
        $order = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1], $this->validOrderTime);

        // Administrador confirma a las 22:00 (fuera de horario de creación)
        $confirmedOrder = $this->orderService->confirmOrder($order, $this->adminUser, 'Confirmado en guardia nocturna');
        $this->assertEquals(Order::STATUS_CONFIRMED, $confirmedOrder->status);

        // Administrador cancela a las 23:00 (fuera de horario de creación)
        $cancelledOrder = $this->orderService->cancelOrder($confirmedOrder, $this->adminUser, 'Cliente reportó error por llamada nocturna');
        $this->assertEquals(Order::STATUS_CANCELLED, $cancelledOrder->status);
    }

    /**
     * 15. Cancelación del cliente solo mientras está pendiente y requiere motivo obligatorio.
     */
    public function test_client_can_cancel_own_order_only_while_pending_and_requires_reason(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1], $this->validOrderTime);

        // 1. Motivo vacío es rechazado
        try {
            $this->orderService->cancelOrder($order, $this->clientUser, '   ');
            $this->fail('Debió exigir motivo de cancelación.');
        } catch (OrderBusinessRuleException $e) {
            $this->assertStringContainsString('motivo de cancelación es obligatorio', $e->getMessage());
        }

        // 2. Con motivo válido, se cancela exitosamente
        $cancelled = $this->orderService->cancelOrder($order, $this->clientUser, 'Ya no requiero el servicio hoy');
        $this->assertEquals(Order::STATUS_CANCELLED, $cancelled->status);

        // Historial registrado con motivo y usuario cliente
        $lastHistory = $cancelled->statusHistories->last();
        $this->assertEquals(Order::STATUS_PENDING, $lastHistory->previous_status);
        $this->assertEquals(Order::STATUS_CANCELLED, $lastHistory->new_status);
        $this->assertEquals($this->clientUser->id, $lastHistory->user_id);
        $this->assertEquals('Ya no requiero el servicio hoy', $lastHistory->reason);
    }

    /**
     * 16. El cliente no puede cancelar pedidos confirmados.
     */
    public function test_client_cannot_cancel_confirmed_order(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1], $this->validOrderTime);

        // Admin confirma
        $this->orderService->confirmOrder($order, $this->adminUser);

        // Cliente intenta cancelar
        $this->expectException(OrderBusinessRuleException::class);
        $this->expectExceptionMessage('El cliente no puede cancelar pedidos que ya han sido confirmados');

        $this->orderService->cancelOrder($order, $this->clientUser, 'Cancelación tardía');
    }

    /**
     * 17. Rechazo de cancelación o acceso a pedidos de otros clientes.
     */
    public function test_client_cannot_access_or_cancel_other_customers_orders(): void
    {
        // Pedido de María Pech
        $order = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1], $this->validOrderTime);

        // José Canché intenta cancelar el pedido de María
        try {
            $this->orderService->cancelOrder($order, $this->otherClientUser, 'Intento malicioso');
            $this->fail('Debió rechazar cancelación de pedido ajeno.');
        } catch (OrderBusinessRuleException $e) {
            $this->assertStringContainsString('No tienes autorización para cancelar este pedido', $e->getMessage());
        }

        // Comprobación mediante OrderPolicy
        $this->assertFalse(Gate::forUser($this->otherClientUser)->allows('view', $order));
        $this->assertFalse(Gate::forUser($this->otherClientUser)->allows('cancel', $order));
        $this->assertTrue(Gate::forUser($this->clientUser)->allows('view', $order));
        $this->assertTrue(Gate::forUser($this->clientUser)->allows('cancel', $order));
    }

    /**
     * 18. Confirmación y cancelación administrativa.
     */
    public function test_admin_can_confirm_and_cancel_orders(): void
    {
        $order = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $this->customerWithoutAccount->id,
            'fixed_quantity' => 2,
            'exchange_quantity' => 1,
        ], $this->validOrderTime);

        // 1. Confirmar
        $confirmed = $this->orderService->confirmOrder($order, $this->adminUser, 'Confirmado vía telefónica');
        $this->assertEquals(Order::STATUS_CONFIRMED, $confirmed->status);

        // 2. Cancelar pedido confirmado (solo Administrador puede)
        $cancelled = $this->orderService->cancelOrder($confirmed, $this->adminUser, 'Cliente llamó para cancelar pedido confirmado');
        $this->assertEquals(Order::STATUS_CANCELLED, $cancelled->status);

        $histories = $cancelled->statusHistories;
        $this->assertCount(3, $histories); // Creación -> Confirmado -> Cancelado
        $this->assertEquals(Order::STATUS_CONFIRMED, $histories[2]->previous_status);
        $this->assertEquals(Order::STATUS_CANCELLED, $histories[2]->new_status);
    }

    /**
     * 19. Rechazo de transiciones inválidas (no se puede volver a pendiente, cancelado es terminal).
     */
    public function test_rejects_invalid_state_transitions(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1], $this->validOrderTime);
        $cancelled = $this->orderService->cancelOrder($order, $this->clientUser, 'Cancelado');

        // Intentar confirmar un pedido cancelado
        try {
            $this->orderService->confirmOrder($cancelled, $this->adminUser);
            $this->fail('Debió rechazar confirmar pedido cancelado.');
        } catch (InvalidOrderTransitionException $e) {
            $this->assertStringContainsString('No se puede confirmar un pedido que ha sido cancelado', $e->getMessage());
        }

        // Intentar cancelar nuevamente un pedido cancelado
        try {
            $this->orderService->cancelOrder($cancelled, $this->adminUser, 'Re-cancelar');
            $this->fail('Debió rechazar cancelar pedido ya cancelado.');
        } catch (InvalidOrderTransitionException $e) {
            $this->assertStringContainsString('estado terminal', $e->getMessage());
        }
    }

    /**
     * 20. El cliente no puede confirmar pedidos bajo ninguna circunstancia.
     */
    public function test_client_cannot_confirm_orders(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1], $this->validOrderTime);

        $this->expectException(OrderBusinessRuleException::class);
        $this->expectExceptionMessage('El cliente no tiene autorización para confirmar pedidos');

        $this->orderService->confirmOrder($order, $this->clientUser);
    }

    /**
     * 21. Repartidor y Operador Planta no tienen acceso a estas operaciones.
     */
    public function test_repartidor_and_planta_have_no_access(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1], $this->validOrderTime);

        // Repartidor
        $this->assertFalse(Gate::forUser($this->repartidorUser)->allows('view', $order));
        $this->assertFalse(Gate::forUser($this->repartidorUser)->allows('create', Order::class));
        $this->assertFalse(Gate::forUser($this->repartidorUser)->allows('confirm', $order));
        $this->assertFalse(Gate::forUser($this->repartidorUser)->allows('cancel', $order));

        // Operador Planta
        $this->assertFalse(Gate::forUser($this->plantaUser)->allows('view', $order));
        $this->assertFalse(Gate::forUser($this->plantaUser)->allows('create', Order::class));
        $this->assertFalse(Gate::forUser($this->plantaUser)->allows('confirm', $order));
        $this->assertFalse(Gate::forUser($this->plantaUser)->allows('cancel', $order));
    }

    /**
     * 22. Crear, confirmar o cancelar un pedido NO modifica los conteos de botellones del cliente.
     */
    public function test_order_lifecycle_does_not_modify_customer_bottles_count(): void
    {
        $initialFixed = $this->clientCustomer->fixed_bottles_count;
        $initialExchange = $this->clientCustomer->exchange_bottles_count;

        // 1. Crear
        $order = $this->orderService->createOrder($this->clientUser, [
            'fixed_quantity' => 2,
            'exchange_quantity' => 1,
        ], $this->validOrderTime);

        $this->assertEquals($initialFixed, $this->clientCustomer->fresh()->fixed_bottles_count);
        $this->assertEquals($initialExchange, $this->clientCustomer->fresh()->exchange_bottles_count);

        // 2. Confirmar
        $this->orderService->confirmOrder($order, $this->adminUser);

        $this->assertEquals($initialFixed, $this->clientCustomer->fresh()->fixed_bottles_count);
        $this->assertEquals($initialExchange, $this->clientCustomer->fresh()->exchange_bottles_count);

        // 3. Cancelar
        $this->orderService->cancelOrder($order, $this->adminUser, 'Cancelación de prueba');

        $this->assertEquals($initialFixed, $this->clientCustomer->fresh()->fixed_bottles_count);
        $this->assertEquals($initialExchange, $this->clientCustomer->fresh()->exchange_bottles_count);
    }

    /**
     * 23. Unicidad de folios correlativos (PED-0001, PED-0002) y unicidad de tipo en detalles.
     */
    public function test_folio_generation_and_order_items_uniqueness(): void
    {
        $order1 = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1], $this->validOrderTime);
        $this->orderService->cancelOrder($order1, $this->adminUser, 'Completado');
        $order2 = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1], $this->validOrderTime);

        $this->assertEquals('PED-0001', $order1->folio);
        $this->assertEquals('PED-0002', $order2->folio);

        // Intentar duplicar la misma modalidad de botellón en el mismo pedido en la base de datos
        $this->expectException(\Illuminate\Database\QueryException::class);
        OrderItem::create([
            'order_id' => $order1->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => 1,
        ]);
    }

    /**
     * 24. Regla de negocio: Un cliente no puede crear un pedido si ya tiene uno activo (pendiente o confirmado).
     */
    public function test_rejects_creating_order_when_customer_already_has_active_order(): void
    {
        $activeOrder = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1], $this->validOrderTime);
        $this->assertEquals(Order::STATUS_PENDING, $activeOrder->status);

        // Intentar crear un segundo pedido mientras el primero está pendiente
        try {
            $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1], $this->validOrderTime);
            $this->fail('Debió rechazar la creación por pedido activo.');
        } catch (OrderBusinessRuleException $e) {
            $this->assertEquals('active_order', $e->getErrorKey());
            $this->assertStringContainsString($activeOrder->folio, $e->getMessage());
        }

        // Confirmar el pedido: sigue estando activo
        $this->orderService->confirmOrder($activeOrder, $this->adminUser);

        try {
            $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1], $this->validOrderTime);
            $this->fail('Debió rechazar la creación con pedido confirmado activo.');
        } catch (OrderBusinessRuleException $e) {
            $this->assertEquals('active_order', $e->getErrorKey());
            $this->assertStringContainsString($activeOrder->folio, $e->getMessage());
        }

        // Cancelar el pedido: deja de estar activo y ahora sí permite uno nuevo
        $this->orderService->cancelOrder($activeOrder, $this->adminUser, 'Liberar para nuevo pedido');
        $newOrder = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1], $this->validOrderTime);
        $this->assertNotNull($newOrder);
        $this->assertEquals('PED-0002', $newOrder->folio);
    }
}
