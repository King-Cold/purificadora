<?php

namespace Tests\Feature;

use App\Exceptions\OrderBusinessRuleException;
use App\Models\BottlePresentation;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\DebtAndCreditService;
use App\Services\DeliveryRouteService;
use App\Services\OrderService;
use App\Services\SettingService;
use App\Services\WeeklyPromotionService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtCreditAndPromotionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $repartidorUser;
    protected User $clientUser;
    protected Customer $customer;
    protected CustomerAddress $address;
    protected DebtAndCreditService $debtService;
    protected SettingService $settingService;
    protected WeeklyPromotionService $promotionService;
    protected OrderService $orderService;
    protected DeliveryRouteService $routeService;

    protected function setUp(): void
    {
        parent::setUp();

        \Carbon\Carbon::setTestNow(\Carbon\Carbon::create(2026, 10, 26, 10, 0, 0, 'America/Merida'));

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->debtService = app(DebtAndCreditService::class);
        $this->settingService = app(SettingService::class);
        $this->promotionService = app(WeeklyPromotionService::class);
        $this->orderService = app(OrderService::class);
        $this->routeService = app(DeliveryRouteService::class);

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

        // Carga de existencias iniciales en camión para pruebas operativas
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
            'fixed_bottles_count' => 5,
            'exchange_bottles_count' => 10,
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
     * 1. Límite configurable de botellones pendientes de pago (valor inicial: 5).
     */
    public function test_configurable_debt_limit_initial_value_is_five(): void
    {
        $this->assertEquals(5, $this->settingService->getMaxPendingPaymentBottles());

        // Administrador puede actualizarlo vía API
        $response = $this->actingAs($this->adminUser)->put(route('admin.settings.debt-limit.update'), [
            'max_pending_payment_bottles' => 3,
        ]);
        $response->assertRedirect();

        $this->assertEquals(3, $this->settingService->getMaxPendingPaymentBottles());
    }

    /**
     * 2. Semáforo del repartidor: verde (0 pendientes), amarillo (1 a 4), rojo (5 o más).
     */
    public function test_repartidor_traffic_light_reflects_debt_accurately(): void
    {
        // Estado inicial: 0 deuda -> Verde
        $tlGreen = $this->debtService->getTrafficLight($this->customer);
        $this->assertEquals('green', $tlGreen['color']);
        $this->assertEquals('Al día · Sin pendientes', $tlGreen['label']);

        // Crear pedido y entregar 3 unidades fiadas
        $order = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 3]);
        $this->orderService->confirmOrder($order, $this->adminUser);
        $route = $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now(), [$order->id]);
        $stop = $route->stops->first();

        // Entrega 3 con 0 pagados (3 fiados)
        $this->routeService->recordStopCompleted($stop, [
            'exchange_delivered' => 3,
            'exchange_empty_received' => 3,
            'units_paid_cash' => 0,
        ], $this->repartidorUser);

        // Estado con 3 deudas -> Amarillo
        $tlYellow = $this->debtService->getTrafficLight($this->customer);
        $this->assertEquals('yellow', $tlYellow['color']);
        $this->assertEquals(3, $tlYellow['pending_bottles']);
        $this->assertEquals('Con deuda · 3 de 5 botellones pendientes de pago', $tlYellow['label']);

        // Segundo pedido para alcanzar el límite de 5
        $order2 = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 2]);
        $this->orderService->confirmOrder($order2, $this->adminUser);
        $route2 = $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now(), [$order2->id]);
        $stop2 = $route2->stops->first();

        $this->routeService->recordStopCompleted($stop2, [
            'exchange_delivered' => 2,
            'exchange_empty_received' => 2,
            'units_paid_cash' => 0,
        ], $this->repartidorUser);

        // Estado con 5 deudas -> Rojo
        $tlRed = $this->debtService->getTrafficLight($this->customer);
        $this->assertEquals('red', $tlRed['color']);
        $this->assertEquals(5, $tlRed['pending_bottles']);
        $this->assertEquals('Límite alcanzado · 5 de 5 botellones pendientes de pago', $tlRed['label']);
    }

    /**
     * 3. Llegar al límite impide generar más deudas, pero permite recibir pagando en efectivo.
     */
    public function test_reaching_limit_blocks_more_debt_but_allows_cash_payment(): void
    {
        // Generar 5 unidades fiadas
        $order = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 5]);
        $this->orderService->confirmOrder($order, $this->adminUser);
        $route = $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now(), [$order->id]);
        $this->routeService->recordStopCompleted($route->stops->first(), [
            'exchange_delivered' => 5,
            'exchange_empty_received' => 5,
            'units_paid_cash' => 0,
        ], $this->repartidorUser);

        $this->assertEquals(5, $this->debtService->getPendingDebtBottles($this->customer));

        // Intento de entregar 1 unidad fiada adicional debe ser rechazado
        $order2 = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 1]);
        $this->orderService->confirmOrder($order2, $this->adminUser);
        $route2 = $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now(), [$order2->id]);

        $this->expectException(OrderBusinessRuleException::class);
        $this->routeService->recordStopCompleted($route2->stops->first(), [
            'exchange_delivered' => 1,
            'exchange_empty_received' => 1,
            'units_paid_cash' => 0, // Intento de fiar
        ], $this->repartidorUser);
    }

    /**
     * 4. Al cliente con límite alcanzado se le permite recibir si paga en efectivo al momento.
     */
    public function test_customer_at_limit_can_receive_bottles_when_paying_cash(): void
    {
        // 5 unidades fiadas
        $order = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 5]);
        $this->orderService->confirmOrder($order, $this->adminUser);
        $route = $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now(), [$order->id]);
        $this->routeService->recordStopCompleted($route->stops->first(), [
            'exchange_delivered' => 5,
            'exchange_empty_received' => 5,
            'units_paid_cash' => 0,
        ], $this->repartidorUser);

        // Nuevo pedido pagando en efectivo
        $order2 = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 2]);
        $this->orderService->confirmOrder($order2, $this->adminUser);
        $route2 = $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now(), [$order2->id]);

        // Entrega 2 y paga 2 en efectivo
        $this->routeService->recordStopCompleted($route2->stops->first(), [
            'exchange_delivered' => 2,
            'exchange_empty_received' => 2,
            'units_paid_cash' => 2,
        ], $this->repartidorUser);

        $order2->refresh();
        $this->assertEquals(Order::STATUS_DELIVERED, $order2->status);
        $this->assertEquals(5, $this->debtService->getPendingDebtBottles($this->customer)); // La deuda no aumentó
    }

    /**
     * 5. Promoción semanal 9+1: al completar 9 pagados en la semana, el décimo es gratis.
     */
    public function test_weekly_promotion_awards_tenth_free_refill(): void
    {
        // Cliente paga 9 botellones
        $this->debtService->recordPayment($this->customer, 9 * 15.00, $this->repartidorUser);

        $promo = $this->promotionService->checkOrGenerateWeeklyPromotion($this->customer);
        $this->assertNotNull($promo);
        $this->assertTrue($promo->isAvailable());

        // Siguiente pedido de 1 garrafón: se aplica gratis
        $order = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 1]);
        $this->orderService->confirmOrder($order, $this->adminUser);
        $route = $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now(), [$order->id]);

        $this->routeService->recordStopCompleted($route->stops->first(), [
            'exchange_delivered' => 1,
            'exchange_empty_received' => 1,
            'units_paid_cash' => 0,
        ], $this->repartidorUser);

        $promo->refresh();
        $this->assertEquals('redeemed', $promo->status);
        $this->assertEquals($order->id, $promo->redeemed_order_id);

        // El botellón gratuito no generó deuda monetaria
        $this->assertEquals(0, $this->debtService->getPendingDebtBottles($this->customer));
        $this->assertEquals(0.00, $this->debtService->getPendingDebtAmount($this->customer));
    }

    /**
     * 6. Aplicación de la promoción en la misma entrega (8 pagados + 2 entregados -> 1 pagado + 1 gratis).
     */
    public function test_promotion_applied_in_the_same_delivery(): void
    {
        // Cliente ya tenía 8 unidades pagadas esta semana
        $this->debtService->recordPayment($this->customer, 8 * 15.00, $this->repartidorUser);

        // Recibe 2 botellones: paga 1 al repartidor
        $order = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 2]);
        $this->orderService->confirmOrder($order, $this->adminUser);
        $route = $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now(), [$order->id]);

        $this->routeService->recordStopCompleted($route->stops->first(), [
            'exchange_delivered' => 2,
            'exchange_empty_received' => 2,
            'units_paid_cash' => 1, // Paga 1 -> completa 9 pagados y el 2.º queda gratuito
        ], $this->repartidorUser);

        $order->refresh();
        $this->assertEquals(Order::STATUS_DELIVERED, $order->status);

        // La deuda pendiente es 0 porque el segundo fue absuelto por la promoción
        $this->assertEquals(0, $this->debtService->getPendingDebtBottles($this->customer));
    }

    /**
     * 7. La promoción vence al terminar el domingo y no se acumula a la semana siguiente.
     */
    public function test_promotion_expires_on_sunday_and_does_not_accumulate(): void
    {
        // Simular pago de 9 botellones en la semana actual
        $this->debtService->recordPayment($this->customer, 9 * 15.00, $this->repartidorUser);
        $promo = $this->promotionService->checkOrGenerateWeeklyPromotion($this->customer);
        $this->assertNotNull($promo);

        // Simular consulta en la siguiente semana
        $nextWeek = now()->setTimezone('America/Merida')->addDays(8);
        $isStillAvailable = $promo->expires_at->greaterThanOrEqualTo($nextWeek);
        $this->assertFalse($isStillAvailable);
    }

    /**
     * 8. Rechaza pagos que dejen una unidad parcialmente pagada (no se admiten abonos fraccionados).
     */
    public function test_rejects_payments_that_leave_a_bottle_partially_paid(): void
    {
        // Cliente tiene 2 botellones fiados a $15.00 ($30.00 en total)
        $order = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 2]);
        $this->orderService->confirmOrder($order, $this->adminUser);
        $route = $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now(), [$order->id]);
        $this->routeService->recordStopCompleted($route->stops->first(), [
            'exchange_delivered' => 2,
            'exchange_empty_received' => 2,
            'units_paid_cash' => 0,
        ], $this->repartidorUser);

        $this->assertEquals(2, $this->debtService->getPendingDebtBottles($this->customer));

        // Intento de pagar $10.00 (abono parcial menor a $15.00) debe ser rechazado
        $initialPaymentsCount = \App\Models\OrderPayment::count();
        $caught = false;
        try {
            $this->debtService->recordPayment($this->customer, 10.00, $this->repartidorUser);
        } catch (OrderBusinessRuleException $e) {
            $caught = true;
            $this->assertStringContainsString('No se permiten abonos parciales', $e->getMessage());
        }
        $this->assertTrue($caught);

        // Intento de pagar $20.00 sin especificar botellones (cubre 1 de $15 pero deja $5.00 parcial)
        $caught2 = false;
        try {
            $this->debtService->recordPayment($this->customer, 20.00, $this->repartidorUser);
        } catch (OrderBusinessRuleException $e) {
            $caught2 = true;
            $this->assertStringContainsString('No se permiten abonos parciales', $e->getMessage());
        }
        $this->assertTrue($caught2);

        // No se guardó ningún pago rechazado como abono, saldo a favor ni anticipo
        $this->assertEquals($initialPaymentsCount, \App\Models\OrderPayment::count());
        $this->assertEquals(2, $this->debtService->getPendingDebtBottles($this->customer));
        $this->assertEquals(30.00, $this->debtService->getPendingDebtAmount($this->customer));
    }

    /**
     * 9. Permite liquidar botellones completos, distingue efectivo recibido y cambio, y registra solo importe liquidado.
     */
    public function test_settles_complete_bottles_and_distinguishes_cash_tendered_from_change_returned(): void
    {
        // Cliente tiene 2 botellones fiados a $15.00 ($30.00 en total)
        $order = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 2]);
        $this->orderService->confirmOrder($order, $this->adminUser);
        $route = $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now(), [$order->id]);
        $this->routeService->recordStopCompleted($route->stops->first(), [
            'exchange_delivered' => 2,
            'exchange_empty_received' => 2,
            'units_paid_cash' => 0,
        ], $this->repartidorUser);

        // Cliente liquida 1 botellón pagando con un billete de $50.00
        $payment = $this->debtService->recordPayment(
            $this->customer,
            50.00, // Efectivo entregado
            $this->repartidorUser,
            null,
            'Liquidación de 1 botellón con billete de $50',
            1 // Unidades exactas a liquidar
        );

        // Registra como pago únicamente el importe exacto liquidado ($15.00)
        $this->assertEquals(15.00, (float) $payment->amount);
        $this->assertEquals(50.00, (float) $payment->cash_tendered);
        $this->assertEquals(35.00, (float) $payment->change_returned);
        $this->assertEquals(1, $payment->bottles_covered_count);

        // La deuda pendiente se reduce exactamente en 1 botellón
        $this->assertEquals(1, $this->debtService->getPendingDebtBottles($this->customer));
        $this->assertEquals(15.00, $this->debtService->getPendingDebtAmount($this->customer));
    }

    /**
     * 10. Calcula el importe con los precios históricos de las unidades seleccionadas.
     */
    public function test_calculates_debt_settlement_with_historical_prices(): void
    {
        // Pedido 1 a $15.00
        $order1 = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 1]);
        $this->orderService->confirmOrder($order1, $this->adminUser);
        $route1 = $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now(), [$order1->id]);
        $this->routeService->recordStopCompleted($route1->stops->first(), [
            'exchange_delivered' => 1,
            'exchange_empty_received' => 1,
            'units_paid_cash' => 0,
        ], $this->repartidorUser);

        // Ahora la tarifa oficial sube a $18.00 en el catálogo
        BottlePresentation::where('capacity', 20.00)->update(['refill_price' => 18.00]);

        // Pedido 2 con la nueva tarifa de $18.00
        $order2 = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 1]);
        $this->orderService->confirmOrder($order2, $this->adminUser);
        $route2 = $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now(), [$order2->id]);
        $this->routeService->recordStopCompleted($route2->stops->first(), [
            'exchange_delivered' => 1,
            'exchange_empty_received' => 1,
            'units_paid_cash' => 0,
        ], $this->repartidorUser);

        // El cliente debe 2 botellones: 1 de $15.00 y 1 de $18.00 = Total $33.00
        $this->assertEquals(2, $this->debtService->getPendingDebtBottles($this->customer));
        $this->assertEquals(33.00, $this->debtService->getPendingDebtAmount($this->customer));

        // Liquidar el primer botellón histórico: debe costar exactamente $15.00 (precio original)
        $payment = $this->debtService->recordPayment(
            $this->customer,
            15.00,
            $this->repartidorUser,
            null,
            'Liquidación del primer botellón',
            1
        );

        $this->assertEquals(15.00, (float) $payment->amount);
        $this->assertEquals(1, $this->debtService->getPendingDebtBottles($this->customer));
        // Queda el segundo botellón histórico de $18.00
        $this->assertEquals(18.00, $this->debtService->getPendingDebtAmount($this->customer));
    }
}
