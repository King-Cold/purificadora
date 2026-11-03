<?php

namespace Tests\Feature;

use App\Models\BottleCustodyMovement;
use App\Models\BottlePresentation;
use App\Models\Customer;
use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\OrderCharge;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\RouteStop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientTrackingAndAdminSupervisionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $clientUser;
    protected Customer $customer;
    protected BottlePresentation $presentation;

    protected function setUp(): void
    {
        parent::setUp();

        // Fijar tiempo dentro del horario laboral de Mérida (Lunes a las 10:00 AM)
        Carbon::setTestNow(Carbon::create(2026, 10, 26, 10, 0, 0, 'America/Merida'));

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Cliente', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Repartidor', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'name' => 'Admin PURITRONIC',
            'email' => 'admin@purificadora.com',
            'status' => 'active',
        ]);
        $this->admin->assignRole('Administrador');

        $this->clientUser = User::factory()->create([
            'name' => 'Don Chucho',
            'email' => 'chucho@example.com',
            'status' => 'active',
        ]);
        $this->clientUser->assignRole('Cliente');

        $this->customer = Customer::create([
            'user_id' => $this->clientUser->id,
            'nickname' => 'Don Chucho',
            'name' => 'Jesús González',
            'phone' => '9991234567',
            'exchange_bottles_count' => 4,
            'fixed_bottles_count' => 2,
        ]);

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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Comprueba que el Dashboard del Cliente muestra semáforo de deuda con nota aclaratoria
     * inconfundible de que son adeudos monetarios y no botellones por devolver.
     */
    public function test_client_dashboard_shows_accurate_debt_traffic_light_and_disclaimer(): void
    {
        $order = Order::create([
            'folio' => 'PED-DEBT-001',
            'customer_id' => $this->customer->id,
            'recipient_name' => $this->customer->nickname,
            'delivery_street' => 'Calle 18',
            'delivery_exterior_number' => '102',
            'delivery_zone' => 'centro',
            'address_snapshot' => [
                'recipient_name' => $this->customer->nickname,
                'delivery_street' => 'Calle 18',
                'delivery_exterior_number' => '102',
                'delivery_zone' => 'centro',
            ],
            'status' => Order::STATUS_DELIVERED,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'bottle_presentation_id' => $this->presentation->id,
            'type' => OrderItem::TYPE_EXCHANGE,
            'bottle_type' => 'cambio',
            'quantity' => 2,
            'unit_price' => 15.00,
            'subtotal' => 30.00,
            'status' => OrderItem::STATUS_DELIVERED,
            'delivered_quantity' => 2,
            'cancelled_quantity' => 0,
        ]);

        OrderCharge::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'customer_id' => $this->customer->id,
            'bottle_type' => 'cambio',
            'quantity_delivered' => 2,
            'unit_price' => 15.00,
            'total_amount' => 30.00,
            'paid_amount' => 0.00,
            'remaining_quantity' => 2,
            'status' => OrderCharge::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->clientUser)->get(route('cliente.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Cliente/Dashboard')
            ->has('debt', fn (Assert $debt) => $debt
                ->where('pending_bottles', 2)
                ->where('clarification_note', 'Son botellones pendientes de pago, no envases por devolver.')
                ->etc()
            )
            ->has('weekly_promo', fn (Assert $promo) => $promo
                ->has('paid_count')
                ->has('required')
                ->has('expires_at')
                ->where('status', 'in_progress')
                ->etc()
            )
        );
    }

    /**
     * Comprueba que los beneficios de la promoción semanal no se muestren como disponibles cuando expiran.
     */
    public function test_weekly_promotion_does_not_show_expired_benefits_as_available(): void
    {
        $order = Order::create([
            'folio' => 'PED-PROMO-001',
            'customer_id' => $this->customer->id,
            'recipient_name' => $this->customer->nickname,
            'delivery_street' => 'Calle 18',
            'delivery_exterior_number' => '102',
            'delivery_zone' => 'centro',
            'address_snapshot' => [
                'recipient_name' => $this->customer->nickname,
                'delivery_street' => 'Calle 18',
                'delivery_exterior_number' => '102',
                'delivery_zone' => 'centro',
            ],
            'status' => Order::STATUS_DELIVERED,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'bottle_presentation_id' => $this->presentation->id,
            'type' => OrderItem::TYPE_EXCHANGE,
            'bottle_type' => 'cambio',
            'quantity' => 9,
            'unit_price' => 15.00,
            'subtotal' => 135.00,
            'status' => OrderItem::STATUS_DELIVERED,
            'delivered_quantity' => 9,
            'cancelled_quantity' => 0,
        ]);

        $charge = OrderCharge::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'customer_id' => $this->customer->id,
            'bottle_type' => 'cambio',
            'quantity_delivered' => 9,
            'unit_price' => 15.00,
            'total_amount' => 135.00,
            'paid_amount' => 135.00,
            'remaining_quantity' => 0,
            'status' => OrderCharge::STATUS_PAID,
        ]);

        OrderPayment::create([
            'order_id' => $order->id,
            'order_charge_id' => $charge->id,
            'customer_id' => $this->customer->id,
            'amount' => 135.00,
            'cash_tendered' => 150.00,
            'change_returned' => 15.00,
            'bottles_covered_count' => 9,
            'received_by_user_id' => $this->admin->id,
            'payment_method' => 'cash',
        ]);

        // Verificamos que en la semana actual tiene el beneficio disponible
        $responseCurrentWeek = $this->actingAs($this->clientUser)->get(route('cliente.dashboard'));
        $responseCurrentWeek->assertInertia(fn (Assert $page) => $page
            ->has('weekly_promo', fn (Assert $promo) => $promo
                ->where('can_redeem', true)
                ->where('status', 'available')
                ->where('status_label', 'Disponible')
                ->etc()
            )
        );

        // Avanzar el tiempo a la semana siguiente (Próximo Lunes 10:00 AM)
        Carbon::setTestNow(Carbon::create(2026, 11, 2, 10, 0, 0, 'America/Merida'));

        $responseNextWeek = $this->actingAs($this->clientUser)->get(route('cliente.dashboard'));
        $responseNextWeek->assertInertia(fn (Assert $page) => $page
            ->has('weekly_promo', fn (Assert $promo) => $promo
                ->where('can_redeem', false)
                ->where('status', 'in_progress')
                ->where('paid_count', 0)
                ->etc()
            )
        );
    }

    /**
     * En pedidos mixtos, si el intercambio de cambio ya se entregó pero los botellones fijos
     * están en planta o en traslado, el pedido NO debe presentarse como Entregado.
     */
    public function test_mixed_order_operational_progress_tracks_exchange_delivered_while_fixed_under_custody(): void
    {
        $order = Order::create([
            'folio' => 'PED-MIXTO-001',
            'customer_id' => $this->customer->id,
            'recipient_name' => $this->customer->nickname,
            'delivery_street' => 'Calle 18',
            'delivery_exterior_number' => '102',
            'delivery_zone' => 'centro',
            'address_snapshot' => [
                'recipient_name' => $this->customer->nickname,
                'delivery_street' => 'Calle 18',
                'delivery_exterior_number' => '102',
                'delivery_zone' => 'centro',
            ],
            'status' => Order::STATUS_IN_ROUTE,
        ]);

        // Item de cambio: entregado
        OrderItem::create([
            'order_id' => $order->id,
            'bottle_presentation_id' => $this->presentation->id,
            'type' => OrderItem::TYPE_EXCHANGE,
            'bottle_type' => 'cambio',
            'quantity' => 2,
            'unit_price' => 15.00,
            'subtotal' => 30.00,
            'status' => OrderItem::STATUS_DELIVERED,
            'delivered_quantity' => 2,
            'cancelled_quantity' => 0,
        ]);

        // Item de fijos: recolectado físicamente por repartidor pero pendiente de devolver
        OrderItem::create([
            'order_id' => $order->id,
            'bottle_presentation_id' => $this->presentation->id,
            'type' => OrderItem::TYPE_FIXED,
            'bottle_type' => 'fijo',
            'quantity' => 1,
            'unit_price' => 15.00,
            'subtotal' => 15.00,
            'status' => OrderItem::STATUS_TRANSPORTING_TO_PLANT,
            'collected_quantity' => 1,
            'received_at_plant_quantity' => 0,
            'delivered_quantity' => 0,
            'cancelled_quantity' => 0,
        ]);

        $this->assertTrue($order->hasCollectedFixedCustody());

        $progress = $order->getOperationalProgress();
        $this->assertTrue($progress['is_mixed']);
        $this->assertEquals('llevando_a_planta', $progress['overall_stage_key']);
        $this->assertNotEquals('entregado', $progress['overall_stage_key']);
        $this->assertEquals('entregado', $progress['exchange']['stage_key']);
        $this->assertEquals('llevando_a_planta', $progress['fixed']['stage_key']);
        $this->assertStringContainsString('fijos', strtolower($progress['explanation']));
    }

    /**
     * Verifica que los botellones fijos no se muestren como 'Lavando' sin que Planta lo registre expresamente.
     */
    public function test_fixed_items_do_not_falsely_display_washing_without_plant_registration(): void
    {
        $order = Order::create([
            'folio' => 'PED-FIJO-001',
            'customer_id' => $this->customer->id,
            'recipient_name' => $this->customer->nickname,
            'delivery_street' => 'Calle 18',
            'delivery_exterior_number' => '102',
            'delivery_zone' => 'centro',
            'address_snapshot' => [
                'recipient_name' => $this->customer->nickname,
                'delivery_street' => 'Calle 18',
                'delivery_exterior_number' => '102',
                'delivery_zone' => 'centro',
            ],
            'status' => Order::STATUS_IN_ROUTE,
        ]);

        $fixedItem = OrderItem::create([
            'order_id' => $order->id,
            'bottle_presentation_id' => $this->presentation->id,
            'type' => OrderItem::TYPE_FIXED,
            'bottle_type' => 'fijo',
            'quantity' => 2,
            'unit_price' => 15.00,
            'subtotal' => 30.00,
            'status' => OrderItem::STATUS_TRANSPORTING_TO_PLANT,
            'collected_quantity' => 2,
            'received_at_plant_quantity' => 0,
            'delivered_quantity' => 0,
            'cancelled_quantity' => 0,
        ]);

        $progress = $order->getOperationalProgress();
        $this->assertEquals('llevando_a_planta', $progress['fixed']['stage_key']);
        $this->assertNotEquals('lavando', $progress['fixed']['stage_key']);

        // Cuando la planta formalmente lo recibe y pasa a lavado:
        $fixedItem->update([
            'status' => OrderItem::STATUS_WASHING,
            'received_at_plant_quantity' => 2,
        ]);

        $order->refresh();
        $progressUpdated = $order->getOperationalProgress();
        $this->assertEquals('lavando', $progressUpdated['fixed']['stage_key']);
        $this->assertEquals('Lavando y llenando (en planta)', $progressUpdated['fixed']['stage_label']);
    }

    /**
     * Un cliente no puede ver pedidos de otro cliente (Autorización 403).
     */
    public function test_client_cannot_view_another_clients_order(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('Cliente');

        $otherCustomer = Customer::create([
            'user_id' => $otherUser->id,
            'nickname' => 'Don Manuel',
            'exchange_bottles_count' => 2,
        ]);

        $otherOrder = Order::create([
            'folio' => 'PED-OTRO-001',
            'customer_id' => $otherCustomer->id,
            'recipient_name' => $otherCustomer->nickname,
            'delivery_street' => 'Calle 50',
            'delivery_exterior_number' => '200',
            'delivery_zone' => 'norte',
            'address_snapshot' => [
                'recipient_name' => $otherCustomer->nickname,
                'delivery_street' => 'Calle 50',
                'delivery_exterior_number' => '200',
                'delivery_zone' => 'norte',
            ],
            'status' => Order::STATUS_CONFIRMED,
        ]);

        $response = $this->actingAs($this->clientUser)->get(route('cliente.orders.show', $otherOrder->id));
        $response->assertStatus(403);
    }

    /**
     * El cliente únicamente puede cancelar pedidos en estado Pendiente.
     */
    public function test_client_can_only_cancel_pending_orders(): void
    {
        $pendingOrder = Order::create([
            'folio' => 'PED-CANCEL-001',
            'customer_id' => $this->customer->id,
            'recipient_name' => $this->customer->nickname,
            'delivery_street' => 'Calle 18',
            'delivery_exterior_number' => '102',
            'delivery_zone' => 'centro',
            'address_snapshot' => [
                'recipient_name' => $this->customer->nickname,
                'delivery_street' => 'Calle 18',
                'delivery_exterior_number' => '102',
                'delivery_zone' => 'centro',
            ],
            'status' => Order::STATUS_PENDING,
        ]);

        // Cancelar pedido pendiente con motivo exitoso
        $responsePending = $this->actingAs($this->clientUser)->post(
            route('cliente.orders.cancel', $pendingOrder->id),
            ['reason' => 'Salí de casa inesperadamente']
        );
        $responsePending->assertRedirect(route('cliente.orders.show', $pendingOrder->id));
        $this->assertEquals(Order::STATUS_CANCELLED, $pendingOrder->fresh()->status);

        // Crear pedido confirmado
        $confirmedOrder = Order::create([
            'folio' => 'PED-CONF-001',
            'customer_id' => $this->customer->id,
            'recipient_name' => $this->customer->nickname,
            'delivery_street' => 'Calle 18',
            'delivery_exterior_number' => '102',
            'delivery_zone' => 'centro',
            'address_snapshot' => [
                'recipient_name' => $this->customer->nickname,
                'delivery_street' => 'Calle 18',
                'delivery_exterior_number' => '102',
                'delivery_zone' => 'centro',
            ],
            'status' => Order::STATUS_CONFIRMED,
        ]);

        // Intento de cancelación por parte del cliente debe ser rechazado (403 Política)
        $responseConfirmed = $this->actingAs($this->clientUser)->post(
            route('cliente.orders.cancel', $confirmedOrder->id),
            ['reason' => 'Ya no quiero el pedido']
        );
        $responseConfirmed->assertStatus(403);
    }

    /**
     * La supervisión del Administrador incluye paradas de ruta, movimientos de custodia, cargos y pagos.
     */
    public function test_admin_order_supervision_shows_route_stops_custody_charges_and_payments(): void
    {
        $driver = User::factory()->create(['name' => 'Repartidor Juan']);
        $driver->assignRole('Repartidor');

        $route = DeliveryRoute::create([
            'folio' => 'RUT-TEST-001',
            'driver_user_id' => $driver->id,
            'date' => Carbon::today('America/Merida')->toDateString(),
            'status' => DeliveryRoute::STATUS_IN_PROGRESS,
        ]);

        $order = Order::create([
            'folio' => 'PED-SUPERV-001',
            'customer_id' => $this->customer->id,
            'delivery_route_id' => $route->id,
            'recipient_name' => $this->customer->nickname,
            'delivery_street' => 'Calle 18',
            'delivery_exterior_number' => '102',
            'delivery_zone' => 'centro',
            'address_snapshot' => [
                'recipient_name' => $this->customer->nickname,
                'delivery_street' => 'Calle 18',
                'delivery_exterior_number' => '102',
                'delivery_zone' => 'centro',
            ],
            'status' => Order::STATUS_IN_ROUTE,
        ]);

        RouteStop::create([
            'delivery_route_id' => $route->id,
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'stop_number' => 1,
            'status' => RouteStop::STATUS_COMPLETED,
            'completed_at' => now('America/Merida'),
        ]);

        BottleCustodyMovement::create([
            'order_id' => $order->id,
            'user_id' => $driver->id,
            'movement_type' => 'recoleccion',
            'bottle_type' => 'fijo',
            'bottle_condition' => 'vacio',
            'quantity' => 2,
            'origin' => 'cliente',
            'destination' => 'camion',
            'status' => 'accepted',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'bottle_presentation_id' => $this->presentation->id,
            'type' => OrderItem::TYPE_EXCHANGE,
            'bottle_type' => 'cambio',
            'quantity' => 2,
            'unit_price' => 15.00,
            'subtotal' => 30.00,
            'status' => OrderItem::STATUS_DELIVERED,
            'delivered_quantity' => 2,
            'cancelled_quantity' => 0,
        ]);

        $charge = OrderCharge::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'customer_id' => $this->customer->id,
            'bottle_type' => 'cambio',
            'quantity_delivered' => 2,
            'unit_price' => 15.00,
            'total_amount' => 30.00,
            'paid_amount' => 30.00,
            'remaining_quantity' => 0,
            'status' => OrderCharge::STATUS_PAID,
        ]);

        OrderPayment::create([
            'order_id' => $order->id,
            'order_charge_id' => $charge->id,
            'customer_id' => $this->customer->id,
            'amount' => 30.00,
            'cash_tendered' => 50.00,
            'change_returned' => 20.00,
            'bottles_covered_count' => 2,
            'received_by_user_id' => $driver->id,
            'payment_method' => 'cash',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.show', $order->id));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Show')
            ->has('order', fn (Assert $o) => $o
                ->where('folio', 'PED-SUPERV-001')
                ->has('route_stops', 1)
                ->has('custody_movements', 1)
                ->has('charges', 1)
                ->has('payments', 1)
                ->has('operational_progress')
                ->etc()
            )
        );
    }

    /**
     * El Administrador no puede cancelar ordinariamente un pedido si hay botellones fijos bajo custodia.
     */
    public function test_admin_cannot_cancel_order_when_fixed_bottles_are_under_custody(): void
    {
        $order = Order::create([
            'folio' => 'PED-CUSTODY-001',
            'customer_id' => $this->customer->id,
            'recipient_name' => $this->customer->nickname,
            'delivery_street' => 'Calle 18',
            'delivery_exterior_number' => '102',
            'delivery_zone' => 'centro',
            'address_snapshot' => [
                'recipient_name' => $this->customer->nickname,
                'delivery_street' => 'Calle 18',
                'delivery_exterior_number' => '102',
                'delivery_zone' => 'centro',
            ],
            'status' => Order::STATUS_IN_ROUTE,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'bottle_presentation_id' => $this->presentation->id,
            'type' => OrderItem::TYPE_FIXED,
            'bottle_type' => 'fijo',
            'quantity' => 2,
            'unit_price' => 15.00,
            'subtotal' => 30.00,
            'status' => OrderItem::STATUS_TRANSPORTING_TO_PLANT,
            'collected_quantity' => 2,
            'received_at_plant_quantity' => 0,
            'delivered_quantity' => 0,
            'cancelled_quantity' => 0,
        ]);

        $this->assertTrue($order->hasCollectedFixedCustody());

        // Intentar cancelar como Administrador
        $response = $this->actingAs($this->admin)->post(
            route('admin.orders.cancel', $order->id),
            ['reason' => 'Cancelación administrativa arbitraria']
        );

        // Debe ser rechazado (403 Forbidden por OrderPolicy::cancel)
        $response->assertStatus(403);
        $this->assertNotEquals(Order::STATUS_CANCELLED, $order->fresh()->status);
    }
}
