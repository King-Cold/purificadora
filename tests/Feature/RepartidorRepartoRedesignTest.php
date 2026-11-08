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

class RepartidorRepartoRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $repartidorUser;
    protected Customer $customer1;
    protected Customer $customer2;
    protected DeliveryRouteService $routeService;
    protected OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();

        \Carbon\Carbon::setTestNow(\Carbon\Carbon::create(2026, 11, 7, 10, 0, 0, 'America/Merida'));

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

        // Cliente 1
        $this->customer1 = Customer::create([
            'code' => 'CLI-0001',
            'nickname' => 'Doña Mari',
            'fixed_bottles_count' => 2,
            'exchange_bottles_count' => 2,
            'phone' => '9991112233',
        ]);
        CustomerAddress::create([
            'customer_id' => $this->customer1->id,
            'street' => 'Calle 50',
            'exterior_number' => '101',
            'neighborhood' => 'Centro',
            'city' => 'Mérida',
            'state' => 'Yucatán',
            'postal_code' => '97000',
            'delivery_zone' => 'north',
            'is_active' => true,
        ]);

        // Cliente 2
        $this->customer2 = Customer::create([
            'code' => 'CLI-0002',
            'nickname' => 'Don Beto',
            'fixed_bottles_count' => 3,
            'exchange_bottles_count' => 3,
            'phone' => '9994445566',
        ]);
        CustomerAddress::create([
            'customer_id' => $this->customer2->id,
            'street' => 'Calle 60',
            'exterior_number' => '202',
            'neighborhood' => 'Sur',
            'city' => 'Mérida',
            'state' => 'Yucatán',
            'postal_code' => '97000',
            'delivery_zone' => 'south',
            'is_active' => true,
        ]);
    }

    /**
     * 1. El repartidor decide a quién visitar primero: puede atender el pedido #2
     * sin haber atendido ni iniciado el pedido #1 y sin secuencia obligatoria.
     */
    public function test_driver_can_serve_order_two_before_order_one_without_sequence_restriction(): void
    {
        // Proveer stock en el camión
        TruckInventory::updateOrCreate(
            ['driver_user_id' => $this->repartidorUser->id],
            [
                'exchange_full_available' => 10,
                'exchange_empty_collected' => 0,
                'exchange_full_initial' => 10,
                'exchange_full_delivered' => 0,
            ]
        );

        // Crear pedido 1 (para Doña Mari) y pedido 2 (para Don Beto)
        $order1 = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $this->customer1->id,
            'exchange_quantity' => 2,
        ]);
        $order2 = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $this->customer2->id,
            'exchange_quantity' => 2,
        ]);

        // Repartidor consulta Reparto: ambos aparecen en pendientes
        $res = $this->actingAs($this->repartidorUser)->get(route('repartidor.reparto'));
        $res->assertOk();
        $res->assertInertia(fn (Assert $page) => $page
            ->component('Repartidor/Reparto/Index')
            ->where('tabCounts.pendientes', 2)
            ->where('tabCounts.atendidos', 0)
        );

        // El repartidor elige atender DIRECTAMENTE el pedido 2 sin tocar el pedido 1
        $serveRes = $this->actingAs($this->repartidorUser)->post(route('repartidor.orders.serve', [
            'order' => $order2->id,
            'tab' => 'pendientes',
            'zone' => 'all',
        ]));

        $stop2 = RouteStop::where('order_id', $order2->id)->first();
        $this->assertNotNull($stop2);

        $serveRes->assertRedirect(route('repartidor.stops.serve', [
            'stop' => $stop2->id,
            'return_tab' => 'pendientes',
            'return_zone' => 'all',
        ]));

        // El chofer atiende la parada del pedido 2 con pago en efectivo
        $processRes = $this->actingAs($this->repartidorUser)->post(route('repartidor.stops.process-serve', $stop2->id), [
            'exchange_delivered' => 2,
            'exchange_empty_received' => 2,
            'units_paid_cash' => 2,
            'cash_tendered' => 30.00,
            'return_tab' => 'pendientes',
            'return_zone' => 'all',
        ]);

        // Redirige preservando tab y zona
        $processRes->assertRedirect(route('repartidor.reparto', ['tab' => 'pendientes', 'zone' => 'all']));

        // Estado posterior en Reparto:
        // El pedido 2 pasó a atendidos.
        // El pedido 1 sigue en pendientes, elegible e intacto.
        $resAfter = $this->actingAs($this->repartidorUser)->get(route('repartidor.reparto'));
        $resAfter->assertOk();
        $resAfter->assertInertia(fn (Assert $page) => $page
            ->where('tabCounts.pendientes', 1)
            ->where('tabCounts.atendidos', 1)
            ->where('items.0.id', $order1->id)
            ->where('items.0.customer_nickname', 'Doña Mari')
        );

        $this->assertEquals(Order::STATUS_DELIVERED, $order2->fresh()->status);
        $this->assertEquals(Order::STATUS_IN_ROUTE, $order1->fresh()->status);
        $this->assertNotNull(RouteStop::where('order_id', $order1->id)->where('status', RouteStop::STATUS_PENDING)->first());
    }

    /**
     * 2. Prueba los tipos de acción y situaciones según existencias y custodia:
     * - Fijos por recoger -> "Recoger X fijos"
     * - Fijos en Planta -> Bloqueado ("En preparación en Planta")
     * - Cambios con stock -> "Entregar X de cambio"
     * - Cambios sin stock -> Bloqueado ("Sin botellones de cambio en camión")
     * - Fijos con propuesta -> "Revisar carga"
     */
    public function test_action_labels_and_types_match_order_situations(): void
    {
        // Camión inicia sin existencias de cambio
        TruckInventory::updateOrCreate(
            ['driver_user_id' => $this->repartidorUser->id],
            [
                'exchange_full_count' => 0,
                'exchange_empty_count' => 0,
                'fixed_full_count' => 0,
                'fixed_empty_count' => 0,
                'damaged_full_count' => 0,
                'damaged_empty_count' => 0,
            ]
        );

        // Pedido A: Cambio sin existencias en camión
        $orderA = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $this->customer1->id,
            'exchange_quantity' => 2,
        ]);

        // Pedido B: Fijos pendientes de recoger
        $orderB = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $this->customer2->id,
            'fixed_quantity' => 3,
        ]);

        $res = $this->actingAs($this->repartidorUser)->get(route('repartidor.reparto'));
        $res->assertOk();

        // Encontrar tarjetas de Order A y Order B
        $items = $res->inertiaProps('items');
        $itemA = collect($items)->firstWhere('id', $orderA->id);
        $itemB = collect($items)->firstWhere('id', $orderB->id);

        // Order A: Bloqueado por falta de existencias de cambio
        $this->assertEquals('blocked', $itemA['action_type']);
        $this->assertFalse($itemA['can_execute']);
        $this->assertEquals('Sin botellones de cambio en camión', $itemA['blocked_reason']);

        // Order B: Fijos por recoger
        $this->assertEquals('collect_fixed', $itemB['action_type']);
        $this->assertTrue($itemB['can_execute']);
        $this->assertEquals('Recoger 3 fijos', $itemB['action_label']);
    }

    /**
     * 3. Fijos propuestos por Planta pero aún no aceptados por el Repartidor:
     * La acción muestra "Revisar carga" con enlace a Mi Carga.
     */
    public function test_order_with_proposed_fixed_custody_shows_review_load_action(): void
    {
        // Pedido fijo donde los envases ya fueron recogidos y preparados en planta
        $order = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $this->customer1->id,
            'fixed_quantity' => 2,
        ]);
        $fixedItem = $order->items->firstWhere('type', OrderItem::TYPE_FIXED);
        $fixedItem->update([
            'collected_quantity' => 2,
            'prepared_quantity' => 2,
        ]);

        // Planta propone la carga al camión (status = proposed)
        BottleCustodyMovement::create([
            'order_id' => $order->id,
            'customer_id' => $this->customer1->id,
            'user_id' => $this->adminUser->id,
            'movement_type' => BottleCustodyMovement::TYPE_TRUCK_LOAD_FIXED,
            'bottle_type' => OrderItem::TYPE_FIXED,
            'origin' => 'plant',
            'destination' => 'truck',
            'quantity' => 2,
            'status' => BottleCustodyMovement::STATUS_PROPOSED,
            'notes' => 'Propuesta de carga de fijos',
        ]);

        $res = $this->actingAs($this->repartidorUser)->get(route('repartidor.reparto'));
        $res->assertOk();

        $items = $res->inertiaProps('items');
        $item = collect($items)->firstWhere('id', $order->id);

        $this->assertEquals('review_load', $item['action_type']);
        $this->assertEquals('Revisar carga', $item['action_label']);
        $this->assertTrue($item['can_execute']);
    }

    /**
     * 4. Fijos aceptados en el camión:
     * La acción muestra "Entregar X fijos".
     */
    public function test_order_with_accepted_fixed_in_truck_shows_deliver_fixed_action(): void
    {
        // Pedido fijo donde los envases ya están en el camión aceptados
        $order = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $this->customer1->id,
            'fixed_quantity' => 2,
        ]);
        $fixedItem = $order->items->firstWhere('type', OrderItem::TYPE_FIXED);
        $fixedItem->update([
            'collected_quantity' => 2,
            'prepared_quantity' => 2,
        ]);

        // Movimiento aceptado de carga al camión
        BottleCustodyMovement::create([
            'order_id' => $order->id,
            'customer_id' => $this->customer1->id,
            'user_id' => $this->adminUser->id,
            'movement_type' => BottleCustodyMovement::TYPE_TRUCK_LOAD_FIXED,
            'bottle_type' => OrderItem::TYPE_FIXED,
            'origin' => 'plant',
            'destination' => 'truck',
            'quantity' => 2,
            'status' => BottleCustodyMovement::STATUS_ACCEPTED,
            'notes' => 'Carga aceptada por chofer',
        ]);

        $res = $this->actingAs($this->repartidorUser)->get(route('repartidor.reparto'));
        $res->assertOk();

        $items = $res->inertiaProps('items');
        $item = collect($items)->firstWhere('id', $order->id);

        $this->assertEquals('deliver_fixed', $item['action_type']);
        $this->assertEquals('Entregar 2 fijos', $item['action_label']);
        $this->assertTrue($item['can_execute']);
    }

    /**
     * 5. Recolección física directa de fijos desde Reparto:
     * El repartidor puede confirmar la recolección sin programar ni pasar por Mi Ruta.
     */
    public function test_driver_can_collect_fixed_directly_from_reparto(): void
    {
        $order = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $this->customer1->id,
            'fixed_quantity' => 2,
        ]);

        $collectRes = $this->actingAs($this->repartidorUser)->post(
            route('repartidor.orders.collect-fixed', $order->id),
            [
                'quantity' => 2,
                'notes' => 'Envases recolectados en excelente estado',
            ]
        );

        $collectRes->assertSessionHasNoErrors();
        $collectRes->assertRedirect();

        $fixedItem = $order->fresh()->items->firstWhere('type', OrderItem::TYPE_FIXED);
        $this->assertEquals(2, $fixedItem->collected_quantity);

        // Movimiento de custodia registrado hacia el camión
        $movement = BottleCustodyMovement::where('order_id', $order->id)
            ->where('movement_type', BottleCustodyMovement::TYPE_COLLECTION_FIXED_FROM_CUSTOMER)
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(2, $movement->quantity);
        $this->assertEquals('truck', $movement->destination);
    }

    /**
     * 6. Filtro de zona es de sólo consulta y no modifica la base de datos.
     */
    public function test_zone_filter_is_read_only_and_does_not_mutate_orders(): void
    {
        $orderNorth = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $this->customer1->id, // North
            'exchange_quantity' => 1,
        ]);
        $orderSouth = $this->orderService->createOrder($this->adminUser, [
            'customer_id' => $this->customer2->id, // South
            'exchange_quantity' => 1,
        ]);

        // Filtrar zona North
        $resNorth = $this->actingAs($this->repartidorUser)->get(route('repartidor.reparto', [
            'tab' => 'pendientes',
            'zone' => 'north',
        ]));
        $resNorth->assertOk();
        $resNorth->assertInertia(fn (Assert $page) => $page
            ->where('activeZone', 'north')
            ->has('items', 1)
            ->where('items.0.id', $orderNorth->id)
        );

        // Filtrar zona South
        $resSouth = $this->actingAs($this->repartidorUser)->get(route('repartidor.reparto', [
            'tab' => 'pendientes',
            'zone' => 'south',
        ]));
        $resSouth->assertOk();
        $resSouth->assertInertia(fn (Assert $page) => $page
            ->where('activeZone', 'south')
            ->has('items', 1)
            ->where('items.0.id', $orderSouth->id)
        );

        // Los estados de los pedidos se mantienen intactos
        $this->assertEquals(Order::STATUS_PENDING, $orderNorth->fresh()->status);
        $this->assertEquals(Order::STATUS_PENDING, $orderSouth->fresh()->status);
        $this->assertNull($orderNorth->fresh()->delivery_route_id);
        $this->assertNull($orderSouth->fresh()->delivery_route_id);
    }
}
