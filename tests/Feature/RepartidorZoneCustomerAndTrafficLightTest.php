<?php

namespace Tests\Feature;

use App\Exceptions\OrderBusinessRuleException;
use App\Models\BottlePresentation;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RouteStop;
use App\Models\User;
use App\Services\DebtAndCreditService;
use App\Services\DeliveryRouteService;
use App\Services\OrderService;
use App\Services\SettingService;
use App\Services\TruckInventoryService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepartidorZoneCustomerAndTrafficLightTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $repartidorUser;
    protected User $clientUser;
    protected DebtAndCreditService $debtService;
    protected SettingService $settingService;
    protected DeliveryRouteService $routeService;
    protected OrderService $orderService;
    protected TruckInventoryService $truckService;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 11, 2, 10, 0, 0, 'America/Merida'));

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->debtService = app(DebtAndCreditService::class);
        $this->settingService = app(SettingService::class);
        $this->routeService = app(DeliveryRouteService::class);
        $this->orderService = app(OrderService::class);
        $this->truckService = app(TruckInventoryService::class);

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

        $this->clientUser = User::factory()->create(['name' => 'Cliente', 'email' => 'cliente@purificadora.com']);
        $this->clientUser->assignRole('Cliente');

        $clientCustomer = Customer::create([
            'user_id' => $this->clientUser->id,
            'nickname' => 'Cliente Usuario',
            'fixed_bottles_count' => 10,
            'exchange_bottles_count' => 10,
        ]);

        CustomerAddress::create([
            'customer_id' => $clientCustomer->id,
            'street' => 'Calle 20',
            'exterior_number' => '100',
            'neighborhood' => 'Centro',
            'city' => 'Hocabá',
            'state' => 'Yucatán',
            'delivery_zone' => 'north',
            'is_default' => true,
        ]);

        // Existencias en camión
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

    // =========================================================================
    // 1. SELECCIÓN DE ZONA EN "MI RUTA"
    // =========================================================================

    public function test_route_stops_contain_normalized_zones_and_preserve_order_and_status(): void
    {
        // Crear 4 clientes con diferentes zonas de reparto
        $cNorth = Customer::create(['nickname' => 'Norte Uno', 'fixed_bottles_count' => 0, 'exchange_bottles_count' => 2]);
        $cSouth = Customer::create(['nickname' => 'Sur Uno', 'fixed_bottles_count' => 0, 'exchange_bottles_count' => 2]);
        $cNone = Customer::create(['nickname' => 'Sin Zona Uno', 'fixed_bottles_count' => 0, 'exchange_bottles_count' => 2]);

        $order1 = Order::create([
            'folio' => 'PED-20261102-0001',
            'customer_id' => $cNorth->id,
            'recipient_name' => 'Norte Uno',
            'recipient_phone' => '9991110001',
            'status' => Order::STATUS_CONFIRMED,
            'delivery_street' => 'Calle 10',
            'delivery_zone' => 'north',
            'address_snapshot' => ['delivery_street' => 'Calle 10', 'delivery_zone' => 'north'],
        ]);
        OrderItem::create(['order_id' => $order1->id, 'type' => OrderItem::TYPE_EXCHANGE, 'quantity' => 2, 'unit_price' => 15.00, 'subtotal' => 30.00, 'status' => OrderItem::STATUS_PENDING]);

        $order2 = Order::create([
            'folio' => 'PED-20261102-0002',
            'customer_id' => $cSouth->id,
            'recipient_name' => 'Sur Uno',
            'recipient_phone' => '9991110002',
            'status' => Order::STATUS_CONFIRMED,
            'delivery_street' => 'Calle 25',
            'delivery_zone' => 'south',
            'address_snapshot' => ['delivery_street' => 'Calle 25', 'delivery_zone' => 'south'],
        ]);
        OrderItem::create(['order_id' => $order2->id, 'type' => OrderItem::TYPE_EXCHANGE, 'quantity' => 1, 'unit_price' => 15.00, 'subtotal' => 15.00, 'status' => OrderItem::STATUS_PENDING]);

        $order3 = Order::create([
            'folio' => 'PED-20261102-0003',
            'customer_id' => $cNone->id,
            'recipient_name' => 'Sin Zona Uno',
            'recipient_phone' => '9991110003',
            'status' => Order::STATUS_CONFIRMED,
            'delivery_street' => null,
            'delivery_zone' => null,
            'address_snapshot' => ['delivery_street' => null, 'delivery_zone' => null],
        ]);
        OrderItem::create(['order_id' => $order3->id, 'type' => OrderItem::TYPE_EXCHANGE, 'quantity' => 1, 'unit_price' => 15.00, 'subtotal' => 15.00, 'status' => OrderItem::STATUS_PENDING]);

        // Crear ruta con orden [order1, order2, order3]
        $route = $this->routeService->createRoute($this->adminUser, null, now(), [$order1->id, $order2->id, $order3->id]);

        $res = $this->actingAs($this->repartidorUser)->get(route('repartidor.route.index'));
        $res->assertOk();
        $res->assertInertia(fn ($page) => $page
            ->component('Repartidor/Route/Index')
            ->has('activeRoute.stops', 3)
            // Parada 1: Norte (orden 1)
            ->where('activeRoute.stops.0.stop_number', 1)
            ->where('activeRoute.stops.0.delivery_zone_key', 'north')
            ->where('activeRoute.stops.0.delivery_zone_label', 'Norte')
            // Parada 2: Sur (orden 2)
            ->where('activeRoute.stops.1.stop_number', 2)
            ->where('activeRoute.stops.1.delivery_zone_key', 'south')
            ->where('activeRoute.stops.1.delivery_zone_label', 'Sur')
            // Parada 3: Sin zona (orden 3)
            ->where('activeRoute.stops.2.stop_number', 3)
            ->where('activeRoute.stops.2.delivery_zone_key', 'none')
            ->where('activeRoute.stops.2.delivery_zone_label', 'Sin zona')
        );

        // La ruta permanece en estado "assigned", y las paradas permanecen en PENDING (sin iniciar ruta)
        $route->refresh();
        $this->assertEquals(DeliveryRoute::STATUS_ASSIGNED, $route->status);
        foreach ($route->stops as $stop) {
            $this->assertEquals(RouteStop::STATUS_PENDING, $stop->status);
        }
    }

    // =========================================================================
    // 2. AGREGAR CLIENTE DESDE PANEL DE REPARTIDOR
    // =========================================================================

    public function test_repartidor_can_access_and_create_standalone_customer_without_route_or_order(): void
    {
        // 1. Acceso a la pantalla "Agregar cliente" sin ruta asignada
        $res = $this->actingAs($this->repartidorUser)->get(route('repartidor.customers.create'));
        $res->assertOk();
        $res->assertInertia(fn ($page) => $page
            ->component('Repartidor/Customers/Create')
            ->where('hasActiveRoute', false)
        );

        // 2. Validación de obligatoriedad de apodo
        $invalidRes = $this->actingAs($this->repartidorUser)->post(route('repartidor.customers.store-quick'), [
            'nickname' => '',
            'name' => 'Solo Nombre',
        ]);
        $invalidRes->assertSessionHasErrors(['nickname']);

        // 3. Creación exitosa: solo apodo obligatorio y nombre opcional (sin teléfono, correo ni domicilio)
        $validRes = $this->actingAs($this->repartidorUser)->post(route('repartidor.customers.store-quick'), [
            'nickname' => 'Doña Mary',
            'name' => 'María Pech',
        ]);
        $validRes->assertRedirect(route('repartidor.customers.create'));
        $validRes->assertSessionHas('createdCustomer');

        $customer = Customer::where('nickname', 'Doña Mary')->first();
        $this->assertNotNull($customer);
        $this->assertNull($customer->user_id); // Sin User
        $this->assertMatchesRegularExpression('/^CLI-\d{4}$/', $customer->code); // CLI-XXXX
        $this->assertEquals('María Pech', $customer->name);
        $this->assertNull($customer->phone);
        $this->assertNull($customer->email);
        $this->assertNull($customer->address);
    }

    public function test_allows_duplicate_nicknames_without_merging_records(): void
    {
        // Registrar dos clientes con el mismo apodo "Don Beto"
        $this->actingAs($this->repartidorUser)->post(route('repartidor.customers.store-quick'), [
            'nickname' => 'Don Beto',
            'name' => 'Alberto Pérez',
        ]);

        $this->actingAs($this->repartidorUser)->post(route('repartidor.customers.store-quick'), [
            'nickname' => 'Don Beto',
            'name' => 'Herberto Chan',
        ]);

        $customers = Customer::where('nickname', 'Don Beto')->get();
        $this->assertCount(2, $customers);
        $this->assertNotEquals($customers[0]->id, $customers[1]->id);
        $this->assertNotEquals($customers[0]->code, $customers[1]->code);
        $this->assertEquals('Alberto Pérez', $customers[0]->name);
        $this->assertEquals('Herberto Chan', $customers[1]->name);
    }

    public function test_direct_service_prepopulates_newly_created_customer(): void
    {
        $customer = Customer::create([
            'nickname' => 'Taller Hermanos',
            'name' => null,
            'fixed_bottles_count' => 0,
            'exchange_bottles_count' => 0,
        ]);

        $res = $this->actingAs($this->repartidorUser)->get(route('repartidor.direct-service.create', [
            'customer_id' => $customer->id,
        ]));

        $res->assertOk();
        $res->assertInertia(fn ($page) => $page
            ->component('Repartidor/Route/DirectService')
            ->where('initialCustomer.id', $customer->id)
            ->where('initialCustomer.nickname', 'Taller Hermanos')
            ->where('initialCustomer.code', $customer->code)
        );
    }

    // =========================================================================
    // 3. SEMÁFORO: VERDE, AMARILLO, ROJO Y LÍMITE CERO
    // =========================================================================

    public function test_traffic_light_strictly_follows_approved_rules(): void
    {
        $customer = Customer::create([
            'nickname' => 'Don Pedro',
            'fixed_bottles_count' => 2,
            'exchange_bottles_count' => 2,
        ]);

        // Configuración inicial: Límite = 5 botellones
        $this->settingService->setMaxPendingPaymentBottles(5);

        // REGLA 1: Verde = 0 pendientes
        $tl0 = $this->debtService->getTrafficLight($customer);
        $this->assertEquals('green', $tl0['color']);
        $this->assertEquals('no_debt', $tl0['status']);
        $this->assertEquals('Al día · Sin pendientes', $tl0['label']);
        $this->assertEquals(0, $tl0['pending_bottles']);
        $this->assertEquals(5, $tl0['limit']);
        $this->assertTrue($tl0['can_receive_more_debt']);

        // Crear pedido y fiar 2 botellones (mayor que cero y menor que 5)
        $order = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 2]);
        $this->orderService->confirmOrder($order, $this->adminUser);
        $item = $order->items->first();
        $this->debtService->recordCharge($order, $item, 2, 15.00, false);
        // Vincular cargo al cliente don Pedro
        \App\Models\OrderCharge::where('order_id', $order->id)->update(['customer_id' => $customer->id]);

        // REGLA 2: Amarillo = mayor que cero y menor que el límite
        $tl2 = $this->debtService->getTrafficLight($customer);
        $this->assertEquals('yellow', $tl2['color']);
        $this->assertEquals('with_debt', $tl2['status']);
        $this->assertEquals(2, $tl2['pending_bottles']);
        $this->assertEquals(5, $tl2['limit']);
        $this->assertTrue($tl2['can_receive_more_debt']);

        // Liberar el pedido anterior marcándolo como entregado para permitir nuevo pedido
        $order->update(['status' => Order::STATUS_DELIVERED]);

        // Fiar 3 botellones más -> total 5 pendientes (= límite)
        $order2 = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 3]);
        $this->orderService->confirmOrder($order2, $this->adminUser);
        $item2 = $order2->items->first();
        $this->debtService->recordCharge($order2, $item2, 3, 15.00, false);
        \App\Models\OrderCharge::where('order_id', $order2->id)->update(['customer_id' => $customer->id]);

        // REGLA 3: Rojo = igual o superior al límite
        $tl5 = $this->debtService->getTrafficLight($customer);
        $this->assertEquals('red', $tl5['color']);
        $this->assertEquals('limit_reached', $tl5['status']);
        $this->assertEquals(5, $tl5['pending_bottles']);
        $this->assertEquals(5, $tl5['limit']);
        $this->assertFalse($tl5['can_receive_more_debt']);

        // REGLA 4: Límite cero -> "Fiado deshabilitado", sin inventar deuda
        $this->settingService->setMaxPendingPaymentBottles(0);

        // Cliente limpio con límite cero: Verde, 0 pendientes, etiqueta "Fiado deshabilitado"
        $cleanCustomer = Customer::create(['nickname' => 'Cliente Limpio', 'fixed_bottles_count' => 1, 'exchange_bottles_count' => 1]);
        $tlZeroClean = $this->debtService->getTrafficLight($cleanCustomer);
        $this->assertEquals('green', $tlZeroClean['color']);
        $this->assertEquals('credit_disabled', $tlZeroClean['status']);
        $this->assertEquals(0, $tlZeroClean['pending_bottles']);
        $this->assertEquals(0, $tlZeroClean['limit']);
        $this->assertEquals('Fiado deshabilitado', $tlZeroClean['label']);
        $this->assertFalse($tlZeroClean['can_receive_more_debt']);

        // Cliente con deuda previa bajo límite cero: Rojo, estado credit_disabled, etiqueta con pendientes
        $tlZeroDebt = $this->debtService->getTrafficLight($customer);
        $this->assertEquals('red', $tlZeroDebt['color']);
        $this->assertEquals('credit_disabled', $tlZeroDebt['status']);
        $this->assertEquals(5, $tlZeroDebt['pending_bottles']);
        $this->assertStringContainsString('Fiado deshabilitado', $tlZeroDebt['label']);
        $this->assertFalse($tlZeroDebt['can_receive_more_debt']);
    }

    public function test_can_charge_debt_respects_configurable_limit(): void
    {
        $customer = Customer::create(['nickname' => 'Prueba Fiado', 'fixed_bottles_count' => 1, 'exchange_bottles_count' => 1]);

        // Límite 0: fiado deshabilitado
        $this->settingService->setMaxPendingPaymentBottles(0);
        $this->assertFalse($this->debtService->canChargeDebt($customer, 1));
        $this->assertTrue($this->debtService->canChargeDebt($customer, 0)); // 0 unidades a fiar siempre permitido

        // Límite 3
        $this->settingService->setMaxPendingPaymentBottles(3);
        $this->assertTrue($this->debtService->canChargeDebt($customer, 3));
        $this->assertFalse($this->debtService->canChargeDebt($customer, 4));
    }
}
