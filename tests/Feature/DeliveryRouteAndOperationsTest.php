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
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryRouteAndOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $repartidorUser;
    protected User $clientUser;
    protected Customer $customer;
    protected CustomerAddress $address;
    protected DeliveryRouteService $routeService;
    protected OrderService $orderService;
    protected OrderCustodyService $custodyService;

    protected function setUp(): void
    {
        parent::setUp();

        \Carbon\Carbon::setTestNow(\Carbon\Carbon::create(2026, 10, 26, 10, 0, 0, 'America/Merida'));

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->routeService = app(DeliveryRouteService::class);
        $this->orderService = app(OrderService::class);
        $this->custodyService = app(OrderCustodyService::class);

        // Crear presentación oficial de 20L
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

        // Carga de existencias iniciales en camión para pruebas de ruta
        app(\App\Services\TruckInventoryService::class)->getOrCreateTruckInventory($this->repartidorUser)->update([
            'exchange_full_available' => 50,
            'exchange_full_initial' => 50,
        ]);

        $this->clientUser = User::factory()->create(['name' => 'María Pech', 'email' => 'maria@example.com']);
        $this->clientUser->assignRole('Cliente');

        $this->customer = Customer::create([
            'user_id' => $this->clientUser->id,
            'code' => 'CLI-0001',
            'name' => 'María Pech',
            'phone' => '9991234567',
            'fixed_bottles_count' => 3,
            'exchange_bottles_count' => 5,
        ]);

        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'street' => 'Calle 20',
            'exterior_number' => '100',
            'neighborhood' => 'Centro',
            'delivery_zone' => 'north',
            'is_default' => true,
        ]);
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * 1. Creación de hoja de ruta y despacho de pedidos confirmados.
     */
    public function test_admin_can_create_route_and_assign_confirmed_orders(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, [
            'exchange_quantity' => 2,
        ]);
        $this->orderService->confirmOrder($order, $this->adminUser);

        $route = $this->routeService->createRoute(
            $this->adminUser,
            $this->repartidorUser,
            now()->toDateString(),
            [$order->id]
        );

        $this->assertEquals(DeliveryRoute::STATUS_ASSIGNED, $route->status);
        $this->assertCount(1, $route->stops);
        $this->assertEquals($order->id, $route->stops->first()->order_id);

        $order->refresh();
        $this->assertEquals(Order::STATUS_IN_ROUTE, $order->status);
        $this->assertEquals($route->id, $order->delivery_route_id);
    }

    /**
     * 2. Operaciones parciales: pidió 3, acepta 2 -> 2 entregados y 1 cancelado con motivo.
     */
    public function test_partial_delivery_records_delivered_and_cancelled_units_with_reason(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, [
            'exchange_quantity' => 3,
        ]);
        $this->orderService->confirmOrder($order, $this->adminUser);

        $route = $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now(), [$order->id]);
        $stop = $route->stops->first();

        // Repartidor completa la parada: entrega 2, cancela 1
        $this->routeService->recordStopCompleted($stop, [
            'exchange_delivered' => 2,
            'exchange_empty_received' => 2,
            'cancellation_reason' => 'Cliente solo cuenta con dinero para 2',
        ], $this->repartidorUser);

        $order->refresh();
        $item = $order->items->firstWhere('type', OrderItem::TYPE_EXCHANGE);

        $this->assertEquals(3, $item->quantity); // Cantidad original solicitada conservada
        $this->assertEquals(2, $item->delivered_quantity);
        $this->assertEquals(1, $item->cancelled_quantity);
        $this->assertEquals('Cliente solo cuenta con dinero para 2', $item->cancellation_reason);
        $this->assertEquals(Order::STATUS_DELIVERED, $order->status); // Pedido finalizado
    }

    /**
     * 3. Faltantes de vacíos de cambio: entrega únicamente lo respaldado por vacíos recibidos.
     */
    public function test_exchange_missing_empty_bottles_delivers_only_backed_units(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, [
            'exchange_quantity' => 2,
        ]);
        $this->orderService->confirmOrder($order, $this->adminUser);

        $route = $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now(), [$order->id]);
        $stop = $route->stops->first();

        // Cliente pidió 2, pero solo tiene 1 vacío disponible
        $this->routeService->recordStopCompleted($stop, [
            'exchange_empty_received' => 1,
            'exchange_delivered' => 2, // Intentó recibir 2, pero solo tiene 1 vacío
        ], $this->repartidorUser);

        $order->refresh();
        $item = $order->items->firstWhere('type', OrderItem::TYPE_EXCHANGE);

        $this->assertEquals(1, $item->delivered_quantity);
        $this->assertEquals(1, $item->cancelled_quantity);
        $this->assertEquals('Falta de envase vacío', $item->cancellation_reason);
        $this->assertEquals(Order::STATUS_DELIVERED, $order->status);
    }

    /**
     * 4. Fijos recogidos bloquean la cancelación normal para Cliente y Administrador.
     */
    public function test_collected_fixed_bottles_strictly_block_normal_cancellation(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, [
            'fixed_quantity' => 2,
        ]);
        $this->orderService->confirmOrder($order, $this->adminUser);

        // Repartidor recoge los 2 fijos en domicilio
        $this->custodyService->recordFixedBottlesCollected($order, 2, $this->repartidorUser);

        $order->refresh();
        $this->assertEquals(Order::STATUS_IN_PROCESS, $order->status);
        $this->assertTrue($order->hasCollectedFixedCustody());
        $this->assertFalse($order->canBeCancelled());

        // Intento de cancelación por cliente rechazado
        $this->expectException(OrderBusinessRuleException::class);
        $this->orderService->cancelOrder($order, $this->clientUser, 'Ya no quiero el servicio');
    }

    /**
     * 5. Administrador también tiene bloqueada la cancelación si hay fijos bajo custodia.
     */
    public function test_admin_cancellation_is_blocked_when_fixed_bottles_are_collected(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, [
            'fixed_quantity' => 1,
        ]);
        $this->orderService->confirmOrder($order, $this->adminUser);
        $this->custodyService->recordFixedBottlesCollected($order, 1, $this->repartidorUser);

        $this->expectException(OrderBusinessRuleException::class);
        $this->orderService->cancelOrder($order, $this->adminUser, 'Cancelación administrativa');
    }

    /**
     * 6. Devolución de fijos vacíos por incidencia no genera cargo de rellenado.
     */
    public function test_return_empty_fixed_bottles_on_incident_does_not_charge_refill(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, [
            'fixed_quantity' => 2,
        ]);
        $this->orderService->confirmOrder($order, $this->adminUser);
        $this->custodyService->recordFixedBottlesCollected($order, 2, $this->repartidorUser);

        // Devolución sin servicio por fuga o desperfecto de los envases
        $this->custodyService->recordFixedBottlesReturnedEmpty(
            $order,
            2,
            $this->repartidorUser,
            'Envases con fisuras no aptos para purificación'
        );

        $order->refresh();
        $this->assertEquals(Order::STATUS_INCIDENT, $order->status);
        $this->assertCount(0, $order->charges); // Sin cargos monetarios generados
        $this->assertFalse($order->hasCollectedFixedCustody()); // Sin envases pendientes de devolver
    }

    /**
     * 7. Repartidor puede registrar clientes nuevos sin cuenta durante la ruta.
     */
    public function test_repartidor_can_register_new_customer_without_account_during_route(): void
    {
        $newCustomer = $this->routeService->registerDirectRouteCustomer($this->repartidorUser, [
            'name' => 'Don Fernando Canché',
            'nickname' => 'Nando',
            'phone' => '9998887766',
            'fixed_bottles_count' => 2,
            'exchange_bottles_count' => 3,
            'street' => 'Calle 18 #45',
            'neighborhood' => 'Centro',
            'delivery_zone' => 'south',
        ]);

        $this->assertNull($newCustomer->user_id);
        $this->assertStringStartsWith('CLI-', $newCustomer->code);
        $this->assertEquals(2, $newCustomer->fixed_bottles_count);
        $this->assertEquals(3, $newCustomer->exchange_bottles_count);
        $this->assertEquals('Calle 18 #45', $newCustomer->address->street);
    }

    /**
     * 8. Servicio directo en ruta de fijos inicia en Llevando a planta (in_process).
     */
    public function test_direct_route_order_for_fixed_bottles_starts_in_process_with_custody(): void
    {
        $order = $this->routeService->registerDirectRouteOrder($this->repartidorUser, $this->customer, [
            'fixed_quantity' => 2,
        ]);

        $this->assertEquals(Order::STATUS_IN_PROCESS, $order->status);
        $fixedItem = $order->items->firstWhere('type', OrderItem::TYPE_FIXED);
        $this->assertEquals(2, $fixedItem->collected_quantity);
        $this->assertEquals(OrderItem::STATUS_TRANSPORTING_TO_PLANT, $fixedItem->status);

        // Movimiento de custodia registrado
        $movement = BottleCustodyMovement::where('order_id', $order->id)->first();
        $this->assertEquals(BottleCustodyMovement::TYPE_COLLECTION_FIXED_FROM_CUSTOMER, $movement->movement_type);
        $this->assertEquals(2, $movement->quantity);
    }

    /**
     * 9. Pedido activo rechaza nuevo pedido si el cliente ya tiene uno en ruta o en proceso.
     */
    public function test_active_order_blocks_duplicate_orders_across_all_operational_states(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, [
            'exchange_quantity' => 1,
        ]);
        $this->orderService->confirmOrder($order, $this->adminUser);

        // Asignar a ruta
        $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now(), [$order->id]);
        $this->assertEquals(Order::STATUS_IN_ROUTE, $order->fresh()->status);

        // Intento de crear un segundo pedido por el cliente debe ser rechazado
        $this->expectException(OrderBusinessRuleException::class);
        $this->orderService->createOrder($this->clientUser, [
            'exchange_quantity' => 1,
        ]);
    }

    /**
     * 10. Visitas completadas no pueden pasar a fallidas.
     */
    public function test_completed_stop_cannot_be_marked_as_failed(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 1]);
        $this->orderService->confirmOrder($order, $this->adminUser);
        $route = $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now(), [$order->id]);
        $stop = $route->stops->first();

        $this->routeService->recordStopCompleted($stop, ['exchange_delivered' => 1, 'exchange_empty_received' => 1], $this->repartidorUser);

        $this->expectException(OrderBusinessRuleException::class);
        $this->routeService->recordStopFailed($stop, 'Cliente ausente', null, $this->repartidorUser);
    }

    /**
     * 11. Validación de único repartidor activo y asignación automática de ruta.
     */
    public function test_sole_active_driver_resolution_and_validation(): void
    {
        // 1. Con el repartidor único existente, createRoute sin especificar chofer lo asigna automáticamente
        $order = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 1]);
        $this->orderService->confirmOrder($order, $this->adminUser);

        $route = $this->routeService->createRoute($this->adminUser, null, now(), [$order->id]);
        $this->assertEquals($this->repartidorUser->id, $route->driver_user_id);

        // 2. Si se crea un segundo repartidor activo, debe lanzar error de configuración
        $secondDriver = User::factory()->create(['name' => 'Segundo Repartidor', 'email' => 'driver2@purificadora.com']);
        $secondDriver->assignRole('Repartidor');

        $this->expectException(OrderBusinessRuleException::class);
        $this->routeService->resolveSoleActiveDriver();
    }

    /**
     * 12. Error de configuración si no hay repartidor activo.
     */
    public function test_error_when_no_active_driver_exists(): void
    {
        // Desactivar al repartidor
        $this->repartidorUser->update(['status' => 'inactive']);

        $this->expectException(OrderBusinessRuleException::class);
        $this->routeService->resolveSoleActiveDriver();
    }

    /**
     * 13. Alta rápida de cliente sin cuenta: solo apodo, sin user_id, sin domicilio fabricado.
     */
    public function test_quick_customer_registration_with_minimal_fields_and_duplicate_nicknames(): void
    {
        // Registro rápido 1: solo apodo
        $cust1 = $this->routeService->registerDirectRouteCustomer($this->repartidorUser, [
            'nickname' => 'Don Beto',
            'fixed_bottles_count' => 1,
            'exchange_bottles_count' => 2,
        ]);

        $this->assertNull($cust1->user_id);
        $this->assertEquals('Don Beto', $cust1->nickname);
        $this->assertNull($cust1->name);
        $this->assertNull($cust1->phone);
        $this->assertStringStartsWith('CLI-', $cust1->code);
        $this->assertCount(0, $cust1->addresses);

        // Registro rápido 2: mismo apodo permitido, identidad por código e ID
        $cust2 = $this->routeService->registerDirectRouteCustomer($this->repartidorUser, [
            'nickname' => 'Don Beto',
            'fixed_bottles_count' => 0,
            'exchange_bottles_count' => 1,
        ]);

        $this->assertNotEquals($cust1->id, $cust2->id);
        $this->assertNotEquals($cust1->code, $cust2->code);
        $this->assertEquals($cust1->nickname, $cust2->nickname);

        // Servicio directo sin domicilio no se bloquea ni inventa datos
        $order = $this->routeService->registerDirectRouteOrder($this->repartidorUser, $cust1, [
            'exchange_quantity' => 1,
            'exchange_empty_received' => 1,
        ]);

        $this->assertNull($order->customer_address_id);
        $this->assertNull($order->delivery_street);
        $this->assertNull($order->delivery_exterior_number);
        $this->assertNull($order->delivery_neighborhood);
        $this->assertNull($order->delivery_city);
        $this->assertNull($order->delivery_state);
        $this->assertNull($order->address_snapshot['delivery_street']);
        $this->assertNotEquals('S/N', $order->delivery_exterior_number);
        $this->assertEquals(Order::STATUS_DELIVERED, $order->status);

        // Conteos del cliente no se alteran automáticamente al entregar
        $cust1->refresh();
        $this->assertEquals(1, $cust1->fixed_bottles_count);
        $this->assertEquals(2, $cust1->exchange_bottles_count);
    }

    /**
     * 14. Cantidades atendidas no pueden superar los envases declarados por el cliente.
     */
    public function test_direct_route_order_cannot_exceed_customer_declared_bottles(): void
    {
        $this->customer->update([
            'fixed_bottles_count' => 1,
            'exchange_bottles_count' => 2,
        ]);

        $this->expectException(OrderBusinessRuleException::class);
        $this->routeService->registerDirectRouteOrder($this->repartidorUser, $this->customer, [
            'exchange_quantity' => 3, // Supera los 2 que tiene registrados
        ]);
    }

    /**
     * 15. Cierre correcto: si se cancelan todas las unidades y 0 entregadas, pasa a cancelado (no entregado).
     */
    public function test_order_closes_as_cancelled_when_all_units_are_cancelled_and_zero_delivered(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 2]);
        $this->orderService->confirmOrder($order, $this->adminUser);

        $route = $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now(), [$order->id]);
        $stop = $route->stops->first();

        // Cliente no tiene envases ni dinero, se cancela todo
        $this->routeService->recordStopCompleted($stop, [
            'exchange_delivered' => 0,
            'exchange_empty_received' => 0,
            'cancellation_reason' => 'Cliente cancela por no tener dinero',
        ], $this->repartidorUser);

        $order->refresh();
        $this->assertEquals(Order::STATUS_CANCELLED, $order->status);
        $this->assertEquals(0, $order->items->first()->delivered_quantity);
        $this->assertEquals(2, $order->items->first()->cancelled_quantity);
    }

    /**
     * 16. Incidencia por avería en ruta conserva las paradas pendientes intactas.
     */
    public function test_route_breakdown_records_incident_and_preserves_pending_stops(): void
    {
        $order1 = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 1]);
        $this->orderService->confirmOrder($order1, $this->adminUser);

        // Crear segundo cliente para segunda parada
        $client2 = User::factory()->create(['name' => 'Juan Tun', 'email' => 'juan@example.com']);
        $client2->assignRole('Cliente');
        $cust2 = Customer::create([
            'user_id' => $client2->id,
            'code' => 'CLI-0002',
            'name' => 'Juan Tun',
            'fixed_bottles_count' => 0,
            'exchange_bottles_count' => 2,
        ]);
        CustomerAddress::create([
            'customer_id' => $cust2->id,
            'street' => 'Calle 22',
            'neighborhood' => 'Centro',
            'delivery_zone' => 'south',
            'is_default' => true,
        ]);
        $order2 = $this->orderService->createOrder($client2, ['exchange_quantity' => 1]);
        $this->orderService->confirmOrder($order2, $this->adminUser);

        $route = $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now(), [$order1->id, $order2->id]);
        $this->assertCount(2, $route->stops);

        // Completar parada 1
        $stop1 = $route->stops->firstWhere('order_id', $order1->id);
        $this->routeService->recordStopCompleted($stop1, [
            'exchange_delivered' => 1,
            'exchange_empty_received' => 1,
        ], $this->repartidorUser);

        // Avería del vehículo
        $this->routeService->recordRouteBreakdown($route, 'Avería mecánica en transmisión del vehículo', $this->repartidorUser);

        $route->refresh();
        $this->assertEquals(DeliveryRoute::STATUS_INCIDENT, $route->status);
        $this->assertStringContainsString('Avería mecánica', $route->notes);

        // Parada 1 sigue completada
        $stop1->refresh();
        $this->assertEquals(RouteStop::STATUS_COMPLETED, $stop1->status);

        // Parada 2 sigue pendiente para reanudación o reprogramación
        $stop2 = $route->stops->firstWhere('order_id', $order2->id);
        $this->assertEquals(RouteStop::STATUS_PENDING, $stop2->status);
        $order2->refresh();
        $this->assertEquals(Order::STATUS_IN_ROUTE, $order2->status);
    }

    /**
     * 17. Dashboard del Repartidor renderiza props correctos sin ruta activa.
     */
    public function test_repartidor_dashboard_renders_with_props_and_no_route(): void
    {
        $response = $this->actingAs($this->repartidorUser)->get(route('repartidor.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Repartidor/Dashboard')
            ->has('driver')
            ->has('formattedDate')
            ->where('activeRoute', null)
            ->has('availableRoutes')
            ->has('truckInventory')
        );
    }

    /**
     * 18. Dashboard del Repartidor renderiza props con ruta activa y zonas calculadas.
     */
    public function test_repartidor_dashboard_renders_with_active_route_and_inventory(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 2]);
        $this->orderService->confirmOrder($order, $this->adminUser);

        $route = $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now(), [$order->id]);
        $this->routeService->startRoute($route, $this->repartidorUser);

        $response = $this->actingAs($this->repartidorUser)->get(route('repartidor.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Repartidor/Dashboard')
            ->has('driver')
            ->has('formattedDate')
            ->where('activeRoute.id', $route->id)
            ->where('activeRoute.status', DeliveryRoute::STATUS_IN_PROGRESS)
            ->has('activeRoute.zones')
            ->has('truckInventory')
        );
    }
}
