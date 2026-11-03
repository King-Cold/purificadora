<?php

namespace Tests\Feature;

use App\Models\BottleCustodyMovement;
use App\Models\BottlePresentation;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RouteStop;
use App\Models\TruckInventory;
use App\Models\User;
use App\Services\DeliveryRouteService;
use App\Services\OrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientToDriverDirectFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $repartidorUser;
    protected User $clientUser;
    protected Customer $customer;
    protected CustomerAddress $address;
    protected DeliveryRouteService $routeService;
    protected OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();

        \Carbon\Carbon::setTestNow(\Carbon\Carbon::create(2026, 11, 3, 10, 0, 0, 'America/Merida'));

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->routeService = app(DeliveryRouteService::class);
        $this->orderService = app(OrderService::class);

        BottlePresentation::create([
            'name' => 'Garrafón 20 Litros',
            'capacity' => 20.00,
            'unit' => 'Litros',
            'refill_price' => 15.00,
            'is_active' => true,
            'is_available_for_delivery' => true,
        ]);

        $this->adminUser = User::factory()->create(['name' => 'Admin Test', 'email' => 'admin@test.com']);
        $this->adminUser->assignRole('Administrador');

        $this->repartidorUser = User::factory()->create(['name' => 'Chofer Repartidor', 'email' => 'chofer@test.com', 'status' => 'active']);
        $this->repartidorUser->assignRole('Repartidor');

        $this->clientUser = User::factory()->create(['name' => 'Doña Mari', 'email' => 'mari@test.com']);
        $this->clientUser->assignRole('Cliente');

        $this->customer = Customer::create([
            'user_id' => $this->clientUser->id,
            'code' => 'CLI-0001',
            'name' => 'María Pérez',
            'nickname' => 'Doña Mari',
            'phone' => '9991234567',
            'fixed_bottles_count' => 5,
            'exchange_bottles_count' => 5,
        ]);

        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'street' => 'Calle 50',
            'exterior_number' => '123',
            'neighborhood' => 'Centro',
            'city' => 'Mérida',
            'state' => 'Yucatán',
            'postal_code' => '97000',
            'delivery_zone' => 'north',
            'is_active' => true,
        ]);
    }

    /**
     * 1. Pedido de cliente inicia pendiente y aparece directamente en Reparto sin confirmación admin.
     */
    public function test_client_order_starts_pending_and_appears_directly_in_repartidor_reparto(): void
    {
        // El cliente registra su pedido
        $order = $this->orderService->createOrder($this->clientUser, [
            'exchange_quantity' => 2,
        ]);

        $this->assertEquals(Order::STATUS_PENDING, $order->status);

        // El repartidor accede a la pantalla unificada de Reparto
        $response = $this->actingAs($this->repartidorUser)->get(route('repartidor.reparto'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Repartidor/Reparto/Index')
            ->where('tabCounts.pendientes', 1)
            ->where('zoneCounts.all', 1)
            ->where('zoneCounts.north', 1)
            ->has('items', 1)
            ->where('items.0.id', $order->id)
            ->where('items.0.folio', $order->folio)
            ->where('items.0.customer_nickname', 'Doña Mari')
            ->where('items.0.exchange_quantity', 2)
            ->where('items.0.zone_key', 'north')
        );
    }

    /**
     * 2. Repartidor puede incorporar pedidos pendientes directamente a su ruta activa y atenderlos.
     */
    public function test_repartidor_can_incorporate_and_serve_pending_order_without_admin_dependence(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, [
            'exchange_quantity' => 2,
        ]);

        // Chofer inicia jornada directamente
        $this->actingAs($this->repartidorUser)->post(route('repartidor.route.start-today'));

        // Cargar botellones al camión para poder entregar
        $truck = TruckInventory::firstOrCreate(['driver_user_id' => $this->repartidorUser->id]);
        $truck->update(['exchange_full_available' => 10]);

        // Chofer presiona "Atender" en el pedido directamente desde Reparto
        $response = $this->actingAs($this->repartidorUser)->post(route('repartidor.orders.serve', $order->id));

        // Debe redirigir directamente a la pantalla de atención de parada (stops.serve)
        $order->refresh();
        $this->assertEquals(Order::STATUS_IN_ROUTE, $order->status);
        $this->assertNotNull($order->delivery_route_id);

        $stop = RouteStop::where('order_id', $order->id)->first();
        $this->assertNotNull($stop);
        $response->assertRedirect(route('repartidor.stops.serve', $stop->id));
    }

    /**
     * 3. Cliente puede cancelar su pedido mientras no haya comenzado la atención física, incluso si está en ruta.
     */
    public function test_client_can_cancel_order_in_route_before_physical_attention_begins(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, [
            'exchange_quantity' => 2,
        ]);

        // Chofer inicia jornada y agrega el pedido a la ruta
        $route = $this->routeService->startOrCreateSoleDriverRoute($this->repartidorUser);
        $stop = $this->routeService->addOrderToActiveRoute($route, $order, $this->repartidorUser);

        $order->refresh();
        $this->assertEquals(Order::STATUS_IN_ROUTE, $order->status);
        $this->assertDatabaseHas('route_stops', ['id' => $stop->id, 'status' => RouteStop::STATUS_PENDING]);

        // Cliente cancela su pedido (la atención física aún no ha empezado)
        $cancelResponse = $this->actingAs($this->clientUser)->post(route('cliente.orders.cancel', $order->id), [
            'reason' => 'Tuvimos que salir de emergencia',
        ]);

        $cancelResponse->assertRedirect(route('cliente.orders.show', $order->id));

        $order->refresh();
        $this->assertEquals(Order::STATUS_CANCELLED, $order->status);
        $this->assertNull($order->delivery_route_id);

        // La parada pendiente en la ruta del chofer se eliminó limpiamente
        $this->assertDatabaseMissing('route_stops', ['id' => $stop->id]);
    }

    /**
     * 4. Cliente no puede cancelar una vez que inició la atención física (unidades entregadas o cobro).
     */
    public function test_client_cannot_cancel_once_physical_attention_has_started(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, [
            'exchange_quantity' => 2,
        ]);

        $item = $order->items->first();
        $item->update(['delivered_quantity' => 1]); // Atención física iniciada

        // Cliente intenta cancelar
        $response = $this->actingAs($this->clientUser)->post(route('cliente.orders.cancel', $order->id), [
            'reason' => 'Quiero cancelar',
        ]);

        $response->assertForbidden();
        $this->assertNotEquals(Order::STATUS_CANCELLED, $order->fresh()->status);
    }

    /**
     * 5. Directorio de clientes accesible para el repartidor con semáforo y búsqueda.
     */
    public function test_repartidor_customers_directory_renders_with_traffic_light(): void
    {
        $response = $this->actingAs($this->repartidorUser)->get(route('repartidor.customers.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Repartidor/Customers/Index')
            ->has('customers.data', 1)
            ->where('customers.data.0.nickname', 'Doña Mari')
            ->where('customers.data.0.code', 'CLI-0001')
            ->where('customers.data.0.traffic_light.color', 'green')
        );
    }

    /**
     * 6. Registro rápido de cliente desde ruta sin usuario y con apodo repetido permitido.
     */
    public function test_quick_customer_creation_without_user_and_duplicate_nickname(): void
    {
        $response = $this->actingAs($this->repartidorUser)->post(route('repartidor.customers.store-quick'), [
            'nickname' => 'Doña Mari', // Mismo apodo
            'name' => 'María González',
        ]);

        $response->assertRedirect(route('repartidor.customers.create'));

        $this->assertDatabaseHas('customers', [
            'nickname' => 'Doña Mari',
            'name' => 'María González',
            'user_id' => null,
        ]);

        // Ambos clientes con 'Doña Mari' coexisten con códigos diferentes
        $count = Customer::where('nickname', 'Doña Mari')->count();
        $this->assertEquals(2, $count);
    }

    /**
     * 7. Filtros por pestaña y zona en pantalla unificada de Reparto.
     */
    public function test_reparto_tabs_and_zones_filtering(): void
    {
        // Crear cliente en zona Sur
        $custSouth = Customer::create([
            'code' => 'CLI-0002',
            'nickname' => 'Don Pepe',
            'fixed_bottles_count' => 3,
            'exchange_bottles_count' => 3,
        ]);
        CustomerAddress::create([
            'customer_id' => $custSouth->id,
            'street' => 'Calle 60',
            'exterior_number' => '456',
            'neighborhood' => 'Sur',
            'city' => 'Mérida',
            'state' => 'Yucatán',
            'postal_code' => '97000',
            'delivery_zone' => 'south',
            'is_active' => true,
        ]);

        // Pedido fijo para Don Pepe
        $orderFijo = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $custSouth->id,
            'fixed_quantity' => 2,
        ]);

        // Pedido cambio para Doña Mari (Norte)
        $orderCambio = $this->orderService->createOrder($this->clientUser, [
            'exchange_quantity' => 3,
        ]);

        // Consultar pestaña "por_recoger"
        $resRecoger = $this->actingAs($this->repartidorUser)->get(route('repartidor.reparto', [
            'tab' => 'por_recoger',
            'zone' => 'all',
        ]));
        $resRecoger->assertOk();
        $resRecoger->assertInertia(fn (Assert $page) => $page
            ->where('activeTab', 'por_recoger')
            ->where('tabCounts.por_recoger', 1)
            ->where('items.0.id', $orderFijo->id)
        );

        // Consultar pestaña "por_entregar" en zona "north"
        $resEntregar = $this->actingAs($this->repartidorUser)->get(route('repartidor.reparto', [
            'tab' => 'por_entregar',
            'zone' => 'north',
        ]));
        $resEntregar->assertOk();
        $resEntregar->assertInertia(fn (Assert $page) => $page
            ->where('activeTab', 'por_entregar')
            ->where('activeZone', 'north')
            ->where('tabCounts.por_entregar', 1)
            ->where('items.0.id', $orderCambio->id)
        );
    }
}

