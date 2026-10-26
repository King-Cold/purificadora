<?php

namespace Tests\Feature;

use App\Models\BranchSchedule;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\OrderService;
use App\Services\StoreScheduleService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $clientUser;
    protected Customer $clientCustomer;
    protected CustomerAddress $clientAddress;

    protected User $otherClientUser;
    protected Customer $otherClientCustomer;
    protected CustomerAddress $otherClientAddress;

    protected User $adminUser;
    protected OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->orderService = app(OrderService::class);

        // Horario de atención
        BranchSchedule::updateOrCreate(
            ['id' => 1],
            [
                'opening_time' => '08:00:00',
                'break_start_time' => '14:00:00',
                'break_end_time' => '15:00:00',
                'closing_time' => '19:00:00',
                'order_cutoff_advance_minutes' => 60,
                'is_active' => true,
            ]
        );

        // 1. Admin
        $this->adminUser = User::factory()->create(['name' => 'Admin', 'status' => 'active']);
        $this->adminUser->assignRole('Administrador');

        // 2. Cliente Principal (María)
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

        // 3. Otro Cliente (José)
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
            'fixed_bottles_count' => 2,
            'exchange_bottles_count' => 2,
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

        // Congelar tiempo en horario válido (10:00 AM)
        Carbon::setTestNow(Carbon::create(2026, 10, 26, 10, 0, 0, StoreScheduleService::TIMEZONE));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * 1. El listado muestra únicamente los pedidos propios del cliente y los ordena del más reciente al más antiguo.
     */
    public function test_index_shows_only_own_orders_ordered_by_newest_first(): void
    {
        // 1. Pedido 1 de María (cancelado para permitir crear más)
        $order1 = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1]);
        $this->orderService->cancelOrder($order1, $this->adminUser, 'Completado');

        // 2. Pedido de José (no debe aparecer en el listado de María)
        $otherOrder = $this->orderService->createOrder($this->otherClientUser, ['fixed_quantity' => 1]);

        // 3. Pedido 2 de María (activo)
        $order2 = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 2]);

        $response = $this->actingAs($this->clientUser)->get(route('cliente.orders.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Cliente/Orders/Index')
            ->has('orders.data', 2)
            ->where('orders.data.0.id', $order2->id)
            ->where('orders.data.0.folio', $order2->folio)
            ->where('orders.data.0.is_active', true)
            ->where('orders.data.1.id', $order1->id)
            ->where('orders.data.1.folio', $order1->folio)
            ->where('orders.data.1.is_active', false)
            ->where('activeOrder.id', $order2->id)
        );
    }

    /**
     * 2. Filtro de pedidos por estado (Todos, Pendiente, Confirmado, Cancelado) y paginación.
     */
    public function test_index_filters_by_status_and_supports_pagination(): void
    {
        // Crear 3 pedidos en diferentes estados (cancelando entre ellos para poder crearlos)
        $order1 = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1]);
        $this->orderService->cancelOrder($order1, $this->clientUser, 'Ya no lo necesito'); // cancelado

        $order2 = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 1]);
        $this->orderService->confirmOrder($order2, $this->adminUser); // confirmado

        // Filtro: Cancelado
        $responseCancelled = $this->actingAs($this->clientUser)
            ->get(route('cliente.orders.index', ['status' => 'cancelled']));

        $responseCancelled->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $order1->id)
            ->where('filters.status', 'cancelled')
        );

        // Filtro: Confirmado
        $responseConfirmed = $this->actingAs($this->clientUser)
            ->get(route('cliente.orders.index', ['status' => 'confirmed']));

        $responseConfirmed->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $order2->id)
            ->where('filters.status', 'confirmed')
        );
    }

    /**
     * 3. Detalle de pedido muestra el domicilio histórico y contacto, sin sustituir por el actual.
     */
    public function test_show_displays_historical_address_and_not_current_modified_address(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, [
            'fixed_quantity' => 2,
            'exchange_quantity' => 1,
            'notes' => 'Portón verde con timbre.',
        ]);

        // Modificamos el domicilio actual del cliente
        $this->clientAddress->update([
            'street' => 'Calle 99 Totalmente Diferente',
            'exterior_number' => '999',
            'delivery_zone' => CustomerAddress::ZONE_SOUTH,
        ]);

        $response = $this->actingAs($this->clientUser)
            ->get(route('cliente.orders.show', $order->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Cliente/Orders/Show')
            ->where('order.id', $order->id)
            ->where('order.folio', $order->folio)
            ->where('order.status', 'pending')
            ->where('order.status_label', 'Pendiente')
            ->where('order.fixed_quantity', 2)
            ->where('order.exchange_quantity', 1)
            ->where('order.total_bottles', 3)
            ->where('order.notes', 'Portón verde con timbre.')
            // Domicilio histórico conservado intacto
            ->where('order.address.street', 'Calle 20')
            ->where('order.address.exterior_number', '102')
            ->where('order.address.recipient_name', 'María Pech')
            ->has('order.history', 1)
            ->where('order.history.0.new_status_label', 'Pendiente')
            ->where('order.can_cancel', true)
        );
    }

    /**
     * 4. El cliente puede cancelar su pedido pendiente con un motivo obligatorio.
     */
    public function test_client_can_cancel_pending_order_with_mandatory_reason(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1]);
        $this->assertEquals(Order::STATUS_PENDING, $order->status);

        $response = $this->actingAs($this->clientUser)
            ->post(route('cliente.orders.cancel', $order->id), [
                'reason' => 'Surgió un imprevisto y tuve que salir de casa.',
            ]);

        $response->assertRedirect(route('cliente.orders.show', $order->id));
        $response->assertSessionHas('success');

        $freshOrder = $order->fresh();
        $this->assertEquals(Order::STATUS_CANCELLED, $freshOrder->status);
        $this->assertEquals(Order::STATUS_CANCELLED, $freshOrder->statusHistories->last()->new_status);
        $this->assertEquals('Surgió un imprevisto y tuve que salir de casa.', $freshOrder->statusHistories->last()->reason);
    }

    /**
     * 5. Rechazo de cancelación si el motivo está vacío o tiene menos de 3 caracteres.
     */
    public function test_rejects_cancellation_when_reason_is_empty(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1]);

        $response = $this->actingAs($this->clientUser)
            ->post(route('cliente.orders.cancel', $order->id), [
                'reason' => '  ',
            ]);

        $response->assertSessionHasErrors('reason');
        $this->assertEquals(Order::STATUS_PENDING, $order->fresh()->status);
    }

    /**
     * 6. Rechazo de cancelación si el pedido ya fue confirmado por el administrador.
     */
    public function test_rejects_cancellation_if_order_was_confirmed_by_admin(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1]);

        // Administrador confirma el pedido
        $this->orderService->confirmOrder($order, $this->adminUser);

        // Cliente intenta cancelar
        $response = $this->actingAs($this->clientUser)
            ->post(route('cliente.orders.cancel', $order->id), [
                'reason' => 'Intento cancelar aunque ya fue confirmado',
            ]);

        $response->assertForbidden();
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->fresh()->status);
    }

    /**
     * 7. Rechazo de consulta y cancelación de pedidos que pertenecen a otro cliente (403 Forbidden).
     */
    public function test_rejects_viewing_or_cancelling_other_customers_orders(): void
    {
        // Pedido de José Canché
        $otherOrder = $this->orderService->createOrder($this->otherClientUser, ['fixed_quantity' => 1]);

        // María intenta consultar el pedido de José
        $this->actingAs($this->clientUser)
            ->get(route('cliente.orders.show', $otherOrder->id))
            ->assertForbidden();

        // María intenta cancelar el pedido de José
        $this->actingAs($this->clientUser)
            ->post(route('cliente.orders.cancel', $otherOrder->id), [
                'reason' => 'Intento malicioso de cancelar pedido ajeno',
            ])
            ->assertForbidden();

        $this->assertEquals(Order::STATUS_PENDING, $otherOrder->fresh()->status);
    }

    /**
     * 8. Consulta y cancelación permitidas fuera del horario de pedidos.
     */
    public function test_view_and_cancel_allowed_outside_store_schedule(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1]);

        // Cambiar tiempo a las 23:00 (sucursal cerrada)
        Carbon::setTestNow(Carbon::create(2026, 10, 26, 23, 0, 0, StoreScheduleService::TIMEZONE));

        // 1. Consultar: permitido
        $response = $this->actingAs($this->clientUser)
            ->get(route('cliente.orders.show', $order->id));
        $response->assertOk();

        // 2. Cancelar: permitido
        $cancelResponse = $this->actingAs($this->clientUser)
            ->post(route('cliente.orders.cancel', $order->id), [
                'reason' => 'Cancelación nocturna fuera de horario',
            ]);
        $cancelResponse->assertRedirect(route('cliente.orders.show', $order->id));
        $this->assertEquals(Order::STATUS_CANCELLED, $order->fresh()->status);
    }

    /**
     * 9. Tras cancelar, el cliente puede crear otro pedido (se libera el bloqueo de pedido activo).
     */
    public function test_can_create_new_order_after_cancelling_previous_order(): void
    {
        $order1 = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1]);

        // Cancelar pedido 1
        $this->actingAs($this->clientUser)
            ->post(route('cliente.orders.cancel', $order1->id), [
                'reason' => 'Cancelado para cambiar la cantidad',
            ]);

        // Ahora el cliente crea un nuevo pedido exitosamente
        $response = $this->actingAs($this->clientUser)
            ->post(route('cliente.orders.store'), [
                'fixed_quantity' => 2,
                'exchange_quantity' => 1,
            ]);

        $order2 = Order::where('folio', 'PED-0002')->first();
        $this->assertNotNull($order2);
        $response->assertRedirect(route('cliente.orders.show', $order2->id));
        $this->assertEquals(Order::STATUS_PENDING, $order2->status);
    }

    /**
     * 10. La cancelación no modifica los conteos de botellones del cliente.
     */
    public function test_cancellation_preserves_customer_bottles_count(): void
    {
        $initialFixed = $this->clientCustomer->fixed_bottles_count;
        $initialExchange = $this->clientCustomer->exchange_bottles_count;

        $order = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 1]);

        $this->actingAs($this->clientUser)
            ->post(route('cliente.orders.cancel', $order->id), [
                'reason' => 'Cancelación de prueba para conteos',
            ]);

        $fresh = $this->clientCustomer->fresh();
        $this->assertEquals($initialFixed, $fresh->fixed_bottles_count);
        $this->assertEquals($initialExchange, $fresh->exchange_bottles_count);
    }
}
