<?php

namespace Tests\Feature;

use App\Exceptions\OrderBusinessRuleException;
use App\Models\BottlePresentation;
use App\Models\Customer;
use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TruckInventory;
use App\Models\User;
use App\Services\DeliveryRouteService;
use App\Services\TruckInventoryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RepartidorDirectServiceRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected User $driver;
    protected User $plantaOperator;
    protected DeliveryRouteService $routeService;
    protected TruckInventoryService $truckInventoryService;

    protected function setUp(): void
    {
        parent::setUp();

        \Carbon\Carbon::setTestNow(\Carbon\Carbon::create(2026, 11, 8, 10, 0, 0, 'America/Merida'));
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->routeService = app(DeliveryRouteService::class);
        $this->truckInventoryService = app(TruckInventoryService::class);

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

        $this->driver = User::factory()->create([
            'name' => 'Chofer Reparto',
            'email' => 'driver@puritronic.com',
        ]);
        $this->driver->assignRole('Repartidor');

        $this->plantaOperator = User::factory()->create([
            'name' => 'Operador Planta',
            'email' => 'planta@puritronic.com',
        ]);
        $this->plantaOperator->assignRole('Operador Planta');
    }

    /**
     * Prueba 1: Recoger 2 fijos pasa directo a Planta sin aparecer en Por recoger de Mi Ruta/Reparto,
     * y funciona aunque el camión tenga 0 cambios llenos disponibles.
     */
    public function test_collecting_fixed_bottles_passes_directly_to_plant_and_not_in_reparto_por_recoger(): void
    {
        // Camión con CERO cambios llenos
        TruckInventory::create([
            'driver_user_id' => $this->driver->id,
            'exchange_full_available' => 0,
            'exchange_empty_collected' => 0,
        ]);

        // Repartidor registra recogida de 2 fijos al paso para un nuevo cliente
        $response = $this->actingAs($this->driver)
            ->post(route('repartidor.direct-service.store'), [
                'nickname' => 'Doña Fija',
                'service_type' => 'fijos',
                'fixed_quantity' => 2,
                'exchange_quantity' => 0,
                'exchange_empty_received' => 0,
            ]);

        $response->assertRedirect(route('repartidor.reparto'));
        $response->assertSessionHas('success');

        // Pedido creado
        $order = Order::where('recipient_name', 'Doña Fija')->first();
        $this->assertNotNull($order);
        $this->assertEquals(Order::STATUS_IN_PROCESS, $order->status);

        $fixedItem = $order->items->firstWhere('type', OrderItem::TYPE_FIXED);
        $this->assertNotNull($fixedItem);
        $this->assertEquals(2, $fixedItem->quantity);
        $this->assertEquals(2, $fixedItem->collected_quantity);
        $this->assertEquals(OrderItem::STATUS_TRANSPORTING_TO_PLANT, $fixedItem->status);

        // 1. NO aparece en Reparto -> Por recoger (porque ya fueron físicamente recogidos)
        $repartoResponse = $this->actingAs($this->driver)
            ->get(route('repartidor.reparto', ['tab' => 'por_recoger']));
        $repartoResponse->assertOk();
        $repartoResponse->assertInertia(fn (Assert $page) => $page
            ->component('Repartidor/Reparto/Index')
            ->where('tabCounts.por_recoger', 0)
        );

        // 2. SÍ aparece directamente en Planta -> Por recibir
        $plantResponse = $this->actingAs($this->plantaOperator)
            ->get(route('planta.fijos.index', ['tab' => 'por-recibir']));
        $plantResponse->assertOk();
        $plantResponse->assertInertia(fn (Assert $page) => $page
            ->component('Planta/Fijos/Index')
            ->has('fixedPending', 1)
            ->where('fixedPending.0.order_id', $order->id)
            ->where('fixedPending.0.expected_quantity', 2)
            ->where('fixedPending.0.customer_nickname', 'Doña Fija')
        );
    }

    /**
     * Prueba 2: Confirmar 2 cambios descuenta 2 llenos y suma 2 vacíos al camión con una sola captura.
     */
    public function test_confirming_exchanges_deducts_fulls_and_increments_empties_in_truck_atomically(): void
    {
        // Camión con 10 cambios llenos y 0 vacíos
        $truck = TruckInventory::create([
            'driver_user_id' => $this->driver->id,
            'exchange_full_available' => 10,
            'exchange_empty_collected' => 0,
        ]);

        // Repartidor confirma 2 cambios
        $response = $this->actingAs($this->driver)
            ->post(route('repartidor.direct-service.store'), [
                'nickname' => 'Tienda Los 2 Cambios',
                'service_type' => 'cambios',
                'exchange_quantity' => 2,
                'exchange_empty_received' => 2,
                'fixed_quantity' => 0,
                'units_paid_cash' => 2,
                'cash_tendered' => 30.00,
            ]);

        $response->assertRedirect(route('repartidor.reparto'));

        // El inventario del camión se actualizó atómicamente:
        // 10 llenos - 2 = 8 llenos disponibles
        // 0 vacíos + 2 = 2 vacíos recolectados
        $truck->refresh();
        $this->assertEquals(8, $truck->exchange_full_available);
        $this->assertEquals(2, $truck->exchange_empty_collected);

        // El pedido quedó cerrado como entregado
        $order = Order::where('recipient_name', 'Tienda Los 2 Cambios')->first();
        $this->assertNotNull($order);
        $this->assertEquals(Order::STATUS_DELIVERED, $order->status);
    }

    /**
     * Prueba 3: Rechaza cambios si el camión tiene existencias insuficientes.
     */
    public function test_exchanges_rejected_if_insufficient_truck_inventory(): void
    {
        // Camión con solo 1 cambio lleno
        TruckInventory::create([
            'driver_user_id' => $this->driver->id,
            'exchange_full_available' => 1,
            'exchange_empty_collected' => 0,
        ]);

        // Intento de hacer 2 cambios cuando solo hay 1 lleno
        $response = $this->actingAs($this->driver)
            ->post(route('repartidor.direct-service.store'), [
                'nickname' => 'Cliente Excedido',
                'service_type' => 'cambios',
                'exchange_quantity' => 2,
                'exchange_empty_received' => 2,
                'fixed_quantity' => 0,
            ]);

        $response->assertSessionHasErrors(['direct']);
    }
}
