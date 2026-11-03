<?php

namespace Tests\Feature;

use App\Exceptions\OrderBusinessRuleException;
use App\Models\BottleCustodyMovement;
use App\Models\BottlePresentation;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RouteStop;
use App\Models\User;
use App\Services\DeliveryRouteService;
use App\Services\OrderCustodyService;
use App\Services\OrderService;
use App\Services\TruckInventoryService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAndDriverRouteManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $repartidorUser;
    protected User $clientUser;
    protected User $plantaUser;
    protected Customer $customerWithAddress;
    protected Customer $customerNicknameOnly;
    protected DeliveryRouteService $routeService;
    protected OrderService $orderService;
    protected TruckInventoryService $truckService;
    protected OrderCustodyService $custodyService;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 10, 26, 10, 0, 0, 'America/Merida'));

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->routeService = app(DeliveryRouteService::class);
        $this->orderService = app(OrderService::class);
        $this->truckService = app(TruckInventoryService::class);
        $this->custodyService = app(OrderCustodyService::class);

        BottlePresentation::create([
            'name' => 'Garrafón 20 Litros',
            'capacity' => 20.00,
            'unit' => 'Litros',
            'refill_price' => 15.00,
            'store_price' => 10.00,
            'new_bottle_price' => 95.00,
            'is_available_for_delivery' => true,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create(['name' => 'Administrador', 'email' => 'admin@purificadora.com']);
        $this->adminUser->assignRole('Administrador');

        $this->repartidorUser = User::factory()->create(['name' => 'Repartidor', 'email' => 'repartidor@purificadora.com']);
        $this->repartidorUser->assignRole('Repartidor');

        $this->plantaUser = User::factory()->create(['name' => 'Operador Planta', 'email' => 'planta@purificadora.com']);
        $this->plantaUser->assignRole('Operador Planta');

        $this->clientUser = User::factory()->create(['name' => 'María Pech', 'email' => 'maria@example.com']);
        $this->clientUser->assignRole('Cliente');

        // Cliente 1: Con cuenta, teléfono y domicilio registrado
        $this->customerWithAddress = Customer::create([
            'user_id' => $this->clientUser->id,
            'code' => 'CLI-0001',
            'name' => 'María Pech',
            'nickname' => 'Doña Mari',
            'phone' => '9991234567',
            'fixed_bottles_count' => 3,
            'exchange_bottles_count' => 5,
        ]);
        CustomerAddress::create([
            'customer_id' => $this->customerWithAddress->id,
            'street' => 'Calle 20',
            'exterior_number' => '100',
            'neighborhood' => 'Centro',
            'delivery_zone' => 'north',
            'is_default' => true,
        ]);

        // Cliente 2: Sin cuenta, sin teléfono y sin domicilio (alta rápida por apodo)
        $this->customerNicknameOnly = Customer::create([
            'user_id' => null,
            'code' => 'CLI-0002',
            'nickname' => 'Don Beto',
            'name' => null,
            'phone' => null,
            'fixed_bottles_count' => 1,
            'exchange_bottles_count' => 2,
        ]);

        // Carga inicial en camión para pruebas de ruta
        $this->truckService->getOrCreateTruckInventory($this->repartidorUser)->update([
            'exchange_full_available' => 50,
            'exchange_full_initial' => 50,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * 1. Administrador puede ver el listado de hojas de ruta y acceder a la creación.
     */
    public function test_admin_can_view_routes_index_and_create_screen(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.routes.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/DeliveryRoutes/Index'));

        $createResponse = $this->actingAs($this->adminUser)->get(route('admin.routes.create'));
        $createResponse->assertOk();
        $createResponse->assertInertia(fn ($page) => $page
            ->component('Admin/DeliveryRoutes/Create')
            ->where('driver.id', $this->repartidorUser->id)
            ->where('configError', null)
        );
    }

    /**
     * 2. Creación de ruta vincula automáticamente al único repartidor y respeta orden manual.
     */
    public function test_admin_creates_route_with_manual_order_and_automatic_driver(): void
    {
        $order1 = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 2]);
        $this->orderService->confirmOrder($order1, $this->adminUser);

        // Pedido directo de cliente solo con apodo
        $order2 = $this->routeService->registerDirectRouteOrder($this->repartidorUser, $this->customerNicknameOnly, [
            'exchange_quantity' => 1,
            'exchange_empty_received' => 0, // No entrega de inmediato, queda programado
        ]);
        // Para que sea elegible, debe quedar confirmado o en proceso
        $order2->update(['status' => Order::STATUS_CONFIRMED]);

        // Admin crea ruta especificando orden invertido [order2, order1]
        $res = $this->actingAs($this->adminUser)->post(route('admin.routes.store'), [
            'date' => now()->toDateString(),
            'notes' => 'Ruta prueba con orden manual',
            'order_ids' => [$order2->id, $order1->id],
        ]);

        $res->assertSessionHasNoErrors();
        $route = DeliveryRoute::first();
        $this->assertNotNull($route);
        $this->assertEquals($this->repartidorUser->id, $route->driver_user_id);
        $this->assertEquals(DeliveryRoute::STATUS_ASSIGNED, $route->status);

        // Verificar paradas y orden
        $stops = $route->stops()->orderBy('stop_number', 'asc')->get();
        $this->assertCount(2, $stops);
        $this->assertEquals($order2->id, $stops[0]->order_id);
        $this->assertEquals(1, $stops[0]->stop_number);
        $this->assertEquals($order1->id, $stops[1]->order_id);
        $this->assertEquals(2, $stops[1]->stop_number);
    }

    /**
     * 3. Rechazo de pedidos duplicados o ya asignados a otra ruta activa.
     */
    public function test_cannot_assign_order_already_in_active_route(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 1]);
        $this->orderService->confirmOrder($order, $this->adminUser);

        $route1 = $this->routeService->createRoute($this->adminUser, null, now(), [$order->id]);
        $this->assertEquals(DeliveryRoute::STATUS_ASSIGNED, $route1->status);

        // Intento de crear otra ruta con el mismo pedido debe fallar
        $this->expectException(OrderBusinessRuleException::class);
        $this->routeService->createRoute($this->adminUser, null, now(), [$order->id]);
    }

    /**
     * 4. Elegibilidad distingue modalidades: intercambio, recolección de fijos, devolución y mixtas.
     */
    public function test_eligible_orders_distinguishes_operation_types(): void
    {
        // 1. Intercambio puro
        $orderEx = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 1]);
        $this->orderService->confirmOrder($orderEx, $this->adminUser);

        // 2. Mixto
        $client2 = User::factory()->create();
        $client2->assignRole('Cliente');
        $cust2 = Customer::create([
            'user_id' => $client2->id,
            'code' => 'CLI-0003',
            'name' => 'Rosa Chan',
            'fixed_bottles_count' => 2,
            'exchange_bottles_count' => 2,
        ]);
        CustomerAddress::create([
            'customer_id' => $cust2->id,
            'street' => 'Calle 30',
            'neighborhood' => 'Centro',
            'delivery_zone' => 'east',
            'is_default' => true,
        ]);
        $orderMix = $this->orderService->createOrder($client2, [
            'exchange_quantity' => 1,
            'fixed_quantity' => 1,
        ]);
        $this->orderService->confirmOrder($orderMix, $this->adminUser);

        $eligible = $this->routeService->getEligibleOrdersForRoute();
        $itemEx = collect($eligible)->firstWhere('id', $orderEx->id);
        $itemMix = collect($eligible)->firstWhere('id', $orderMix->id);

        $this->assertEquals('intercambio', $itemEx['operation_type']);
        $this->assertEquals('mixto', $itemMix['operation_type']);
    }

    /**
     * 5. Inicio de ruta inicia el recorrido pero conserva las paradas como pendientes.
     */
    public function test_starting_route_does_not_announce_all_stops_as_en_camino(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 2]);
        $this->orderService->confirmOrder($order, $this->adminUser);

        $route = $this->routeService->createRoute($this->adminUser, null, now(), [$order->id]);
        $stop = $route->stops->first();
        $this->assertEquals(RouteStop::STATUS_PENDING, $stop->status);

        // Repartidor inicia la ruta
        $res = $this->actingAs($this->repartidorUser)->post(route('repartidor.route.start', $route->id));
        $res->assertSessionHasNoErrors();

        $route->refresh();
        $this->assertEquals(DeliveryRoute::STATUS_IN_PROGRESS, $route->status);

        // La parada sigue estando en estado PENDING para no anunciar entrega prematura
        $stop->refresh();
        $this->assertEquals(RouteStop::STATUS_PENDING, $stop->status);
    }

    /**
     * 6. Visita fallida registra motivo obligatorio y no cancela el pedido.
     */
    public function test_failed_stop_requires_reason_and_does_not_cancel_order(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 2]);
        $this->orderService->confirmOrder($order, $this->adminUser);

        $route = $this->routeService->createRoute($this->adminUser, null, now(), [$order->id]);
        $this->routeService->startRoute($route, $this->repartidorUser);

        $stop = $route->stops->first();

        // Repartidor marca visita fallida
        $res = $this->actingAs($this->repartidorUser)->post(route('repartidor.stops.fail', $stop->id), [
            'failure_reason' => 'Cliente ausente / Domicilio cerrado',
            'notes' => 'Se esperó 10 minutos',
        ]);
        $res->assertSessionHasNoErrors();

        $stop->refresh();
        $this->assertEquals(RouteStop::STATUS_FAILED, $stop->status);
        $this->assertEquals('Cliente ausente / Domicilio cerrado', $stop->failure_reason);

        // El pedido NO se cancela
        $order->refresh();
        $this->assertNotEquals(Order::STATUS_CANCELLED, $order->status);
        $this->assertEquals(Order::STATUS_IN_ROUTE, $order->status);
    }

    /**
     * 7. Avería mecánica de unidad en ruta y posterior reanudación.
     */
    public function test_route_breakdown_and_resume_lifecycle(): void
    {
        $order1 = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 1]);
        $this->orderService->confirmOrder($order1, $this->adminUser);

        $route = $this->routeService->createRoute($this->adminUser, null, now(), [$order1->id]);
        $this->routeService->startRoute($route, $this->repartidorUser);

        // Reportar avería mecánica
        $resBreakdown = $this->actingAs($this->repartidorUser)->post(route('repartidor.route.breakdown', $route->id), [
            'reason' => 'Neumático delantero ponchado',
        ]);
        $resBreakdown->assertSessionHasNoErrors();

        $route->refresh();
        $this->assertEquals(DeliveryRoute::STATUS_INCIDENT, $route->status);
        $this->assertStringContainsString('Neumático delantero', $route->notes);

        // Reanudar ruta
        $resResume = $this->actingAs($this->repartidorUser)->post(route('repartidor.route.resume', $route->id));
        $resResume->assertSessionHasNoErrors();

        $route->refresh();
        $this->assertEquals(DeliveryRoute::STATUS_IN_PROGRESS, $route->status);
        $this->assertStringContainsString('Ruta reanudada', $route->notes);
    }

    /**
     * 8. Rechazo del cierre de ruta si aún existen paradas pendientes.
     */
    public function test_cannot_complete_route_with_unresolved_pending_stops(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 1]);
        $this->orderService->confirmOrder($order, $this->adminUser);

        $route = $this->routeService->createRoute($this->adminUser, null, now(), [$order->id]);
        $this->routeService->startRoute($route, $this->repartidorUser);

        // Intentar completar mientras la parada sigue pendiente debe fallar
        $this->expectException(OrderBusinessRuleException::class);
        $this->routeService->completeRoute($route, $this->repartidorUser);
    }

    /**
     * 9. Clientes registrados solo con apodo se presentan sin ubicación fabricada y sin teléfono inventado.
     */
    public function test_route_display_for_nickname_only_customers(): void
    {
        $order = $this->routeService->registerDirectRouteOrder($this->repartidorUser, $this->customerNicknameOnly, [
            'exchange_quantity' => 1,
            'exchange_empty_received' => 0,
        ]);
        $order->update(['status' => Order::STATUS_CONFIRMED]);

        $route = $this->routeService->createRoute($this->adminUser, null, now(), [$order->id]);

        $res = $this->actingAs($this->repartidorUser)->get(route('repartidor.route.index'));
        $res->assertOk();
        $res->assertInertia(fn ($page) => $page
            ->component('Repartidor/Route/Index')
            ->has('activeRoute.stops', 1)
            ->where('activeRoute.stops.0.customer_nickname', 'Don Beto')
            ->where('activeRoute.stops.0.customer_name', null)
            ->where('activeRoute.stops.0.has_phone', false)
            ->where('activeRoute.stops.0.customer_phone', null)
            ->where('activeRoute.stops.0.address_summary', 'Sin ubicación registrada')
            ->where('activeRoute.stops.0.has_address', false)
        );
    }

    /**
     * 10. Seguridad y protección de roles en rutas.
     */
    public function test_security_and_role_restrictions_on_routes(): void
    {
        // Cliente no puede acceder a listado de rutas ni a "Mi Ruta"
        $this->actingAs($this->clientUser)->get(route('admin.routes.index'))->assertForbidden();
        $this->actingAs($this->clientUser)->get(route('repartidor.route.index'))->assertForbidden();

        // Operador de Planta no puede crear rutas administrativas
        $this->actingAs($this->plantaUser)->get(route('admin.routes.create'))->assertForbidden();

        // Administrador NO puede aceptar transferencias en "Mi Carga" en nombre del Repartidor
        $movement = $this->truckService->proposePlantToTruckTransfer($this->plantaUser, OrderItem::TYPE_EXCHANGE, 10);
        $adminAccept = $this->actingAs($this->adminUser)->post(route('repartidor.truck-load.accept', $movement->id), [
            'confirmed_quantity' => 10,
        ]);
        $adminAccept->assertForbidden();
    }

    /**
     * 11. Una parada fallida puede reprogramarse tras cerrar la ruta sin duplicar datos ni perder historial.
     */
    public function test_failed_stop_order_is_released_on_route_completion_for_rescheduling_without_data_duplication(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 2]);
        $this->orderService->confirmOrder($order, $this->adminUser);

        // Crear y arrancar Ruta 1
        $route1 = $this->routeService->createRoute($this->adminUser, null, now(), [$order->id]);
        $this->routeService->startRoute($route1, $this->repartidorUser);

        $stop1 = $route1->stops()->first();
        $this->assertNotNull($stop1);

        // Registrar visita fallida
        $this->routeService->recordStopFailed($stop1, 'Cliente no se encontraba en el domicilio', 'Tocado timbre 3 veces', $this->repartidorUser);

        $stop1->refresh();
        $this->assertEquals(RouteStop::STATUS_FAILED, $stop1->status);
        $this->assertEquals('Cliente no se encontraba en el domicilio', $stop1->failure_reason);

        // Cerrar Ruta 1
        $this->routeService->completeRoute($route1, $this->repartidorUser);

        $route1->refresh();
        $this->assertEquals(DeliveryRoute::STATUS_COMPLETED, $route1->status);

        // El pedido debe haber sido liberado a CONFIRMED con delivery_route_id = null
        $order->refresh();
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->status);
        $this->assertNull($order->delivery_route_id);

        // La parada histórica en Ruta 1 debe conservar su estatus FAILED y motivo
        $stop1->refresh();
        $this->assertEquals(RouteStop::STATUS_FAILED, $stop1->status);
        $this->assertEquals('Cliente no se encontraba en el domicilio', $stop1->failure_reason);

        // El pedido debe ser elegible nuevamente
        $eligible = $this->routeService->getEligibleOrdersForRoute();
        $eligibleIds = array_column($eligible, 'id');
        $this->assertContains($order->id, $eligibleIds);

        // Reprogramar en Ruta 2
        $route2 = $this->routeService->createRoute($this->adminUser, null, now()->addDay(), [$order->id]);
        $this->assertCount(1, $route2->stops);
        $stop2 = $route2->stops()->first();
        $this->assertEquals(RouteStop::STATUS_PENDING, $stop2->status);
        $this->assertNotEquals($stop1->id, $stop2->id);

        // Verificar que no se duplicaron ítems en el pedido
        $this->assertCount(1, $order->items);
        $this->assertEquals(2, $order->items()->first()->quantity);
    }
}

