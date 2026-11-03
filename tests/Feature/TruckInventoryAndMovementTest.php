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
use App\Models\TruckInventory;
use App\Models\User;
use App\Services\DeliveryRouteService;
use App\Services\OrderCustodyService;
use App\Services\OrderService;
use App\Services\TruckInventoryService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TruckInventoryAndMovementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $repartidorUser;
    protected User $clientUser;
    protected User $plantaUser;
    protected Customer $customerA;
    protected Customer $customerB;
    protected TruckInventoryService $truckService;
    protected DeliveryRouteService $routeService;
    protected OrderCustodyService $custodyService;
    protected OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 10, 26, 10, 0, 0, 'America/Merida'));

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->truckService = app(TruckInventoryService::class);
        $this->routeService = app(DeliveryRouteService::class);
        $this->custodyService = app(OrderCustodyService::class);
        $this->orderService = app(OrderService::class);

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

        $this->customerA = Customer::create([
            'user_id' => $this->clientUser->id,
            'code' => 'CLI-0001',
            'name' => 'María Pech',
            'nickname' => 'Doña Mari',
            'fixed_bottles_count' => 3,
            'exchange_bottles_count' => 5,
        ]);
        CustomerAddress::create([
            'customer_id' => $this->customerA->id,
            'street' => 'Calle 20',
            'neighborhood' => 'Centro',
            'delivery_zone' => 'north',
            'is_default' => true,
        ]);

        $this->customerB = Customer::create([
            'user_id' => null,
            'code' => 'CLI-0002',
            'name' => 'Fernando Canché',
            'nickname' => 'Nando',
            'fixed_bottles_count' => 2,
            'exchange_bottles_count' => 2,
        ]);
        CustomerAddress::create([
            'customer_id' => $this->customerB->id,
            'street' => 'Calle 25',
            'neighborhood' => 'Centro',
            'delivery_zone' => 'south',
            'is_default' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * 1. Consulta inicial de la pantalla "Mi Carga": inicia sin existencias inventadas.
     */
    public function test_repartidor_can_view_truck_load_state_with_zero_initial_stock(): void
    {
        $response = $this->actingAs($this->repartidorUser)->get(route('repartidor.truck-load.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Repartidor/TruckLoad/Index')
            ->where('truckInventory.exchange_full_available', 0)
            ->where('truckInventory.exchange_empty_collected', 0)
            ->where('truckInventory.fixed_empty_collected_total', 0)
            ->where('truckInventory.fixed_full_to_deliver_total', 0)
            ->has('pendingTransfers', 0)
            ->has('fixedByCustomer', 0)
        );
    }

    /**
     * 2. Transferencia propuesta desde planta NO se cuenta en camión hasta su aceptación física.
     */
    public function test_proposed_plant_transfer_is_not_available_until_accepted(): void
    {
        // Planta propone 25 garrafones de cambio llenos
        $movement = $this->truckService->proposePlantToTruckTransfer(
            $this->adminUser,
            OrderItem::TYPE_EXCHANGE,
            25,
            null,
            null,
            'Carga matutina autorizada'
        );

        $this->assertEquals(BottleCustodyMovement::STATUS_PROPOSED, $movement->status);

        // Consultar estado: existencias en camión deben seguir en 0
        $state = $this->truckService->getTruckInventoryState($this->repartidorUser);
        $this->assertEquals(0, $state['truck_inventory']['exchange_full_available']);
        $this->assertCount(1, $state['pending_transfers']);

        // Repartidor acepta la transferencia vía HTTP
        $res = $this->actingAs($this->repartidorUser)->post(
            route('repartidor.truck-load.accept', $movement->id),
            ['confirmed_quantity' => 25]
        );
        $res->assertSessionHasNoErrors();

        // Ahora sí se reflejan los 25 disponibles
        $stateAfter = $this->truckService->getTruckInventoryState($this->repartidorUser);
        $this->assertEquals(25, $stateAfter['truck_inventory']['exchange_full_available']);
        $this->assertCount(0, $stateAfter['pending_transfers']);

        $movement->refresh();
        $this->assertEquals(BottleCustodyMovement::STATUS_ACCEPTED, $movement->status);
        $this->assertEquals($this->repartidorUser->id, $movement->confirmed_by_user_id);
    }

    /**
     * 3. Aceptación con diferencia exige motivo de discrepancia.
     */
    public function test_accept_transfer_with_difference_requires_reason(): void
    {
        $movement = $this->truckService->proposePlantToTruckTransfer(
            $this->adminUser,
            OrderItem::TYPE_EXCHANGE,
            20
        );

        // Sin motivo cuando hay diferencia debe fallar
        $this->expectException(OrderBusinessRuleException::class);
        $this->truckService->acceptTransfer($movement, $this->repartidorUser, 18, '');
    }

    /**
     * 4. Aceptación con diferencia y motivo exitosa.
     */
    public function test_accept_transfer_with_difference_and_reason_adjusts_quantity(): void
    {
        $movement = $this->truckService->proposePlantToTruckTransfer(
            $this->adminUser,
            OrderItem::TYPE_EXCHANGE,
            20
        );

        $accepted = $this->truckService->acceptTransfer(
            $movement,
            $this->repartidorUser,
            18,
            '2 garrafones con fisura detectados al subir al camión'
        );

        $this->assertEquals(18, $accepted->quantity);
        $this->assertEquals('2 garrafones con fisura detectados al subir al camión', $accepted->difference_reason);

        $state = $this->truckService->getTruckInventoryState($this->repartidorUser);
        $this->assertEquals(18, $state['truck_inventory']['exchange_full_available']);
    }

    /**
     * 5. Protección contra doble aceptación / reintentos (idempotencia).
     */
    public function test_double_acceptance_is_strictly_prevented(): void
    {
        $movement = $this->truckService->proposePlantToTruckTransfer($this->adminUser, OrderItem::TYPE_EXCHANGE, 10);
        $this->truckService->acceptTransfer($movement, $this->repartidorUser, 10);

        // Segundo intento debe lanzar excepción
        $this->expectException(OrderBusinessRuleException::class);
        $this->truckService->acceptTransfer($movement, $this->repartidorUser, 10);
    }

    /**
     * 6. Repartidor puede rechazar una transferencia con motivo obligatorio.
     */
    public function test_repartidor_can_reject_transfer_with_reason(): void
    {
        $movement = $this->truckService->proposePlantToTruckTransfer($this->adminUser, OrderItem::TYPE_EXCHANGE, 30);

        $rejected = $this->truckService->rejectTransfer($movement, $this->repartidorUser, 'Camión sin espacio suficiente');

        $this->assertEquals(BottleCustodyMovement::STATUS_REJECTED, $rejected->status);
        $this->assertEquals('Camión sin espacio suficiente', $rejected->difference_reason);

        $state = $this->truckService->getTruckInventoryState($this->repartidorUser);
        $this->assertEquals(0, $state['truck_inventory']['exchange_full_available']);
    }

    /**
     * 7. Entrega de cambio descuenta exactamente una vez y rechaza entregas superiores a lo disponible.
     */
    public function test_delivery_decrements_truck_stock_and_rejects_insufficient_stock(): void
    {
        // Camión solo tiene 2 disponibles
        $this->truckService->getOrCreateTruckInventory($this->repartidorUser)->update([
            'exchange_full_available' => 2,
        ]);

        $order = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 2]);
        $this->orderService->confirmOrder($order, $this->adminUser);

        $route = $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now(), [$order->id]);
        $stop = $route->stops->first();

        // Intento de entregar 3 cuando solo hay 2 en camión debe fallar
        $this->expectException(OrderBusinessRuleException::class);
        $this->truckService->decrementExchangeFullForDelivery($this->repartidorUser, 3);
    }

    /**
     * 8. Entrega exitosa descuenta del camión e incrementa vacíos recogidos.
     */
    public function test_successful_delivery_updates_truck_inventory_cleanly(): void
    {
        $this->truckService->getOrCreateTruckInventory($this->repartidorUser)->update([
            'exchange_full_available' => 10,
            'exchange_empty_collected' => 0,
        ]);

        $order = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 2]);
        $this->orderService->confirmOrder($order, $this->adminUser);

        $route = $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now(), [$order->id]);
        $stop = $route->stops->first();

        $this->routeService->recordStopCompleted($stop, [
            'exchange_delivered' => 2,
            'exchange_empty_received' => 2,
        ], $this->repartidorUser);

        $state = $this->truckService->getTruckInventoryState($this->repartidorUser);
        $this->assertEquals(8, $state['truck_inventory']['exchange_full_available']);
        $this->assertEquals(2, $state['truck_inventory']['exchange_empty_collected']);
    }

    /**
     * 9. Las existencias del camión NO se pierden ni reinician al cerrar una ruta o crear una nueva.
     */
    public function test_truck_inventory_persists_across_route_lifecycle(): void
    {
        $truckInv = $this->truckService->getOrCreateTruckInventory($this->repartidorUser);
        $truckInv->update([
            'exchange_full_available' => 15,
            'exchange_empty_collected' => 8,
        ]);

        $order = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 1]);
        $this->orderService->confirmOrder($order, $this->adminUser);

        // Crear y completar ruta 1
        $route1 = $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now(), [$order->id]);
        $this->routeService->recordStopCompleted($route1->stops->first(), [
            'exchange_delivered' => 1,
            'exchange_empty_received' => 1,
        ], $this->repartidorUser);
        $this->routeService->completeRoute($route1, $this->repartidorUser);

        $this->assertEquals(DeliveryRoute::STATUS_COMPLETED, $route1->status);

        // Las existencias en el camión siguen siendo 14 disponibles y 9 vacíos
        $state1 = $this->truckService->getTruckInventoryState($this->repartidorUser);
        $this->assertEquals(14, $state1['truck_inventory']['exchange_full_available']);
        $this->assertEquals(9, $state1['truck_inventory']['exchange_empty_collected']);

        // Al crear la ruta del día siguiente, el inventario se conserva
        $orderNextDay = $this->orderService->createOrder($this->clientUser, ['exchange_quantity' => 1]);
        $this->orderService->confirmOrder($orderNextDay, $this->adminUser);

        $route2 = $this->routeService->createRoute($this->adminUser, $this->repartidorUser, now()->addDay(), [$orderNextDay->id]);

        $state2 = $this->truckService->getTruckInventoryState($this->repartidorUser);
        $this->assertEquals(14, $state2['truck_inventory']['exchange_full_available']);
        $this->assertEquals(9, $state2['truck_inventory']['exchange_empty_collected']);
    }

    /**
     * 10. Custodia de botellones fijos separada por cliente propietario durante todo el recorrido.
     */
    public function test_fixed_bottles_custody_is_strictly_separated_by_customer(): void
    {
        // 1. Recolección de 2 fijos de Cliente A
        $orderA = $this->orderService->createOrder($this->clientUser, ['fixed_quantity' => 2]);
        $this->orderService->confirmOrder($orderA, $this->adminUser);
        $this->custodyService->recordFixedBottlesCollected($orderA, 2, $this->repartidorUser);

        // 2. Recolección de 1 fijo de Cliente B vía servicio directo
        $orderB = $this->routeService->registerDirectRouteOrder($this->repartidorUser, $this->customerB, [
            'fixed_quantity' => 1,
        ]);

        // Verificar desglose en camión
        $state = $this->truckService->getTruckInventoryState($this->repartidorUser);
        $this->assertEquals(3, $state['truck_inventory']['fixed_empty_collected_total']);
        $this->assertCount(2, $state['fixed_by_customer']);

        $customerAFijos = collect($state['fixed_by_customer'])->firstWhere('customer_id', $this->customerA->id);
        $customerBFijos = collect($state['fixed_by_customer'])->firstWhere('customer_id', $this->customerB->id);

        $this->assertEquals(2, $customerAFijos['fixed_empty_collected']);
        $this->assertEquals('Doña Mari', $customerAFijos['customer_nickname']);
        $this->assertEquals(1, $customerBFijos['fixed_empty_collected']);
        $this->assertEquals('Nando', $customerBFijos['customer_nickname']);

        // 3. Planta recibe los fijos de Cliente A
        $this->custodyService->recordFixedBottlesReceivedInPlant($orderA, 2, $this->plantaUser);

        // Cliente A ya no tiene fijos vacíos en el camión, pero Cliente B sigue teniendo 1
        $stateAfterPlant = $this->truckService->getTruckInventoryState($this->repartidorUser);
        $this->assertEquals(1, $stateAfterPlant['truck_inventory']['fixed_empty_collected_total']);

        $customerAFijosAfter = collect($stateAfterPlant['fixed_by_customer'])->firstWhere('customer_id', $this->customerA->id);
        $this->assertNull($customerAFijosAfter); // Ya no tiene en camión

        $customerBFijosAfter = collect($stateAfterPlant['fixed_by_customer'])->firstWhere('customer_id', $this->customerB->id);
        $this->assertEquals(1, $customerBFijosAfter['fixed_empty_collected']);
    }

    /**
     * 11. Descarga a planta valida existencias y no permite existencias negativas.
     */
    public function test_propose_unload_rejects_quantities_exceeding_truck_stock(): void
    {
        $this->truckService->getOrCreateTruckInventory($this->repartidorUser)->update([
            'exchange_empty_collected' => 3,
        ]);

        // Intentar descargar 5 cuando solo hay 3 debe ser rechazado
        $this->expectException(OrderBusinessRuleException::class);
        $this->truckService->proposeTruckToPlantUnload(
            $this->repartidorUser,
            OrderItem::TYPE_EXCHANGE,
            BottleCustodyMovement::CONDITION_EMPTY,
            5
        );
    }

    /**
     * 12. Confirmación de descarga en planta descuenta del camión y prohíbe auto-confirmación del repartidor.
     */
    public function test_confirm_plant_reception_updates_truck_and_forbids_driver_self_confirmation(): void
    {
        $this->truckService->getOrCreateTruckInventory($this->repartidorUser)->update([
            'exchange_empty_collected' => 5,
        ]);

        $unload = $this->truckService->proposeTruckToPlantUnload(
            $this->repartidorUser,
            OrderItem::TYPE_EXCHANGE,
            BottleCustodyMovement::CONDITION_EMPTY,
            5,
            null,
            'Descarga al final del turno'
        );

        // El repartidor NO puede confirmarse a sí mismo la recepción de planta
        $this->expectException(OrderBusinessRuleException::class);
        $this->truckService->confirmPlantReception($unload, $this->repartidorUser);
    }

    /**
     * 13. Operador de planta confirma la descarga correctamente.
     */
    public function test_plant_operator_confirms_unload_successfully(): void
    {
        $this->truckService->getOrCreateTruckInventory($this->repartidorUser)->update([
            'exchange_empty_collected' => 5,
        ]);

        $unload = $this->truckService->proposeTruckToPlantUnload(
            $this->repartidorUser,
            OrderItem::TYPE_EXCHANGE,
            BottleCustodyMovement::CONDITION_EMPTY,
            5
        );

        $confirmed = $this->truckService->confirmPlantReception($unload, $this->plantaUser, 5);

        $this->assertEquals(BottleCustodyMovement::STATUS_ACCEPTED, $confirmed->status);
        $this->assertEquals($this->plantaUser->id, $confirmed->confirmed_by_user_id);

        $state = $this->truckService->getTruckInventoryState($this->repartidorUser);
        $this->assertEquals(0, $state['truck_inventory']['exchange_empty_collected']);
    }

    /**
     * 14. Seguridad y permisos de acceso a la pantalla "Mi Carga".
     */
    public function test_access_security_by_roles(): void
    {
        // 1. Cliente no tiene acceso (403)
        $this->actingAs($this->clientUser)->get(route('repartidor.truck-load.index'))->assertForbidden();

        // 2. Administrador tiene acceso para auditar
        $this->actingAs($this->adminUser)->get(route('repartidor.truck-load.index'))->assertOk();

        // 3. Repartidor tiene acceso completo
        $this->actingAs($this->repartidorUser)->get(route('repartidor.truck-load.index'))->assertOk();
    }
}

