<?php

namespace Tests\Feature;

use App\Models\BottlePresentation;
use App\Models\BranchSchedule;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Services\StoreScheduleService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $superAdminUser;
    protected User $clientUser;
    protected User $driverUser;
    protected User $plantUser;

    protected Customer $customerWithoutAccount;
    protected CustomerAddress $customerAddress;

    protected Customer $customerWithAccount;
    protected CustomerAddress $clientAddress;

    protected OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->orderService = app(OrderService::class);

        // Configuración de horario de atención comercial (8:00 a 19:00, corte 18:00)
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

        // Catálogo de garrafón 20L
        BottlePresentation::updateOrCreate(
            ['name' => 'Garrafón 20 Litros'],
            [
                'capacity' => 20,
                'unit' => 'Litros',
                'refill_price' => 15.00,
                'store_price' => 10.00,
                'is_available_for_delivery' => true,
                'is_active' => true,
            ]
        );

        // Usuarios y Roles
        $this->adminUser = User::factory()->create(['name' => 'Admin Test', 'status' => 'active']);
        $this->adminUser->assignRole('Administrador');

        $this->superAdminUser = User::factory()->create(['name' => 'Super Admin Test', 'status' => 'active']);
        $this->superAdminUser->assignRole('Super Admin');

        $this->clientUser = User::factory()->create(['name' => 'Cliente Test', 'status' => 'active']);
        $this->clientUser->assignRole('Cliente');

        $this->driverUser = User::factory()->create(['name' => 'Repartidor Test', 'status' => 'active']);
        $this->driverUser->assignRole('Repartidor');

        $this->plantUser = User::factory()->create(['name' => 'Operador Planta Test', 'status' => 'active']);
        $this->plantUser->assignRole('Operador Planta');

        // Cliente SIN cuenta vinculada (para pedidos administrativos)
        $this->customerWithoutAccount = Customer::create([
            'user_id' => null,
            'code' => 'CLI-SINCTA-01',
            'name' => 'Don Manuel Chan',
            'nickname' => 'Don Manolo',
            'phone' => '9991112233',
            'fixed_bottles_count' => 4,
            'exchange_bottles_count' => 3,
        ]);

        $this->customerAddress = CustomerAddress::create([
            'customer_id' => $this->customerWithoutAccount->id,
            'recipient_name' => 'Don Manuel Chan',
            'recipient_phone' => '9991112233',
            'street' => 'Calle 15',
            'exterior_number' => '40',
            'neighborhood' => 'San Juan',
            'city' => 'Hocabá',
            'state' => 'Yucatán',
            'postal_code' => '97450',
            'delivery_zone' => CustomerAddress::ZONE_NORTH,
            'is_default' => true,
        ]);

        // Cliente CON cuenta vinculada
        $this->customerWithAccount = Customer::create([
            'user_id' => $this->clientUser->id,
            'code' => 'CLI-CONCTA-01',
            'name' => 'María Pech',
            'nickname' => 'Mary',
            'phone' => '9994445566',
            'fixed_bottles_count' => 2,
            'exchange_bottles_count' => 2,
        ]);

        $this->clientAddress = CustomerAddress::create([
            'customer_id' => $this->customerWithAccount->id,
            'recipient_name' => 'María Pech',
            'recipient_phone' => '9994445566',
            'street' => 'Calle 20',
            'exterior_number' => '102',
            'neighborhood' => 'Centro',
            'city' => 'Hocabá',
            'state' => 'Yucatán',
            'postal_code' => '97450',
            'delivery_zone' => CustomerAddress::ZONE_SOUTH,
            'is_default' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Helper para simular horario dentro de la ventana de recepción (10:00 hrs Merida).
     */
    protected function travelToOpenSchedule(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 26, 10, 0, 0, StoreScheduleService::TIMEZONE));
    }

    /**
     * Helper para simular horario fuera de recepción (22:00 hrs Merida).
     */
    protected function travelToClosedSchedule(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 26, 22, 0, 0, StoreScheduleService::TIMEZONE));
    }

    // =========================================================================
    // 1. LISTADO GLOBAL, BÚSQUEDA Y FILTROS
    // =========================================================================

    public function test_admin_can_view_orders_index_with_orders_from_both_account_types(): void
    {
        $this->travelToOpenSchedule();

        // Pedido de cliente sin cuenta registrado por admin
        $orderSinCuenta = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $this->customerWithoutAccount->id,
            'fixed_quantity' => 2,
            'exchange_quantity' => 1,
            'notes' => 'Tocar fuerte',
        ]);

        // Pedido de cliente con cuenta creado por el cliente
        $orderConCuenta = $this->orderService->createOrder($this->clientUser, [
            'fixed_quantity' => 1,
            'exchange_quantity' => 1,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.orders.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Orders/Index')
                ->has('orders.data', 2)
                ->where('orders.data.0.folio', $orderConCuenta->folio)
                ->where('orders.data.0.customer.has_account', true)
                ->where('orders.data.1.folio', $orderSinCuenta->folio)
                ->where('orders.data.1.customer.has_account', false)
            );
    }

    public function test_admin_can_filter_orders_by_search_term_folio_or_customer(): void
    {
        $this->travelToOpenSchedule();

        $order1 = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $this->customerWithoutAccount->id,
            'fixed_quantity' => 1,
            'exchange_quantity' => 0,
        ]);

        // Cancelamos para permitir crear otro
        $this->orderService->cancelOrder($order1, $this->adminUser, 'Prueba búsqueda');

        $order2 = $this->orderService->createOrder($this->clientUser, [
            'fixed_quantity' => 1,
            'exchange_quantity' => 0,
        ]);

        // Buscar por apodo "Don Manolo"
        $response = $this->actingAs($this->adminUser)->get(route('admin.orders.index', ['search' => 'Don Manolo']));
        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Orders/Index')
                ->has('orders.data', 1)
                ->where('orders.data.0.folio', $order1->folio)
            );

        // Buscar por código de cliente
        $response = $this->actingAs($this->adminUser)->get(route('admin.orders.index', ['search' => 'CLI-CONCTA-01']));
        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Orders/Index')
                ->has('orders.data', 1)
                ->where('orders.data.0.folio', $order2->folio)
            );
    }

    public function test_admin_can_filter_orders_by_status(): void
    {
        $this->travelToOpenSchedule();

        $order1 = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $this->customerWithoutAccount->id,
            'fixed_quantity' => 1,
            'exchange_quantity' => 0,
        ]);

        $order2 = $this->orderService->createOrder($this->clientUser, [
            'fixed_quantity' => 1,
            'exchange_quantity' => 0,
        ]);

        // Confirmar order1
        $this->orderService->confirmOrder($order1, $this->adminUser);

        // Filtrar pendientes (solo order2)
        $response = $this->actingAs($this->adminUser)->get(route('admin.orders.index', ['status' => Order::STATUS_PENDING]));
        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Orders/Index')
                ->has('orders.data', 1)
                ->where('orders.data.0.folio', $order2->folio)
            );

        // Filtrar confirmados (solo order1)
        $response = $this->actingAs($this->adminUser)->get(route('admin.orders.index', ['status' => Order::STATUS_CONFIRMED]));
        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Orders/Index')
                ->has('orders.data', 1)
                ->where('orders.data.0.folio', $order1->folio)
            );
    }

    // =========================================================================
    // 2. BUSCADOR EN TIEMPO REAL DE CLIENTES SIN CUENTA
    // =========================================================================

    public function test_search_customers_returns_only_customers_without_account(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson(route('admin.orders.search-customers', ['search' => 'Manuel']));

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'id' => $this->customerWithoutAccount->id,
                'name' => 'Don Manuel Chan',
                'nickname' => 'Don Manolo',
                'code' => 'CLI-SINCTA-01',
                'fixed_bottles_count' => 4,
                'exchange_bottles_count' => 3,
                'has_address' => true,
            ]);

        // Intentar buscar a María (cliente con cuenta) no debe devolverla
        $responseWithAccount = $this->actingAs($this->adminUser)->getJson(route('admin.orders.search-customers', ['search' => 'María']));
        $responseWithAccount->assertOk()
            ->assertJsonCount(0);
    }

    // =========================================================================
    // 3. REGISTRO ADMINISTRATIVO
    // =========================================================================

    public function test_admin_can_successfully_register_order_for_customer_without_account(): void
    {
        $this->travelToOpenSchedule();

        $response = $this->actingAs($this->adminUser)->post(route('admin.orders.store'), [
            'customer_id' => $this->customerWithoutAccount->id,
            'fixed_quantity' => 2,
            'exchange_quantity' => 1,
            'notes' => 'Entregar por la tarde',
        ]);

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);

        $response->assertRedirect(route('admin.orders.show', $order->id));
        $response->assertSessionHas('success');

        $this->assertEquals($this->customerWithoutAccount->id, $order->customer_id);
        $this->assertEquals($this->adminUser->id, $order->created_by_user_id);
        $this->assertEquals($this->adminUser->name, $order->creator->name);
        $this->assertEquals(2, $order->fixed_quantity);
        $this->assertEquals(1, $order->exchange_quantity);
        $this->assertEquals(3, $order->total_bottles);
        $this->assertEquals(Order::STATUS_PENDING, $order->status);
        $this->assertEquals('Calle 15', $order->delivery_street);
        $this->assertEquals('40', $order->delivery_exterior_number);

        // Los conteos del cliente no se alteran
        $this->customerWithoutAccount->refresh();
        $this->assertEquals(4, $this->customerWithoutAccount->fixed_bottles_count);
        $this->assertEquals(3, $this->customerWithoutAccount->exchange_bottles_count);
    }

    public function test_admin_order_creation_strictly_rejects_customer_with_account(): void
    {
        $this->travelToOpenSchedule();

        $response = $this->actingAs($this->adminUser)->post(route('admin.orders.store'), [
            'customer_id' => $this->customerWithAccount->id,
            'fixed_quantity' => 1,
            'exchange_quantity' => 0,
        ]);

        $response->assertSessionHasErrors(['customer_id']);
        $this->assertEquals(0, Order::count());
    }

    public function test_admin_order_creation_rejects_when_outside_store_schedule(): void
    {
        $this->travelToClosedSchedule();

        $response = $this->actingAs($this->adminUser)->post(route('admin.orders.store'), [
            'customer_id' => $this->customerWithoutAccount->id,
            'fixed_quantity' => 1,
            'exchange_quantity' => 1,
        ]);

        $response->assertSessionHasErrors(['schedule']);
        $this->assertEquals(0, Order::count());
    }

    public function test_admin_order_creation_rejects_quantities_exceeding_registered_counts(): void
    {
        $this->travelToOpenSchedule();

        // fixed_bottles_count es 4, solicitamos 5
        $response = $this->actingAs($this->adminUser)->post(route('admin.orders.store'), [
            'customer_id' => $this->customerWithoutAccount->id,
            'fixed_quantity' => 5,
            'exchange_quantity' => 1,
        ]);

        $response->assertSessionHasErrors(['fixed_quantity']);
        $this->assertEquals(0, Order::count());
    }

    public function test_admin_order_creation_rejects_total_zero_bottles(): void
    {
        $this->travelToOpenSchedule();

        $response = $this->actingAs($this->adminUser)->post(route('admin.orders.store'), [
            'customer_id' => $this->customerWithoutAccount->id,
            'fixed_quantity' => 0,
            'exchange_quantity' => 0,
        ]);

        $response->assertSessionHasErrors(['quantity']);
        $this->assertEquals(0, Order::count());
    }

    public function test_admin_order_creation_rejects_when_customer_already_has_active_order(): void
    {
        $this->travelToOpenSchedule();

        // Creamos el primer pedido
        $firstOrder = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $this->customerWithoutAccount->id,
            'fixed_quantity' => 1,
            'exchange_quantity' => 0,
        ]);

        // Intentar registrar un segundo pedido mientras el primero sigue activo
        $response = $this->actingAs($this->adminUser)->post(route('admin.orders.store'), [
            'customer_id' => $this->customerWithoutAccount->id,
            'fixed_quantity' => 1,
            'exchange_quantity' => 0,
        ]);

        $response->assertSessionHasErrors(['active_order']);
        $this->assertEquals(1, Order::count());
    }

    public function test_admin_order_creation_rejects_when_customer_has_no_active_address(): void
    {
        $this->travelToOpenSchedule();

        // Eliminamos el domicilio
        $this->customerAddress->delete();

        $response = $this->actingAs($this->adminUser)->post(route('admin.orders.store'), [
            'customer_id' => $this->customerWithoutAccount->id,
            'fixed_quantity' => 1,
            'exchange_quantity' => 0,
        ]);

        $response->assertSessionHasErrors(['customer_id']);
        $this->assertEquals(0, Order::count());
    }

    // =========================================================================
    // 4. DETALLE ADMINISTRATIVO
    // =========================================================================

    public function test_admin_can_view_order_show_with_historical_snapshot(): void
    {
        $this->travelToOpenSchedule();

        $order = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $this->customerWithoutAccount->id,
            'fixed_quantity' => 2,
            'exchange_quantity' => 1,
            'notes' => 'Nota especial',
        ]);

        // Modificamos el cliente actual para verificar que los datos históricos del pedido se conservan intactos
        $this->customerWithoutAccount->update(['name' => 'Manuel Chan Modificado']);
        $this->customerAddress->update(['street' => 'Calle Nueva Modificada 99']);

        $response = $this->actingAs($this->adminUser)->get(route('admin.orders.show', $order->id));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Orders/Show')
                ->where('order.folio', $order->folio)
                ->where('order.status', Order::STATUS_PENDING)
                ->where('order.fixed_quantity', 2)
                ->where('order.exchange_quantity', 1)
                ->where('order.total_bottles', 3)
                ->where('order.notes', 'Nota especial')
                ->where('order.address.street', 'Calle 15') // Preservado histórico
                ->where('order.creator.name', $this->adminUser->name)
                ->where('order.customer.has_account', false)
            );
    }

    // =========================================================================
    // 5. CONFIRMACIÓN ADMINISTRATIVA
    // =========================================================================

    public function test_admin_can_confirm_pending_order(): void
    {
        $this->travelToOpenSchedule();

        $order = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $this->customerWithoutAccount->id,
            'fixed_quantity' => 1,
            'exchange_quantity' => 0,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.orders.confirm', $order->id), [
            'comment' => 'Pedido confirmado por teléfono',
        ]);

        $response->assertRedirect(route('admin.orders.show', $order->id));
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->status);
        $this->assertTrue($order->isConfirmed());

        // Historial registrado con responsable admin
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'user_id' => $this->adminUser->id,
            'previous_status' => Order::STATUS_PENDING,
            'new_status' => Order::STATUS_CONFIRMED,
            'reason' => 'Pedido confirmado por teléfono',
        ]);

        // Los conteos del cliente no se alteran
        $this->customerWithoutAccount->refresh();
        $this->assertEquals(4, $this->customerWithoutAccount->fixed_bottles_count);
        $this->assertEquals(3, $this->customerWithoutAccount->exchange_bottles_count);
    }

    public function test_admin_cannot_confirm_already_confirmed_or_cancelled_order(): void
    {
        $this->travelToOpenSchedule();

        $order = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $this->customerWithoutAccount->id,
            'fixed_quantity' => 1,
            'exchange_quantity' => 0,
        ]);

        $this->orderService->confirmOrder($order, $this->adminUser);

        // Intentar confirmar de nuevo
        $response = $this->actingAs($this->adminUser)->post(route('admin.orders.confirm', $order->id));
        $response->assertForbidden(); // La policy prohíbe confirm si no canBeConfirmed()
    }

    // =========================================================================
    // 6. CANCELACIÓN ADMINISTRATIVA
    // =========================================================================

    public function test_admin_can_cancel_pending_order_with_mandatory_reason(): void
    {
        $this->travelToOpenSchedule();

        $order = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $this->customerWithoutAccount->id,
            'fixed_quantity' => 1,
            'exchange_quantity' => 0,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.orders.cancel', $order->id), [
            'reason' => 'El cliente llamó para cancelar',
        ]);

        $response->assertRedirect(route('admin.orders.show', $order->id));
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals(Order::STATUS_CANCELLED, $order->status);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'user_id' => $this->adminUser->id,
            'previous_status' => Order::STATUS_PENDING,
            'new_status' => Order::STATUS_CANCELLED,
            'reason' => 'El cliente llamó para cancelar',
        ]);
    }

    public function test_admin_can_cancel_confirmed_order_with_mandatory_reason(): void
    {
        $this->travelToOpenSchedule();

        $order = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $this->customerWithoutAccount->id,
            'fixed_quantity' => 1,
            'exchange_quantity' => 0,
        ]);

        $this->orderService->confirmOrder($order, $this->adminUser);

        $response = $this->actingAs($this->adminUser)->post(route('admin.orders.cancel', $order->id), [
            'reason' => 'Imprevisto en ruta, se reprograma con el cliente',
        ]);

        $response->assertRedirect(route('admin.orders.show', $order->id));
        $order->refresh();
        $this->assertEquals(Order::STATUS_CANCELLED, $order->status);
    }

    public function test_cancellation_requires_reason(): void
    {
        $this->travelToOpenSchedule();

        $order = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $this->customerWithoutAccount->id,
            'fixed_quantity' => 1,
            'exchange_quantity' => 0,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.orders.cancel', $order->id), [
            'reason' => '',
        ]);

        $response->assertSessionHasErrors(['reason']);
        $order->refresh();
        $this->assertEquals(Order::STATUS_PENDING, $order->status);
    }

    public function test_cannot_cancel_already_cancelled_order(): void
    {
        $this->travelToOpenSchedule();

        $order = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $this->customerWithoutAccount->id,
            'fixed_quantity' => 1,
            'exchange_quantity' => 0,
        ]);

        $this->orderService->cancelOrder($order, $this->adminUser, 'Primera cancelación');

        $response = $this->actingAs($this->adminUser)->post(route('admin.orders.cancel', $order->id), [
            'reason' => 'Segunda cancelación',
        ]);

        $response->assertForbidden();
    }

    // =========================================================================
    // 7. CONSULTAR, CONFIRMAR Y CANCELAR FUERA DE HORARIO
    // =========================================================================

    public function test_admin_can_view_confirm_and_cancel_outside_store_schedule(): void
    {
        $this->travelToOpenSchedule();

        $order = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $this->customerWithoutAccount->id,
            'fixed_quantity' => 1,
            'exchange_quantity' => 0,
        ]);

        // Cambiamos a fuera de horario (22:00 hrs)
        $this->travelToClosedSchedule();

        // Consultar funciona fuera de horario
        $responseShow = $this->actingAs($this->adminUser)->get(route('admin.orders.show', $order->id));
        $responseShow->assertOk();

        // Confirmar funciona fuera de horario
        $responseConfirm = $this->actingAs($this->adminUser)->post(route('admin.orders.confirm', $order->id), [
            'comment' => 'Confirmación nocturna',
        ]);
        $responseConfirm->assertRedirect(route('admin.orders.show', $order->id));
        $order->refresh();
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->status);

        // Cancelar funciona fuera de horario
        $responseCancel = $this->actingAs($this->adminUser)->post(route('admin.orders.cancel', $order->id), [
            'reason' => 'Cancelación nocturna',
        ]);
        $responseCancel->assertRedirect(route('admin.orders.show', $order->id));
        $order->refresh();
        $this->assertEquals(Order::STATUS_CANCELLED, $order->status);
    }

    // =========================================================================
    // 8. PROTECCIÓN DE RUTAS Y ROLES NO AUTORIZADOS
    // =========================================================================

    public function test_client_cannot_access_admin_orders(): void
    {
        $this->actingAs($this->clientUser)->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs($this->clientUser)->get(route('admin.orders.create'))->assertForbidden();
    }

    public function test_driver_cannot_access_admin_orders(): void
    {
        $this->actingAs($this->driverUser)->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs($this->driverUser)->get(route('admin.orders.create'))->assertForbidden();
    }

    public function test_plant_operator_cannot_access_admin_orders(): void
    {
        $this->actingAs($this->plantUser)->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs($this->plantUser)->get(route('admin.orders.create'))->assertForbidden();
    }

    public function test_super_admin_has_full_access_to_admin_orders(): void
    {
        $this->travelToOpenSchedule();

        $responseIndex = $this->actingAs($this->superAdminUser)->get(route('admin.orders.index'));
        $responseIndex->assertOk();

        $responseCreate = $this->actingAs($this->superAdminUser)->get(route('admin.orders.create'));
        $responseCreate->assertOk();
    }

    // =========================================================================
    // 9. REFLEJO DE CAMBIOS ADMINISTRATIVOS EN "MIS PEDIDOS" DEL CLIENTE
    // =========================================================================

    public function test_admin_confirmation_and_cancellation_reflected_in_client_orders(): void
    {
        $this->travelToOpenSchedule();

        // Cliente crea su propio pedido
        $order = $this->orderService->createOrder($this->clientUser, [
            'fixed_quantity' => 1,
            'exchange_quantity' => 1,
        ]);

        // Cliente ve su pedido como Pendiente
        $clientResponse1 = $this->actingAs($this->clientUser)->get(route('cliente.orders.show', $order->id));
        $clientResponse1->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cliente/Orders/Show')
                ->where('order.status', Order::STATUS_PENDING)
            );

        // Admin confirma el pedido
        $this->actingAs($this->adminUser)->post(route('admin.orders.confirm', $order->id));

        // Cliente ahora ve su pedido como Confirmado
        $clientResponse2 = $this->actingAs($this->clientUser)->get(route('cliente.orders.show', $order->id));
        $clientResponse2->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cliente/Orders/Show')
                ->where('order.status', Order::STATUS_CONFIRMED)
            );

        // Admin cancela el pedido
        $this->actingAs($this->adminUser)->post(route('admin.orders.cancel', $order->id), [
            'reason' => 'Cancelación administrativa justificada',
        ]);

        // Cliente ve su pedido Cancelado con el motivo
        $clientResponse3 = $this->actingAs($this->clientUser)->get(route('cliente.orders.show', $order->id));
        $clientResponse3->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cliente/Orders/Show')
                ->where('order.status', Order::STATUS_CANCELLED)
                ->where('order.cancellation_reason', 'Cancelación administrativa justificada')
            );
    }
}
