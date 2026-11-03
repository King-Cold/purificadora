<?php

namespace Tests\Feature;

use App\Exceptions\OrderBusinessRuleException;
use App\Models\BottlePresentation;
use App\Models\Customer;
use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\OrderCharge;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\RouteStop;
use App\Models\TruckInventory;
use App\Models\User;
use App\Services\WeeklyPromotionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RepartidorStopServingAndDirectServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $driver;
    protected BottlePresentation $presentation;

    protected function setUp(): void
    {
        parent::setUp();

        // Fijar tiempo dentro del horario laboral de Mérida (Lunes a las 11:00 AM)
        Carbon::setTestNow(Carbon::create(2026, 10, 26, 11, 0, 0, 'America/Merida'));

        Role::firstOrCreate(['name' => 'Repartidor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);

        $this->driver = User::factory()->create([
            'email' => 'repartidor@purificadora.com',
            'status' => 'active',
        ]);
        $this->driver->assignRole('Repartidor');

        $this->presentation = BottlePresentation::firstOrCreate(
            ['capacity' => 20.00],
            [
                'name' => 'Garrafón 20L',
                'refill_price' => 15.00,
                'bottle_price' => 120.00,
                'is_available_for_delivery' => true,
                'is_active' => true,
            ]
        );
    }

    protected function createCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'nickname' => 'Cliente Test',
            'exchange_bottles_count' => 5,
            'fixed_bottles_count' => 0,
        ], $attributes));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function createActiveRouteWithStop(Customer $customer, array $orderItems): array
    {
        $order = Order::create([
            'folio' => 'PED-TEST-' . uniqid(),
            'customer_id' => $customer->id,
            'created_by_user_id' => $this->driver->id,
            'recipient_name' => $customer->nickname,
            'delivery_street' => 'Calle 20',
            'delivery_exterior_number' => '100',
            'delivery_zone' => 'centro',
            'address_snapshot' => [
                'recipient_name' => $customer->nickname,
                'delivery_street' => 'Calle 20',
                'delivery_exterior_number' => '100',
                'delivery_zone' => 'centro',
            ],
            'status' => Order::STATUS_IN_ROUTE,
        ]);

        foreach ($orderItems as $item) {
            OrderItem::create(array_merge([
                'order_id' => $order->id,
                'unit_price' => 15.00,
                'delivered_quantity' => 0,
                'cancelled_quantity' => 0,
                'status' => OrderItem::STATUS_PENDING,
            ], $item));
        }

        $route = DeliveryRoute::create([
            'folio' => 'RUT-TEST-' . uniqid(),
            'driver_user_id' => $this->driver->id,
            'date' => now('America/Merida')->toDateString(),
            'status' => DeliveryRoute::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        $stop = RouteStop::create([
            'delivery_route_id' => $route->id,
            'order_id' => $order->id,
            'stop_number' => 1,
            'status' => RouteStop::STATUS_PENDING,
        ]);

        $order->update(['delivery_route_id' => $route->id]);

        return [$route, $stop, $order];
    }

    public function test_serve_stop_screen_loads_with_all_metrics_and_truck_inventory(): void
    {
        $customer = $this->createCustomer([
            'nickname' => 'Don Chucho',
            'exchange_bottles_count' => 3,
            'fixed_bottles_count' => 0,
        ]);

        [$route, $stop, $order] = $this->createActiveRouteWithStop($customer, [
            ['type' => OrderItem::TYPE_EXCHANGE, 'quantity' => 2],
        ]);

        TruckInventory::create([
            'driver_user_id' => $this->driver->id,
            'exchange_full_available' => 10,
            'exchange_full_initial' => 10,
            'exchange_full_delivered' => 0,
            'exchange_empty_collected' => 0,
        ]);

        $response = $this->actingAs($this->driver)
            ->get(route('repartidor.stops.serve', $stop->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Repartidor/Route/ServeStop')
            ->where('stop.id', $stop->id)
            ->where('route.id', $route->id)
            ->where('customer.nickname', 'Don Chucho')
            ->where('traffic_light.color', 'green')
            ->where('truck.exchange_full_available', 10)
            ->where('order.exchange_requested', 2)
        );
    }

    public function test_serve_stop_blocked_when_route_is_paused_by_incident(): void
    {
        $customer = $this->createCustomer([
            'nickname' => 'Doña Tere',
            'exchange_bottles_count' => 2,
        ]);

        [$route, $stop, $order] = $this->createActiveRouteWithStop($customer, [
            ['type' => OrderItem::TYPE_EXCHANGE, 'quantity' => 2],
        ]);

        $route->update(['status' => DeliveryRoute::STATUS_INCIDENT]);

        $response = $this->actingAs($this->driver)
            ->get(route('repartidor.stops.serve', $stop->id));

        $response->assertRedirect(route('repartidor.route.index'));
        $response->assertSessionHasErrors(['stop']);
    }

    public function test_full_exchange_serving_deducts_full_increments_empty_and_records_payment(): void
    {
        $customer = $this->createCustomer([
            'nickname' => 'Familia Pérez',
            'exchange_bottles_count' => 5,
        ]);

        [$route, $stop, $order] = $this->createActiveRouteWithStop($customer, [
            ['type' => OrderItem::TYPE_EXCHANGE, 'quantity' => 2],
        ]);

        $truck = TruckInventory::create([
            'driver_user_id' => $this->driver->id,
            'exchange_full_available' => 8,
            'exchange_full_initial' => 8,
            'exchange_full_delivered' => 0,
            'exchange_empty_collected' => 0,
        ]);

        $response = $this->actingAs($this->driver)
            ->post(route('repartidor.stops.process-serve', $stop->id), [
                'exchange_delivered' => 2,
                'exchange_empty_received' => 2,
                'units_paid_cash' => 2,
                'cash_tendered' => 30.00,
            ]);

        $response->assertRedirect(route('repartidor.route.index'));

        // Parada completada y pedido entregado
        $this->assertEquals(RouteStop::STATUS_COMPLETED, $stop->fresh()->status);
        $this->assertEquals(Order::STATUS_DELIVERED, $order->fresh()->status);

        // Inventario del camión actualizado exactamente
        $truck->refresh();
        $this->assertEquals(6, $truck->exchange_full_available);
        $this->assertEquals(2, $truck->exchange_full_delivered);
        $this->assertEquals(2, $truck->exchange_empty_collected);

        // Cobro y pago registrado
        $this->assertDatabaseHas('order_charges', [
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'quantity_delivered' => 2,
            'total_amount' => 30.00,
            'status' => OrderCharge::STATUS_PAID,
        ]);

        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'amount' => 30.00,
            'cash_tendered' => 30.00,
            'change_returned' => 0.00,
            'bottles_covered_count' => 2,
        ]);
    }

    public function test_partial_exchange_cancels_missing_empty_bottles_with_reason(): void
    {
        $customer = $this->createCustomer([
            'nickname' => 'Tienda Gómez',
            'exchange_bottles_count' => 5,
        ]);

        [$route, $stop, $order] = $this->createActiveRouteWithStop($customer, [
            ['type' => OrderItem::TYPE_EXCHANGE, 'quantity' => 3],
        ]);

        TruckInventory::create([
            'driver_user_id' => $this->driver->id,
            'exchange_full_available' => 10,
        ]);

        // Cliente pidió 3 pero solo tiene 1 vacío
        $response = $this->actingAs($this->driver)
            ->post(route('repartidor.stops.process-serve', $stop->id), [
                'exchange_delivered' => 1,
                'exchange_empty_received' => 1,
                'cancellation_reason' => 'Falta de envase vacío',
                'units_paid_cash' => 1,
                'cash_tendered' => 15.00,
            ]);

        $response->assertRedirect(route('repartidor.route.index'));

        $item = $order->items->first()->fresh();
        $this->assertEquals(1, $item->delivered_quantity);
        $this->assertEquals(2, $item->cancelled_quantity);
        $this->assertEquals('Falta de envase vacío', $item->cancellation_reason);
        $this->assertEquals(OrderItem::STATUS_DELIVERED, $item->status);

        // Pedido termina en ENTREGADO porque las 3 unidades están resueltas (1 entregada, 2 canceladas)
        $this->assertEquals(Order::STATUS_DELIVERED, $order->fresh()->status);

        // Solo se cobra la unidad entregada
        $this->assertDatabaseHas('order_charges', [
            'order_id' => $order->id,
            'quantity_delivered' => 1,
            'total_amount' => 15.00,
        ]);
    }

    public function test_fixed_collection_starts_in_process_with_no_refill_charge(): void
    {
        $customer = $this->createCustomer([
            'nickname' => 'Don Manuel Fijos',
            'fixed_bottles_count' => 2,
        ]);

        [$route, $stop, $order] = $this->createActiveRouteWithStop($customer, [
            ['type' => OrderItem::TYPE_FIXED, 'quantity' => 2],
        ]);

        $response = $this->actingAs($this->driver)
            ->post(route('repartidor.stops.process-serve', $stop->id), [
                'fixed_collected' => 2,
            ]);

        $response->assertRedirect(route('repartidor.route.index'));

        // Parada completada
        $this->assertEquals(RouteStop::STATUS_COMPLETED, $stop->fresh()->status);

        // Pedido pasa a EN PROCESO (Llevando a planta) y no a entregado
        $this->assertEquals(Order::STATUS_IN_PROCESS, $order->fresh()->status);
        $this->assertTrue($order->hasCollectedFixedCustody());

        // Movimiento de custodia registrado hacia camión
        $this->assertDatabaseHas('bottle_custody_movements', [
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'destination' => 'truck',
            'quantity' => 2,
            'movement_type' => 'collection_fixed_from_customer',
        ]);

        // No se generó cargo por refil en la recolección
        $this->assertDatabaseMissing('order_charges', [
            'order_id' => $order->id,
        ]);
    }

    public function test_mixed_order_delivery_and_collection_keeps_order_in_process_for_fixed_custody(): void
    {
        $customer = $this->createCustomer([
            'nickname' => 'Comedor La Bendición',
            'exchange_bottles_count' => 2,
            'fixed_bottles_count' => 1,
        ]);

        [$route, $stop, $order] = $this->createActiveRouteWithStop($customer, [
            ['type' => OrderItem::TYPE_EXCHANGE, 'quantity' => 2],
            ['type' => OrderItem::TYPE_FIXED, 'quantity' => 1],
        ]);

        TruckInventory::create([
            'driver_user_id' => $this->driver->id,
            'exchange_full_available' => 5,
        ]);

        $response = $this->actingAs($this->driver)
            ->post(route('repartidor.stops.process-serve', $stop->id), [
                'exchange_delivered' => 2,
                'exchange_empty_received' => 2,
                'fixed_collected' => 1,
                'units_paid_cash' => 2,
                'cash_tendered' => 30.00,
            ]);

        $response->assertRedirect(route('repartidor.route.index'));

        // Parada completada
        $this->assertEquals(RouteStop::STATUS_COMPLETED, $stop->fresh()->status);

        // Cambio cobrado
        $this->assertDatabaseHas('order_charges', [
            'order_id' => $order->id,
            'quantity_delivered' => 2,
            'status' => OrderCharge::STATUS_PAID,
        ]);

        // Pedido CONTINÚA ACTIVO en in_process debido a que el fijo sigue bajo custodia
        $this->assertEquals(Order::STATUS_IN_PROCESS, $order->fresh()->status);
        $this->assertTrue($order->isActive());
    }

    public function test_weekly_promotion_earned_in_same_delivery(): void
    {
        $customer = $this->createCustomer([
            'nickname' => 'Panadería La Esperanza',
            'exchange_bottles_count' => 10,
        ]);

        // Simular que ya pagó 8 botellones en la semana
        OrderPayment::create([
            'customer_id' => $customer->id,
            'received_by_user_id' => $this->driver->id,
            'amount' => 120.00,
            'cash_tendered' => 120.00,
            'change_returned' => 0.00,
            'payment_method' => 'cash',
            'bottles_covered_count' => 8,
            'created_at' => now('America/Merida')->startOfWeek()->addDay(),
        ]);

        [$route, $stop, $order] = $this->createActiveRouteWithStop($customer, [
            ['type' => OrderItem::TYPE_EXCHANGE, 'quantity' => 2],
        ]);

        TruckInventory::create([
            'driver_user_id' => $this->driver->id,
            'exchange_full_available' => 5,
        ]);

        // Entrega 2 botellones, paga 1 en efectivo ($15). Alcanza 9 pagados y el 10º es gratuito en la misma entrega
        $response = $this->actingAs($this->driver)
            ->post(route('repartidor.stops.process-serve', $stop->id), [
                'exchange_delivered' => 2,
                'exchange_empty_received' => 2,
                'units_paid_cash' => 1,
                'cash_tendered' => 15.00,
            ]);

        $response->assertRedirect(route('repartidor.route.index'));

        // Se bonificó 1 unidad de promoción gratuita
        $this->assertDatabaseHas('order_charges', [
            'order_id' => $order->id,
            'total_amount' => 0.00,
            'status' => OrderCharge::STATUS_WAIVED_PROMOTION,
        ]);

        // Se pagó la otra unidad
        $this->assertDatabaseHas('order_charges', [
            'order_id' => $order->id,
            'total_amount' => 15.00,
            'status' => OrderCharge::STATUS_PAID,
        ]);

        $this->assertEquals(Order::STATUS_DELIVERED, $order->fresh()->status);
    }

    public function test_cash_tendered_calculates_change_and_records_exact_paid_amount(): void
    {
        $customer = $this->createCustomer([
            'nickname' => 'Doña Mari',
            'exchange_bottles_count' => 2,
        ]);

        [$route, $stop, $order] = $this->createActiveRouteWithStop($customer, [
            ['type' => OrderItem::TYPE_EXCHANGE, 'quantity' => 1],
        ]);

        TruckInventory::create([
            'driver_user_id' => $this->driver->id,
            'exchange_full_available' => 5,
        ]);

        // Recibe 1 botellón ($15), cliente paga con billete de $50
        $response = $this->actingAs($this->driver)
            ->post(route('repartidor.stops.process-serve', $stop->id), [
                'exchange_delivered' => 1,
                'exchange_empty_received' => 1,
                'units_paid_cash' => 1,
                'cash_tendered' => 50.00,
            ]);

        $response->assertRedirect(route('repartidor.route.index'));

        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'amount' => 15.00, // Solo el importe exacto liquidado
            'cash_tendered' => 50.00,
            'change_returned' => 35.00,
            'bottles_covered_count' => 1,
        ]);
    }

    public function test_settle_past_debt_from_route_reduces_pending_and_applies_historical_price(): void
    {
        $customer = $this->createCustomer([
            'nickname' => 'Don Pepe Deudor',
        ]);

        $pastOrder = Order::create([
            'folio' => 'PED-PAST-001',
            'customer_id' => $customer->id,
            'created_by_user_id' => $this->driver->id,
            'recipient_name' => $customer->nickname,
            'address_snapshot' => [
                'recipient_name' => $customer->nickname,
            ],
            'status' => Order::STATUS_DELIVERED,
        ]);

        $item = OrderItem::create([
            'order_id' => $pastOrder->id,
            'type' => OrderItem::TYPE_EXCHANGE,
            'quantity' => 2,
            'delivered_quantity' => 2,
            'unit_price' => 15.00,
            'status' => OrderItem::STATUS_DELIVERED,
        ]);

        OrderCharge::create([
            'order_id' => $pastOrder->id,
            'order_item_id' => $item->id,
            'customer_id' => $customer->id,
            'bottle_type' => OrderItem::TYPE_EXCHANGE,
            'quantity_delivered' => 2,
            'unit_price' => 15.00,
            'total_amount' => 30.00,
            'paid_amount' => 0.00,
            'status' => OrderCharge::STATUS_PENDING,
        ]);

        // Cliente tiene 2 botellones pendientes ($30). Liquida 1 botellón con billete de $20
        $response = $this->actingAs($this->driver)
            ->post(route('repartidor.customers.settle-debt', $customer->id), [
                'bottles_count' => 1,
                'cash_tendered' => 20.00,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('order_payments', [
            'customer_id' => $customer->id,
            'amount' => 15.00,
            'cash_tendered' => 20.00,
            'change_returned' => 5.00,
            'bottles_covered_count' => 1,
        ]);

        // Queda 1 botellón pendiente en el cargo
        $this->assertEquals(15.00, $pastOrder->charges->first()->fresh()->paid_amount);
        $this->assertEquals(OrderCharge::STATUS_PARTIALLY_PAID, $pastOrder->charges->first()->fresh()->status);
    }

    public function test_direct_service_creates_order_and_executes_atomically_for_new_customer(): void
    {
        TruckInventory::create([
            'driver_user_id' => $this->driver->id,
            'exchange_full_available' => 10,
        ]);

        $route = DeliveryRoute::create([
            'folio' => 'RUT-ACTIVA-001',
            'driver_user_id' => $this->driver->id,
            'date' => now('America/Merida')->toDateString(),
            'status' => DeliveryRoute::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->driver)
            ->post(route('repartidor.direct-service.store'), [
                'nickname' => 'Tiendita Doña Lupe',
                'name' => null, // Opcional
                'phone' => null, // Opcional
                'exchange_quantity' => 2,
                'exchange_empty_received' => 2,
                'units_paid_cash' => 2,
                'cash_tendered' => 30.00,
            ]);

        $response->assertRedirect(route('repartidor.route.index'));

        // Se creó el cliente con solo apodo sin cuenta de usuario
        $customer = Customer::where('nickname', 'Tiendita Doña Lupe')->first();
        $this->assertNotNull($customer);
        $this->assertNull($customer->user_id);

        // Se creó el pedido y quedó en entregado
        $order = Order::where('customer_id', $customer->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals(Order::STATUS_DELIVERED, $order->status);

        // El cobro quedó liquidado
        $this->assertDatabaseHas('order_charges', [
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'quantity_delivered' => 2,
            'total_amount' => 30.00,
            'status' => OrderCharge::STATUS_PAID,
        ]);
    }

    public function test_direct_service_rejected_if_customer_already_has_active_order(): void
    {
        $customer = $this->createCustomer([
            'nickname' => 'Cliente Con Pedido Activo',
            'exchange_bottles_count' => 5,
        ]);

        // Crear pedido activo para este cliente
        Order::create([
            'folio' => 'PED-ACTIVO-001',
            'customer_id' => $customer->id,
            'created_by_user_id' => $this->driver->id,
            'recipient_name' => $customer->nickname,
            'address_snapshot' => [
                'recipient_name' => $customer->nickname,
            ],
            'status' => Order::STATUS_CONFIRMED,
        ]);

        TruckInventory::create([
            'driver_user_id' => $this->driver->id,
            'exchange_full_available' => 10,
        ]);

        DeliveryRoute::create([
            'folio' => 'RUT-ACTIVA-002',
            'driver_user_id' => $this->driver->id,
            'date' => now('America/Merida')->toDateString(),
            'status' => DeliveryRoute::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->driver)
            ->post(route('repartidor.direct-service.store'), [
                'customer_id' => $customer->id,
                'exchange_quantity' => 1,
                'exchange_empty_received' => 1,
                'units_paid_cash' => 1,
                'cash_tendered' => 15.00,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['direct']);
    }
}
