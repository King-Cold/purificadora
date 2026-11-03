<?php

namespace Tests\Feature;

use App\Exceptions\OrderBusinessRuleException;
use App\Models\BottleCustodyMovement;
use App\Models\BottleIncident;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlantInventory;
use App\Models\TruckInventory;
use App\Models\User;
use App\Services\OrderCustodyService;
use App\Services\PlantInventoryService;
use App\Services\TruckInventoryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantInventoryAndIncidentTest extends TestCase
{
    use RefreshDatabase;

    protected User $plantaUser;
    protected User $adminUser;
    protected User $repartidorUser;
    protected User $clientUser;
    protected Customer $customer;
    protected PlantInventoryService $plantService;
    protected TruckInventoryService $truckService;
    protected OrderCustodyService $custodyService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->plantService = app(PlantInventoryService::class);
        $this->truckService = app(TruckInventoryService::class);
        $this->custodyService = app(OrderCustodyService::class);

        // Operador Planta
        $this->plantaUser = User::factory()->create([
            'name' => 'Llenador Puritronic',
            'email' => 'planta@puritronic.com',
        ]);
        $this->plantaUser->assignRole('Operador Planta');

        // Administrador
        $this->adminUser = User::factory()->create([
            'name' => 'Administrador General',
            'email' => 'admin@puritronic.com',
        ]);
        $this->adminUser->assignRole('Administrador');

        // Repartidor
        $this->repartidorUser = User::factory()->create([
            'name' => 'Repartidor Ruta',
            'email' => 'repartidor@puritronic.com',
        ]);
        $this->repartidorUser->assignRole('Repartidor');

        // Cliente
        $this->clientUser = User::factory()->create([
            'name' => 'Cliente Doña Sofía',
            'email' => 'sofia@gmail.com',
        ]);
        $this->clientUser->assignRole('Cliente');

        $this->customer = Customer::create([
            'user_id' => $this->clientUser->id,
            'code' => 'CLI-0001',
            'name' => 'Doña Sofía',
            'nickname' => 'Doña Sofía',
            'fixed_bottles_count' => 4,
            'exchange_bottles_count' => 2,
        ]);

        CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'street' => 'Av Hidalgo',
            'exterior_number' => '100',
            'neighborhood' => 'Centro',
            'city' => 'Ciudad',
            'postal_code' => '87000',
            'zone' => 'Norte',
            'is_active' => true,
        ]);
    }

    /**
     * 1. Conteo inicial físico pendiente por defecto y registro exitoso con cantidades válidas y ceros.
     */
    public function test_initial_count_pending_by_default_and_registers_successfully(): void
    {
        $this->assertTrue($this->plantService->isInitialCountPending());

        $inv = $this->plantService->recordInitialCount($this->plantaUser, 0, 50, 'Apertura de turno');

        $this->assertFalse($this->plantService->isInitialCountPending());
        $this->assertEquals(0, $inv->exchange_empty_available);
        $this->assertEquals(50, $inv->exchange_full_available);
        $this->assertEquals(0, $inv->exchange_washing_filling);
        $this->assertEquals(0, $inv->exchange_full_reserved);
        $this->assertEquals($this->plantaUser->id, $inv->initial_count_user_id);
        $this->assertNotNull($inv->initial_count_completed_at);

        // Movimiento en bitácora
        $this->assertDatabaseHas('bottle_custody_movements', [
            'movement_type' => BottleCustodyMovement::TYPE_PLANT_INITIAL_COUNT,
            'user_id' => $this->plantaUser->id,
            'quantity' => 50,
            'origin' => 'physical_audit',
            'destination' => 'plant',
        ]);
    }

    /**
     * 2. Rechazo de cantidades negativas y duplicación del conteo inicial.
     */
    public function test_initial_count_rejects_negative_quantities_and_duplicate_registration(): void
    {
        // Negativo
        $this->expectException(OrderBusinessRuleException::class);
        $this->plantService->recordInitialCount($this->plantaUser, -5, 20);

        // Registro válido
        $this->plantService->recordInitialCount($this->plantaUser, 30, 40);

        // Intento duplicado debe lanzar excepción 422
        $this->expectException(OrderBusinessRuleException::class);
        $this->plantService->recordInitialCount($this->plantaUser, 10, 10);
    }

    /**
     * 3. Rechazo de duplicación vía endpoint HTTP con sesión de Operador Planta.
     */
    public function test_http_endpoint_rejects_duplicate_initial_count(): void
    {
        // Primer conteo vía HTTP
        $res1 = $this->actingAs($this->plantaUser)->post(route('planta.inventory.initial-count'), [
            'empty_count' => 20,
            'full_count' => 30,
            'notes' => 'Primer conteo físico',
        ]);
        $res1->assertSessionHasNoErrors();
        $this->assertFalse($this->plantService->isInitialCountPending());

        // Segundo conteo vía HTTP debe fallar con error de validación/negocio
        $res2 = $this->actingAs($this->plantaUser)->post(route('planta.inventory.initial-count'), [
            'empty_count' => 10,
            'full_count' => 10,
        ]);
        $res2->assertSessionHasErrors(['initial_count']);
    }

    /**
     * 4. Separación estricta entre existencias de Planta, camión y fijos de clientes.
     */
    public function test_strict_isolation_between_plant_truck_and_client_fixed(): void
    {
        // Planta: conteo inicial 100 llenos
        $this->plantService->recordInitialCount($this->plantaUser, 20, 100);

        // Camión: inventario inicial 0
        $truckState = $this->truckService->getTruckInventoryState($this->repartidorUser);
        $this->assertEquals(0, $truckState['truck_inventory']['exchange_full_available']);

        // Cliente: 4 fijos registrados en su propiedad
        $this->assertEquals(4, $this->customer->fresh()->fixed_bottles_count);

        // Planta tiene 100, camión tiene 0, cliente conserva sus 4
        $plantState = $this->plantService->getInventoryState();
        $this->assertEquals(100, $plantState['balances']['exchange_full_available']);
        $this->assertEquals(20, $plantState['balances']['exchange_empty_available']);
        $this->assertCount(0, $plantState['fixed_in_plant_by_customer']);
    }

    /**
     * 5. Registro de incidencia en botellón fijo:
     * - Vincula cliente, pedido, cantidad, etapa, daño, responsable.
     * - Separa los dañados de los disponibles.
     * - NO descuenta fixed_bottles_count del cliente.
     * - NO cobra rellenado ni cierra el pedido.
     */
    public function test_fixed_bottle_incident_separates_damage_without_altering_ownership_or_charges(): void
    {
        // Crear pedido con 2 fijos
        $order = Order::create([
            'folio' => 'PED-TEST-01',
            'customer_id' => $this->customer->id,
            'recipient_name' => 'Doña Sofía',
            'delivery_street' => 'Av Hidalgo',
            'delivery_exterior_number' => '100',
            'delivery_zone' => 'Norte',
            'address_snapshot' => ['street' => 'Av Hidalgo', 'exterior_number' => '100'],
            'status' => Order::STATUS_IN_PROCESS,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'type' => OrderItem::TYPE_FIXED,
            'quantity' => 2,
            'collected_quantity' => 2,
            'received_at_plant_quantity' => 2,
            'prepared_quantity' => 0,
            'damaged_quantity' => 0,
            'status' => OrderItem::STATUS_WASHING,
        ]);

        // Registrar incidencia: 1 fijo quebrado en lavado
        $incident = $this->plantService->recordBottleIncident($this->plantaUser, [
            'bottle_type' => OrderItem::TYPE_FIXED,
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'quantity' => 1,
            'stage' => BottleIncident::STAGE_WASHING,
            'damage_type' => BottleIncident::DAMAGE_BROKEN,
            'notes' => 'Fisura longitudinal profunda en base del garrafón detectada en lavado',
        ]);

        $this->assertInstanceOf(BottleIncident::class, $incident);
        $this->assertEquals(BottleIncident::STATUS_PENDING, $incident->status);
        $this->assertEquals(1, $incident->quantity);
        $this->assertEquals($this->customer->id, $incident->customer_id);

        // 1. Dañados en OrderItem separados de los disponibles
        $item->refresh();
        $this->assertEquals(1, $item->damaged_quantity);

        // Disponible para preparar: received (2) - prepared (0) - damaged (1) = 1
        $pendingToPrepare = $item->received_at_plant_quantity - $item->prepared_quantity - $item->damaged_quantity;
        $this->assertEquals(1, $pendingToPrepare);

        // 2. NO se descontó fixed_bottles_count del cliente
        $this->assertEquals(4, $this->customer->fresh()->fixed_bottles_count);

        // 3. El pedido NO fue cerrado ni cancelado
        $this->assertEquals(Order::STATUS_IN_PROCESS, $order->fresh()->status);

        // 4. No se generaron cargos adicionales ficticios
        $this->assertDatabaseMissing('order_charges', [
            'order_id' => $order->id,
            'type' => 'damage_penalty',
        ]);

        // 5. Se generó movimiento auditable de custodia en cuarentena
        $this->assertDatabaseHas('bottle_custody_movements', [
            'order_id' => $order->id,
            'movement_type' => BottleCustodyMovement::TYPE_BOTTLE_INCIDENT,
            'destination' => 'incident_quarantine',
            'quantity' => 1,
        ]);
    }

    /**
     * 6. Incidencia en botellones de cambio descuenta del balance correspondiente de planta.
     */
    public function test_exchange_bottle_incident_deducts_from_plant_balance(): void
    {
        $this->plantService->recordInitialCount($this->plantaUser, 30, 50);

        // Reportar 2 vacíos rotos en recepción
        $incident = $this->plantService->recordBottleIncident($this->plantaUser, [
            'bottle_type' => OrderItem::TYPE_EXCHANGE,
            'quantity' => 2,
            'stage' => BottleIncident::STAGE_RECEPTION,
            'damage_type' => BottleIncident::DAMAGE_BROKEN,
            'notes' => '2 garrafones con cuarteadura en cuello',
        ]);

        $this->assertEquals(2, $incident->quantity);

        $state = $this->plantService->getInventoryState();
        $this->assertEquals(28, $state['balances']['exchange_empty_available']);
        $this->assertEquals(2, $state['balances']['exchange_damaged_total']);
    }

    /**
     * 7. Operaciones de producción:
     * - Iniciar lavado: vacíos -> en proceso
     * - Finalizar lavado: en proceso -> llenos
     * - No permite convertir vacíos directamente en llenos
     */
    public function test_production_lifecycle_empty_to_washing_and_washing_to_full(): void
    {
        $this->plantService->recordInitialCount($this->plantaUser, 40, 10);

        // Iniciar lavado de 15 vacíos
        $this->plantService->startExchangeWashing($this->plantaUser, 15);

        $state1 = $this->plantService->getInventoryState();
        $this->assertEquals(25, $state1['balances']['exchange_empty_available']);
        $this->assertEquals(15, $state1['balances']['exchange_washing_filling']);
        $this->assertEquals(10, $state1['balances']['exchange_full_available']);

        // Finalizar llenado de 10 botellones
        $this->plantService->finishExchangeWashing($this->plantaUser, 10);

        $state2 = $this->plantService->getInventoryState();
        $this->assertEquals(25, $state2['balances']['exchange_empty_available']);
        $this->assertEquals(5, $state2['balances']['exchange_washing_filling']);
        $this->assertEquals(20, $state2['balances']['exchange_full_available']);

        // Rechazo de finalizar más de lo que está en proceso
        $this->expectException(OrderBusinessRuleException::class);
        $this->plantService->finishExchangeWashing($this->plantaUser, 10);
    }

    /**
     * 8. Reserva de carga a camión:
     * - Proponer carga reserva existencias en planta
     * - Aceptar carga transfiere de planta al camión
     * - Rechazar carga regresa las existencias a disponibles en planta
     */
    public function test_truck_load_reservation_and_transfer_lifecycle(): void
    {
        $this->plantService->recordInitialCount($this->plantaUser, 10, 30);

        // Planta propone carga de 12 garrafones llenos
        $movement = $this->truckService->proposePlantToTruckTransfer(
            $this->plantaUser,
            OrderItem::TYPE_EXCHANGE,
            12
        );

        $stateProposed = $this->plantService->getInventoryState();
        $this->assertEquals(18, $stateProposed['balances']['exchange_full_available']);
        $this->assertEquals(12, $stateProposed['balances']['exchange_full_reserved']);

        // Repartidor rechaza la carga
        $this->truckService->rejectTransfer($movement, $this->repartidorUser, 'Espacio insuficiente');

        $stateRejected = $this->plantService->getInventoryState();
        $this->assertEquals(30, $stateRejected['balances']['exchange_full_available']);
        $this->assertEquals(0, $stateRejected['balances']['exchange_full_reserved']);

        // Nueva propuesta de 10 y aceptación por el chofer
        $movement2 = $this->truckService->proposePlantToTruckTransfer(
            $this->plantaUser,
            OrderItem::TYPE_EXCHANGE,
            10
        );
        $this->truckService->acceptTransfer($movement2, $this->repartidorUser, 10);

        $stateAccepted = $this->plantService->getInventoryState();
        $this->assertEquals(20, $stateAccepted['balances']['exchange_full_available']);
        $this->assertEquals(0, $stateAccepted['balances']['exchange_full_reserved']);

        $truckState = $this->truckService->getTruckInventoryState($this->repartidorUser);
        $this->assertEquals(10, $truckState['truck_inventory']['exchange_full_available']);
    }

    /**
     * 9. Seguridad y permisos por rol.
     */
    public function test_role_based_security_and_permissions(): void
    {
        // Operador Planta accede a dashboard y existencias
        $this->actingAs($this->plantaUser)->get(route('planta.dashboard'))->assertOk();
        $this->actingAs($this->plantaUser)->get(route('planta.inventory.index'))->assertOk();

        // Administrador puede supervisar existencias
        $this->actingAs($this->adminUser)->get(route('planta.inventory.index'))->assertOk();

        // Cliente no tiene acceso
        $this->actingAs($this->clientUser)->get(route('planta.dashboard'))->assertForbidden();
        $this->actingAs($this->clientUser)->get(route('planta.inventory.index'))->assertForbidden();

        // Repartidor no tiene acceso a operaciones de planta
        $this->actingAs($this->repartidorUser)->get(route('planta.dashboard'))->assertForbidden();
        $this->actingAs($this->repartidorUser)->get(route('planta.inventory.index'))->assertForbidden();
    }
}
