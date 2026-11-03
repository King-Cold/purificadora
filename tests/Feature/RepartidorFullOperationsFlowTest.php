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
use App\Services\DebtAndCreditService;
use App\Services\DeliveryRouteService;
use App\Services\OrderCustodyService;
use App\Services\OrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepartidorFullOperationsFlowTest extends TestCase
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
    protected DebtAndCreditService $debtService;

    protected function setUp(): void
    {
        parent::setUp();

        \Carbon\Carbon::setTestNow(\Carbon\Carbon::create(2026, 11, 3, 10, 0, 0, 'America/Merida'));

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->routeService = app(DeliveryRouteService::class);
        $this->orderService = app(OrderService::class);
        $this->custodyService = app(OrderCustodyService::class);
        $this->debtService = app(DebtAndCreditService::class);

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

        $this->repartidorUser = User::factory()->create(['name' => 'Chofer Repartidor', 'email' => 'chofer@test.com']);
        $this->repartidorUser->assignRole('Repartidor');

        $this->clientUser = User::factory()->create(['name' => 'Cliente Test', 'email' => 'cliente@test.com']);
        $this->clientUser->assignRole('Cliente');

        $this->customer = Customer::create([
            'user_id' => $this->clientUser->id,
            'code' => 'CLI-0001',
            'name' => 'Doña Tere Pech',
            'nickname' => 'Doña Tere',
            'phone' => '9991234567',
            'fixed_bottles_count' => 3,
            'exchange_bottles_count' => 5,
        ]);

        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'street' => 'Calle 15',
            'exterior_number' => '100',
            'neighborhood' => 'Centro',
            'city' => 'Hocabá',
            'state' => 'Yucatán',
            'delivery_zone' => 'north',
            'is_default' => true,
        ]);
    }

    /**
     * 1. Dashboard del repartidor muestra contadores reales de las 6 funciones operativas.
     */
    public function test_dashboard_displays_real_operational_counters(): void
    {
        // Crear 1 pedido confirmado con botellones fijos (por recoger)
        $order = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 2]);
        $this->orderService->confirmOrder($order, $this->adminUser);

        $response = $this->actingAs($this->repartidorUser)->get(route('repartidor.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Repartidor/Dashboard')
            ->has('counters')
            ->where('counters.por_recoger', 1)
            ->where('counters.por_entregar', 0)
            ->where('counters.cobrar_pendientes', 0)
            ->has('truckInventory')
        );
    }

    /**
     * 2. Pantalla "Por Recoger" lista pedidos con fijos pendientes y permite filtrar por zona.
     */
    public function test_por_recoger_lists_orders_and_filters_by_zone(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 2]);
        $this->orderService->confirmOrder($order, $this->adminUser);

        $response = $this->actingAs($this->repartidorUser)->get(route('repartidor.collections.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Repartidor/Collections/Index')
            ->has('orders', 1)
            ->where('orders.0.folio', $order->folio)
            ->where('orders.0.fixed_quantity_pending', 2)
            ->where('orders.0.customer.nickname', 'Doña Tere')
            ->where('orders.0.delivery_zone_key', 'north')
            ->where('orders.0.is_assigned_to_current_route', false)
            ->has('zoneCounts')
            ->where('zoneCounts.north', 1)
        );
    }

    /**
     * 3. Repartidor puede confirmar recolección física de botellones fijos vacíos.
     */
    public function test_repartidor_can_confirm_fixed_bottles_collection(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 2]);
        $this->orderService->confirmOrder($order, $this->adminUser);

        $response = $this->actingAs($this->repartidorUser)
            ->post(route('repartidor.orders.collect-fixed', $order->id), [
                'quantity' => 2,
                'notes' => 'Recolección física en portón del domicilio',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals(Order::STATUS_IN_PROCESS, $order->status);

        $fixedItem = $order->items()->where('type', OrderItem::TYPE_FIXED)->first();
        $this->assertEquals(2, $fixedItem->collected_quantity);
        $this->assertEquals(OrderItem::STATUS_TRANSPORTING_TO_PLANT, $fixedItem->status);

        // Verifica que no se generó cargo por rellenado en este momento
        $this->assertDatabaseMissing('order_charges', [
            'order_id' => $order->id,
        ]);

        // Verifica movimiento de custodia
        $this->assertDatabaseHas('bottle_custody_movements', [
            'order_id' => $order->id,
            'bottle_type' => OrderItem::TYPE_FIXED,
            'movement_type' => BottleCustodyMovement::TYPE_COLLECTION_FIXED_FROM_CUSTOMER,
            'destination' => 'truck',
            'quantity' => 2,
        ]);
    }

    /**
     * 4. Pantalla "Por Entregar" muestra pedidos y explica bloqueos por falta de existencias en camión.
     */
    public function test_por_entregar_explains_stock_block_without_hiding_orders(): void
    {
        $order = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 3]);
        $this->orderService->confirmOrder($order, $this->adminUser);

        // El camión tiene 0 garrafones llenos inicialmente
        $response = $this->actingAs($this->repartidorUser)->get(route('repartidor.deliveries.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Repartidor/Deliveries/Index')
            ->has('deliveries', 1)
            ->where('deliveries.0.folio', $order->folio)
            ->where('deliveries.0.exchange_quantity', 3)
            ->where('deliveries.0.is_blocked', true)
            ->where('truckExchangeAvailable', 0)
        );
    }

    /**
     * 5. Iniciar jornada de hoy crea o inicia la ruta automáticamente para el único repartidor activo.
     */
    public function test_start_today_route_creates_and_starts_route_for_sole_driver(): void
    {
        $response = $this->actingAs($this->repartidorUser)->post(route('repartidor.route.start-today'));

        $response->assertRedirect(route('repartidor.route.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('delivery_routes', [
            'driver_user_id' => $this->repartidorUser->id,
            'status' => DeliveryRoute::STATUS_IN_PROGRESS,
        ]);
    }

    /**
     * 6. Pantalla "Cobrar Pendientes" permite liquidar botellones históricos de forma independiente.
     */
    public function test_cobrar_pendientes_lists_debt_and_settles_complete_bottles(): void
    {
        // Crear cargo previo fiado de 2 botellones a $15.00
        $order = Order::create([
            'folio' => 'PED-PREV-01',
            'customer_id' => $this->customer->id,
            'created_by_user_id' => $this->adminUser->id,
            'customer_address_id' => $this->address->id,
            'recipient_name' => $this->customer->name,
            'address_snapshot' => [
                'recipient_name' => $this->customer->name,
                'delivery_street' => 'Calle 15',
                'delivery_zone' => 'north',
            ],
            'status' => Order::STATUS_DELIVERED,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'type' => OrderItem::TYPE_EXCHANGE,
            'quantity' => 2,
            'delivered_quantity' => 2,
            'unit_price' => 15.00,
            'status' => OrderItem::STATUS_DELIVERED,
        ]);

        $this->debtService->recordCharge($order, $item, 2, 15.00);

        // Consultar pantalla de Cobrar Pendientes
        $response = $this->actingAs($this->repartidorUser)->get(route('repartidor.debt.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Repartidor/Debt/Index')
            ->has('customersWithDebt', 1)
            ->where('customersWithDebt.0.pending_bottles', 2)
            ->where('customersWithDebt.0.pending_amount', fn ($amount) => (float) $amount === 30.0)
        );

        // Liquidar 1 botellón completo con $20 en efectivo (cambio $5)
        $settleResponse = $this->actingAs($this->repartidorUser)
            ->post(route('repartidor.customers.settle-debt', $this->customer->id), [
                'bottles_count' => 1,
                'cash_tendered' => 20.00,
                'notes' => 'Cobro independiente en calle',
            ]);

        $settleResponse->assertRedirect();
        $settleResponse->assertSessionHas('success');

        // Queda 1 botellón pendiente
        $this->assertEquals(1, $this->debtService->getPendingDebtBottles($this->customer));
    }
}
