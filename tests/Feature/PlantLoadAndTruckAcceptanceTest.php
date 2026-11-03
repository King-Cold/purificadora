<?php

namespace Tests\Feature;

use App\Models\BottleCustodyMovement;
use App\Models\BottleIncident;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlantInventory;
use App\Models\TruckInventory;
use App\Models\User;
use App\Services\PlantInventoryService;
use App\Services\TruckInventoryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantLoadAndTruckAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $operatorUser;
    protected User $adminUser;
    protected User $driverUser;
    protected Customer $customer1;
    protected Customer $customer2;
    protected PlantInventoryService $plantService;
    protected TruckInventoryService $truckService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->plantService = app(PlantInventoryService::class);
        $this->truckService = app(TruckInventoryService::class);

        // Operador Planta
        $this->operatorUser = User::factory()->create([
            'name' => 'Operador Puritronic',
            'email' => 'operador@puritronic.com',
        ]);
        $this->operatorUser->assignRole('Operador Planta');

        // Administrador
        $this->adminUser = User::factory()->create([
            'name' => 'Administrador Planta',
            'email' => 'admin@puritronic.com',
        ]);
        $this->adminUser->assignRole('Administrador');

        // Repartidor
        $this->driverUser = User::factory()->create([
            'name' => 'Repartidor Ruta',
            'email' => 'repartidor@puritronic.com',
        ]);
        $this->driverUser->assignRole('Repartidor');

        // Cliente 1
        $clientUser1 = User::factory()->create([
            'name' => 'Cliente Don Fernando',
            'email' => 'fijo1@cliente.com',
        ]);
        $clientUser1->assignRole('Cliente');

        $this->customer1 = Customer::create([
            'user_id' => $clientUser1->id,
            'code' => 'CLI-1001',
            'name' => 'Don Fernando',
            'nickname' => 'Don Fer',
            'fixed_bottles_count' => 5,
            'exchange_bottles_count' => 0,
        ]);

        CustomerAddress::create([
            'customer_id' => $this->customer1->id,
            'street' => 'Calle 50',
            'exterior_number' => '123',
            'neighborhood' => 'Centro',
            'city' => 'Hocabá',
            'postal_code' => '87000',
            'zone' => 'Norte',
            'is_active' => true,
        ]);

        // Cliente 2
        $clientUser2 = User::factory()->create([
            'name' => 'Doña María',
            'email' => 'fijo2@cliente.com',
        ]);
        $clientUser2->assignRole('Cliente');

        $this->customer2 = Customer::create([
            'user_id' => $clientUser2->id,
            'code' => 'CLI-1002',
            'name' => 'Doña María',
            'nickname' => 'Doña Mari',
            'fixed_bottles_count' => 3,
            'exchange_bottles_count' => 0,
        ]);

        CustomerAddress::create([
            'customer_id' => $this->customer2->id,
            'street' => 'Calle 22',
            'exterior_number' => '456',
            'neighborhood' => 'Centro',
            'city' => 'Hocabá',
            'postal_code' => '87000',
            'zone' => 'Sur',
            'is_active' => true,
        ]);

        // Conteo inicial base para Planta: 40 vacíos y 25 llenos
        $this->plantService->recordInitialCount($this->operatorUser, 40, 25, 'Inventario base para pruebas de carga');
    }

    /**
     * Helper para crear pedido y dejar botellones fijos preparados en planta.
     */
    protected function createPreparedFixedItem(Customer $customer, int $totalQty, int $preparedQty): OrderItem
    {
        $order = Order::create([
            'customer_id' => $customer->id,
            'folio' => 'ORD-FIJO-' . uniqid(),
            'status' => Order::STATUS_IN_PROCESS,
            'recipient_name' => $customer->name,
            'delivery_street' => 'Calle Principal #100',
            'address_snapshot' => ['street' => 'Calle Principal'],
        ]);

        return OrderItem::create([
            'order_id' => $order->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => $totalQty,
            'collected_quantity' => $totalQty,
            'received_at_plant_quantity' => $totalQty,
            'washing_quantity' => $preparedQty,
            'prepared_quantity' => $preparedQty,
            'reserved_quantity' => 0,
            'loaded_quantity' => 0,
            'damaged_quantity' => 0,
            'status' => $preparedQty >= $totalQty ? OrderItem::STATUS_READY_FOR_DELIVERY : OrderItem::STATUS_WASHING,
            'unit_price' => 20.0,
        ]);
    }

    public function test_access_permissions_for_plant_loads_screen(): void
    {
        // Operador Planta accede con permisos activos
        $response = $this->actingAs($this->operatorUser)->get(route('planta.loads.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Planta/Loads/Index')
            ->where('isOperator', true)
            ->where('isAdmin', false)
            ->has('balances')
            ->has('fixed_ready_to_load')
            ->has('pending_proposals')
            ->has('transfer_history')
        );

        // Administrador accede en modo supervisión
        $responseAdmin = $this->actingAs($this->adminUser)->get(route('planta.loads.index'));
        $responseAdmin->assertStatus(200);
        $responseAdmin->assertInertia(fn ($page) => $page->component('Planta/Loads/Index')
            ->where('isOperator', false)
            ->where('isAdmin', true)
        );

        // Repartidor no tiene acceso a la pantalla de Planta
        $responseDriver = $this->actingAs($this->driverUser)->get(route('planta.loads.index'));
        $responseDriver->assertStatus(403);
    }

    public function test_admin_cannot_propose_or_withdraw_loads(): void
    {
        // Administrador intenta proponer carga
        $response = $this->actingAs($this->adminUser)->post(route('planta.loads.store'), [
            'bottle_type' => 'cambio',
            'quantity' => 5,
        ]);
        $response->assertStatus(403);

        $inv = PlantInventory::first();
        $this->assertEquals(0, $inv->exchange_full_reserved);
    }

    public function test_propose_exchange_load_reserves_units_and_does_not_affect_truck_early(): void
    {
        $inv = PlantInventory::first();
        $initialAvailable = $inv->exchange_full_available; // 25

        $response = $this->actingAs($this->operatorUser)->post(route('planta.loads.store'), [
            'bottle_type' => 'cambio',
            'quantity' => 10,
            'notes' => 'Carga matutina',
        ]);

        $response->assertRedirect();
        $inv->refresh();

        $this->assertEquals($initialAvailable - 10, $inv->exchange_full_available);
        $this->assertEquals(10, $inv->exchange_full_reserved);

        // Comprobar movimiento propuesto
        $movement = BottleCustodyMovement::where('status', BottleCustodyMovement::STATUS_PROPOSED)->first();
        $this->assertNotNull($movement);
        $this->assertEquals(10, $movement->quantity);
        $this->assertEquals(OrderItem::TYPE_EXCHANGE, $movement->bottle_type);
        $this->assertEquals('plant', $movement->origin);
        $this->assertEquals('truck', $movement->destination);

        // Comprobar que el camión aún tiene 0 unidades (no se suman anticipadamente)
        $truckInv = $this->truckService->getOrCreateTruckInventory($this->driverUser);
        $this->assertEquals(0, $truckInv->exchange_full_available);

        // Impedir proponer más de los disponibles
        $responseOverflow = $this->actingAs($this->operatorUser)->post(route('planta.loads.store'), [
            'bottle_type' => 'cambio',
            'quantity' => 20, // Quedan 15
        ]);
        $responseOverflow->assertSessionHasErrors('load');
    }

    public function test_propose_fixed_load_reserves_prepared_units_by_customer_and_order(): void
    {
        $item = $this->createPreparedFixedItem($this->customer1, 5, 4); // 4 preparados listos

        $response = $this->actingAs($this->operatorUser)->post(route('planta.loads.store'), [
            'bottle_type' => 'fijo',
            'order_item_id' => $item->id,
            'quantity' => 3,
            'notes' => '3 fijos listos de Don Fernando',
        ]);

        $response->assertRedirect();
        $item->refresh();

        $this->assertEquals(3, $item->reserved_quantity);
        $this->assertEquals(1, $item->getReadyToLoadQuantity()); // 4 preparados - 3 reservados = 1 libre

        // Movimiento propuesto conserva cliente y pedido
        $movement = BottleCustodyMovement::where('order_item_id', $item->id)->first();
        $this->assertNotNull($movement);
        $this->assertEquals($this->customer1->id, $movement->customer_id);
        $this->assertEquals($item->order_id, $movement->order_id);
        $this->assertEquals(3, $movement->quantity);
        $this->assertEquals(BottleCustodyMovement::STATUS_PROPOSED, $movement->status);

        // No se puede proponer más de lo libre
        $responseOverflow = $this->actingAs($this->operatorUser)->post(route('planta.loads.store'), [
            'bottle_type' => 'fijo',
            'order_item_id' => $item->id,
            'quantity' => 2, // Solo queda 1 libre
        ]);
        $responseOverflow->assertSessionHasErrors('load');
    }

    public function test_withdraw_proposal_before_acceptance_safely_releases_reservations(): void
    {
        // 1. Proponer cambio
        $this->actingAs($this->operatorUser)->post(route('planta.loads.store'), [
            'bottle_type' => 'cambio',
            'quantity' => 8,
        ]);
        $exchangeMovement = BottleCustodyMovement::where('status', BottleCustodyMovement::STATUS_PROPOSED)->first();

        // 2. Proponer fijo
        $fixedItem = $this->createPreparedFixedItem($this->customer2, 3, 3);
        $this->actingAs($this->operatorUser)->post(route('planta.loads.store'), [
            'bottle_type' => 'fijo',
            'order_item_id' => $fixedItem->id,
            'quantity' => 2,
        ]);
        $fixedMovement = BottleCustodyMovement::where('order_item_id', $fixedItem->id)->first();

        // Verificar reservas activas
        $inv = PlantInventory::first();
        $this->assertEquals(8, $inv->exchange_full_reserved);
        $this->assertEquals(2, $fixedItem->fresh()->reserved_quantity);

        // Retirar propuesta de cambios
        $responseWithdrawExchange = $this->actingAs($this->operatorUser)->post(route('planta.loads.withdraw', $exchangeMovement->id));
        $responseWithdrawExchange->assertRedirect();

        $inv->refresh();
        $this->assertEquals(0, $inv->exchange_full_reserved);
        $this->assertEquals(25, $inv->exchange_full_available);
        $this->assertEquals(BottleCustodyMovement::STATUS_CANCELLED, $exchangeMovement->fresh()->status);

        // Retirar propuesta de fijos
        $responseWithdrawFixed = $this->actingAs($this->operatorUser)->post(route('planta.loads.withdraw', $fixedMovement->id));
        $responseWithdrawFixed->assertRedirect();

        $fixedItem->refresh();
        $this->assertEquals(0, $fixedItem->reserved_quantity);
        $this->assertEquals(3, $fixedItem->getReadyToLoadQuantity());
        $this->assertEquals(BottleCustodyMovement::STATUS_CANCELLED, $fixedMovement->fresh()->status);
    }

    public function test_driver_full_acceptance_transfers_stock_to_truck_and_enables_delivery(): void
    {
        // 1. Carga de cambios
        $this->actingAs($this->operatorUser)->post(route('planta.loads.store'), [
            'bottle_type' => 'cambio',
            'quantity' => 12,
        ]);
        $exchangeMovement = BottleCustodyMovement::where('status', BottleCustodyMovement::STATUS_PROPOSED)->first();

        // 2. Carga de fijos
        $fixedItem = $this->createPreparedFixedItem($this->customer1, 4, 4);
        $this->actingAs($this->operatorUser)->post(route('planta.loads.store'), [
            'bottle_type' => 'fijo',
            'order_item_id' => $fixedItem->id,
            'quantity' => 4,
        ]);
        $fixedMovement = BottleCustodyMovement::where('order_item_id', $fixedItem->id)->first();

        // Operador NO puede aceptar en nombre del repartidor
        $responseOpAccept = $this->actingAs($this->operatorUser)->post(route('repartidor.truck-load.accept', $exchangeMovement->id));
        $responseOpAccept->assertStatus(403);

        // Repartidor acepta la carga de cambios completa
        $responseDriverExchange = $this->actingAs($this->driverUser)->post(route('repartidor.truck-load.accept', $exchangeMovement->id), [
            'confirmed_quantity' => 12,
        ]);
        $responseDriverExchange->assertRedirect();

        // Repartidor acepta la carga de fijos completa
        $responseDriverFixed = $this->actingAs($this->driverUser)->post(route('repartidor.truck-load.accept', $fixedMovement->id), [
            'confirmed_quantity' => 4,
        ]);
        $responseDriverFixed->assertRedirect();

        // Comprobar existencias del camión
        $truckInv = $this->truckService->getOrCreateTruckInventory($this->driverUser);
        $this->assertEquals(12, $truckInv->exchange_full_available);
        $this->assertEquals(12, $truckInv->exchange_full_initial);

        // Comprobar estado del fijo
        $fixedItem->refresh();
        $this->assertEquals(4, $fixedItem->loaded_quantity);
        $this->assertEquals(0, $fixedItem->reserved_quantity);
        $this->assertEquals(OrderItem::STATUS_OUT_FOR_DELIVERY, $fixedItem->status);

        // Verificar que en 'Mi Carga' del repartidor aparece el desglose por cliente
        $truckState = $this->truckService->getTruckInventoryState($this->driverUser);
        $this->assertEquals(4, $truckState['truck_inventory']['fixed_full_to_deliver_total']);
        $this->assertEquals('Don Fernando', $truckState['fixed_by_customer'][0]['customer_name']);
        $this->assertEquals(4, $truckState['fixed_by_customer'][0]['fixed_full_to_deliver']);
    }

    public function test_partial_acceptance_with_structured_breakdown(): void
    {
        // Proponer 10 garrafones de cambio
        $this->actingAs($this->operatorUser)->post(route('planta.loads.store'), [
            'bottle_type' => 'cambio',
            'quantity' => 10,
        ]);
        $movement = BottleCustodyMovement::where('status', BottleCustodyMovement::STATUS_PROPOSED)->first();

        // Repartidor confirma 6 recibidos:
        // 2 retenidos en planta aptos (se liberan a disponibles)
        // 1 dañado al cargar (crea incidencia y no entra a disponibles ni camión)
        // 1 sin localizar (discrepancia)
        $response = $this->actingAs($this->driverUser)->post(route('repartidor.truck-load.accept', $movement->id), [
            'confirmed_quantity' => 6,
            'difference_reason' => 'Faltaron 4 garrafones por espacio y uno roto en tarima',
            'retained_in_plant_quantity' => 2,
            'damaged_quantity' => 1,
            'damage_type' => 'golpe_fondo',
            'damage_notes' => 'Golpe en fondo al subir al camión',
            'unlocated_quantity' => 1,
        ]);

        $response->assertRedirect();

        // Camión recibe exactamente 6
        $truckInv = $this->truckService->getOrCreateTruckInventory($this->driverUser);
        $this->assertEquals(6, $truckInv->exchange_full_available);

        // Planta: los 2 retenidos se reintegraron a disponibles
        $inv = PlantInventory::first();
        $this->assertEquals(0, $inv->exchange_full_reserved);
        $this->assertEquals(17, $inv->exchange_full_available); // 25 inicial - 10 propuesto + 2 liberados = 17

        // Incidencia registrada para el dañado
        $this->assertDatabaseHas('bottle_incidents', [
            'bottle_type' => OrderItem::TYPE_EXCHANGE,
            'stage' => 'carga_camion',
            'damage_type' => 'golpe_fondo',
            'quantity' => 1,
            'reported_by_user_id' => $this->driverUser->id,
        ]);

        // Movimiento de custodia actualizado con discrepancia
        $movement->refresh();
        $this->assertEquals(6, $movement->quantity);
        $this->assertEquals('accepted_with_discrepancy', $movement->status);
    }

    public function test_driver_rejection_safely_releases_reservations(): void
    {
        $this->actingAs($this->operatorUser)->post(route('planta.loads.store'), [
            'bottle_type' => 'cambio',
            'quantity' => 5,
        ]);
        $movement = BottleCustodyMovement::where('status', BottleCustodyMovement::STATUS_PROPOSED)->first();

        $response = $this->actingAs($this->driverUser)->post(route('repartidor.truck-load.reject', $movement->id), [
            'reason' => 'Camión lleno por pedido especial, no cabe más carga',
            'retained_in_plant_quantity' => 5,
        ]);

        $response->assertRedirect();

        // Existencias de camión permanecen en 0
        $truckInv = $this->truckService->getOrCreateTruckInventory($this->driverUser);
        $this->assertEquals(0, $truckInv->exchange_full_available);

        // Reserva liberada a disponibles en planta
        $inv = PlantInventory::first();
        $this->assertEquals(0, $inv->exchange_full_reserved);
        $this->assertEquals(25, $inv->exchange_full_available);

        $movement->refresh();
        $this->assertEquals(BottleCustodyMovement::STATUS_REJECTED, $movement->status);
    }

    public function test_complete_fixed_lifecycle_accounting_with_partial_stages(): void
    {
        // Caso integral: Pedido de 6 botellones fijos de Don Fernando
        $order = Order::create([
            'customer_id' => $this->customer1->id,
            'folio' => 'ORD-INTEGRAL-01',
            'status' => Order::STATUS_IN_PROCESS,
            'recipient_name' => $this->customer1->name,
            'delivery_street' => 'Calle 50 #123',
            'address_snapshot' => ['street' => 'Calle 50'],
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => 6,
            'collected_quantity' => 6,
            'received_at_plant_quantity' => 6, // 6 aptos recibidos en planta
            'washing_quantity' => 0,
            'prepared_quantity' => 0,
            'reserved_quantity' => 0,
            'loaded_quantity' => 0,
            'damaged_quantity' => 0,
            'status' => OrderItem::STATUS_TRANSPORTING_TO_PLANT,
            'unit_price' => 20.0,
        ]);

        // 1. Ingresar 4 a lavado (quedan 2 pendientes de lavar)
        $this->plantService->startFixedWashing($this->operatorUser, $item, 4);
        $item->refresh();
        $this->assertEquals(2, $item->getPendingToWashQuantity());
        $this->assertEquals(4, $item->getInWashingQuantity());

        // 2. Durante el lavado se detecta 1 dañado
        $this->plantService->reportFixedWashingDamage($this->operatorUser, $item, 1, 'fuga_cuerpo', 'Fisura en túnel');
        $item->refresh();
        $this->assertEquals(3, $item->getInWashingQuantity());
        $this->assertEquals(1, $item->damaged_quantity);
        $this->assertEquals(6, $item->received_at_plant_quantity); // Acumulado histórico intacto

        // 3. Finalizar 2 preparados (queda 1 en lavado)
        $this->plantService->finishFixedPreparation($this->operatorUser, $item, 2);
        $item->refresh();
        $this->assertEquals(1, $item->getInWashingQuantity());
        $this->assertEquals(2, $item->getReadyToLoadQuantity());

        // 4. Proponer carga de 2 fijos preparados
        $loadMovement = $this->truckService->proposePlantToTruckTransfer(
            $this->operatorUser,
            OrderItem::TYPE_FIXED,
            2,
            $this->customer1->id,
            $order->id,
            'Carga de prueba',
            $item->id
        );
        $item->refresh();
        $this->assertEquals(2, $item->reserved_quantity);
        $this->assertEquals(0, $item->getReadyToLoadQuantity()); // 0 libres para proponer

        // 5. Repartidor acepta los 2 en su camión
        $this->truckService->acceptTransfer($loadMovement, $this->driverUser, 2);
        $item->refresh();
        $this->assertEquals(2, $item->loaded_quantity);
        $this->assertEquals(0, $item->reserved_quantity);
        $this->assertEquals(OrderItem::STATUS_OUT_FOR_DELIVERY, $item->status);

        // DEMOSTRACIÓN DE CONSERVACIÓN:
        // Total original aptos recibidos: 6
        // - Pendientes de lavar: 2
        // - En lavado actualmente: 1
        // - Dañados en lavado: 1
        // - Preparados libres en planta: 0
        // - Cargados al camión: 2
        // Suma: 2 + 1 + 1 + 0 + 2 = 6. ¡Todas las unidades contabilizadas exactamente una sola vez!
        $this->assertEquals(2, $item->getPendingToWashQuantity());
        $this->assertEquals(1, $item->getInWashingQuantity());
        $this->assertEquals(1, $item->damaged_quantity);
        $this->assertEquals(0, $item->getReadyToLoadQuantity());
        $this->assertEquals(2, $item->loaded_quantity);
        $this->assertEquals(
            6,
            $item->getPendingToWashQuantity() +
            $item->getInWashingQuantity() +
            $item->damaged_quantity +
            $item->getReadyToLoadQuantity() +
            $item->loaded_quantity
        );
    }
}
